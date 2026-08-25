<?php
/**
 * WhatsApp grubu aboneliğine katıl — iyzico tekrarlayan ödeme.
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/subscriptions.php';

list($student, $loadError) = ogrenci_panel_student('abonelik.php');

$error = isset($_GET['err']) ? mb_substr(clean($_GET['err']), 0, 300) : '';
$pdo = null;
$title = 'WhatsApp analiz grubu';
$blurb = '';
$priceLabel = '—';
$intervalLabel = subscription_interval_label();
$enabled = true;
$already = null;
$needPhone = true;
$phone = (string)($student['phone'] ?? '');
$csrf = student_csrf_token();

try {
    $pdo = db();
    subscriptions_ensure_schema($pdo);
    $title = subscription_title($pdo);
    $blurb = subscription_blurb($pdo);
    $priceLabel = subscription_price_label($pdo);
    $enabled = subscription_enabled($pdo) && subscription_price_kurus($pdo) >= 100 && iyzico_ready();
    subscription_abandon_unpaid_pending($pdo, (int)$student['id']);
    $already = subscription_blocking_row($pdo, (int)$student['id']);
    if (!$already) {
        $already = subscription_find_current($pdo, (int)$student['id']);
    }
    $needPhone = preg_replace('/\D/', '', $phone) === '';
} catch (Throwable $e) {
    if ($loadError === '') {
        $loadError = 'Abonelik bilgileri yüklenemedi.';
    }
}

if ($already && (in_array($already['status'], ['active', 'past_due'], true) || subscription_is_entitled($already))) {
    header('Location: aboneliklerim.php', true, 302);
    exit;
}

ogrenci_head('Abone ol', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'abonelikler', 'Abone ol', $title);
?>

<?php if ($loadError !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($loadError) ?></span></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
<?php endif; ?>

<div class="sub-checkout">
  <div class="panel">
    <h2><?= ogrenci_e($title) ?></h2>
    <p class="panel-sub"><?= ogrenci_e($blurb) ?></p>
    <ul class="panel-meta">
      <li><span>Tutar</span><b><?= ogrenci_e($priceLabel) ?> / <?= ogrenci_e($intervalLabel) ?></b></li>
      <li><span>İptal</span><b>İstediğiniz an, siteden</b></li>
      <li><span>WhatsApp grubu</span><b>Yönetici sizi elle ekler</b></li>
    </ul>
    <p class="hint" style="margin-top:16px">Kart bilgileri bu sitede tutulmaz; iyzico güvenli sayfasında girilir. Grup katılım linki herkese açık gösterilmez.</p>
    <?php if (iyzico_ready() && iyzico_is_sandbox()): ?>
      <p class="hint">Test modu: çekim <strong>günlük</strong> yapılır (canlıda aylık olacaktır).</p>
    <?php endif; ?>
  </div>

  <?php if (!$enabled): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i><span>Abonelik şu an satışa açık değil. Daha sonra tekrar deneyin.</span></div>
  <?php else: ?>
    <form method="post" action="../api/iyzico_sub_checkout.php" class="panel" data-guard>
      <h2>Ödeme bilgileri</h2>
      <p class="panel-sub">Onayladığınızda iyzico kart formuna yönlendirilirsiniz.</p>
      <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
      <?php if (!$needPhone): ?>
        <input type="hidden" name="phone" value="<?= ogrenci_e($phone) ?>">
      <?php else: ?>
        <div class="field">
          <label>Telefon *</label>
          <input type="tel" name="phone" required autocomplete="tel" placeholder="05xx xxx xx xx">
        </div>
      <?php endif; ?>
      <div class="field">
        <label>Adres <span class="optional">(isteğe bağlı)</span></label>
        <input type="text" name="address" autocomplete="street-address" placeholder="Mahalle, sokak, no">
      </div>
      <div class="field">
        <label>İl <span class="optional">(isteğe bağlı)</span></label>
        <input type="text" name="city" autocomplete="address-level1" placeholder="<?= ogrenci_e(defined('BRAND_CITY') ? BRAND_CITY : 'İzmir') ?>">
      </div>
      <label class="sub-legal">
        <input type="checkbox" name="terms" value="1" required>
        <span>
          <a href="../yasal/on-bilgilendirme.html" target="_blank" rel="noopener">Ön Bilgilendirme Formu</a>’nu ve
          <a href="../yasal/mesafeli-satis.html" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>’ni okudum, aboneliğin her dönemde yenileneceğini kabul ediyorum.
        </span>
      </label>
      <button type="submit" class="btn btn-primary btn-block">
        <i class="fa-solid fa-lock"></i> <?= ogrenci_e($priceLabel) ?> öde ve abone ol
      </button>
      <?php if (iyzico_is_sandbox()): ?>
        <p class="hint" style="margin-top:14px">Test kartı: 5528 7900 0000 0008 · 12/30 · CVC 123 · SMS 123456</p>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>

<?php
ogrenci_panel_end();
ogrenci_foot();
