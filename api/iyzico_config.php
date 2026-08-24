<?php
/**
 * iyzico yapılandırması.
 *
 * Gizli anahtarlar api/iyzico_config.local.php içine yazılır (Git'e girmez).
 * Örnek: api/iyzico_config.local.example.php
 *
 * Anahtarları alma:
 *   Sandbox : https://sandbox-merchant.iyzipay.com → Ayarlar → Firma Ayarları → API Anahtarları
 *   Canlı   : https://merchant.iyzipay.com → aynı yol
 *
 * Kurulum kontrolü: GET /api/iyzico_status.php
 */

/**
 * Anahtar kaynağı: canlı/kişisel anahtarlar local dosyadan gelir. O dosya yoksa
 * Git'teki sandbox anahtarlarına düşülür; böylece test ortamı deploy ile hazır
 * olur. Local dosya varken sandbox dosyasına hiç bakılmaz.
 */
if (is_file(__DIR__ . '/iyzico_config.local.php')) {
    require __DIR__ . '/iyzico_config.local.php';
    define('IYZICO_KEY_SOURCE', 'local');
} elseif (is_file(__DIR__ . '/iyzico_config.sandbox.php')) {
    require __DIR__ . '/iyzico_config.sandbox.php';
    define('IYZICO_KEY_SOURCE', 'sandbox-file');
} else {
    define('IYZICO_KEY_SOURCE', 'none');
}

if (!defined('IYZICO_API_KEY')) {
    define('IYZICO_API_KEY', '');
}
if (!defined('IYZICO_SECRET_KEY')) {
    define('IYZICO_SECRET_KEY', '');
}

/** 1 = sandbox (test), 0 = canlı. Varsayılan test. */
if (!defined('IYZICO_TEST_MODE')) {
    define('IYZICO_TEST_MODE', 1);
}

/** Taksit seçenekleri — [1] yalnızca tek çekim demektir. */
if (!defined('IYZICO_INSTALLMENTS')) {
    define('IYZICO_INSTALLMENTS', '1,2,3,6,9,12');
}

/** Abonelik webhook imzası için işyeri no (opsiyonel). Merchant panelinden. */
if (!defined('IYZICO_MERCHANT_ID')) {
    define('IYZICO_MERCHANT_ID', '');
}

const IYZICO_SANDBOX_BASE = 'https://sandbox-api.iyzipay.com';
const IYZICO_PRODUCTION_BASE = 'https://api.iyzipay.com';

/** Checkout Form uç noktaları (docs.iyzico.com) */
const IYZICO_PATH_CF_INITIALIZE = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
const IYZICO_PATH_CF_RETRIEVE = '/payment/iyzipos/checkoutform/auth/ecom/detail';
const IYZICO_PATH_PAYMENT_DETAIL = '/payment/detail';

/** Abonelik (Subscription) v2 — sandbox'ta ürünün açık olması gerekir */
const IYZICO_PATH_SUB_PRODUCTS = '/v2/subscription/products';
const IYZICO_PATH_SUB_CF_INIT = '/v2/subscription/checkoutform/initialize';
const IYZICO_PATH_SUB_CF_RETRIEVE = '/v2/subscription/checkoutform/retrieve';

function iyzico_base_url(): string {
    return ((int)IYZICO_TEST_MODE === 1) ? IYZICO_SANDBOX_BASE : IYZICO_PRODUCTION_BASE;
}

function iyzico_ready(): bool {
    return IYZICO_API_KEY !== '' && IYZICO_SECRET_KEY !== '';
}

function iyzico_is_sandbox(): bool {
    return (int)IYZICO_TEST_MODE === 1;
}

/** Taksit listesini tam sayı dizisine çevir */
function iyzico_installments(): array {
    $out = [];
    foreach (explode(',', (string)IYZICO_INSTALLMENTS) as $part) {
        $n = (int)trim($part);
        if ($n >= 1 && !in_array($n, $out, true)) {
            $out[] = $n;
        }
    }
    return $out ?: [1];
}

/**
 * iyzico'nun ödeme sonucunu göndereceği adres.
 *
 * İsteğin geldiği gerçek kök adresten üretilir; böylece yerelde 127.0.0.1,
 * canlıda bmcapitalakademi.com kendiliğinden doğru olur.
 */
function iyzico_callback_url(): string {
    if (!function_exists('paytr_site_base')) {
        require_once __DIR__ . '/paytr_schema.php';
    }
    return rtrim(paytr_site_base(), '/') . '/api/iyzico_callback.php';
}

function iyzico_webhook_url(): string {
    if (!function_exists('paytr_site_base')) {
        require_once __DIR__ . '/paytr_schema.php';
    }
    return rtrim(paytr_site_base(), '/') . '/api/iyzico_webhook.php';
}

function iyzico_sub_callback_url(): string {
    if (!function_exists('paytr_site_base')) {
        require_once __DIR__ . '/paytr_schema.php';
    }
    return rtrim(paytr_site_base(), '/') . '/api/iyzico_sub_callback.php';
}
