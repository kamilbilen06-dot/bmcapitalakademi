<?php
/**
 * Öğrenci girişi. ?next= ile ödeme akışına geri dönüş desteklenir.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/../api/login_guard.php';
require_once __DIR__ . '/_layout.php';

start_student_session();

$next = ogrenci_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$redirect = $next !== '' ? $next : 'index.php';

if (is_student()) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$notice = '';
$email = '';

if (isset($_GET['sifre']) && $_GET['sifre'] === 'yeni') {
    $notice = 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.';
}
if (isset($_GET['dogrulandi'])) {
    $notice = 'E-posta adresiniz doğrulandı. Şimdi giriş yapabilirsiniz.';
}
if (isset($_GET['cikis'])) {
    $notice = 'Güvenli çıkış yapıldı.';
}
// Sosyal giriş akışından dönen hata mesajı
if (isset($_GET['err'])) {
    $error = mb_substr(clean($_GET['err']), 0, 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!student_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen tekrar deneyin.';
    } elseif ($email === '' || $password === '') {
        $error = 'E-posta ve şifre gerekli.';
    } else {
        $guard = login_guard_check('student', $email);
        if (!$guard['ok']) {
            $error = $guard['error'];
        } else {
        try {
            $pdo = db();
            students_ensure_schema($pdo);
            $res = student_authenticate($pdo, $email, $password);
            if ($res['error'] !== '') {
                if ($res['error'] === 'unverified') {
                    login_guard_clear('student', $email);
                    $q = 'dogrulama.php?email=' . rawurlencode($email);
                    if ($next !== '') {
                        $q .= '&next=' . rawurlencode($next);
                    }
                    header('Location: ' . $q);
                    exit;
                }
                login_guard_fail('student', $email);
                $error = $res['error'];
            } else {
                login_guard_clear('student', $email);
                student_login_session($res['row']);
                header('Location: ' . $redirect);
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Giriş yapılamadı. Veritabanı bağlantısını kontrol edin.';
        }
        }
    }
}

$csrf = student_csrf_token();
$registerUrl = 'kayit.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');

ogrenci_head('Giriş', 'page-auth');
?>
<div class="auth-page">
  <?php ogrenci_auth_top('giris', $next); ?>
  <?php ogrenci_auth_hero('Hoş Geldiniz', 'Kendinizi geliştirin, yatırımlarınızı büyütün.'); ?>

  <main class="auth-shell">
    <div class="auth-card">
      <h2 class="auth-card-title">Hesabınıza giriş yapın</h2>

      <?php if ($notice !== ''): ?>
        <div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i><span><?= ogrenci_e($notice) ?></span></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
      <?php endif; ?>
      <?php if ($next !== ''): ?>
        <div class="alert alert-info"><i class="fa-solid fa-lock"></i><span>Satın alma işlemine devam etmek için giriş yapmanız gerekiyor.</span></div>
      <?php endif; ?>

      <?php ogrenci_social_buttons($next); ?>

      <form method="post" data-guard novalidate>
        <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
        <input type="hidden" name="next" value="<?= ogrenci_e($next) ?>">

        <div class="field">
          <label for="f-email">E-posta</label>
          <div class="input-wrap">
            <i class="fa-regular fa-envelope field-icon"></i>
            <input id="f-email" type="email" name="email" value="<?= ogrenci_e($email) ?>" required autofocus autocomplete="email" placeholder="ornek@email.com">
          </div>
        </div>

        <div class="field">
          <label for="f-pw">Şifre</label>
          <div class="input-wrap">
            <input id="f-pw" type="password" name="password" required autocomplete="current-password" placeholder="Şifreniz">
            <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
          </div>
        </div>

        <p class="forgot-line"><a href="sifremi-unuttum.php">Şifremi Unuttum</a></p>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Giriş Yap</button>
      </form>

      <p class="form-foot">Hesabınız yok mu? <a href="<?= ogrenci_e($registerUrl) ?>">Hemen Kayıt Ol!</a></p>
    </div>

    <a class="auth-back" href="../index.html"><i class="fa-solid fa-arrow-left"></i> Siteye dön</a>
  </main>
</div>
<?php ogrenci_foot(); ?>
