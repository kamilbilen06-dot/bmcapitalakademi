<?php
/**
 * PayTR iFrame kapatıldı. Kart iyzico, havale / EFT odeme.php’dedir.
 */
require_once __DIR__ . '/helpers.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
json_out([
    'ok' => false,
    'error' => 'PayTR kapalı. Kart ödemesi iyzico, havale / EFT ödeme sayfasındadır.',
    'code' => 'disabled',
], 410);
