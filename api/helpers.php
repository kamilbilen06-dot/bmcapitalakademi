<?php
/**
 * Ortak yardımcılar: JSON yanıt, girdi okuma, oturum/auth kontrolü.
 */
require_once __DIR__ . '/config.php';

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json() {
    static $cached = null;
    static $done = false;
    if ($done) {
        return $cached;
    }
    $done = true;
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
}

function request_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function session_cookie_options(): array {
    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function start_admin_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === SESSION_NAME) {
            return;
        }
        // Aynı istekte öğrenci oturumu açıksa onu kapatıp panel oturumuna geç
        session_write_close();
    }
    session_name(SESSION_NAME);
    session_set_cookie_params(session_cookie_options());
    session_start();
}

/** Herhangi bir giriş yapılmış mı (admin veya eğitmen) */
function is_admin() {
    start_admin_session();
    return !empty($_SESSION['admin_id']);
}

function is_logged_in() {
    return is_admin();
}

function current_role() {
    start_admin_session();
    $role = $_SESSION['admin_role'] ?? 'admin';
    return $role === 'egitmen' ? 'egitmen' : 'admin';
}

function is_site_admin() {
    return is_logged_in() && current_role() === 'admin';
}

function current_instructor_id() {
    start_admin_session();
    return (int)($_SESSION['instructor_id'] ?? 0);
}

function require_admin() {
    if (!is_logged_in()) {
        json_out(['ok' => false, 'error' => 'Yetkisiz erişim'], 401);
    }
}

/** Sadece site yöneticisi (yönetim paneli) */
function require_site_admin() {
    if (!is_logged_in()) {
        json_out(['ok' => false, 'error' => 'Yetkisiz erişim'], 401);
    }
    if (!is_site_admin()) {
        json_out(['ok' => false, 'error' => 'Bu alana sadece yönetici girebilir'], 403);
    }
}

/** Eğitmen paneli: admin veya eğitmen hesabı */
function require_egitmen_access() {
    if (!is_logged_in()) {
        json_out(['ok' => false, 'error' => 'Yetkisiz erişim'], 401);
    }
    $role = current_role();
    if ($role !== 'admin' && $role !== 'egitmen') {
        json_out(['ok' => false, 'error' => 'Yetkisiz erişim'], 403);
    }
    if ($role === 'egitmen' && current_instructor_id() <= 0) {
        json_out(['ok' => false, 'error' => 'Hesabınız bir eğitmen profiline bağlı değil. Yöneticiye bildirin.'], 403);
    }
}

/**
 * Giriş sonrası session alanlarını doldur.
 */
function login_session(array $row) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_user'] = $row['username'];
    $role = ($row['role'] ?? 'admin') === 'egitmen' ? 'egitmen' : 'admin';
    $_SESSION['admin_role'] = $role;
    $_SESSION['instructor_id'] = !empty($row['instructor_id']) ? (int)$row['instructor_id'] : 0;
    try {
        if (function_exists('db')) {
            db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
                ->execute([(int)$row['id']]);
        }
    } catch (Throwable $e) {
        // Kolon henüz yoksa giriş yine de tamamlanır
    }
}

/* ---------------------------------------------------------------------------
 * Öğrenci oturumu — admin/eğitmen panelinden ayrı çerez (STUDENT_SESSION_NAME).
 * PHP tek istekte tek oturum tutabildiği için geçişte önceki oturum kapatılır.
 * ------------------------------------------------------------------------- */

function start_student_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === STUDENT_SESSION_NAME) {
            return;
        }
        session_write_close();
    }
    session_name(STUDENT_SESSION_NAME);
    session_set_cookie_params(session_cookie_options());
    session_start();
}

function is_student() {
    start_student_session();
    return !empty($_SESSION['student_id']);
}

function current_student_id() {
    start_student_session();
    return (int)($_SESSION['student_id'] ?? 0);
}

/** Oturumdaki öğrenci özeti (DB sorgusu yapmaz) */
function current_student() {
    if (!is_student()) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['student_id'],
        'name' => (string)($_SESSION['student_name'] ?? ''),
        'email' => (string)($_SESSION['student_email'] ?? ''),
    ];
}

/** JSON API'lerde öğrenci oturumu zorunlu */
function require_student() {
    if (!is_student()) {
        json_out(['ok' => false, 'error' => 'Bu işlem için giriş yapmalısınız', 'code' => 'auth'], 401);
    }
}

/** Sayfalarda öğrenci oturumu zorunlu; yoksa girişe yönlendir */
function require_student_page($next = '') {
    if (is_student()) {
        return;
    }
    $url = 'giris.php';
    if ($next !== '') {
        $url .= '?next=' . rawurlencode($next);
    }
    header('Location: ' . $url);
    exit;
}

