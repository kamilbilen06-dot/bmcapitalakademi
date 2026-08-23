<?php
/**
 * Sosyal giriş (Google vb.) yapılandırması.
 *
 * Gizli anahtarlar api/oauth_config.local.php içine yazılır (Git'e girmez).
 * Örnek: api/oauth_config.local.example.php
 *
 * Google Cloud Console adımları:
 *   1) console.cloud.google.com → proje oluştur
 *   2) "APIs & Services" → "OAuth consent screen" → External → uygulama adı, destek e-postası
 *   3) "Credentials" → "Create credentials" → "OAuth client ID" → Web application
 *   4) Authorized redirect URIs (dördünü de ekleyin):
 *        http://localhost:8000/api/oauth_google_callback.php
 *        http://127.0.0.1:8000/api/oauth_google_callback.php
 *        https://www.bmcapitalakademi.com/api/oauth_google_callback.php
 *        https://bmcapitalakademi.com/api/oauth_google_callback.php
 *   5) Client ID + Client secret değerlerini oauth_config.local.php dosyasına yazın
 *
 * Güncel redirect URI'yi görmek için: GET /api/oauth_status.php
 */

if (is_file(__DIR__ . '/oauth_config.local.php')) {
    require __DIR__ . '/oauth_config.local.php';
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', '');
}

/** Google uç noktaları (OpenID Connect discovery değerleri) */
const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

function oauth_google_ready(): bool {
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

/**
 * İsteğin geldiği gerçek kök adres.
 *
 * PUBLIC_SITE_URL kullanılmaz: yerelde 127.0.0.1, canlıda alan adı gerekir ve
 * Google redirect_uri'yi harfi harfine eşleştirir.
 */
function oauth_request_origin(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

/** Sağlayıcı geri dönüş adresi — sorgu parametresi yok (Google tam eşleşme ister) */
function oauth_redirect_uri(string $provider): string {
    return oauth_request_origin() . '/api/oauth_' . $provider . '_callback.php';
}

/**
 * Sağlayıcıya sunucudan sunucuya istek (POST form veya GET + Bearer).
 * TLS doğrulaması hiçbir koşulda kapatılmaz.
 */
function oauth_http(string $url, ?array $postFields = null, array $headers = []): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($postFields !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($postFields);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = is_string($body) ? json_decode($body, true) : null;
    return [
        'status' => $status,
        'error' => $err,
        'data' => is_array($json) ? $json : [],
    ];
}
