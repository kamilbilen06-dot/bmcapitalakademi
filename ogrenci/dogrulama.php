<?php
/**
 * E-posta doğrulama — 6 haneli kod veya e-postadaki bağlantı.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/../api/mailer.php';
require_once __DIR__ . '/_layout.php';

start_student_session();

$next = ogrenci_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$redirect = $next !== '' ? $next : 'index.php';

$email = student_normalize_email($_GET['email'] ?? $_POST['email'] ?? ($_SESSION['verify_email'] ?? ''));
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$notice = '';
$devCode = (string)($_SESSION['verify_dev_code'] ?? '');
$devLink = (string)($_SESSION['verify_dev_link'] ?? '');

try {
    $pdo = db();
    students_ensure_schema($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $error = 'Veritabanına ulaşılamadı. Bağlantı ayarlarını kontrol edin.';
}

if ($pdo && $token !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $studentId = student_consume_token($pdo, $token, 'verify');
    if ($studentId > 0) {
        student_mark_email_verified($pdo, $studentId);
        $row = student_find_by_id($pdo, $studentId);
        unset($_SESSION['verify_dev_code'], $_SESSION['verify_dev_link'], $_SESSION['verify_email']);
        if ($row) {
            $pdo->prepare("UPDATE students SET last_login_at = NOW() WHERE id = ?")->execute([$studentId]);
            student_login_session($row);
            header('Location: ' . $redirect);
            exit;
        }
    }
    $error = 'Bu doğrulama bağlantısı geçersiz veya süresi dolmuş. Yeni kod isteyin.';
}

if ($pdo && is_student()) {
    $me = student_find_by_id($pdo, current_student_id());
    if ($me && student_is_email_verified($me)) {
        header('Location: ' . $redirect);
        exit;
    }
}

if ($pdo && $email !== '') {
    $existing = student_find_by_email($pdo, $email);
    if ($existing && student_is_email_verified($existing)) {
        header('Location: giris.php?dogrulandi=1' . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
        exit;
    }
}

$needSend = !empty($_SESSION['verify_need_send']);
if ($needSend) {
    unset($_SESSION['verify_need_send']);
}

if ($pdo && $_SERVER['REQUEST_METHOD'] !== 'POST' && $token === '' && $needSend && $email !== '') {
    $student = student_find_by_email($pdo, $email);
    if ($student && !student_is_email_verified($student)) {
        $out = student_deliver_verification($pdo, $student);
        if ($out['status'] === 'sent') {
            $notice = 'Doğrulama kodu e-posta adresinize gönderildi.';
        } elseif ($out['status'] === 'failed') {
            $devCode = $out['code'];
            $devLink = $out['link'];
            $_SESSION['verify_dev_code'] = $devCode;
            $_SESSION['verify_dev_link'] = $devLink;
            $notice = 'E-posta şu an gönderilemedi. Aşağıdaki kodu kullanarak doğrulayabilirsiniz.';
        }
    }
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!student_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen tekrar deneyin.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        $student = student_find_by_email($pdo, $email);
        if (!$student) {
            $error = 'Bu e-posta ile bir hesap bulunamadı.';
        } elseif (student_is_email_verified($student)) {
            header('Location: giris.php?dogrulandi=1');
            exit;
        } else {
            $action = (string)($_POST['action'] ?? 'code');
            if ($action === 'resend') {
                $out = student_deliver_verification($pdo, $student);
                $_SESSION['verify_email'] = $email;
                if ($out['status'] === 'wait') {
                    $error = 'Yeni kod için ' . $out['wait'] . ' saniye bekleyin.';
                } elseif ($out['status'] === 'sent') {
                    unset($_SESSION['verify_dev_code'], $_SESSION['verify_dev_link']);
                    $devCode = '';
                    $devLink = '';
                    $notice = 'Yeni doğrulama kodu e-posta adresinize gönderildi.';
                } else {
                    $_SESSION['verify_dev_code'] = $out['code'];
                    $_SESSION['verify_dev_link'] = $out['link'];
                    $devCode = $out['code'];
                    $devLink = $out['link'];
                    $notice = 'E-posta şu an gönderilemedi. Aşağıdaki kodu kullanarak doğrulayabilirsiniz.';
                }
            } else {
                $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
                $studentId = student_consume_verify_code($pdo, $email, $code);
                if ($studentId <= 0) {
                    $error = 'Kod hatalı veya süresi dolmuş. Yeni kod isteyin.';
                } else {
                    $row = student_find_by_id($pdo, $studentId);
                    unset($_SESSION['verify_dev_code'], $_SESSION['verify_dev_link'], $_SESSION['verify_email']);
                    if ($row) {
                        $pdo->prepare("UPDATE students SET last_login_at = NOW() WHERE id = ?")->execute([$studentId]);
                        student_login_session($row);
                        header('Location: ' . $redirect);
                        exit;
                    }
                }
            }
        }
    }
}

$csrf = student_csrf_token();
if ($email !== '') {
    $_SESSION['verify_email'] = $email;
}

ogrenci_head('E-posta Doğrulama', 'page-auth');
?>
<div class="auth-page">
  <?php ogrenci_auth_top(); ?>
  <?php ogrenci_auth_hero('E-postanı Doğrula', 'Hesabını açmak için son bir adım kaldı.'); ?>

  <main class="auth-shell">
    <div class="auth-card">
      <h2 class="auth-card-title">Doğrulama kodu</h2>

      <?php if ($notice !== ''): ?>
        <div class="alert alert-ok"><i class="fa-solid fa-paper-plane"></i><span><?= ogrenci_e($notice) ?></span></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
      <?php endif; ?>

      <p class="auth-lead">
        <?php if ($email !== ''): ?>
          <strong><?= ogrenci_e($email) ?></strong> adresine 6 haneli bir kod gönderiyoruz. Kodu e-postadan alıp aşağıya yazın.
        <?php else: ?>
          Kayıt sırasında kullandığınız e-posta adresini girin, doğrulama kodunu gönderelim.
        <?php endif; ?>
      </p>

      <?php if ($devCode !== ''): ?>
        <div class="alert alert-info">
          <i class="fa-solid fa-flask"></i>
          <span>
            E-posta gönderilemedi. Kodunuz:
            <span class="verify-code-inline"><?= ogrenci_e($devCode) ?></span>
            <?php if ($devLink !== ''): ?>
              · <a href="<?= ogrenci_e($devLink) ?>">Bağlantı ile doğrula</a>
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>

      <form method="post" data-guard novalidate>
        <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
        <input type="hidden" name="next" value="<?= ogrenci_e($next) ?>">
        <input type="hidden" name="action" value="code">

        <?php if ($email === ''): ?>
        <div class="field">
          <label for="f-email">E-posta</label>
          <div class="input-wrap">
            <i class="fa-regular fa-envelope field-icon"></i>
            <input id="f-email" type="email" name="email" required autofocus autocomplete="email" placeholder="ornek@email.com">
          </div>
        </div>
        <?php else: ?>
          <input type="hidden" name="email" value="<?= ogrenci_e($email) ?>">
        <?php endif; ?>

        <div class="field">
          <label for="f-code">6 haneli kod</label>
          <input id="f-code" class="verify-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" <?= $email !== '' ? 'autofocus' : '' ?>>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">E-Posta Adresini Doğrula</button>
      </form>

      <form method="post" class="verify-resend" data-guard>
        <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
        <input type="hidden" name="next" value="<?= ogrenci_e($next) ?>">
        <input type="hidden" name="action" value="resend">
        <input type="hidden" name="email" value="<?= ogrenci_e($email) ?>">
        <button type="submit" class="btn btn-outline btn-block" <?= $email === '' ? 'disabled' : '' ?>>Kodu tekrar gönder</button>
      </form>

      <p class="form-foot">Yanlış adres mi? <a href="kayit.php">Yeniden kayıt ol</a> · <a href="giris.php">Giriş</a></p>
    </div>

    <a class="auth-back" href="../index.html"><i class="fa-solid fa-arrow-left"></i> Siteye dön</a>
  </main>
</div>
<?php ogrenci_foot(); ?>
