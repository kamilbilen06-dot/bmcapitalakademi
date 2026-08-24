<?php
/**
 * Eğitmen şifre sıfırlama talebi.
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../api/mailer.php';
require_once __DIR__ . '/../api/instructor_account.php';

start_admin_session();
if (is_logged_in() && !is_site_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$sent = false;
$devLink = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = instructor_normalize_email($_POST['email'] ?? '');
    if (!admin_csrf_valid($_POST['csrf'] ?? '')) {
        $error = 'Oturum süresi doldu. Lütfen tekrar deneyin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        try {
            $pdo = db();
            instructors_ensure_schema($pdo);
            auth_ensure_schema($pdo);
            instructor_tokens_ensure($pdo);
            $st = $pdo->prepare(
                "SELECT u.id FROM admin_users u
                 LEFT JOIN instructors i ON i.id = u.instructor_id
                 WHERE u.role = 'egitmen' AND (u.username = ? OR i.email = ?)
                 LIMIT 1"
            );
            $st->execute([$email, $email]);
            $userId = (int)$st->fetchColumn();
            if ($userId <= 0) {
                $error = 'Bu e-posta ile eğitmen hesabı bulunamadı.';
            } else {
                $invite = instructor_deliver_invite($pdo, $userId, 'reset', false);
                $link = (string)($invite['link'] ?? '');
                if (instructor_invite_is_local($link)) {
                    $devLink = $link;
                }
                if (!mailer_is_configured() || $link === '') {
                    $error = 'E-posta gönderimi kapalı. Yönetici → Eğitmenler → davet / şifre linkini açın.';
                } else {
                    $payload = $invite['email_payload'] ?? [];
                    $purpose = (string)($invite['purpose'] ?? 'reset');
                    mailer_defer(static function () use ($payload, $link, $purpose): void {
                        mailer_send_instructor_invite($payload, $link, $purpose);
                    });
                    $sent = true;
                }
            }
        } catch (Throwable $e) {
            $error = 'İşlem tamamlanamadı. Veritabanı bağlantısını kontrol edin.';
        }
    }
}

$csrf = admin_csrf_token();
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şifremi Unuttum · Eğitmen — BM Capital</title>
  <link rel="stylesheet" href="assets/egitmen.css?v=20260824a">
</head>
<body class="login-body">
  <form class="login-card" method="post" action="sifremi-unuttum.php">
    <div class="login-brand"><span class="bm">BM</span> Capital · Eğitmen</div>
    <h1>Şifremi unuttum</h1>
    <p class="login-sub">E-postana şifre belirleme bağlantısı gönderilir.</p>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($sent): ?>
      <div class="alert ok">Şifre sıfırlama bağlantısı gönderildi.</div>
      <?php if ($devLink !== ''): ?>
        <p class="login-sub"><a href="<?= htmlspecialchars($devLink) ?>">Yerel: şifreyi buradan belirle</a></p>
      <?php endif; ?>
      <a class="btn-primary" href="login.php" style="display:block;text-align:center;text-decoration:none;margin-top:12px">Girişe dön</a>
    <?php else: ?>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <label>E-posta</label>
      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus autocomplete="email" placeholder="ornek@email.com">
      <button type="submit" class="btn-primary">Bağlantı gönder</button>
    <?php endif; ?>
    <a class="login-back" href="login.php">← Girişe dön</a>
  </form>
</body>
</html>
