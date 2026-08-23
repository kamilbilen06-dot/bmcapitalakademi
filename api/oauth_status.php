<?php
/**
 * Sosyal giriş kurulum kontrolü.
 *
 * Google paneline yazılması gereken redirect URI'yi ve anahtarların yerinde
 * olup olmadığını gösterir. Anahtar değerleri sızdırılmaz.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/oauth_config.php';

header('Cache-Control: no-store');

json_out([
    'ok' => true,
    'origin' => oauth_request_origin(),
    'providers' => [
        'google' => [
            'ready' => oauth_google_ready(),
            'clientIdSet' => GOOGLE_CLIENT_ID !== '',
            'clientSecretSet' => GOOGLE_CLIENT_SECRET !== '',
            'redirectUri' => oauth_redirect_uri('google'),
            'startUrl' => '/api/oauth_google.php',
        ],
    ],
    'tls' => [
        'caBundle' => ini_get('curl.cainfo') ?: '(ayarlanmadi)',
    ],
    'note' => 'redirectUri degerini Google Cloud Console > Credentials > OAuth client ID > Authorized redirect URIs alanina birebir ekleyin.',
]);
