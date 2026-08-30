<?php
/**
 * ÖRNEK — kopyalayın: api/mail_config.local.php
 * Bu dosya (local) Git'e girmez.
 *
 * CANLI (cPanel/Linux): mail sunucunun kendi servisiyle (Exim) gider.
 * Gönderen adres domain olmalı; Gmail adresiyle sunucudan göndermek
 * DMARC'ı bozar ve spam'e düşürür.
 *
 * cPanel'de bir kez yapılacaklar:
 *   1) Email Accounts → noreply@bmcapitalakademi.com oluşturun
 *   2) Email Deliverability → SPF ve DKIM "Enable"
 *
 * YEREL (Windows): sendmail yok, o yüzden otomatik olarak SMTP denenir.
 * Aşağıdaki Gmail bilgileri yalnızca yerel test için gereklidir.
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

/**
 * Gönderim yolu: 'smtp' (Gmail, hızlı) veya 'server' (cPanel Exim).
 * Tanımlamazsanız api/mail_config.php varsayılanı ('smtp') geçerlidir.
 * SPF ve DKIM açıldıktan sonra 'server' yapıp noreply@ ile gönderebilirsiniz.
 */
// define('MAIL_TRANSPORT', 'server');
