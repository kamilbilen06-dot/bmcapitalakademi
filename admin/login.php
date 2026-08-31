<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../api/auth_schema.php';
require_once __DIR__ . '/../api/login_guard.php';

start_admin_session();

if (is_logged_in()) {
    if (is_site_admin()) {
        header('Location: index.php');
        exit;
    }
    header('Location: ../egitmen/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $guard = login_guard_check('admin', $user !== '' ? $user : 'unknown');
    if (!$guard['ok']) {
        $error = $guard['error'];
    } else {
        try {
            $pdo = db();
            try {
                site_bootstrap_admin($pdo);
            } catch (Throwable $e) {
                error_log('admin login bootstrap: ' . $e->getMessage());
            }
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$user]);
            $row = $stmt->fetch();
            if ($row && password_verify($pass, $row['password_hash'])) {
                $role = ($row['role'] ?? 'admin') === 'egitmen' ? 'egitmen' : 'admin';
                if ($role !== 'admin') {
                    login_guard_fail('admin', $user);
                    $error = 'Bu hesap yönetim paneli için değil. Eğitmen paneline giriş yapın.';
                } else {
                    login_guard_clear('admin', $user);
                    login_session($row);
                    header('Location: index.php');
                    exit;
                }
            } else {
                login_guard_fail('admin', $user !== '' ? $user : 'unknown');
                $error = 'Kullanıcı adı veya şifre hatalı.';
            }
        } catch (Throwable $e) {
            error_log('admin login: ' . $e->getMessage());
            $error = 'Bağlantı hatası. Veritabanı ayarlarını ve kurulumu kontrol edin.';
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yönetim Paneli · Giriş — BM Capital</title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-body">
  <form class="login-card" method="post">
    <div class="login-brand"><span class="bm">BM</span> Capital</div>
    <h1>Yönetim Paneli</h1>
    <p class="login-sub">Devam etmek için giriş yapın</p>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Kullanıcı Adı</label>
    <input type="text" name="username" required autofocus autocomplete="username">
    <label>Şifre</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit" class="btn-primary">Giriş Yap</button>
    <p class="login-sub" style="margin-top:14px">Eğitmen misiniz? <a href="../egitmen/login.php">Eğitmen paneli</a></p>
    <a class="login-back" href="../index.html">← Siteye dön</a>
  </form>
</body>
</html>
