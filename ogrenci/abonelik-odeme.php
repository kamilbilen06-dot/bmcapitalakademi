<?php
/**
 * iyzico abonelik Checkout Form gövdesi (ödeme sayfası URL dönmezse).
 */
require_once __DIR__ . '/_layout.php';

list($student, $loadError) = ogrenci_panel_student('abonelik-odeme.php');

$form = (string)($_SESSION['iyzico_sub_form'] ?? '');
unset($_SESSION['iyzico_sub_form']);

if ($form === '') {
    header('Location: abonelik.php', true, 302);
    exit;
}

if (preg_match('/^\\s*(<!DOCTYPE|<html)/i', $form)) {
    header('Content-Type: text/html; charset=utf-8');
    echo $form;
    exit;
}

ogrenci_head('Kart ile öde', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'abonelikler', 'Kart ile öde', 'Kart bilgileriniz iyzico sayfasında işlenir.');
?>

<?php if ($loadError !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($loadError) ?></span></div>
<?php endif; ?>

<div class="panel sub-iyzico-form">
  <p class="panel-sub">Ödemeyi tamamladıktan sonra otomatik olarak Aboneliklerim sayfasına dönersiniz.</p>
  <?= $form ?>
</div>

<?php
ogrenci_panel_end();
ogrenci_foot();
