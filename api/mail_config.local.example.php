<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e girmez.
 *
 * Canlı sitede (bmcapitalakademi.com) kod cPanel Exim / PHP mail kullanır.
 * Ücret yoktur. Gönderen: noreply@bmcapitalakademi.com
 *
 * cPanel’de bir kez:
 *  1) Email Accounts → noreply@bmcapitalakademi.com (isteğe bağlı, önerilir)
 *  2) Email Deliverability → SPF + DKIM Enable
 *
 * Yerelde Windows’ta PHP mail çalışmaz; SMTP_DRIVER=smtp ve Gmail bırakın.
 */

define('SMTP_DRIVER', 'local'); // canlı: local (cPanel) · yerelde test: smtp
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_SECURE', 'none');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@bmcapitalakademi.com');
define('SMTP_FROM_NAME', 'BM Capital Akademi');
define('SMTP_NOTIFY', 'bmcapitalakademi@gmail.com');
