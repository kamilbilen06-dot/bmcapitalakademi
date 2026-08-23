<?php
/**
 * Öğrenci kaydı — ücretsiz hesap.
 * Kayıt sonrası e-posta doğrulaması istenir; oturum doğrulanınca açılır.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/_layout.php';

start_student_session();

$next = ogrenci_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$redirect = $next !== '' ? $next : 'index.php';

if (is_student()) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$name = '';
$email = '';
$marketing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $marketing = !empty($_POST['marketing']);
    $kvkk = !empty($_POST['kvkk']);

    if (!student_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen formu tekrar gönderin.';
    } elseif (!$kvkk) {
        $error = 'Kayıt için KVKK Aydınlatma Metni’ni onaylamanız gerekir.';
    } else {
        $error = student_validate_registration($name, $email, $password, $password2);
    }

    if ($error === '') {
        try {
            $pdo = db();
            students_ensure_schema($pdo);
            // Telefon ödeme adımında isteniyor; kayıtta sorulmuyor.
            $res = student_register($pdo, $name, $email, $password, '', $marketing);
            if ($res['error'] === 'unverified_exists' || ($res['error'] === '' && !empty($res['row']))) {
                $_SESSION['verify_need_send'] = 1;
                $_SESSION['verify_email'] = (string)$res['row']['email'];
                $q = 'dogrulama.php?email=' . rawurlencode((string)$res['row']['email']);
                if ($next !== '') {
                    $q .= '&next=' . rawurlencode($next);
                }
                header('Location: ' . $q);
                exit;
            }
            if ($res['error'] !== '') {
                $error = $res['error'];
            }
        } catch (Throwable $e) {
            $error = 'Kayıt tamamlanamadı. Veritabanı bağlantısını kontrol edin.';
        }
    }
}

$csrf = student_csrf_token();
$loginUrl = 'giris.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');

ogrenci_head('Ücretsiz Kayıt', 'page-auth');
?>
<div class="auth-page">
  <?php ogrenci_auth_top('kayit', $next); ?>
  <?php ogrenci_auth_hero('Aramıza Katıl', 'Kaydolun, eğitimlerinize tek yerden ulaşın.'); ?>

  <main class="auth-shell">
    <div class="auth-card">
      <h2 class="auth-card-title">Ücretsiz hesap oluşturun</h2>

      <?php if ($error !== ''): ?>
        <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
      <?php endif; ?>

      <?php ogrenci_social_buttons($next); ?>

      <form method="post" data-guard novalidate>
        <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
        <input type="hidden" name="next" value="<?= ogrenci_e($next) ?>">

        <div class="field">
          <label for="f-name">Ad Soyad</label>
          <div class="input-wrap">
            <i class="fa-regular fa-user field-icon"></i>
            <input id="f-name" type="text" name="name" value="<?= ogrenci_e($name) ?>" required autofocus autocomplete="name" placeholder="Adınız ve soyadınız">
          </div>
        </div>

        <div class="field">
          <label for="f-email">E-posta</label>
          <div class="input-wrap">
            <i class="fa-regular fa-envelope field-icon"></i>
            <input id="f-email" type="email" name="email" value="<?= ogrenci_e($email) ?>" required autocomplete="email" placeholder="ornek@email.com">
          </div>
        </div>

        <div class="field">
          <label for="f-pw">Şifre</label>
          <div class="input-wrap">
            <input id="f-pw" type="password" name="password" required autocomplete="new-password" placeholder="En az <?= STUDENT_MIN_PASSWORD ?> karakter">
            <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
          </div>
        </div>

        <div class="field">
          <label for="f-pw2">Şifre (tekrar)</label>
          <div class="input-wrap">
            <input id="f-pw2" type="password" name="password2" required autocomplete="new-password" placeholder="Şifrenizi doğrulayın">
            <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
          </div>
        </div>

        <label class="check-line">
          <input type="checkbox" name="kvkk" value="1" required<?= !empty($_POST['kvkk']) ? ' checked' : '' ?>>
          <span>
            <a href="../yasal/kvkk.html" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>’ni okudum, kişisel verilerimin işlenmesini kabul ediyorum.
          </span>
        </label>

        <label class="check-line">
          <input type="checkbox" name="marketing" value="1"<?= $marketing ? ' checked' : '' ?>>
          <span>Yeni eğitim duyuruları ve kampanyalardan e-posta ile haberdar olmak istiyorum.</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Kayıt Ol</button>
      </form>

      <p class="form-foot">Hesabınız var mı? <a href="<?= ogrenci_e($loginUrl) ?>">Hemen Giriş Yap!</a></p>
    </div>

    <p class="auth-note">
      Ayrıca <a href="../yasal/gizlilik.html" target="_blank" rel="noopener">Gizlilik Politikası</a> ve
      <a href="../yasal/cerez.html" target="_blank" rel="noopener">Çerez Politikası</a> geçerlidir.
    </p>

    <a class="auth-back" href="../index.html"><i class="fa-solid fa-arrow-left"></i> Siteye dön</a>
  </main>
</div>
<?php ogrenci_foot(); ?>
