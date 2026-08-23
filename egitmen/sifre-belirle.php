<?php
/**
 * Eğitmen davet / şifre sıfırlama — jetonla yeni şifre.
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../api/instructor_account.php';

start_admin_session();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$tokenValid = false;

try {
    $pdo = db();
    instructors_ensure_schema($pdo);
    auth_ensure_schema($pdo);
    instructor_tokens_ensure($pdo);
    $tokenValid = instructor_token_row($pdo, $token) !== null;
} catch (Throwable $e) {
    $error = 'Veritabanına ulaşılamadı.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    if (!admin_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Formu yeniden gönderin.';
    } elseif (mb_strlen($password) < INSTRUCTOR_MIN_PASSWORD) {
        $error = 'Şifre en az ' . INSTRUCTOR_MIN_PASSWORD . ' karakter olmalı.';
    } elseif ($password !== $password2) {
        $error = 'Şifreler birbiriyle uyuşmuyor.';
    } else {
        $userId = instructor_consume_token($pdo, $token);
        if ($userId <= 0) {
            $tokenValid = false;
        } else {
            $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            $st = $pdo->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $row = $st->fetch();
            if ($row) {
                login_session($row);
            }
            header('Location: index.php');
            exit;
        }
    }
}

$csrf = admin_csrf_token();
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şifre Belirle · Eğitmen — BM Capital</title>
  <link rel="stylesheet" href="assets/egitmen.css">
</head>
<body class="login-body">
  <form class="login-card" method="post">
    <div class="login-brand"><span class="bm">BM</span> Capital · Eğitmen</div>
    <h1>Şifreni belirle</h1>
    <p class="login-sub">Panele girmek için kendi şifreni yaz.</p>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$tokenValid): ?>
      <div class="alert err">Bu bağlantı geçersiz veya süresi dolmuş.</div>
      <a class="btn-primary" href="sifremi-unuttum.php" style="display:block;text-align:center;text-decoration:none;margin-top:12px">Yeni bağlantı iste</a>
    <?php else: ?>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <label>Yeni şifre</label>
      <input type="password" name="password" required autofocus autocomplete="new-password" minlength="<?= INSTRUCTOR_MIN_PASSWORD ?>" placeholder="En az <?= INSTRUCTOR_MIN_PASSWORD ?> karakter">
      <label>Yeni şifre (tekrar)</label>
      <input type="password" name="password2" required autocomplete="new-password">
      <button type="submit" class="btn-primary">Kaydet ve gir</button>
    <?php endif; ?>
    <a class="login-back" href="login.php">← Girişe dön</a>
  </form>
</body>
</html>
