<?php
/**
 * Operasyon cron — iyzico iade senkronu, abonelik gece eşitlemesi, eski jetonlar.
 *
 * Abonelik: dönem saatinde süre doldurulmaz. Türkiye 24:00 sonrası iyzico’ya
 * bakılır; çekildiyse / ACTIVE ise aynı kayıt uzar, değilse expired olur.
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
    $out = ['ok' => true, 'expired' => 0, 'renewed' => 0, 'invoices' => 0, 'refunds' => 0, 'tokens' => 0, 'collapsed' => 0];

try {
    $pdo = db();
    payments_ensure_schema($pdo);
    subscriptions_ensure_schema($pdo);
    students_ensure_schema($pdo);

    $rec = subscription_reconcile_due_periods($pdo);
    $out['expired'] = $rec['expired'];
    $out['renewed'] = $rec['renewed'];
    subscription_dedupe_invoices_by_payment_id($pdo);
    $out['invoices'] = subscription_import_iyzico_payments(
        $pdo,
        date('Y-m-d', strtotime('-4 days')),
        date('Y-m-d'),
        0.0,
        6
    );
    subscription_dedupe_invoices_by_payment_id($pdo);
    $out['collapsed'] = subscription_collapse_duplicate_actives($pdo);
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
