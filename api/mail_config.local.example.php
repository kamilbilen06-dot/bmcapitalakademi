<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e girmez.
 *
 * Canlı hız: Resend HTTPS (api.resend.com, port 443).
 * GoDaddy Exim rölesi ~60 sn; Gmail SMTP bu hostingde kapalı.
 *
 * 1) https://resend.com — API key
 * 2) Domains → bmcapitalakademi.com → Resend'in verdiği TXT'leri
 *    GoDaddy DNS (alan adı DNS) ekleyin, Verify
 * 3) Anahtarı buraya veya Yönetim → Ayarlar'a yazın
 */

define('MAIL_DOMAIN_FROM', 'noreply@bmcapitalakademi.com');
define('SMTP_NOTIFY', 'bmcapitalakademi@gmail.com');

define('RESEND_API_KEY', '');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'bmcapitalakademi@gmail.com');
define('SMTP_PASS', '');
define('SMTP_FROM', 'bmcapitalakademi@gmail.com');
define('SMTP_FROM_NAME', 'BM Capital Akademi');

define('MAIL_TRANSPORT', 'http');
