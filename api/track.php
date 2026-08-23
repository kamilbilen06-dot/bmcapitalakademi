<?php
/**
 * Ziyaretçi takibi - her sayfa görüntülemesini kaydeder (panelde istatistik olarak gösterilir).
 * Aynı ziyaretçinin kısa süre içindeki tekrar istekleri (30 dk) tekrar sayılmaz.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}

$in = body_json();
$path = mb_substr(clean($in['path'] ?? ''), 0, 255);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Basit bot filtresi
if (stripos($ua, 'bot') !== false || stripos($ua, 'spider') !== false || stripos($ua, 'crawl') !== false) {
    json_out(['ok' => true]);
}

try {
    $pdo = db();
    // Aynı IP + path son 30 dk içinde kaydedildiyse tekrar yazma
    $chk = $pdo->prepare("SELECT COUNT(*) FROM page_views WHERE ip = ? AND path = ? AND created_at > (NOW() - INTERVAL 30 MINUTE)");
    $chk->execute([$ip, $path]);
    if ((int)$chk->fetchColumn() === 0) {
        $stmt = $pdo->prepare("INSERT INTO page_views (ip, path, ua) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $path, $ua]);
    }
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_out(['ok' => false], 200);
}
