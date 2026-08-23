<?php
/**
 * Satın Alma Geçmişi — öğrencinin sipariş kartları.
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/payments_schema.php';
require_once __DIR__ . '/../api/iyzico_client.php';

list($student, $loadError) = ogrenci_panel_student('satin-alma-gecmisi.php');

$orders = [];
try {
    $pdo = db();
    payments_ensure_schema($pdo);
    payments_sync_iyzico_refunds($pdo, (int)$student['id']);
    $st = $pdo->prepare(
        "SELECT o.id, o.merchant_oid, o.provider, o.status, o.amount_kurus, o.paid_price,
                o.created_at, o.paid_at, o.refunded_at, o.provider_payment_id, o.error_message,
                c.title AS course_title
         FROM payment_orders o
         LEFT JOIN courses c ON c.id = o.course_id
         WHERE o.student_id = ?
           AND o.provider_payment_id <> ''
         ORDER BY o.created_at DESC"
    );
    $st->execute([(int)$student['id']]);
    $orders = $st->fetchAll();
} catch (Throwable $e) {
    $loadError = $loadError !== '' ? $loadError : 'Siparişler yüklenemedi.';
}

ogrenci_head('Satın Alma Geçmişi', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'gecmis', 'Satın Alma Geçmişi', 'Siparişlerinizi ve ödeme durumlarını görüntüleyin.');

if ($loadError !== '') {
    echo '<div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span>' . ogrenci_e($loadError) . '</span></div>';
}

if (!$orders) {
    echo '<div class="empty"><div class="ico"><i class="fa-solid fa-receipt"></i></div>';
    echo '<h3>Henüz sipariş yok</h3>';
    echo '<p>Satın aldığınız eğitimler burada sipariş numarası, tutar ve durumla listelenir.</p>';
    echo '<div class="empty-actions"><a class="btn btn-primary" href="../egitimler.html">Eğitimlere git</a></div></div>';
} else {
    echo '<div class="order-list">';
    foreach ($orders as $o) {
        ogrenci_order_card($o);
    }
    echo '</div>';
}

ogrenci_panel_end();
ogrenci_foot();

function ogrenci_order_card(array $o): void {
    $status = (string)($o['status'] ?? '');
    $idLabel = (string)$o['id'];
    $when = $o['paid_at'] ?: $o['created_at'];
    $date = $when ? date('d.m.Y', strtotime((string)$when)) : '';
    $kurus = (int)$o['amount_kurus'];
    $price = '₺' . number_format($kurus / 100, 2, ',', '.');
    $method = ($o['provider'] ?? '') === 'paytr' ? 'Kredi Kartı (PayTR)' : 'Kredi Kartı Tek Çekim';
    $title = trim((string)($o['course_title'] ?? '')) !== '' ? (string)$o['course_title'] : 'Eğitim';

    $badgeClass = 'wait';
    $badgeIcon = 'fa-clock';
    $badgeText = 'Beklemede';
    if ($status === 'paid') {
        $badgeClass = 'ok';
        $badgeIcon = 'fa-check';
        $badgeText = 'Ödeme Başarılı';
    } elseif ($status === 'refunded') {
        $isCancel = stripos((string)($o['error_message'] ?? ''), 'iptal') !== false;
        $badgeClass = $isCancel ? 'cancel' : 'ref';
        $badgeIcon = $isCancel ? 'fa-ban' : 'fa-rotate-left';
        $badgeText = $isCancel ? 'İptal Edildi' : 'İade Edildi';
    } elseif ($status === 'cancelled') {
        $badgeClass = 'cancel';
        $badgeIcon = 'fa-ban';
        $badgeText = 'İptal Edildi';
    } elseif ($status === 'failed') {
        $badgeClass = 'fail';
        $badgeIcon = 'fa-xmark';
        $badgeText = 'Ödeme Alınamadı';
    } elseif ($status === 'review') {
        $badgeClass = 'wait';
        $badgeIcon = 'fa-hourglass-half';
        $badgeText = 'İncelemede';
    }
    ?>
    <article class="order-card">
      <div class="order-top">
        <span><?= ogrenci_e($idLabel) ?></span>
        <span><?= ogrenci_e($date) ?></span>
      </div>
      <div class="order-mid">
        <div>
          <b class="order-method"><?= ogrenci_e($method) ?></b>
          <div class="order-price"><?= ogrenci_e($price) ?></div>
        </div>
        <span class="order-badge <?= $badgeClass ?>">
          <i class="fa-solid <?= $badgeIcon ?>"></i> <?= ogrenci_e($badgeText) ?>
        </span>
      </div>
      <div class="order-item">
        <i class="fa-solid fa-cart-shopping"></i>
        <span><?= ogrenci_e($title) ?></span>
      </div>
      <details class="order-more">
        <summary><i class="fa-solid fa-chevron-down"></i> Daha Fazla</summary>
        <ul>
          <li><span>Sipariş no</span><b><?= ogrenci_e((string)$o['merchant_oid']) ?></b></li>
          <?php if (!empty($o['provider_payment_id'])): ?>
            <li><span>Ödeme no</span><b><?= ogrenci_e((string)$o['provider_payment_id']) ?></b></li>
          <?php endif; ?>
          <?php if (!empty($o['paid_at'])): ?>
            <li><span>Ödeme zamanı</span><b><?= ogrenci_e(date('d.m.Y H:i', strtotime((string)$o['paid_at']))) ?></b></li>
          <?php endif; ?>
          <?php if ($status === 'refunded' && !empty($o['refunded_at'])): ?>
            <li><span>İade</span><b><?= ogrenci_e(date('d.m.Y H:i', strtotime((string)$o['refunded_at']))) ?></b></li>
          <?php endif; ?>
        </ul>
      </details>
    </article>
    <?php
}
