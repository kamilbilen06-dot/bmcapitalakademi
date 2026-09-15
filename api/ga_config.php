<?php
/**
 * Google Analytics ölçüm kimliği — statik HTML sayfaları script src ile yükler.
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site_brand.php';

$id = '';
try {
    $pdo = db();
    $st = $pdo->prepare("SELECT v FROM settings WHERE k = 'ga_measurement_id' LIMIT 1");
    $st->execute();
    $row = $st->fetchColumn();
    if (is_string($row) && $row !== '') {
        $id = $row;
    }
} catch (Throwable $e) {
    // Yerel DB yoksa site_brand sabitine düş
}

if ($id === '' && defined('GA_MEASUREMENT_ID')) {
    $id = (string)GA_MEASUREMENT_ID;
}

$id = strtoupper(trim($id));
if (!preg_match('/^G-[A-Z0-9]{6,12}$/', $id)) {
    $id = '';
}

echo 'window.BM_GA_MEASUREMENT_ID=' . json_encode($id, JSON_UNESCAPED_UNICODE) . ';';
