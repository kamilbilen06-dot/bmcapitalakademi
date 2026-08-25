<?php
/**
 * iyzico abonelik Checkout Form sonucu.
 *
 * Kullanıcıyı POST token ile döndürür. Sonuca bu POST'tan güvenilmez;
 * iyzico'dan retrieve edilir.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/subscriptions.php';

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));

function sub_callback_redirect(string $ok, string $message = ''): void {
    $qs = [];
    if ($ok === '1') {
        $qs['ok'] = '1';
    } elseif ($message !== '') {
        $qs['err'] = $message;
    }
    header('Location: /ogrenci/aboneliklerim.php' . ($qs ? '?' . http_build_query($qs) : ''), true, 302);
    exit;
}

if ($token === '') {
    sub_callback_redirect('0', 'Ödeme bilgisi alınamadı.');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $pdo = db();
    subscriptions_ensure_schema($pdo);

    $row = subscription_find_by_token($pdo, $token);
    payments_log_write(
        0,
        'iyzico',
        'inbound',
        'sub_callback',
        [
            'found' => (bool)$row,
            'token_len' => strlen($token),
            'token_tail' => substr($token, -8),
            'status' => $row ? (string)$row['status'] : '',
        ]
    );

    if (!$row) {
        sub_callback_redirect('0', 'Abonelik kaydı bulunamadı.');
    }
    if ($row['status'] === 'active') {
        sub_callback_redirect('1');
    }
    if ($row['status'] === 'cancelled' && subscription_is_entitled($row)) {
        sub_callback_redirect('1');
    }

    $res = iyzico_sub_checkout_retrieve($token, (string)$row['conversation_id']);
    if (!$res['ok']) {
        // Doğrulama isteğinin patlaması aboneliğin başarısız olduğunu GÖSTERMEZ.
        // iyzico tarafında abonelik açılmış olabilir; kaydı iptal etmek yerine
        // pending bırakıyoruz, aboneliklerim sayfası iyzico'dan tekrar sorup eşitler.
        $pdo->prepare("UPDATE subscriptions SET error_message = ?, updated_at = NOW() WHERE id = ?")
            ->execute([mb_substr($res['error'], 0, 255), (int)$row['id']]);
        error_log('IYZICO SUB DOGRULANAMADI ref=' . $row['conversation_id'] . ' ' . $res['error']);
        sub_callback_redirect('0', 'Aboneliğiniz doğrulanıyor. Sayfayı birkaç saniye sonra yenileyin.');
    }

    if (!$res['active']) {
        $st = strtoupper((string)($res['inner']['subscriptionStatus'] ?? $res['inner']['status'] ?? ''));
        $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', error_message = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'")
            ->execute(['Abonelik onaylanmadı (' . $st . ')', (int)$row['id']]);
        sub_callback_redirect('0', 'Abonelik onaylanmadı.');
    }

    subscription_activate_from_retrieve($pdo, $row, $res['inner']);
    sub_callback_redirect('1');
} catch (Throwable $e) {
    error_log('iyzico sub callback: ' . $e->getMessage());
    sub_callback_redirect('0', 'Abonelik sonucu işlenemedi.');
}
