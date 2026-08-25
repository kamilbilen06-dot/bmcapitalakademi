<?php
/**
 * iyzico kurulum kontrolü. Anahtar değerleri sızdırılmaz.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/iyzico_client.php';

header('Cache-Control: no-store');

$key = (string)IYZICO_API_KEY;
$looksSandbox = strpos($key, 'sandbox-') === 0;

$ping = ['ok' => false, 'error' => 'Anahtar yok', 'reason' => 'config'];
if (iyzico_ready()) {
    $pingRes = iyzico_request(IYZICO_PATH_CF_RETRIEVE, [
        'locale' => 'tr',
        'token' => 'status-ping',
    ]);
    $ping = [
        'ok' => ($pingRes['reason'] ?? '') !== 'network' && ($pingRes['reason'] ?? '') !== 'config',
        'error' => (string)($pingRes['error'] ?? ''),
        'reason' => (string)($pingRes['reason'] ?? ''),
    ];
}

json_out([
    'ok' => true,
    'ready' => iyzico_ready(),
    'apiKeySet' => $key !== '',
    'secretKeySet' => IYZICO_SECRET_KEY !== '',
    'keySource' => defined('IYZICO_KEY_SOURCE') ? IYZICO_KEY_SOURCE : 'none',
    'testMode' => iyzico_is_sandbox(),
    'baseUrl' => iyzico_base_url(),
    'callbackUrl' => iyzico_callback_url(),
    'subscriptionCallbackUrl' => iyzico_sub_callback_url(),
    'webhookUrl' => iyzico_webhook_url(),
    'installments' => iyzico_installments(),
    'keyEnvMatches' => $key === '' ? null : ($looksSandbox === iyzico_is_sandbox()),
    'apiReachable' => !empty($ping['ok']),
    'apiPing' => $ping,
    'tls' => ['caBundle' => ini_get('curl.cainfo') ?: '(ayarlanmadi)'],
    'note' => 'Canli anahtarlari Admin → Ayarlar → iyzico alanina yazin. Sandbox anahtarlari "sandbox-" ile baslar.',
]);
