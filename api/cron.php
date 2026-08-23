<?php
/**
 * Operasyon cron — iyzico iade senkronu, abonelik süresi, eski jetonlar.
 *
 * Hosting cron örneği (her 15 dk):
 *   php /home/.../public_html/api/cron.php KEY
 * veya
 *   https://DOMAIN/api/cron.php?key=KEY
 *
 * KEY: api/config.php içindeki CRON_KEY. Boşsa yalnız localhost kabul edilir.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/iyzico_client.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/students_schema.php';

$given = '';
if (PHP_SAPI === 'cli') {
    $given = (string)($argv[1] ?? '');
} else {
    $given = (string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
}

$key = defined('CRON_KEY') ? (string)CRON_KEY : '';
$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'cli')));
$isLocal = PHP_SAPI === 'cli' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
if ($key === '') {
    if (!$isLocal) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'cron kapalı';
        exit;
    }
} elseif (!hash_equals($key, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'yetkisiz';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$out = ['ok' => true, 'expired' => 0, 'refunds' => 0, 'tokens' => 0];

try {
    $pdo = db();
    payments_ensure_schema($pdo);
    subscriptions_ensure_schema($pdo);
    students_ensure_schema($pdo);

    $out['expired'] = subscription_expire_overdue($pdo);
    $out['refunds'] = payments_sync_iyzico_refunds_all($pdo, 40);

    try {
        $n = $pdo->exec(
            "DELETE FROM student_tokens
             WHERE (used_at IS NOT NULL AND used_at < (NOW() - INTERVAL 7 DAY))
                OR expires_at < (NOW() - INTERVAL 1 DAY)"
        );
        $out['tokens'] = (int)$n;
    } catch (Throwable $e) {
        $out['tokens'] = 0;
    }
} catch (Throwable $e) {
    error_log('cron: ' . $e->getMessage());
    $out['ok'] = false;
    $out['error'] = 'Sunucu hatası';
    http_response_code(500);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
