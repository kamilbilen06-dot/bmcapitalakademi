<?php
/**
 * PayTR kurulum kontrolü (gizli anahtar döndürmez)
 * GET /api/paytr_status.php
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_brand.php';
require_once __DIR__ . '/paytr_config.php';
require_once __DIR__ . '/paytr_schema.php';

header('Access-Control-Allow-Origin: *');

$ready = paytr_credentials_ready();
$base = paytr_site_base();
$host = (string)(parse_url($base, PHP_URL_HOST) ?? '');
$isLocal = (bool)preg_match('/^(localhost|127\.0\.0\.1)$/i', $host);
json_out([
    'ok' => true,
    'ready' => $ready,
    'test_mode' => (int)(defined('PAYTR_TEST_MODE') ? PAYTR_TEST_MODE : 1),
    'merchant_id_set' => defined('PAYTR_MERCHANT_ID') && PAYTR_MERCHANT_ID !== '' && PAYTR_MERCHANT_ID !== 'XXXXXX',
    'brand_domain_ready' => (bool)BRAND_DOMAIN_READY,
    'site_base' => $base,
    'site_is_local' => $isLocal,
    'docs' => [
        'hazir_altyapi' => 'https://www.paytr.com/entegrasyon',
        'ozel_php_iframe' => 'https://dev.paytr.com/iframe-api',
        'adim1' => 'https://dev.paytr.com/iframe-api/iframe-api-1-adim',
        'adim2' => 'https://dev.paytr.com/iframe-api/iframe-api-2-adim',
        'basvuru' => 'https://www.paytr.com/uye-isyeri-olun',
        'launch_checklist' => site_public_url() . '/api/launch_status.php',
    ],
    'callback_url' => $base . '/api/paytr_callback.php',
    'ok_url' => $base . '/odeme-basarili.html',
    'fail_url' => $base . '/odeme-basarisiz.html',
    'hint' => !$ready
        ? 'Mağaza Paneli > Entegrasyon Bilgileri → api/paytr_config.local.php'
        : ($isLocal
            ? 'Anahtarlar var ama site URL localhost. Canlı tahsilat için PUBLIC_SITE_URL (domain) şart.'
            : (defined('PAYTR_TEST_MODE') && (int)PAYTR_TEST_MODE === 1
                ? 'Test modu açık. odeme.php ile test kartını deneyin; sonra IYZICO_TEST_MODE=0 / PAYTR_TEST_MODE=0.'
                : 'Canlı moda hazır. Gerçek kart tahsilatı açıktır.')),
]);
