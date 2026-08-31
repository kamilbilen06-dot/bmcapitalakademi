<?php
/**
 * Öğrenci hesabı iş mantığı — sayfalar (ogrenci/*.php) ve JSON API
 * (student_auth.php) aynı fonksiyonları kullanır.
 *
 * Doğrulama hataları Türkçe metin olarak döner; çağıran taraf gösterir.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/students_schema.php';

const STUDENT_MIN_PASSWORD = 8;
const STUDENT_TOKEN_TTL_MIN = 1440;
const STUDENT_VERIFY_TTL_MIN = 30;
const STUDENT_VERIFY_RESEND_SEC = 45;

function student_normalize_email($email) {
    return mb_strtolower(trim((string)$email));
}

function student_normalize_visitor_id($visitorId): string {
    $clean = strtoupper(preg_replace('/[^a-f0-9]/i', '', (string)$visitorId) ?? '');
    return strlen($clean) >= 8 ? substr($clean, 0, 16) : '';
}

function student_bind_visitor(PDO $pdo, int $studentId, $visitorId): void {
    $visitorId = student_normalize_visitor_id($visitorId);
    if ($studentId <= 0 || $visitorId === '') {
        return;
    }
    $pdo->prepare(
        "UPDATE students SET visitor_id = ?
         WHERE id = ? AND (visitor_id IS NULL OR visitor_id = '')"
    )->execute([$visitorId, $studentId]);
}

function student_find_by_email(PDO $pdo, $email) {
    $st = $pdo->prepare("SELECT * FROM students WHERE email = ? LIMIT 1");
    $st->execute([student_normalize_email($email)]);
    $row = $st->fetch();
    return $row ?: null;
}

function student_find_by_id(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $st->execute([(int)$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Kayıt formu doğrulaması. Hata varsa metin, yoksa '' döner.
 */
function student_validate_registration($name, $email, $password, $password2) {
    if (mb_strlen(trim((string)$name)) < 2) {
        return 'Ad soyad en az 2 karakter olmalı.';
    }
    if (!filter_var(student_normalize_email($email), FILTER_VALIDATE_EMAIL)) {
        return 'Geçerli bir e-posta adresi girin.';
    }
    if (mb_strlen((string)$password) < STUDENT_MIN_PASSWORD) {
        return 'Şifre en az ' . STUDENT_MIN_PASSWORD . ' karakter olmalı.';
    }
    if ($password2 !== null && $password !== $password2) {
        return 'Şifreler birbiriyle uyuşmuyor.';
    }
    return '';
}

/**
 * Yeni öğrenci oluştur. Başarılıysa satır, değilse hata metni döner.
 * @return array{row:?array, error:string}
 */
function student_register(PDO $pdo, $name, $email, $password, $phone = '', $marketingConsent = false, $visitorId = '') {
    $email = student_normalize_email($email);
    $visitorId = student_normalize_visitor_id($visitorId);
    $existing = student_find_by_email($pdo, $email);
    if ($existing) {
        student_bind_visitor($pdo, (int)$existing['id'], $visitorId);
        if (empty($existing['email_verified_at'])) {
            return ['row' => $existing, 'error' => 'unverified_exists'];
        }
        return ['row' => null, 'error' => 'Bu e-posta ile bir hesap zaten var. Giriş yapmayı deneyin.'];
    }
    $pdo->prepare(
        "INSERT INTO students (email, visitor_id, password_hash, name, phone, status, marketing_consent, created_at)
         VALUES (?,?,?,?,?,'active',?,NOW())"
    )->execute([
        $email,
        $visitorId,
        password_hash($password, PASSWORD_DEFAULT),
        mb_substr(trim((string)$name), 0, 160),
        mb_substr(trim((string)$phone), 0, 40),
        $marketingConsent ? 1 : 0,
    ]);

    $row = student_find_by_email($pdo, $email);
    if ($row) {
        student_link_enrollments($pdo, (int)$row['id'], $email);
    }
    return ['row' => $row, 'error' => ''];
}

/**
 * Giriş denemesi. Başarılıysa satır, değilse hata metni.
 * @return array{row:?array, error:string}
 */
