<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../api/instructors_schema.php';
require_once __DIR__ . '/../api/egitmen_schema.php';
require_once __DIR__ . '/../api/auth_schema.php';
require_once __DIR__ . '/../api/instructor_account.php';
require_once __DIR__ . '/../api/login_guard.php';

start_admin_session();
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $guardId = $raw !== '' ? $raw : 'unknown';
    $guard = login_guard_check('egitmen', $guardId);
    if (!$guard['ok']) {
        $error = $guard['error'];
    } else {
    try {
        $pdo = db();
        egitmen_ensure_schema($pdo);
        instructors_ensure_schema($pdo);
        auth_ensure_schema($pdo);

        $tries = [instructor_normalize_email($raw)];
        if ($tries[0] !== $raw && $raw !== '') {
            $tries[] = $raw;
        }
        $row = null;
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
        foreach ($tries as $u) {
            $stmt->execute([$u]);
            $row = $stmt->fetch();
            if ($row) {
                break;
            }
        }

        if ($row && !instructor_has_usable_password($row)) {
            login_guard_fail('egitmen', $guardId);
            $error = 'Şifrenizi henüz belirlemediniz. E-postanızdaki bağlantıyı kullanın veya şifremi unuttum deyin.';
        } elseif ($row && password_verify($pass, $row['password_hash'])) {
            $role = ($row['role'] ?? 'admin') === 'egitmen' ? 'egitmen' : 'admin';
            if ($role === 'egitmen' && empty($row['instructor_id'])) {
                login_guard_fail('egitmen', $guardId);
                $error = 'Hesabınız bir eğitmen profiline bağlı değil. Yöneticiye bildirin.';
            } else {
                login_guard_clear('egitmen', $guardId);
                login_session($row);
                header('Location: index.php');
                exit;
            }
        } else {
            login_guard_fail('egitmen', $guardId);
            $error = 'E-posta veya şifre hatalı.';
        }
    } catch (Throwable $e) {
        $error = 'Bağlantı hatası. Veritabanı ayarlarını kontrol edin.';
    }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eğitmen Paneli · Giriş — BM Capital</title>
  <link rel="stylesheet" href="assets/egitmen.css">
</head>
<body class="login-body">
  <form class="login-card" method="post">
    <div class="login-brand"><span class="bm">BM</span> Capital · Eğitmen</div>
    <h1>Eğitmen Paneli</h1>
    <p class="login-sub">Kurslarınızı ve profilinizi yönetmek için giriş yapın</p>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>E-posta</label>
    <input type="text" name="username" required autofocus autocomplete="username" placeholder="ornek@email.com">
    <label>Şifre</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit" class="btn-primary">Giriş Yap</button>
    <p class="login-sub" style="margin-top:14px"><a href="sifremi-unuttum.php">Şifremi unuttum</a></p>
    <a class="login-back" href="../index.html">← Siteye dön</a>
  </form>
</body>
</html>
