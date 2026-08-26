<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e girmez.
 *
 * Mail Gmail SMTP ile gider (smtp.gmail.com). cPanel Exim / GoDaddy
 * HOSTING RELAY kullanılmaz; o kuyruk Gmail'de ~1 dk bekletir.
 *
 * SMTP_PASS: Google hesap → 2 adımlı doğrulama → Uygulama şifreleri.
 * cPanel "SMTP Restrictions" 587'yi keserse 465/SSL otomatik denenir.
 */

/** Sunucudan gönderirken kullanılacak adres */
define('MAIL_DOMAIN_FROM', 'noreply@bmcapitalakademi.com');

/** Cevapların düşeceği kutu */
define('SMTP_NOTIFY', 'bmcapitalakademi@gmail.com');

/** Yerel test / yedek SMTP (boş bırakılabilir) */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'bmcapitalakademi@gmail.com');
define('SMTP_PASS', '');
define('SMTP_FROM', 'bmcapitalakademi@gmail.com');
define('SMTP_FROM_NAME', 'BM Capital Akademi');

define('MAIL_TRANSPORT', 'smtp');
