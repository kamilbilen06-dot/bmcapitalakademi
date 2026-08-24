<?php
/**
 * Canlı yayın kontrol listesi.
 * GET /api/launch_status.php
 * Gizli anahtar döndürmez.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_brand.php';

header('Access-Control-Allow-Origin: *');

$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$publicUrl = site_public_url();
$configuredUrl = PUBLIC_SITE_URL !== '' ? rtrim(PUBLIC_SITE_URL, '/') : (defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : '');
$isLocalHost = (bool)preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', (string)(parse_url($publicUrl, PHP_URL_HOST) ?? ''));

$uploadsDir = dirname(__DIR__) . '/uploads/courses';
$uploadsOk = is_dir($uploadsDir) && is_writable($uploadsDir);

$dbOk = false;
$dbName = '';
$publishedCourses = 0;
$installLocked = defined('INSTALL_LOCKED') && INSTALL_LOCKED;
try {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $dbOk = true;
    $dbName = defined('DB_NAME') ? DB_NAME : '';
    try {
        require_once __DIR__ . '/egitmen_schema.php';
        egitmen_ensure_schema($pdo);
        $publishedCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
    } catch (Throwable $e) {
        $publishedCourses = 0;
    }
} catch (Throwable $e) {
    $dbOk = false;
}

$steps = [
    [
        'id' => 'brand-domain',
        'title' => 'Marka adı + domain',
        'done' => (bool)BRAND_DOMAIN_READY && $configuredUrl !== '' && !$isLocalHost,
        'detail' => BRAND_DOMAIN_READY
            ? ('Marka: ' . BRAND_NAME . ' · URL: ' . ($configuredUrl ?: $publicUrl))
            : 'api/site_brand.local.php oluşturun (örnek: site_brand.local.example.php). Domain alınca PUBLIC_SITE_URL ve BRAND_DOMAIN_READY=true.',
        'owner' => 'siz_hoca',
    ],
    [
        'id' => 'host-ssl',
        'title' => 'Hosting + DNS + HTTPS',
        'done' => $httpsOn && !$isLocalHost && $uploadsOk,
        'detail' => implode(' · ', array_filter([
            $httpsOn ? 'HTTPS açık' : 'HTTPS yok',
            $isLocalHost ? 'Hâlâ localhost' : 'Public host',
            $uploadsOk ? 'uploads/courses yazılabilir' : 'uploads/courses yazılamıyor',
        ])),
        'owner' => 'siz',
    ],
    [
        'id' => 'deploy-rebrand',
        'title' => 'Kod canlı + DB kurulum',
        'done' => $dbOk && $installLocked && $configuredUrl !== '',
        'detail' => implode(' · ', array_filter([
            $dbOk ? ('DB: ' . $dbName) : 'DB bağlantısı yok',
            $installLocked ? 'INSTALL_LOCKED=true' : 'INSTALL_LOCKED=false — kurulumdan sonra kilitleyin',
            $configuredUrl !== '' ? 'PUBLIC_SITE_URL/SITE_URL set' : 'Public URL config eksik',
        ])),
        'owner' => 'yazilim_siz',
    ],
    [
        'id' => 'instructor-share',
        'title' => 'Hoca kurs yayınla + link paylaş',
        'done' => $publishedCourses > 0 && $configuredUrl !== '' && !$isLocalHost,
        'detail' => $publishedCourses > 0
            ? ($publishedCourses . ' yayında kurs · paylaşım: egitmen paneli → Yayın Durumu')
            : 'Eğitmen panelinden kursu Yayında yapın; paylaş linkini kopyalayın.',
        'owner' => 'hoca',
    ],
];

$doneCount = 0;
foreach ($steps as $s) {
    if (!empty($s['done'])) {
        $doneCount++;
    }
}

json_out([
    'ok' => true,
    'brand' => site_brand_payload(),
    'progress' => [
        'done' => $doneCount,
        'total' => count($steps),
        'pct' => (int)round(100 * $doneCount / max(1, count($steps))),
    ],
    'steps' => $steps,
    'hints' => [
        'Kart ödemesi iyzico ile alınır. Havale / EFT ödeme sayfasında durur.',
        'Yeni domain olmadan canlı kart ödemesi açılamaz.',
    ],
]);
