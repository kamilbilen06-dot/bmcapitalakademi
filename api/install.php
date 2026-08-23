<?php
/**
 * BM Capital - Kurulum
 * Tabloları oluşturur, mevcut içeriği yükler ve yönetici hesabı açar.
 * Çalıştırdıktan sonra api/config.php içinde INSTALL_LOCKED = true yapın.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (INSTALL_LOCKED) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die('Kurulum kapalı.');
}

$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$localHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$localRemote = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);
if (!$localHost || !$localRemote) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die('Kurulum kapalı.');
}

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    if ($user === '' || strlen($pass) < 6) {
        $error = 'Kullanıcı adı gerekli ve şifre en az 6 karakter olmalı.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, 'admin')
                ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
            $stmt->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);

            $done = true;
        } catch (Throwable $e) {
            $error = 'Hata: ' . $e->getMessage();
        }
    }
}

?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BM Capital - Kurulum</title>
  <style>
    body{font-family:'Segoe UI',Arial,sans-serif;background:#1f2d3d;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0}
    .box{background:#26374a;padding:36px;border-radius:16px;max-width:440px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.4)}
    h1{margin:0 0 6px;font-size:22px}p{color:#c7cdd6;font-size:14px}
    label{display:block;margin:16px 0 6px;font-size:14px;font-weight:600}
    input{width:100%;padding:12px;border-radius:8px;border:1px solid #3a4d63;background:#1f2d3d;color:#fff;font-size:15px;box-sizing:border-box}
    button{margin-top:20px;width:100%;padding:13px;border:none;border-radius:8px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;font-weight:700;font-size:15px;cursor:pointer}
    .ok{background:#1e7a45;padding:16px;border-radius:8px;margin-bottom:12px}
    .err{background:#b3261e;padding:12px;border-radius:8px;margin-bottom:12px;font-size:14px}
    a{color:#f39c12}
    code{background:#1f2d3d;padding:2px 6px;border-radius:4px}
  </style>
</head>
<body>
  <div class="box">
    <?php if ($done): ?>
      <div class="ok"><strong>Kurulum tamamlandı!</strong></div>
      <p>Yönetici hesabınız oluşturuldu ve içerik yüklendi. Şimdi:</p>
      <p>1. Güvenlik için <code>api/config.php</code> içinde <code>INSTALL_LOCKED = true</code> yapın (veya bu dosyayı silin).<br>
      2. <a href="../admin/login.php">Yönetim paneline giriş yapın →</a></p>
    <?php else: ?>
      <h1>BM Capital Kurulum</h1>
      <p>Veritabanı tablolarını oluşturup yönetici hesabınızı belirleyin. (Önce <code>api/config.php</code> içindeki veritabanı bilgilerini doldurun.)</p>
      <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <label>Yönetici Kullanıcı Adı</label>
        <input type="text" name="username" required autofocus>
        <label>Şifre (en az 6 karakter)</label>
        <input type="password" name="password" required>
        <button type="submit">Kurulumu Başlat</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
