<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e eklenmez.
 *
 * Hosting SMTP (cPanel / Plesk) veya Gmail uygulama şifresi kullanın.
 * Admin panel → Ayarlar içindeki SMTP alanları da aynı işi görür;
 * bu dosya doluysa ayar tablosunun üstüne yazılır.
 *
 * Gmail: smtp.gmail.com, 587, tls, hesap e-postası + uygulama şifresi
 * cPanel: mail.alanadiniz.com, 587, tls, tam e-posta + şifre
 */

define('SMTP_HOST', 'mail.bmcapitalakademi.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // tls | ssl | none
define('SMTP_USER', 'noreply@bmcapitalakademi.com');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@bmcapitalakademi.com');
define('SMTP_FROM_NAME', 'BM Capital Akademi');

/** Satış ve yeni abonelik bildirimleri (boşsa bmcapitalakademi@gmail.com) */
define('SMTP_NOTIFY', 'bmcapitalakademi@gmail.com');
