<?php
/**
 * Ders videosu / tanıtım / kaynak dosyaları için imza ve erişim.
 *
 * Doğrudan uploads/courses/*.mp4 adresi kapatılır; akış api/media.php üzerinden gider.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/student_account.php';

function media_signing_key(): string {
    if (defined('MEDIA_SIGNING_KEY') && MEDIA_SIGNING_KEY !== '') {
        return MEDIA_SIGNING_KEY;
    }
    return hash('sha256', DB_NAME . '|' . DB_USER . '|' . SESSION_NAME . '|bmcap-media-v1');
}

function media_sign_payload(string $kind, int $id, int $exp, int $index = 0): string {
    return hash_hmac('sha256', $kind . '|' . $id . '|' . $index . '|' . $exp, media_signing_key());
}

/**
 * İmzalı göreli URL (site köküne göre). $index kaynak dosya sırası için.
 */
function media_signed_query(string $kind, int $id, int $ttl = 14400, int $index = 0): string {
    $exp = time() + max(60, $ttl);
    $sig = media_sign_payload($kind, $id, $exp, $index);
    $q = [
        'kind' => $kind,
        'id' => $id,
        'exp' => $exp,
        'sig' => $sig,
    ];
    if ($index > 0) {
        $q['i'] = $index;
    }
    return 'api/media.php?' . http_build_query($q);
}

function media_sig_ok(string $kind, int $id, int $exp, string $sig, int $index = 0): bool {
    if ($sig === '' || $exp < time() || $exp > time() + 86400 * 7) {
        return false;
    }
    $expected = media_sign_payload($kind, $id, $exp, $index);
    return hash_equals($expected, strtolower($sig)) || hash_equals($expected, $sig);
}

/** uploads/courses altındaki göreli yolu mutlak ve güvenli dosya yoluna çevirir. */
function media_abs_path(string $rel): ?string {
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    if (!preg_match('#^uploads/courses/[0-9]+/#', $rel)) {
        return null;
    }
    $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'courses');
    $abs = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($root === false || $abs === false || !is_file($abs)) {
        return null;
    }
    $prefix = $root . DIRECTORY_SEPARATOR;
    if (strncasecmp($abs, $prefix, strlen($prefix)) !== 0) {
        return null;
    }
    return $abs;
}

function media_mime(string $abs): string {
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    return match ($ext) {
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'txt' => 'text/plain; charset=utf-8',
        default => 'application/octet-stream',
    };
}

function media_peek_student_id(): int {
    if (empty($_COOKIE[STUDENT_SESSION_NAME])) {
        return 0;
    }
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== STUDENT_SESSION_NAME) {
        session_write_close();
    }
    start_student_session();
    $id = (int)($_SESSION['student_id'] ?? 0);
    session_write_close();
    return $id;
}

/** @return array{ok:bool, role:string, instructor_id:int} */
function media_peek_admin(): array {
    $empty = ['ok' => false, 'role' => '', 'instructor_id' => 0];
    if (empty($_COOKIE[SESSION_NAME])) {
        return $empty;
    }
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== SESSION_NAME) {
        session_write_close();
    }
    start_admin_session();
    $ok = !empty($_SESSION['admin_id']);
    $role = (string)($_SESSION['admin_role'] ?? 'admin');
    $iid = (int)($_SESSION['instructor_id'] ?? 0);
    session_write_close();
    if (!$ok) {
        return $empty;
    }
    return ['ok' => true, 'role' => $role === 'egitmen' ? 'egitmen' : 'admin', 'instructor_id' => $iid];
}

function media_instructor_owns(PDO $pdo, int $courseId): bool {
    $admin = media_peek_admin();
    if (!$admin['ok']) {
        return false;
    }
    if ($admin['role'] !== 'egitmen') {
        return true;
    }
    if ($admin['instructor_id'] <= 0 || $courseId <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT instructor_id FROM courses WHERE id = ?');
    $st->execute([$courseId]);
    $row = $st->fetch();
    return $row && (int)$row['instructor_id'] === $admin['instructor_id'];
}

function media_student_paid(PDO $pdo, int $courseId): bool {
    $sid = media_peek_student_id();
    if ($sid <= 0 || $courseId <= 0) {
        return false;
    }
    return student_has_paid_access($pdo, $sid, $courseId);
}

function media_can_access_lecture(PDO $pdo, array $lecture, bool $sigOk): bool {
    $courseId = (int)($lecture['course_id'] ?? 0);
    if (media_instructor_owns($pdo, $courseId) || media_student_paid($pdo, $courseId)) {
        return true;
    }
    $preview = (int)($lecture['is_preview'] ?? 0) === 1;
    if (!$preview) {
        return false;
    }
    // Misafir önizleme: imzalı URL (kurs.php üretir) veya eğitmen oturumu
    return $sigOk;
}

function media_deny(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $msg;
    exit;
}

function media_stream_file(string $abs, string $downloadName = '', bool $asAttachment = false): void {
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('max_execution_time', '0');
    ignore_user_abort(true);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $size = filesize($abs);
    if ($size === false) {
        media_deny(404, 'Dosya bulunamadı.');
    }
    $mime = media_mime($abs);
    $start = 0;
    $end = $size - 1;
    $code = 200;

    $range = (string)($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        if ($m[1] !== '') {
            $start = (int)$m[1];
        }
        if ($m[2] !== '') {
            $end = (int)$m[2];
        }
        if ($start > $end || $start >= $size || $end >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $code = 206;
    }

    $length = $end - $start + 1;
    http_response_code($code);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    header('Cache-Control: private, max-age=0, no-store');
    header('X-Content-Type-Options: nosniff');
    if ($code === 206) {
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }
    $disp = $asAttachment ? 'attachment' : 'inline';
    $name = $downloadName !== '' ? $downloadName : basename($abs);
    header('Content-Disposition: ' . $disp . '; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
        exit;
    }

    $fp = fopen($abs, 'rb');
    if ($fp === false) {
        media_deny(500, 'Dosya açılamadı.');
    }
    if ($start > 0) {
        fseek($fp, $start);
    }
    $left = $length;
    while ($left > 0 && !feof($fp) && connection_status() === CONNECTION_NORMAL) {
        $chunk = fread($fp, (int)min(8192 * 8, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    exit;
}
