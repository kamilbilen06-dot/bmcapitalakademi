<?php
/**
 * iyzico ödeme sonucu dönüşü.
 *
 * Ödeme formu tamamlandığında iyzico kullanıcıyı bu adrese POST ile yönlendirir
 * ve gövdede `token` gönderir. Sonucun kendisine ASLA bu POST'tan güvenilmez:
 * ödeme durumu iyzico'ya sunucudan sorulur ve yanıt imzası doğrulanır.
 *
 * Yapılan kontroller:
 *   1. Jeton bizim oluşturduğumuz bir siparişe ait mi
 *   2. iyzico yanıtının imzası geçerli mi (araya girme koruması)
 *   3. Tahsil edilen tutar siparişteki tutarla aynı mı (fiyat oynama koruması)
 *   4. Aynı sipariş iki kez işlenmiyor (idempotency)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/iyzico_client.php';

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));

function callback_redirect(string $page, string $reference = '', string $message = '') {
    $qs = [];
    if ($reference !== '') {
        $qs['oid'] = $reference;
    }
    if ($message !== '') {
        $qs['msg'] = $message;
    }
    header('Location: /' . $page . ($qs ? '?' . http_build_query($qs) : ''), true, 302);
    exit;
}

if ($token === '') {
    callback_redirect('odeme-basarisiz.html', '', 'Ödeme bilgisi alınamadı.');
}

try {
    $pdo = db();
    payments_ensure_schema($pdo);

    $st = $pdo->prepare("SELECT * FROM payment_orders WHERE provider_token = ? AND provider = 'iyzico' LIMIT 1");
    $st->execute([$token]);
    $order = $st->fetch();

    payments_log_write(
        $order ? (int)$order['id'] : 0,
        'iyzico',
        'inbound',
        'callback',
        [
            'found' => (bool)$order,
            'token_len' => strlen($token),
            'token_tail' => substr($token, -8),
            'order_status' => $order ? (string)$order['status'] : '',
        ]
    );

    if (!$order) {
        error_log('iyzico callback: bilinmeyen jeton');
        callback_redirect('odeme-basarisiz.html', '', 'Sipariş bulunamadı.');
    }

    $reference = (string)$order['merchant_oid'];

    // Zaten sonuçlanmış siparişi tekrar işleme (kullanıcı sayfayı yenilerse)
    if ($order['status'] === 'paid') {
        callback_redirect('odeme-basarili.html', $reference);
    }
    if ($order['status'] === 'failed') {
        callback_redirect('odeme-basarisiz.html', $reference, (string)$order['error_message']);
    }
    if ($order['status'] === 'review') {
        callback_redirect('odeme-basarisiz.html', $reference, 'Ödemeniz kontrol ediliyor. Sonuç e-posta ile bildirilecek.');
    }

    // Sonucu iyzico'dan sor — tek güvenilir kaynak
    $res = iyzico_checkout_retrieve($token, (string)$order['conversation_id']);

    if (!$res['ok']) {
        // iyzico açıkça hata döndürdüyse sonuç kesindir: ödeme olmamıştır.
        if ($res['reason'] === 'api') {
            $pdo->prepare("UPDATE payment_orders SET status = 'failed', error_message = ? WHERE id = ?")
                ->execute([mb_substr($res['error'], 0, 255), (int)$order['id']]);
            callback_redirect('odeme-basarisiz.html', $reference, $res['error']);
        }
        // Ulaşamadıysak veya imza tutmadıysa sonucu BİLMİYORUZ. Para çekilmiş
        // olabileceği için 'failed' demiyoruz; incelemeye alıyoruz.
        $pdo->prepare("UPDATE payment_orders SET status = 'review', error_message = ? WHERE id = ?")
            ->execute([mb_substr($res['error'], 0, 255), (int)$order['id']]);
        error_log('IYZICO INCELEME GEREKLI ref=' . $reference . ' tur=' . $res['reason'] . ' hata=' . $res['error']);
        payments_notify_review($order);
        callback_redirect('odeme-basarisiz.html', $reference, 'Ödemenizin sonucu doğrulanamadı. Ekibimiz kontrol edip en kısa sürede size dönecek.');
    }

    $d = $res['data'];
    $paidPrice = (string)($d['paidPrice'] ?? '0');
    $paidKurus = (int)round(((float)$paidPrice) * 100);
    $expectedKurus = (int)$order['amount_kurus'];

    if (!$res['paid']) {
        $status = strtoupper((string)($d['paymentStatus'] ?? ''));
        $fraud = (int)($d['fraudStatus'] ?? 1);
        // fraudStatus 0 = dolandırıcılık incelemesinde: sipariş beklemede kalır,
        // iyzico onayladıktan sonra elle veya bildirimle açılır.
        if ($fraud === 0) {
            $pdo->prepare("UPDATE payment_orders SET status = 'review', error_message = ?, paid_price = ?, provider_payment_id = ? WHERE id = ?")
                ->execute(['Dolandiricilik incelemesinde', $paidPrice, (string)($d['paymentId'] ?? ''), (int)$order['id']]);
            error_log('IYZICO INCELEME GEREKLI ref=' . $reference . ' tur=fraud');
            payments_notify_review($order);
            callback_redirect('odeme-basarisiz.html', $reference, 'Ödemeniz kontrol ediliyor. Sonuç e-posta ile bildirilecek.');
        }
        $pdo->prepare("UPDATE payment_orders SET status = 'failed', error_message = ?, provider_payment_id = ? WHERE id = ?")
            ->execute(['Ödeme onaylanmadı (' . $status . ')', (string)($d['paymentId'] ?? ''), (int)$order['id']]);
        callback_redirect('odeme-basarisiz.html', $reference, 'Ödeme onaylanmadı.');
    }

    // Tutar kontrolü — beklenenden az tahsil edildiyse erişim açılmaz.
    // Ödeme başarılı döndüğü için para çekilmiştir: 'failed' değil 'review'.
    if ($paidKurus < $expectedKurus) {
        $pdo->prepare("UPDATE payment_orders SET status = 'review', error_message = ?, paid_price = ?, provider_payment_id = ? WHERE id = ?")
            ->execute(['Tutar uyusmadi', $paidPrice, (string)($d['paymentId'] ?? ''), (int)$order['id']]);
        error_log("IYZICO INCELEME GEREKLI ref=$reference tur=tutar beklenen=$expectedKurus alinan=$paidKurus");
        payments_notify_review($order);
        callback_redirect('odeme-basarisiz.html', $reference, 'Ödeme tutarı doğrulanamadı. Lütfen bizimle iletişime geçin.');
    }

    $txId = '';
    if (!empty($d['itemTransactions'][0]['paymentTransactionId'])) {
        $txId = (string)$d['itemTransactions'][0]['paymentTransactionId'];
    }

    $pdo->prepare(
        "UPDATE payment_orders
         SET status = 'paid', paid_price = ?, provider_payment_id = ?, provider_transaction_id = ?,
             error_message = '', paid_at = NOW()
         WHERE id = ?"
    )->execute([$paidPrice, (string)($d['paymentId'] ?? ''), $txId, (int)$order['id']]);

    payments_grant_enrollment($pdo, $order, 'iyzico');

    callback_redirect('odeme-basarili.html', $reference);
} catch (Throwable $e) {
    error_log('iyzico callback istisnasi: ' . $e->getMessage());
    callback_redirect('odeme-basarisiz.html', '', 'Beklenmeyen bir hata oluştu.');
}
