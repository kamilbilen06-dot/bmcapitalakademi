<?php
/**
 * Ziyaretçi takibi — her sayfa görüntülemesi.
 * Anonim visitor_id (çerez) aynı kişiyi sonraki ziyaretlerde birleştirir.
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

$lowPath = strtolower($path);
if (
    str_contains($lowPath, '/admin')
    || str_contains($lowPath, '/egitmen')
    || str_contains($lowPath, '/api/')
) {
    json_out(['ok' => true]);
}

if (
    stripos($ua, 'bot') !== false
    || stripos($ua, 'spider') !== false
    || stripos($ua, 'crawl') !== false
    || stripos($ua, 'preview') !== false
) {
    json_out(['ok' => true]);
}

$vid = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', (string)($in['vid'] ?? ($_COOKIE['bm_vid'] ?? ''))));
if (strlen($vid) < 8) {
    try {
        $vid = strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $e) {
        $vid = strtoupper(substr(hash('sha256', $ip . $ua . microtime(true)), 0, 8));
    }
}
$vid = substr($vid, 0, 8);

if (!headers_sent()) {
    setcookie('bm_vid', $vid, [
        'expires' => time() + 400 * 86400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\\d+$/', '', $host);

try {
    $pdo = db();
    analytics_ensure_schema($pdo);
    $source = analytics_source_from_referrer($referrer, $host);
    $city = analytics_lookup_city($pdo, $ip);

    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM page_views
         WHERE visitor_id = ? AND path = ? AND created_at > (NOW() - INTERVAL 8 SECOND)"
    );
    $chk->execute([$vid, $path]);
    if ((int)$chk->fetchColumn() === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO page_views (ip, visitor_id, path, referrer, source, city, title, ua)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$ip, $vid, $path, $referrer, $source, $city, $title, $ua]);
    }
    json_out(['ok' => true, 'vid' => $vid]);
} catch (Throwable $e) {
    json_out(['ok' => false], 200);
}
