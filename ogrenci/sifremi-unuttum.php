<?php
/**
 * Şifre sıfırlama talebi.
 *
 * Hesap varsa Gmail SMTP ile bağlantı gönderilir (eğitmen paneliyle aynı yol).
 * Yoksa kayıt sayfasına yönlendirilir.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/../api/mailer.php';
require_once __DIR__ . '/_layout.php';

start_student_session();
if (is_student()) {
    header('Location: index.php');
    exit;
}

$error = '';
$sent = false;
$devLink = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');

    if (!student_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen tekrar deneyin.';
    } elseif (!filter_var(student_normalize_email($email), FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        try {
            $pdo = db();
            students_ensure_schema($pdo);
            $student = student_find_by_email($pdo, $email);
            if (!$student) {
                header('Location: kayit.php?from=reset&email=' . rawurlencode(student_normalize_email($email)));
                exit;
            }
            if (function_exists('session_write_close')) {
                session_write_close();
            }
            $token = student_issue_token($pdo, (int)$student['id'], 'reset');
            $link = student_reset_link($token);
            if (ogrenci_is_local_env() || !mailer_url_is_safe($link)) {
                $devLink = 'sifre-sifirla.php?token=' . urlencode($token);
            }
            if (!mailer_is_configured()) {
                $error = 'E-posta gönderimi şu an kapalı. Lütfen bizimle iletişime geçin.';
            } else {
                $res = mailer_send_reset($student, $link);
                if (!empty($res['ok'])) {
                    $sent = true;
                } else {
                    $error = 'E-posta gönderilemedi. Birkaç saniye sonra tekrar deneyin.';
                }
            }
        } catch (Throwable $e) {
            $error = 'İşlem tamamlanamadı. Veritabanı bağlantısını kontrol edin.';
        }
    }
}

$csrf = student_csrf_token();

ogrenci_head('Şifremi Unuttum', 'page-auth');
?>
<div class="auth-page">
  <?php ogrenci_auth_top(); ?>
  <?php ogrenci_auth_hero('Şifreni Sıfırla', 'E-posta adresinizi girin, sıfırlama bağlantısını gönderelim.'); ?>

  <main class="auth-shell">
    <div class="auth-card">
      <h2 class="auth-card-title">Şifre sıfırlama</h2>

      <?php if ($error !== ''): ?>
        <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($sent): ?>
        <div class="alert alert-ok">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Şifre sıfırlama bağlantısı gönderildi.</span>
        </div>
        <?php if ($devLink !== ''): ?>
          <div class="alert alert-info">
            <i class="fa-solid fa-flask"></i>
            <span>
              <b>Yerel geliştirme:</b> e-postaya localhost linki koyulmaz (Gmail spam’e atar).
              <a href="<?= ogrenci_e($devLink) ?>">Şifreyi buradan sıfırlayın</a>
            </span>
          </div>
        <?php endif; ?>
        <a href="giris.php" class="btn btn-outline btn-block">Giriş ekranına dön</a>
      <?php else: ?>
        <form method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
          <div class="field">
            <label for="f-email">E-posta</label>
            <div class="input-wrap">
              <i class="fa-regular fa-envelope field-icon"></i>
              <input id="f-email" type="email" name="email" value="<?= ogrenci_e($email) ?>" required autofocus autocomplete="email" placeholder="ornek@email.com">
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">Sıfırlama bağlantısı gönder</button>
        </form>
        <p class="form-foot">Şifrenizi hatırladınız mı? <a href="giris.php">Hemen Giriş Yap!</a></p>
      <?php endif; ?>
    </div>

    <a class="auth-back" href="../index.html"><i class="fa-solid fa-arrow-left"></i> Siteye dön</a>
  </main>
</div>
<?php
ogrenci_foot();
?>
