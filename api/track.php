<?php
/**
 * Ziyaretçi takibi - her sayfa görüntülemesini kaydeder (panelde istatistik olarak gösterilir).
 * Aynı ziyaretçinin kısa süre içindeki tekrar istekleri (30 dk) tekrar sayılmaz.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/analytics.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}

$in = body_json();
$path = mb_substr(clean($in['path'] ?? ''), 0, 255);
if ($path === '') {
    $path = mb_substr(clean((string)($_SERVER['REQUEST_URI'] ?? '/')), 0, 255);
}
$title = mb_substr(clean($in['title'] ?? ''), 0, 160);
$referrer = mb_substr(clean($in['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 255);
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$visitorId = analytics_visitor_id($in['visitor_id'] ?? ($_COOKIE['bm_vid'] ?? ''));
if (!headers_sent()) {
    setcookie('bm_vid', $visitorId, [
        'expires' => time() + 400 * 86400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

// Basit bot filtresi
if (stripos($ua, 'bot') !== false || stripos($ua, 'spider') !== false || stripos($ua, 'crawl') !== false) {
    json_out(['ok' => true]);
}

try {
    $pdo = db();
    analytics_ensure_schema($pdo);
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $source = analytics_source_from_referrer($referrer, $host);
    $city = analytics_lookup_city($pdo, $ip);

    // Sayfa yenilemesinin çift kaydını önle; ayrı sayfalar normal görüntülemedir.
    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM page_views
         WHERE visitor_id = ? AND path = ? AND created_at > (NOW() - INTERVAL 8 SECOND)"
    );
    $chk->execute([$visitorId, $path]);
    if ((int)$chk->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO page_views (ip, visitor_id, path, referrer, source, city, title, ua)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$ip, $visitorId, $path, $referrer, $source, $city, $title, $ua]);
    }
    json_out(['ok' => true, 'visitor_id' => $visitorId]);
} catch (Throwable $e) {
    json_out(['ok' => false], 200);
}
