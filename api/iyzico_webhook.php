<?php
/**
 * iyzico sunucudan-sunucuya bildirim.
 *
 * Merchant paneli: Ayarlar → Firma Ayarları → Merchant Bildirimleri
 * Adres: /api/iyzico_webhook.php
 *
 * Ödeme sonucu (CHECKOUT_FORM_AUTH), abonelik çekimleri
 * (subscription.order.success / failure) ve panelden iade buraya düşer.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/iyzico_client.php';

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');

$raw = (string)file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload) || $payload === []) {
    parse_str($raw, $parsed);
    $payload = is_array($parsed) ? $parsed : $_POST;
}
if (!is_array($payload) || $payload === []) {
    echo 'OK';
    exit;
}

$sig = '';
foreach (['HTTP_X_IYZ_SIGNATURE_V3', 'HTTP_X_IYZ_SIGNATURE-V3', 'HTTP_X_IYZ_SIGNATURE'] as $h) {
    if (!empty($_SERVER[$h])) {
        $sig = trim((string)$_SERVER[$h]);
        break;
    }
}

$eventRaw = (string)($payload['iyziEventType'] ?? '');
$event = strtoupper($eventRaw);
$isSubEvent = str_starts_with(strtolower($eventRaw), 'subscription.');
$status = strtoupper((string)($payload['status'] ?? ''));
$token = trim((string)($payload['token'] ?? ''));
$conv = trim((string)($payload['paymentConversationId'] ?? $payload['conversationId'] ?? ''));
$paymentId = trim((string)($payload['iyziPaymentId'] ?? $payload['paymentId'] ?? ''));

$sigOk = $sig !== '' && (
    iyzico_webhook_signature_ok($payload, $sig)
    || iyzico_subscription_webhook_signature_ok($payload, $sig)
);

if ($isSubEvent && !$sigOk) {
    payments_log_write(0, 'iyzico', 'inbound', 'webhook:sub_bad_sig', $payload);
    error_log('iyzico webhook imza uyusmadi (abonelik)');
    echo 'OK';
    exit;
}

if (!$isSubEvent && $sig !== '' && !$sigOk) {
    payments_log_write(payments_log_resolve_order_id($payload), 'iyzico', 'inbound', 'webhook:bad_sig', $payload);
    error_log('iyzico webhook imza uyusmadi');
    echo 'OK';
    exit;
}

$isRefund = str_contains($event, 'REFUND')
    || str_contains($event, 'CANCEL')
    || str_contains($event, 'VOID');

try {
    $pdo = db();
    payments_ensure_schema($pdo);

    if ($isSubEvent) {
        require_once __DIR__ . '/subscriptions.php';
        subscriptions_ensure_schema($pdo);
        payments_log_write(0, 'iyzico', 'inbound', 'webhook:' . ($event !== '' ? $event : 'sub'), $payload);
        subscription_handle_webhook($pdo, $payload);
        echo 'OK';
        exit;
    }

    $order = iyzico_find_order($pdo, $token, $conv, $paymentId);
    payments_log_write(
        $order ? (int)$order['id'] : payments_log_resolve_order_id($payload),
        'iyzico',
        'inbound',
        'webhook:' . ($event !== '' ? $event : 'unknown'),
        $payload
    );
    if (!$order) {
        echo 'OK';
        exit;
    }

    if ($isRefund && $order['status'] === 'paid') {
        payments_revoke_enrollment($pdo, $order, 'iyzico iade (' . ($event !== '' ? $event : 'webhook') . ')');
        echo 'OK';
        exit;
    }

    // Ödeme bildirimi: token varsa sonucu tekrar sorgula (callback kaçarsa yedek)
    if ($order['status'] === 'pending' && $token !== '' && $status === 'SUCCESS') {
        $res = iyzico_checkout_retrieve($token, (string)$order['conversation_id']);
        if ($res['ok'] && $res['paid']) {
            $paidPrice = (string)($res['data']['paidPrice'] ?? '');
            $pdo->prepare(
                "UPDATE payment_orders
                 SET status = 'paid', paid_price = ?, provider_payment_id = ?, error_message = '', paid_at = NOW()
                 WHERE id = ?"
            )->execute([$paidPrice, (string)($res['data']['paymentId'] ?? $paymentId), (int)$order['id']]);
            payments_grant_enrollment($pdo, $order, 'iyzico');
        }
    }

    // Zaten ödenmiş sipariş: iyzico'dan teyit et (panel iadesi event adıyla gelmezse)
    if ($order['status'] === 'paid' && ($order['provider_payment_id'] !== '' || $paymentId !== '')) {
        $pid = $paymentId !== '' ? $paymentId : (string)$order['provider_payment_id'];
        $report = iyzico_payment_report($pid);
        if ($report['ok'] && iyzico_report_looks_refunded($report['data'])) {
            payments_revoke_enrollment($pdo, $order, iyzico_report_cancel_reason($report['data']));
        } else {
            $res = iyzico_payment_retrieve($pid, (string)$order['conversation_id']);
            if ($res['ok'] && iyzico_looks_refunded($res['data'])) {
                payments_revoke_enrollment($pdo, $order, 'iyzico iade');
            }
        }
    }
} catch (Throwable $e) {
    error_log('iyzico webhook istisnasi: ' . $e->getMessage());
}

echo 'OK';

function iyzico_find_order(PDO $pdo, string $token, string $conv, string $paymentId): ?array {
    if ($token !== '') {
        $st = $pdo->prepare("SELECT * FROM payment_orders WHERE provider = 'iyzico' AND provider_token = ? LIMIT 1");
        $st->execute([$token]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }
    }
    if ($conv !== '') {
        $st = $pdo->prepare(
            "SELECT * FROM payment_orders
             WHERE provider = 'iyzico' AND (conversation_id = ? OR merchant_oid = ?) LIMIT 1"
        );
        $st->execute([$conv, $conv]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }
    }
    if ($paymentId !== '') {
        $st = $pdo->prepare("SELECT * FROM payment_orders WHERE provider = 'iyzico' AND provider_payment_id = ? LIMIT 1");
        $st->execute([$paymentId]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }
    }
    return null;
}