function student_authenticate(PDO $pdo, $email, $password) {
    $row = student_find_by_email($pdo, $email);
    if (!$row || $row['password_hash'] === '' || !password_verify((string)$password, $row['password_hash'])) {
        return ['row' => null, 'error' => 'E-posta veya şifre hatalı.'];
    }
    if (($row['status'] ?? 'active') !== 'active') {
        return ['row' => null, 'error' => 'Hesabınız askıya alınmış. Lütfen bizimle iletişime geçin.'];
    }
    if (empty($row['email_verified_at'])) {
        return ['row' => $row, 'error' => 'unverified'];
    }
    $pdo->prepare("UPDATE students SET last_login_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
    student_link_enrollments($pdo, (int)$row['id'], (string)$row['email']);
    return ['row' => $row, 'error' => ''];
}

function student_set_password(PDO $pdo, $studentId, $password) {
    $pdo->prepare("UPDATE students SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$studentId]);
}

/** E-posta ile açılmış eski kayıtları bu hesaba bağla */
function student_link_enrollments(PDO $pdo, $studentId, $email) {
    try {
        $pdo->prepare(
            "UPDATE course_enrollments SET student_id = ?
             WHERE student_email = ? AND (student_id IS NULL OR student_id = 0)"
        )->execute([(int)$studentId, student_normalize_email($email)]);
    } catch (Throwable $e) {
        // Kolon yoksa sessiz geç.
    }
}

/* ---------------------------------------------------------------------------
 * Sosyal giriş (Google vb.)
 * ------------------------------------------------------------------------- */

/**
 * Sağlayıcıdan gelen kimliği hesapla eşleştir; gerekiyorsa hesap oluştur.
 *
 * Sıra:
 *   1) Daha önce bağlanmış kimlik varsa o hesaba gir.
 *   2) E-posta doğrulanmışsa ve aynı e-postalı hesap varsa kimliği ona bağla.
 *   3) Yoksa şifresiz (yalnızca sosyal giriş) yeni hesap aç.
 *
 * Doğrulanmamış e-posta kabul edilmez: aksi halde başkasının hesabı ele
 * geçirilebilir.
 *
 * @return array{row:?array, error:string, created:bool}
 */
function student_login_with_identity(
    PDO $pdo,
    string $provider,
    string $providerUid,
    string $email,
    string $name = '',
    bool $emailVerified = false,
    string $avatarUrl = '',
    string $visitorId = ''
): array {
    $provider = mb_strtolower(trim($provider));
    $providerUid = trim($providerUid);
    $email = student_normalize_email($email);

    if ($provider === '' || $providerUid === '') {
        return ['row' => null, 'error' => 'Sağlayıcı bilgisi eksik.', 'created' => false];
    }

    // 1) Bağlı kimlik
    $st = $pdo->prepare("SELECT student_id FROM student_identities WHERE provider = ? AND provider_uid = ? LIMIT 1");
    $st->execute([$provider, $providerUid]);
    $linked = $st->fetch();

    if ($linked) {
        $row = student_find_by_id($pdo, (int)$linked['student_id']);
        if (!$row) {
            // Hesap silinmiş, artık kimlik de geçersiz
            $pdo->prepare("DELETE FROM student_identities WHERE provider = ? AND provider_uid = ?")
                ->execute([$provider, $providerUid]);
            return ['row' => null, 'error' => 'Bağlı hesap bulunamadı. Lütfen tekrar deneyin.', 'created' => false];
        }
        if (($row['status'] ?? 'active') !== 'active') {
            return ['row' => null, 'error' => 'Hesabınız askıya alınmış. Lütfen bizimle iletişime geçin.', 'created' => false];
        }
        student_identity_touch($pdo, $provider, $providerUid);
        student_fill_profile_gaps($pdo, $row, $name, $avatarUrl);
        student_bind_visitor($pdo, (int)$row['id'], $visitorId);
        $pdo->prepare("UPDATE students SET last_login_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
        student_link_enrollments($pdo, (int)$row['id'], (string)$row['email']);
        return ['row' => student_find_by_id($pdo, (int)$row['id']), 'error' => '', 'created' => false];
    }

    if (!$emailVerified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'row' => null,
            'error' => 'Sağlayıcı doğrulanmış bir e-posta adresi paylaşmadı. Lütfen e-posta ile kayıt olun.',
            'created' => false,
        ];
    }

    // 2) Aynı e-postalı mevcut hesaba bağla
    $existing = student_find_by_email($pdo, $email);
    $created = false;

    if (!$existing) {
        // 3) Yeni hesap — şifre yok, yalnızca sosyal giriş
        $pdo->prepare(
            "INSERT INTO students (email, password_hash, name, phone, avatar_url, status, marketing_consent, email_verified_at, created_at)
             VALUES (?, '', ?, '', ?, 'active', 0, NOW(), NOW())"
        )->execute([
            $email,
            mb_substr(trim($name), 0, 160),
            mb_substr(trim($avatarUrl), 0, 400),
        ]);
        $existing = student_find_by_email($pdo, $email);
        $created = true;
    }

    if (!$existing) {
        return ['row' => null, 'error' => 'Hesap oluşturulamadı. Lütfen tekrar deneyin.', 'created' => false];
    }
    if (($existing['status'] ?? 'active') !== 'active') {
        return ['row' => null, 'error' => 'Hesabınız askıya alınmış. Lütfen bizimle iletişime geçin.', 'created' => false];
    }

    $pdo->prepare(
        "INSERT INTO student_identities (student_id, provider, provider_uid, email, last_login_at)
         VALUES (?,?,?,?, NOW())
         ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), email = VALUES(email), last_login_at = NOW()"
    )->execute([(int)$existing['id'], $provider, $providerUid, $email]);

    if (!$created) {
        student_fill_profile_gaps($pdo, $existing, $name, $avatarUrl);
    }
    $pdo->prepare("UPDATE students SET last_login_at = NOW(), email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
        ->execute([(int)$existing['id']]);
    student_link_enrollments($pdo, (int)$existing['id'], $email);
    student_bind_visitor($pdo, (int)$existing['id'], $visitorId);

    return ['row' => student_find_by_id($pdo, (int)$existing['id']), 'error' => '', 'created' => $created];
}

function student_identity_touch(PDO $pdo, string $provider, string $providerUid) {
    $pdo->prepare("UPDATE student_identities SET last_login_at = NOW() WHERE provider = ? AND provider_uid = ?")
        ->execute([$provider, $providerUid]);
}

/** Boş olan ad/avatar alanlarını sağlayıcı verisiyle doldur (mevcut veriyi ezmez) */
function student_fill_profile_gaps(PDO $pdo, array $row, string $name, string $avatarUrl) {
    $sets = [];
    $args = [];
    if (trim((string)$row['name']) === '' && trim($name) !== '') {
        $sets[] = 'name = ?';
        $args[] = mb_substr(trim($name), 0, 160);
    }
    if (trim((string)($row['avatar_url'] ?? '')) === '' && trim($avatarUrl) !== '') {
        $sets[] = 'avatar_url = ?';
        $args[] = mb_substr(trim($avatarUrl), 0, 400);
    }
    if (!$sets) {
        return;
    }
    $args[] = (int)$row['id'];
    $pdo->prepare("UPDATE students SET " . implode(', ', $sets) . " WHERE id = ?")->execute($args);
}

/** Hesaba bağlı sosyal giriş sağlayıcıları */
function student_identities(PDO $pdo, $studentId): array {
    $st = $pdo->prepare("SELECT provider, email, created_at FROM student_identities WHERE student_id = ? ORDER BY id");
    $st->execute([(int)$studentId]);
    return $st->fetchAll();
}

/** Şifresi olmayan (yalnızca sosyal giriş) hesap mı */
function student_has_password(?array $row): bool {
    return is_array($row) && trim((string)($row['password_hash'] ?? '')) !== '';
}

/**
 * Tek kullanımlık jeton üret. Ham jeton döner (DB'de yalnızca hash tutulur).
 */
function student_issue_token(PDO $pdo, $studentId, $purpose = 'reset', $ttlMinutes = STUDENT_TOKEN_TTL_MIN) {
    $raw = bin2hex(random_bytes(32));
    $pdo->prepare(
        "INSERT INTO student_tokens (student_id, token_hash, purpose, expires_at)
         VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    )->execute([(int)$studentId, hash('sha256', $raw), $purpose, (int)$ttlMinutes]);
    return $raw;
}

/**
 * Jetonu doğrula ve tüket. Geçerliyse student_id, değilse 0 döner.
 */
function student_extract_token(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/(?:^|[?&]|\\/)(?:token|t)=([a-f0-9]{32,128})/i', $raw, $m)) {
        return strtolower($m[1]);
    }
    if (preg_match('/sifre-sifirla\\.php\\/([a-f0-9]{32,128})/i', $raw, $m)) {
        return strtolower($m[1]);
    }
    $clean = strtolower(preg_replace('/[^a-f0-9]/i', '', $raw) ?? '');
    if (strlen($clean) >= 32 && strlen($clean) <= 128) {
        return $clean;
    }
    return '';
}

function student_read_token(): string {
    foreach ([
        request_scalar($_GET['token'] ?? ''),
        request_scalar($_GET['t'] ?? ''),
        request_scalar($_POST['token'] ?? ''),
    ] as $cand) {
        $t = student_extract_token($cand);
        if ($t !== '') {
            return $t;
        }
    }
    $qs = student_extract_token(urldecode((string) ($_SERVER['QUERY_STRING'] ?? '')));
    if ($qs !== '') {
        return $qs;
    }
    $uri = student_extract_token(urldecode((string) ($_SERVER['REQUEST_URI'] ?? '')));
    if ($uri !== '') {
        return $uri;
    }
    return student_extract_token(trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/'));
}

/**
 * Jetonun neden çalışmadığını ayırt eder: empty | missing | used | expired | ok
 *
 * @return array{state:string, student_id:int}
 */
function student_token_status(PDO $pdo, $rawToken, $purpose = 'reset'): array {
    $rawToken = strtolower(preg_replace('/[^a-f0-9]/i', '', trim((string) $rawToken)) ?? '');
    if ($rawToken === '') {
        return ['state' => 'empty', 'student_id' => 0];
    }
    $st = $pdo->prepare(
        "SELECT student_id, used_at, (expires_at <= NOW()) AS expired
         FROM student_tokens WHERE token_hash = ? AND purpose = ? ORDER BY id DESC LIMIT 1"
    );
    $st->execute([hash('sha256', $rawToken), $purpose]);
    $row = $st->fetch();
    if (!$row) {
        return ['state' => 'missing', 'student_id' => 0];
    }
    if (!empty($row['used_at'])) {
        return ['state' => 'used', 'student_id' => (int) $row['student_id']];
    }
    if ((int) $row['expired'] === 1) {
        return ['state' => 'expired', 'student_id' => (int) $row['student_id']];
    }
    return ['state' => 'ok', 'student_id' => (int) $row['student_id']];
}

function student_token_state_message(string $state): string {
    switch ($state) {
        case 'empty':
            return 'Bağlantıda jeton yok. E-postadaki “Şifreyi Sıfırla” düğmesine tekrar tıklayın.';
        case 'used':
            return 'Bu bağlantı daha önce kullanıldı. Yeni bağlantı isteyin.';
        case 'expired':
            return 'Bu bağlantının süresi doldu. Yeni bağlantı isteyin.';
        default:
            return 'Bu bağlantı bulunamadı. E-postadaki en son bağlantıyı kullanın veya yeni bağlantı isteyin.';
    }
}

function student_consume_token(PDO $pdo, $rawToken, $purpose = 'reset') {
    $rawToken = strtolower(preg_replace('/[^a-f0-9]/i', '', trim((string) $rawToken)) ?? '');
    if ($rawToken === '') {
        return 0;
    }
    $st = $pdo->prepare(
        "SELECT id, student_id FROM student_tokens
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $st->execute([hash('sha256', $rawToken), $purpose]);
    $row = $st->fetch();
    if (!$row) {
        return 0;
    }
    $pdo->prepare("UPDATE student_tokens SET used_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
    return (int)$row['student_id'];
}

function student_is_email_verified(?array $row): bool {
    return is_array($row) && !empty($row['email_verified_at']);
}

function student_mark_email_verified(PDO $pdo, int $studentId): void {
    $pdo->prepare("UPDATE students SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
        ->execute([$studentId]);
    $pdo->prepare("UPDATE student_tokens SET used_at = NOW()
                   WHERE student_id = ? AND purpose = 'verify' AND used_at IS NULL")
        ->execute([$studentId]);
}

function student_code_hash(int $studentId, string $code): string {
    $code = preg_replace('/\D+/', '', $code);
    return hash('sha256', $studentId . ':' . $code);
}

/**
 * E-posta doğrulama jetonu + 6 haneli kod. Ham jeton ve kod döner.
 * @return array{token:string, code:string}
 */
function student_issue_verification(PDO $pdo, $studentId): array {
    $studentId = (int)$studentId;
    $raw = bin2hex(random_bytes(32));
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE student_tokens SET used_at = NOW()
                   WHERE student_id = ? AND purpose = 'verify' AND used_at IS NULL")
        ->execute([$studentId]);
    $pdo->prepare(
        "INSERT INTO student_tokens (student_id, token_hash, code_hash, purpose, expires_at)
         VALUES (?,?,?, 'verify', DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    )->execute([
        $studentId,
        hash('sha256', $raw),
        student_code_hash($studentId, $code),
        STUDENT_VERIFY_TTL_MIN,
    ]);
    return ['token' => $raw, 'code' => $code];
}

function student_verify_resend_wait_sec(PDO $pdo, int $studentId): int {
    $st = $pdo->prepare(
        "SELECT created_at FROM student_tokens
         WHERE student_id = ? AND purpose = 'verify'
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$studentId]);
    $at = $st->fetchColumn();
    if (!$at) {
        return 0;
    }
    $elapsed = time() - strtotime((string)$at);
    $wait = STUDENT_VERIFY_RESEND_SEC - $elapsed;
    return $wait > 0 ? $wait : 0;
}

/**
 * Yeni doğrulama kodu üret ve mail at.
 * @return array{status:string, wait:int, code:string, link:string}
 */
function student_deliver_verification(PDO $pdo, array $student): array {
    require_once __DIR__ . '/mailer.php';
    $id = (int)$student['id'];
    $wait = student_verify_resend_wait_sec($pdo, $id);
    if ($wait > 0) {
        return ['status' => 'wait', 'wait' => $wait, 'code' => '', 'link' => ''];
    }
    $challenge = student_issue_verification($pdo, $id);
    $link = student_verify_link($challenge['token']);
    $sent = mailer_send_verify($student, $challenge['code'], $link);
    if (!empty($sent['ok'])) {
        return ['status' => 'sent', 'wait' => 0, 'code' => '', 'link' => ''];
    }
    return ['status' => 'failed', 'wait' => 0, 'code' => $challenge['code'], 'link' => $link];
}

function student_consume_verify_code(PDO $pdo, $email, $code): int {
    $student = student_find_by_email($pdo, $email);
    if (!$student) {
        return 0;
    }
    $code = preg_replace('/\D+/', '', (string)$code);
    if (strlen($code) !== 6) {
        return 0;
    }
    $st = $pdo->prepare(
        "SELECT id, student_id FROM student_tokens
         WHERE student_id = ? AND purpose = 'verify' AND code_hash = ?
           AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $st->execute([(int)$student['id'], student_code_hash((int)$student['id'], $code)]);
    $row = $st->fetch();
    if (!$row) {
        return 0;
    }
    student_mark_email_verified($pdo, (int)$row['student_id']);
    return (int)$row['student_id'];
}

function student_verify_link(string $token): string {
    return rtrim(site_public_url(), '/') . '/ogrenci/dogrulama.php?token=' . rawurlencode($token);
}

function student_reset_link(string $token): string {
    $token = strtolower(preg_replace('/[^a-f0-9]/i', '', $token) ?? '');
    return rtrim(site_mail_public_url(), '/') . '/ogrenci/sifre-sifirla.php?token=' . rawurlencode($token);
}

/** Jeton geçerli mi (tüketmeden kontrol) */
function student_token_is_valid(PDO $pdo, $rawToken, $purpose = 'reset') {
    $rawToken = strtolower(preg_replace('/[^a-f0-9]/i', '', trim((string) $rawToken)) ?? '');
    if ($rawToken === '') {
        return false;
    }
    $st = $pdo->prepare(
        "SELECT id FROM student_tokens
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $st->execute([hash('sha256', $rawToken), $purpose]);
    return (bool)$st->fetch();
}

/**
 * Öğrencinin kayıtlı olduğu kurslar (ödenmiş + bekleyen).
 */
function student_courses(PDO $pdo, $studentId) {
    $st = $pdo->prepare(
        "SELECT e.id AS enrollment_id, e.course_id, e.progress_pct, e.enrolled_at, e.last_visit_at,
                e.last_lecture_id, e.last_seconds, e.payment_status, e.source,
                c.title, c.subtitle, c.image_path, c.price, c.level, c.status AS course_status,
                i.name AS instructor_name, i.slug AS instructor_slug
         FROM course_enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN instructors i ON i.id = c.instructor_id
         WHERE e.student_id = ?
           AND COALESCE(e.payment_status, '') <> 'refunded'
         ORDER BY (e.payment_status = 'paid') DESC, e.enrolled_at DESC"
    );
    $st->execute([(int)$studentId]);
    $rows = $st->fetchAll();
    if (!$rows) {
        return [];
    }

    $ids = array_map(static fn($r) => (int)$r['course_id'], $rows);
    $stats = student_course_stats($pdo, $ids);
    foreach ($rows as &$r) {
        $cid = (int)$r['course_id'];
        $r['lecture_count'] = $stats[$cid]['cnt'] ?? 0;
        $r['duration_sec'] = $stats[$cid]['dur'] ?? 0;
    }
    return $rows;
}

/** Kurs başına ders sayısı ve toplam süre */
function student_course_stats(PDO $pdo, array $courseIds) {
    $courseIds = array_values(array_unique(array_filter($courseIds)));
    if (!$courseIds) {
        return [];
    }
    $place = implode(',', array_fill(0, count($courseIds), '?'));
    $st = $pdo->prepare(
        "SELECT course_id, COUNT(*) AS cnt, COALESCE(SUM(duration_sec),0) AS dur
         FROM course_lectures WHERE course_id IN ($place) GROUP BY course_id"
    );
    $st->execute($courseIds);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int)$r['course_id']] = ['cnt' => (int)$r['cnt'], 'dur' => (int)$r['dur']];
    }
    return $out;
}

/** Saniyeyi "3sa 12dk" biçimine çevir */
function student_format_duration($seconds) {
    $seconds = (int)$seconds;
    if ($seconds <= 0) {
        return '';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $m > 0 ? $h . 'sa ' . $m . 'dk' : $h . 'sa';
    }
    return max(1, $m) . 'dk';
}

/** Öğrencinin belirli bir kursa erişimi var mı (ödenmiş kayıt) */
function student_has_paid_access(PDO $pdo, $studentId, $courseId) {
    $st = $pdo->prepare(
        "SELECT id FROM course_enrollments
         WHERE student_id = ? AND course_id = ? AND payment_status = 'paid' LIMIT 1"
    );
    $st->execute([(int)$studentId, (int)$courseId]);
    return (bool)$st->fetch();
}

/** Satın alınmış kurs id listesi */
function student_paid_course_ids(PDO $pdo, $studentId): array {
    $st = $pdo->prepare(
        "SELECT course_id FROM course_enrollments
         WHERE student_id = ? AND payment_status = 'paid'"
    );
    $st->execute([(int)$studentId]);
    $ids = [];
    foreach ($st->fetchAll() as $r) {
        $ids[] = (int)$r['course_id'];
    }
    return $ids;
}

/**
 * Öğrencinin bir kurstaki ders ilerlemeleri: [lecture_id => row]
 */
function student_lecture_progress_map(PDO $pdo, int $studentId, int $courseId): array {
    if ($studentId <= 0 || $courseId <= 0) {
        return [];
    }
    progress_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT lecture_id, position_sec, max_sec, duration_sec, completed_at
         FROM course_lecture_progress
         WHERE student_id = ? AND course_id = ?"
    );
    $st->execute([$studentId, $courseId]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(int)$row['lecture_id']] = $row;
    }
    return $out;
}

function student_enrollment_resume(PDO $pdo, int $studentId, int $courseId): array {
    $st = $pdo->prepare(
        "SELECT last_lecture_id, last_seconds, progress_pct
         FROM course_enrollments
         WHERE student_id = ? AND course_id = ? AND payment_status = 'paid' LIMIT 1"
    );
    $st->execute([$studentId, $courseId]);
    $row = $st->fetch();
    return $row ?: ['last_lecture_id' => null, 'last_seconds' => 0, 'progress_pct' => 0];
}

function student_recalc_course_progress(PDO $pdo, int $studentId, int $courseId): int {
    $totalSt = $pdo->prepare(
        "SELECT COUNT(*) FROM course_lectures WHERE course_id = ? AND video_path <> ''"
    );
    $totalSt->execute([$courseId]);
    $total = (int)$totalSt->fetchColumn();
    if ($total <= 0) {
        return 0;
    }
    $doneSt = $pdo->prepare(
        "SELECT COUNT(*) FROM course_lecture_progress p
         JOIN course_lectures l ON l.id = p.lecture_id
         WHERE p.student_id = ? AND p.course_id = ? AND p.completed_at IS NOT NULL
           AND l.video_path <> ''"
    );
    $doneSt->execute([$studentId, $courseId]);
    $done = (int)$doneSt->fetchColumn();
    $pct = (int)round(100 * $done / $total);
    $pct = max(0, min(100, $pct));
    $pdo->prepare(
        "UPDATE course_enrollments
         SET progress_pct = ?, last_visit_at = NOW()
         WHERE student_id = ? AND course_id = ? AND payment_status = 'paid'"
    )->execute([$pct, $studentId, $courseId]);
    return $pct;
}
