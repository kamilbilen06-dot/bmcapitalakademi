<?php
/**
 * Hesabım — kişisel bilgiler ve şifre değiştirme.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/_layout.php';

start_student_session();
require_student_page('profil.php');

$session = current_student();
$error = '';
$notice = '';
$student = null;
$pdo = null;

try {
    $pdo = db();
    students_ensure_schema($pdo);
    $student = student_find_by_id($pdo, $session['id']);
    if (!$student) {
        student_logout();
        header('Location: giris.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = $_POST['form'] ?? '';

        if (!student_csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Oturum süresi doldu. Lütfen formu tekrar gönderin.';
        } elseif ($form === 'profile') {
            $name = clean($_POST['name'] ?? '');
            $phone = clean($_POST['phone'] ?? '');
            if (mb_strlen($name) < 2) {
                $error = 'Ad soyad en az 2 karakter olmalı.';
            } else {
                $pdo->prepare("UPDATE students SET name = ?, phone = ? WHERE id = ?")
                    ->execute([mb_substr($name, 0, 160), mb_substr($phone, 0, 40), (int)$student['id']]);
                $student = student_find_by_id($pdo, (int)$student['id']);
                student_login_session($student);
                $notice = 'Bilgileriniz güncellendi.';
            }
        } elseif ($form === 'password') {
            $current = (string)($_POST['current'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            // Yalnızca Google ile açılmış hesapta mevcut şifre yoktur
            $needsCurrent = student_has_password($student);

            if ($needsCurrent && !password_verify($current, (string)$student['password_hash'])) {
                $error = 'Mevcut şifreniz hatalı.';
            } elseif (mb_strlen($password) < STUDENT_MIN_PASSWORD) {
                $error = 'Yeni şifre en az ' . STUDENT_MIN_PASSWORD . ' karakter olmalı.';
            } elseif ($password !== $password2) {
                $error = 'Yeni şifreler birbiriyle uyuşmuyor.';
            } else {
                student_set_password($pdo, (int)$student['id'], $password);
                $student = student_find_by_id($pdo, (int)$student['id']);
                $notice = $needsCurrent
                    ? 'Şifreniz güncellendi.'
                    : 'Şifreniz oluşturuldu. Artık Google’ın yanı sıra e-posta ve şifreyle de giriş yapabilirsiniz.';
            }
        }
    }
} catch (Throwable $e) {
    $error = 'İşlem tamamlanamadı. Veritabanı bağlantısını kontrol edin.';
    $student = $student ?: ['id' => $session['id'], 'name' => $session['name'], 'email' => $session['email'], 'phone' => '', 'created_at' => null, 'last_login_at' => null];
}

$csrf = student_csrf_token();
$hasPassword = student_has_password($student);

ogrenci_head('Profili Düzenle', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'profil', 'Profili Düzenle', 'Kişisel bilgilerinizi ve giriş şifrenizi buradan yönetin.');
?>

    <?php if ($notice !== ''): ?>
      <div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i><span><?= ogrenci_e($notice) ?></span></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($error) ?></span></div>
    <?php endif; ?>

    <div class="panel-grid">
      <section class="panel">
        <h2>Kişisel bilgiler</h2>
        <p class="panel-sub">Fatura ve iletişim için kullanılır.</p>
        <form method="post" data-guard novalidate>
          <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
          <input type="hidden" name="form" value="profile">

          <div class="field">
            <label for="f-name">Ad Soyad</label>
            <div class="input-wrap">
              <i class="fa-regular fa-user field-icon"></i>
              <input id="f-name" type="text" name="name" value="<?= ogrenci_e($student['name']) ?>" required autocomplete="name">
            </div>
          </div>

          <div class="field">
            <label for="f-email">E-posta</label>
            <div class="input-wrap">
              <i class="fa-regular fa-envelope field-icon"></i>
              <input id="f-email" type="email" value="<?= ogrenci_e($student['email']) ?>" readonly>
            </div>
            <p class="hint">E-posta adresi giriş kimliğinizdir; değiştirmek için bize yazın.</p>
          </div>

          <div class="field">
            <label for="f-phone">Telefon <span class="optional">(isteğe bağlı)</span></label>
            <div class="input-wrap">
              <i class="fa-solid fa-phone field-icon"></i>
              <input id="f-phone" type="tel" name="phone" value="<?= ogrenci_e($student['phone'] ?? '') ?>" autocomplete="tel" placeholder="05xx xxx xx xx">
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Bilgileri kaydet</button>
        </form>
      </section>

      <section class="panel">
        <h2><?= $hasPassword ? 'Şifre değiştir' : 'Şifre oluştur' ?></h2>
        <p class="panel-sub">
          <?= $hasPassword
              ? 'Güvenliğiniz için şifrenizi düzenli olarak yenileyin.'
              : 'Hesabınız Google ile açıldı. Şifre belirlerseniz e-posta ile de giriş yapabilirsiniz.' ?>
        </p>
        <form method="post" data-guard novalidate>
          <input type="hidden" name="csrf" value="<?= ogrenci_e($csrf) ?>">
          <input type="hidden" name="form" value="password">

          <?php if ($hasPassword): ?>
          <div class="field">
            <label for="f-cur">Mevcut şifre</label>
            <div class="input-wrap">
              <input id="f-cur" type="password" name="current" required autocomplete="current-password">
              <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>
          <?php endif; ?>

          <div class="field">
            <label for="f-pw">Yeni şifre</label>
            <div class="input-wrap">
              <input id="f-pw" type="password" name="password" required autocomplete="new-password" placeholder="En az <?= STUDENT_MIN_PASSWORD ?> karakter">
              <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>

          <div class="field">
            <label for="f-pw2">Yeni şifre (tekrar)</label>
            <div class="input-wrap">
              <input id="f-pw2" type="password" name="password2" required autocomplete="new-password">
              <button type="button" class="pw-toggle" aria-label="Şifreyi göster"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>

          <button type="submit" class="btn btn-dark btn-block">
            <i class="fa-solid fa-key"></i> <?= $hasPassword ? 'Şifreyi güncelle' : 'Şifreyi oluştur' ?>
          </button>
        </form>
      </section>
    </div>

<?php
ogrenci_panel_end();
ogrenci_foot();