function student_login_session(array $row) {
    start_student_session();
    session_regenerate_id(true);
    $_SESSION['student_id'] = (int)$row['id'];
    $_SESSION['student_name'] = (string)($row['name'] ?? '');
    $_SESSION['student_email'] = (string)($row['email'] ?? '');
}

function student_logout() {
    start_student_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Form sahteciliğine karşı jeton (öğrenci formları) */
function student_csrf_token() {
    start_student_session();
    if (empty($_SESSION['student_csrf'])) {
        $_SESSION['student_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['student_csrf'];
}

function student_csrf_valid($token) {
    start_student_session();
    $known = (string)($_SESSION['student_csrf'] ?? '');
    return $known !== '' && is_string($token) && hash_equals($known, $token);
}

/** Yönetim / eğitmen paneli form sahteciliği jetonu */
function admin_csrf_token() {
    start_admin_session();
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_valid($token) {
    start_admin_session();
    $known = (string)($_SESSION['admin_csrf'] ?? '');
    return $known !== '' && is_string($token) && $token !== '' && hash_equals($known, $token);
}

function admin_csrf_request_valid(): bool {
    $header = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (admin_csrf_valid($header)) {
        return true;
    }
    if (admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        return true;
    }
    $in = body_json();
    return admin_csrf_valid((string)($in['csrf'] ?? ''));
}

/** GET/HEAD jeton istemez. POST ve diğer yazma istekleri ister. */
function admin_csrf_protect(): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
        return;
    }
    if (!admin_csrf_request_valid()) {
        json_out(['ok' => false, 'error' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], 403);
    }
}

/** Metni satırlara böl (textarea -> dizi) */
function lines_to_array($text) {
    if (!is_string($text)) return [];
    $parts = preg_split('/\r\n|\r|\n/', trim($text));
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}

function clean($v) {
    return is_string($v) ? trim($v) : $v;
}

/**
 * post_max_size aşıldığında PHP $_POST ve $_FILES boş döner; course_id gibi alanlar kaybolur.
 * @return string|null Kullanıcıya gösterilecek hata metni veya null
 */
function upload_multipart_overflow_message(): ?string {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return null;
    }
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len <= 0 || !empty($_POST) || !empty($_FILES)) {
        return null;
    }
    $limit = (string)(ini_get('post_max_size') ?: '8M');
    return 'Dosya istek boyutu PHP post_max_size limitini aşıyor (' . $limit . '). '
        . 'cPanel > Select PHP Version > Options içinde post_max_size ve upload_max_filesize değerlerini yükseltin (ör. 256M).';
}

/**
 * GET/POST değeri dizi gelebilir (www/https yönlendirmesi token=...&token=...).
 * Dizi stringe çevrilirse PHP "Array to string conversion" uyarısı basar ve jeton bozulur.
 */
function request_scalar($value): string {
    while (is_array($value)) {
        if ($value === []) {
            return '';
        }
        $value = reset($value);
    }
    if ($value === null || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim((string) $value);
}

/**
 * Eğitmen payı (0–100). Ayar yoksa 60.
 */
function instructor_share_pct(PDO $pdo): float {
    $pct = 60.0;
    try {
        $st = $pdo->query("SELECT v FROM settings WHERE k = 'instructor_share_pct' LIMIT 1");
        $v = $st ? $st->fetchColumn() : false;
        if ($v !== false && $v !== null && trim((string)$v) !== '') {
            $n = (float)str_replace(',', '.', trim((string)$v));
            if ($n >= 0 && $n <= 100) {
                $pct = $n;
            }
        }
    } catch (Throwable $e) {
        // Ayar tablosu yoksa varsayılan
    }
    return $pct;
}

/** Eğitmene özel pay; boşsa site varsayılanı. */
function instructor_share_pct_for(PDO $pdo, int $instructorId): float {
    $fallback = instructor_share_pct($pdo);
    if ($instructorId <= 0) {
        return $fallback;
    }
    try {
        $st = $pdo->prepare('SELECT share_pct FROM instructors WHERE id = ? LIMIT 1');
        $st->execute([$instructorId]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && trim((string)$v) !== '') {
            $n = (float)str_replace(',', '.', trim((string)$v));
            if ($n >= 0 && $n <= 100) {
                return $n;
            }
        }
    } catch (Throwable $e) {
        // Kolon yoksa varsayılan
    }
    return $fallback;
}

function instructor_share_pct_parse($raw): ?float {
    $s = str_replace(',', '.', trim((string)$raw));
    if ($s === '') {
        return null;
    }
    if (!is_numeric($s)) {
        return null;
    }
    $n = (float)$s;
    if ($n < 0 || $n > 100) {
        return null;
    }
    return $n;
}

function instructor_earn_kurus(int $salesKurus, float $pct): int {
    return (int)round($salesKurus * ($pct / 100.0));
}
