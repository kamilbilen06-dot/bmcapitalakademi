<?php
/**
 * Marka + public domain — canlı yayın için tek kaynak.
 *
 * Yeni marka / domain kararlaştırılınca:
 *   1) site_brand.local.example.php → site_brand.local.php kopyala
 *   2) Değerleri doldur (BRAND_NAME, PUBLIC_SITE_URL, …)
 *   3) api/launch_status.php ile kontrol et
 *
 * site_brand.local.php Git'e girmez.
 */

if (is_file(__DIR__ . '/site_brand.local.php')) {
    require __DIR__ . '/site_brand.local.php';
}

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'Europe/Istanbul');
}

/** Marka tam adı (header/SEO) */
if (!defined('BRAND_NAME')) {
    define('BRAND_NAME', 'BM Capital Akademi');
}
/** Kısa marka (schema alternateName) */
if (!defined('BRAND_SHORT')) {
    define('BRAND_SHORT', 'BM Capital');
}
/** Logo kutusu metni (ör. BM) */
if (!defined('BRAND_MARK')) {
    define('BRAND_MARK', 'BM');
}
/** Wordmark ikinci satır */
if (!defined('BRAND_WORD')) {
    define('BRAND_WORD', 'Capital');
}
/** Wordmark altı (Akademi) */
if (!defined('BRAND_TAGLINE')) {
    define('BRAND_TAGLINE', 'Akademi');
}
/**
 * Canlı kök URL — sonda / yok.
 * Örnek: https://www.yenimarka.com
 * Boşsa istek host'undan türetilir (yerel geliştirme).
 */
if (!defined('PUBLIC_SITE_URL')) {
    define('PUBLIC_SITE_URL', '');
}
/** Şehir (schema / iletişim) */
if (!defined('BRAND_CITY')) {
    define('BRAND_CITY', 'İzmir');
}
/**
 * true: marka + domain dolduruldu, canlı satışa hazır.
 * Yeni projede local dosyada true yapın.
 */
if (!defined('BRAND_DOMAIN_READY')) {
    define('BRAND_DOMAIN_READY', false);
}

/**
 * Public site kök URL (HTTPS tercihli).
 */
function site_public_url(): string {
    if (PUBLIC_SITE_URL !== '') {
        return rtrim(PUBLIC_SITE_URL, '/');
    }
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** Maildeki tıklanır adres. Localhost Gmail’de spam sayıldığı için canlı domain kullanılır. */
function site_mail_public_url(): string {
    if (defined('PUBLIC_SITE_URL') && PUBLIC_SITE_URL !== '') {
        return rtrim((string)PUBLIC_SITE_URL, '/');
    }
    $u = site_public_url();
    $host = strtolower((string)(parse_url($u, PHP_URL_HOST) ?? ''));
    $local = $host === ''
        || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || (bool)preg_match('/\.(local|test|localhost)$/', $host);
    if (!$local && filter_var($host, FILTER_VALIDATE_IP)) {
        $local = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    if (!$local) {
        if (strcasecmp($host, 'bmcapitalakademi.com') === 0) {
            return preg_replace('#://bmcapitalakademi\\.com#i', '://www.bmcapitalakademi.com', $u, 1);
        }
        return $u;
    }
    return 'https://www.bmcapitalakademi.com';
}

function site_brand_payload(): array {
    return [
        'marka' => BRAND_NAME,
        'short' => BRAND_SHORT,
        'mark' => BRAND_MARK,
        'word' => BRAND_WORD,
        'tagline' => BRAND_TAGLINE,
        'sehir' => BRAND_CITY,
        'publicUrl' => site_public_url(),
        'brandDomainReady' => (bool)BRAND_DOMAIN_READY,
    ];
}

function site_course_share_url(string $courseIdOrSlug): string {
    $id = rawurlencode($courseIdOrSlug);
    return site_public_url() . '/egitim-detay.html?id=' . $id;
}

function site_odeme_share_url(string $courseIdOrSlug = ''): string {
    $base = site_public_url() . '/odeme.php';
    if ($courseIdOrSlug === '') {
        return $base;
    }
    // odeme.php "course" parametresini bekler
    return $base . '?course=' . rawurlencode($courseIdOrSlug);
}
