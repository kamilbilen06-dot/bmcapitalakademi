<?php
/**
 * Google ile giriş — adım 2: yetki kodunu jetona çevirip oturum aç.
 *
 * Bu dosyanın adresi Google panelindeki "Authorized redirect URI" ile
 * harfi harfine aynı olmalıdır (bu yüzden sorgu parametresi kullanılmaz).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/student_account.php';
require_once __DIR__ . '/oauth_config.php';

start_student_session();

$saved = $_SESSION['oauth'] ?? null;
unset($_SESSION['oauth']); // tek kullanımlık
$next = is_array($saved) ? (string)($saved['next'] ?? '') : '';

/** Hata durumunda giriş sayfasına mesajla dön */
function oauth_fail(string $message, string $next = '') {
    $qs = ['err' => $message];
    if ($next !== '') {
        $qs['next'] = $next;
    }
    header('Location: /ogrenci/giris.php?' . http_build_query($qs), true, 302);
    exit;
}

// Kullanıcı Google ekranında vazgeçtiyse sessizce geri dön
if (isset($_GET['error'])) {
    $code = (string)$_GET['error'];
    if ($code === 'access_denied') {
        header('Location: /ogrenci/giris.php' . ($next !== '' ? '?next=' . urlencode($next) : ''), true, 302);
        exit;
    }
    oauth_fail('Google girişi tamamlanamadı (' . preg_replace('/[^a-z_]/', '', $code) . ').', $next);
}

$code = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');

if ($code === '' || $state === '') {
    oauth_fail('Google yanıtı eksik geldi. Lütfen tekrar deneyin.', $next);
}
if (!is_array($saved) || ($saved['provider'] ?? '') !== 'google' || empty($saved['state'])) {
    oauth_fail('Oturum bilgisi bulunamadı. Lütfen tekrar deneyin.', $next);
}
if (!hash_equals((string)$saved['state'], $state)) {
    oauth_fail('Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.', $next);
}
if (time() - (int)($saved['created'] ?? 0) > 900) {
    oauth_fail('İşlem zaman aşımına uğradı. Lütfen tekrar deneyin.', $next);
}
if (!oauth_google_ready()) {
    oauth_fail('Google ile giriş henüz yapılandırılmadı.', $next);
}

try {
    $token = oauth_http(GOOGLE_TOKEN_URL, [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => oauth_redirect_uri('google'),
        'grant_type' => 'authorization_code',
        'code_verifier' => (string)$saved['verifier'],
    ]);

    if ($token['status'] !== 200 || empty($token['data']['access_token'])) {
        error_log('Google token hatası: http=' . $token['status'] . ' ' . ($token['error'] ?: json_encode($token['data'])));
        oauth_fail('Google ile bağlantı kurulamadı. Lütfen tekrar deneyin.', $next);
    }

    // Kullanıcı bilgisi doğrudan Google'dan TLS üzerinden alınır
    $me = oauth_http(GOOGLE_USERINFO_URL, null, ['Authorization: Bearer ' . $token['data']['access_token']]);
    if ($me['status'] !== 200 || empty($me['data']['sub'])) {
        error_log('Google userinfo hatası: http=' . $me['status'] . ' ' . $me['error']);
        oauth_fail('Google hesap bilgileri alınamadı. Lütfen tekrar deneyin.', $next);
    }

    $info = $me['data'];
    $pdo = db();
    students_ensure_schema($pdo);

    $result = student_login_with_identity(
        $pdo,
        'google',
        (string)$info['sub'],
        (string)($info['email'] ?? ''),
        (string)($info['name'] ?? ''),
        !empty($info['email_verified']),
        (string)($info['picture'] ?? '')
    );

    if (!$result['row']) {
        oauth_fail($result['error'] ?: 'Giriş yapılamadı.', $next);
    }

    student_login_session($result['row']);

    $target = $next !== '' ? $next : '/ogrenci/';
    if ($result['created']) {
        $target .= (strpos($target, '?') === false ? '?' : '&') . 'welcome=1';
    }
    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    error_log('Google OAuth istisnası: ' . $e->getMessage());
    oauth_fail('Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.', $next);
}
