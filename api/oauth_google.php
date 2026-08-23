<?php
/**
 * Google ile giriş — adım 1: kullanıcıyı Google'a yönlendir.
 *
 * GET /api/oauth_google.php?next=/ogrenci/
 *
 * Authorization Code + PKCE (S256) akışı kullanılır. CSRF'ye karşı rastgele
 * "state" üretilip öğrenci oturumunda saklanır; dönüşte karşılaştırılır.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/oauth_config.php';

start_student_session();

$next = oauth_sanitize_next($_GET['next'] ?? '');

if (!oauth_google_ready()) {
    header('Location: /ogrenci/giris.php?err=' . urlencode('Google ile giriş henüz yapılandırılmadı.')
        . ($next !== '' ? '&next=' . urlencode($next) : ''));
    exit;
}

$state = bin2hex(random_bytes(16));
$verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

$_SESSION['oauth'] = [
    'provider' => 'google',
    'state' => $state,
    'verifier' => $verifier,
    'next' => $next,
    'created' => time(),
];

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => oauth_redirect_uri('google'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
    // Hesap seçtirme: birden fazla Google hesabı olanlar için daha net
    'prompt' => 'select_account',
];

header('Location: ' . GOOGLE_AUTH_URL . '?' . http_build_query($params), true, 302);
exit;

/** Dönüş adresi yalnızca site içi olabilir (open redirect koruması) */
function oauth_sanitize_next($next): string {
    $next = trim((string)$next);
    if ($next === '' || $next[0] !== '/' || strpos($next, '//') === 0) {
        return '';
    }
    return $next;
}
