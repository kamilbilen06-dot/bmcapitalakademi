<?php
/**
 * PayTR durum API’si kapatıldı.
 */
require_once __DIR__ . '/helpers.php';
json_out(['ok' => false, 'error' => 'PayTR kullanılmıyor. Kart iyzico, havale / EFT ödeme sayfasındadır.', 'code' => 'disabled'], 410);
