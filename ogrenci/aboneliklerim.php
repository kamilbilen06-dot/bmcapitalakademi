<?php
/**
 * Aboneliklerim — WhatsApp grubu üyeliği durumu ve iptal.
 *
 * İptal gelecek çekimleri durdurur. İade / kart iadesi burada yoktur.
 * Grup linki gösterilmez; ekleme-çıkarma yönetici tarafından yapılır.
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/subscriptions.php';

list($student, $loadError) = ogrenci_panel_student('aboneliklerim.php');

$notice = isset($_GET['ok']) ? 'Aboneliğiniz aktif. Yönetici sizi WhatsApp grubuna ekleyecektir.' : '';
$error = isset($_GET['err']) ? mb_substr(clean($_GET['err']), 0, 300) : '';
$row = null;
$csrf = student_csrf_token();
$pdo = null;

try {
    $pdo = db();
    subscriptions_ensure_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'cancel') {
        if (!student_csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Oturum süresi doldu. Lütfen tekrar deneyin.';
        } else {
            $res = subscription_cancel_for_student($pdo, (int)$student['id']);
            if ($res['ok']) {
                $notice = 'Aboneliğiniz iptal edildi. Bu dönem sonuna kadar üyeliğiniz açık kalır; sonraki çekim yapılmaz.';
            } else {
                $error = $res['error'];
            }
        }
    }

    // Ödeme dönüşünde (ok/err) beklemeden, diğer açılışlarda kısa aralıkla
    // iyzico'ya sorup doğrulanamamış kaydı eşitle.
    $justReturned = isset($_GET['ok']) || isset($_GET['err']);
    $synced = subscription_reconcile_for_student($pdo, (int)$student['id'], $justReturned);

    $row = subscription_find_current($pdo, (int)$student['id']);

    if ($synced && $row && (string)$row['status'] === 'active') {
        $error = '';
        $notice = 'Aboneliğiniz aktif. Yönetici sizi WhatsApp grubuna ekleyecektir.';
    }
} catch (Throwable $e) {
    if ($loadError === '') {
        $loadError = 'Abonelik bilgileri yüklenemedi.';
    }
}

$entitled = $row ? subscription_is_entitled($row) : false;
$canCancel = $row && in_array($row['status'], ['active', 'past_due', 'pending'], true);
$canSubscribe = !$row || (!$entitled && !in_array($row['status'], ['active', 'past_due', 'pending'], true));

function sub_fmt_dt($v): string {
    if (!$v) {
        return '—';
    }
    $t = strtotime((string)$v);
    return $t ? date('d.m.Y H:i', $t) : '—';
}

ogrenci_head('Aboneliklerim', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'abonelikler', 'Aboneliklerim', 'WhatsApp analiz grubu üyeliğinizi buradan yönetin.');
?>

<?php if ($loadError !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($loadError) ?></span></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
<?php endif; ?>
<?php if ($notice !== ''): ?>
  <div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i><span><?= ogrenci_e($notice) ?></span></div>
<?php endif; ?>

<?php if (!$row): ?>
  <div class="soon">
    <div class="soon-ico"><i class="fa-brands fa-whatsapp"></i></div>
    <h2>Aktif aboneliğiniz yok</h2>
    <p>WhatsApp analiz grubuna katılmak için abone olabilirsiniz. Kartınız her dönemde otomatik çekilir; istediğiniz an iptal edebilirsiniz. Gruba ekleme yönetici tarafından yapılır.</p>
    <div class="soon-actions">
      <a class="btn btn-primary" href="abonelik.php"><i class="fa-solid fa-lock"></i> Abone ol</a>
    </div>
  </div>
<?php else: ?>
  <?php
    $badge = 'wait';
    if ($row['status'] === 'active' && $entitled) {
        $badge = 'ok';
    } elseif ($row['status'] === 'cancelled' && $entitled) {
        $badge = 'wait';
    } elseif (in_array($row['status'], ['cancelled', 'expired'], true)) {
        $badge = 'cancel';
    } elseif ($row['status'] === 'past_due') {
        $badge = 'fail';
    }
    $subTitle = 'WhatsApp analiz grubu';
    try {
        $subTitle = subscription_title($pdo ?: db());
    } catch (Throwable $e) {
    }
  ?>
  <div class="panel">
    <h2><?= ogrenci_e($subTitle) ?></h2>
    <p class="panel-sub">Durum ve sonraki çekim</p>
    <ul class="panel-meta">
      <li>
        <span>Durum</span>
        <b><span class="order-badge <?= ogrenci_e($badge) ?>"><?= ogrenci_e(subscription_status_label((string)$row['status'])) ?></span></b>
      </li>
      <li><span>Tutar</span><b><?= ogrenci_e(number_format(((int)$row['amount_kurus']) / 100, 2, ',', '.')) ?> TL / <?= ogrenci_e($row['interval_unit'] === 'DAILY' ? 'gün' : 'ay') ?></b></li>
      <li><span><?= $row['status'] === 'cancelled' ? 'Üyelik bitişi' : 'Sonraki dönem' ?></span><b><?= ogrenci_e(sub_fmt_dt($row['current_period_end'])) ?></b></li>
      <li><span>Son ödeme</span><b><?= ogrenci_e(sub_fmt_dt($row['last_paid_at'])) ?></b></li>
    </ul>

    <?php if ($entitled): ?>
      <p class="hint" style="margin-top:16px">Grup daveti siteden gönderilmez. Ödemeniz görünür görünmez yönetici sizi WhatsApp grubuna ekler.</p>
    <?php endif; ?>

    <div class="soon-actions" style="justify-content:flex-start;margin-top:22px">
      <?php if ($canSubscribe): ?>
        <a class="btn btn-primary" href="abonelik.php">Yeniden abone ol</a>
      <?php endif; ?>
      <?php if ($canCancel): ?>
        <form method="post" onsubmit="return confirm('Aboneliği iptal etmek istiyor musunuz? Bu dönem sonuna kadar üyelik açık kalır; sonraki çekim yapılmaz.');">
          <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
          <input type="hidden" name="form" value="cancel">
          <button type="submit" class="btn btn-outline">Aboneliği iptal et</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
ogrenci_panel_end();
ogrenci_foot();
