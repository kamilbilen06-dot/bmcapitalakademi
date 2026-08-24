<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e girmez.
 *
 * Çalışan yol: Gmail uygulama şifresi (eğitmen paneli de bunu kullanır).
 * smtp.gmail.com / 587 / tls / Gmail adresi + uygulama şifresi
 */

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'bmcapitalakademi@gmail.com');
define('SMTP_PASS', '');
define('SMTP_FROM', 'bmcapitalakademi@gmail.com');
define('SMTP_FROM_NAME', 'BM Capital Akademi');
define('SMTP_NOTIFY', 'bmcapitalakademi@gmail.com');
