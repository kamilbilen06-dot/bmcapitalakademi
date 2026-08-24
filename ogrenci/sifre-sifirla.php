<?php
/**
 * Şifre sıfırlama — tek kullanımlık jetonla yeni şifre belirleme.
 * Jeton yalnızca şifre başarıyla değiştiğinde tüketilir.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/_layout.php';

start_student_session();

$token = student_read_token();
$error = '';
$tokenValid = false;
$tokenState = 'empty';

try {
    $pdo = db();
    students_ensure_schema($pdo);
    $status = student_token_status($pdo, $token, 'reset');
    $tokenState = (string) $status['state'];
    $tokenValid = $tokenState === 'ok';
} catch (Throwable $e) {
    $error = 'Veritabanına ulaşılamadı. Bağlantı ayarlarını kontrol edin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $tokenValid) {
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (!student_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen formu tekrar gönderin.';
    } elseif (mb_strlen($password) < STUDENT_MIN_PASSWORD) {
        $error = 'Şifre en az ' . STUDENT_MIN_PASSWORD . ' karakter olmalı.';
    } elseif ($password !== $password2) {
        $error = 'Şifreler birbiriyle uyuşmuyor.';
    } else {
        $studentId = student_consume_token($pdo, $token, 'reset');
        if ($studentId <= 0) {
            $tokenValid = false;
            $tokenState = 'used';
        } else {
            student_set_password($pdo, $studentId, $password);
            student_logout();
            header('Location: giris.php?sifre=yeni');
            exit;
        }
    }
}

$csrf = student_csrf_token();

ogrenci_head('Yeni Şifre', 'page-auth');
?>
<div class="auth-page">
  <?php ogrenci_auth_top(); ?>
  <?php ogrenci_auth_hero('Yeni Şifre', 'Güçlü ve daha önce kullanmadığınız bir şifre seçin.'); ?>

  <main class="auth-shell">
    <div class="auth-card">
      <h2 class="auth-card-title">Şifrenizi güncelleyin</h2>

      <?php if ($error !== ''): ?>
        <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
      <?php endif; ?>

      <?php if (!$tokenValid): ?>
        <div class="alert alert-err">
          <i class="fa-solid fa-link-slash"></i>
          <span><?= ogrenci_e(student_token_state_message($tokenState)) ?></span>
        </div>
        <a href="sifremi-unuttum.php" class="btn btn-primary btn-block btn-lg">Yeni bağlantı gönder</a>
      <?php else: ?>
        <form method="post" data-guard novalidate>
          <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
          <input type="hidden" name="token" value="<?= ogrenci_e($token) ?>">

          <div class="field">
            <label for="f-pw">Yeni şifre</label>
            <div class="input-wrap">
              <input id="f-pw" type="password" name="password" required autofocus autocomplete="new-password" placeholder="En az <?= STUDENT_MIN_PASSWORD ?> karakter">
              <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>

          <div class="field">
            <label for="f-pw2">Yeni şifre (tekrar)</label>
            <div class="input-wrap">
              <input id="f-pw2" type="password" name="password2" required autocomplete="new-password" placeholder="Şifrenizi doğrulayın">
              <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">Şifreyi güncelle</button>
        </form>
      <?php endif; ?>
    </div>

    <a class="auth-back" href="giris.php"><i class="fa-solid fa-arrow-left"></i> Giriş ekranına dön</a>
  </main>
</div>
<?php ogrenci_foot(); ?>
