<?php
/**
 * Satın Alma Geçmişi — öğrencinin sipariş kartları.
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/payments_schema.php';
require_once __DIR__ . '/../api/subscriptions_schema.php';
require_once __DIR__ . '/../api/iyzico_config.php';

list($student, $loadError) = ogrenci_panel_student('satin-alma-gecmisi.php');

$orders = [];
$subs = [];
try {
    $pdo = db();
    payments_ensure_schema($pdo);
    subscriptions_ensure_schema($pdo);

    $st = $pdo->prepare(
        "SELECT o.id, o.merchant_oid, o.provider, o.status, o.amount_kurus, o.paid_price,
                o.created_at, o.paid_at, o.refunded_at, o.provider_payment_id, o.error_message,
                c.title AS course_title
         FROM payment_orders o
         LEFT JOIN courses c ON c.id = o.course_id
         WHERE o.student_id = ?
           AND o.status NOT IN ('pending')
         ORDER BY COALESCE(o.paid_at, o.created_at) DESC"
    );
    $st->execute([(int)$student['id']]);
    $orders = $st->fetchAll();

    $stSub = $pdo->prepare(
        "SELECT s.id, s.conversation_id, s.status, s.amount_kurus, s.interval_unit,
                s.created_at, s.last_paid_at, s.current_period_end, s.cancelled_at
         FROM subscriptions s
         WHERE s.student_id = ?
           AND s.status NOT IN ('pending')
         ORDER BY COALESCE(s.last_paid_at, s.created_at) DESC"
    );
    $stSub->execute([(int)$student['id']]);
    $subs = $stSub->fetchAll();
} catch (Throwable $e) {
    $loadError = $loadError !== '' ? $loadError : 'Siparişler yüklenemedi.';
}

ogrenci_head('Satın Alma Geçmişi', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'gecmis', 'Satın Alma Geçmişi', 'Siparişlerinizi ve ödeme durumlarını görüntüleyin.');

if ($loadError !== '') {
    echo '<div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span>' . ogrenci_e($loadError) . '</span></div>';
}

if (iyzico_ready() && iyzico_is_sandbox()) {
    echo '<div class="alert alert-warn" style="margin-bottom:16px;"><i class="fa-solid fa-flask"></i><span>'
        . 'Test modu: iyzico panelinde işlemler <strong>sandbox-merchant.iyzipay.com → İşlemler</strong> sayfasında görünür; '
        . 'canlı merchant panelinde görünmez.</span></div>';
}

if (!$orders && !$subs) {
    echo '<div class="empty"><div class="ico"><i class="fa-solid fa-receipt"></i></div>';
    echo '<h3>Henüz sipariş yok</h3>';
    echo '<p>Satın aldığınız eğitimler burada sipariş numarası, tutar ve durumla listelenir.</p>';
    echo '<div class="empty-actions"><a class="btn btn-primary" href="../egitimler.html">Eğitimlere git</a></div></div>';
} else {
    if ($orders) {
        echo '<div class="order-list">';
        foreach ($orders as $o) {
            ogrenci_order_card($o);
        }
        echo '</div>';
    }
    if ($subs) {
        if ($orders) {
            echo '<h3 style="margin:24px 0 12px;font-size:1rem;">Abonelikler</h3>';
        }
        echo '<div class="order-list">';
        foreach ($subs as $s) {
            ogrenci_sub_card($s);
        }
        echo '</div>';
    }
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
    $provider = (string)($o['provider'] ?? '');
    $method = $provider === 'havale' ? 'Havale / EFT' : 'Kredi Kartı Tek Çekim';
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

function ogrenci_sub_card(array $s): void {
    require_once __DIR__ . '/../api/subscriptions.php';
    $status = (string)($s['status'] ?? '');
    $when = $s['last_paid_at'] ?: $s['created_at'];
    $date = $when ? date('d.m.Y', strtotime((string)$when)) : '';
    $kurus = (int)$s['amount_kurus'];
    $price = '₺' . number_format($kurus / 100, 2, ',', '.');
    $period = strtoupper((string)($s['interval_unit'] ?? '')) === 'DAILY' ? 'günlük' : 'aylık';
    $badgeClass = 'wait';
    $badgeIcon = 'fa-clock';
    $badgeText = subscription_status_label($status, $status === 'cancelled' && !empty($s['current_period_end']) && strtotime((string)$s['current_period_end']) > time());
    if ($status === 'active') {
        $badgeClass = 'ok';
        $badgeIcon = 'fa-check';
    } elseif (in_array($status, ['cancelled', 'expired'], true)) {
        $badgeClass = 'cancel';
        $badgeIcon = 'fa-ban';
    } elseif ($status === 'failed' || $status === 'past_due') {
        $badgeClass = 'fail';
        $badgeIcon = 'fa-xmark';
    }
    ?>
    <article class="order-card">
      <div class="order-top">
        <span>Abonelik #<?= ogrenci_e((string)$s['id']) ?></span>
        <span><?= ogrenci_e($date) ?></span>
      </div>
      <div class="order-mid">
        <div>
          <b class="order-method">WhatsApp analiz grubu · <?= ogrenci_e($period) ?></b>
          <div class="order-price"><?= ogrenci_e($price) ?></div>
        </div>
        <span class="order-badge <?= $badgeClass ?>">
          <i class="fa-solid <?= $badgeIcon ?>"></i> <?= ogrenci_e($badgeText) ?>
        </span>
      </div>
      <details class="order-more">
        <summary><i class="fa-solid fa-chevron-down"></i> Daha Fazla</summary>
        <ul>
          <li><span>Referans</span><b><?= ogrenci_e((string)$s['conversation_id']) ?></b></li>
          <?php if (!empty($s['last_paid_at'])): ?>
            <li><span>Son ödeme</span><b><?= ogrenci_e(date('d.m.Y H:i', strtotime((string)$s['last_paid_at']))) ?></b></li>
          <?php endif; ?>
        </ul>
      </details>
    </article>
    <?php
}