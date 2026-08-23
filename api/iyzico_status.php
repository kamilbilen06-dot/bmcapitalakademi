<?php
/**
 * iyzico kurulum kontrolü. Anahtar değerleri sızdırılmaz.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/iyzico_client.php';

header('Cache-Control: no-store');

$key = (string)IYZICO_API_KEY;
$looksSandbox = strpos($key, 'sandbox-') === 0;

json_out([
    'ok' => true,
    'ready' => iyzico_ready(),
    'apiKeySet' => $key !== '',
    'secretKeySet' => IYZICO_SECRET_KEY !== '',
    'testMode' => iyzico_is_sandbox(),
    'baseUrl' => iyzico_base_url(),
    'callbackUrl' => iyzico_callback_url(),
    'subscriptionCallbackUrl' => iyzico_sub_callback_url(),
    'webhookUrl' => iyzico_webhook_url(),
    'installments' => iyzico_installments(),
    'keyEnvMatches' => $key === '' ? null : ($looksSandbox === iyzico_is_sandbox()),
    'tls' => ['caBundle' => ini_get('curl.cainfo') ?: '(ayarlanmadi)'],
    'note' => 'Anahtarlari api/iyzico_config.local.php dosyasina yazin. Sandbox anahtarlari "sandbox-" ile baslar.',
]);
