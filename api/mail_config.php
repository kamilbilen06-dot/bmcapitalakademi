<?php
/**
 * SMTP ayarları — local dosya (Git'e girmez) veya settings tablosu.
 */
if (is_file(__DIR__ . '/mail_config.local.php')) {
    require __DIR__ . '/mail_config.local.php';
}

/**
 * Gönderim yolu. Gmail SMTP kimliği doğrulanmış gönderim yaptığı için mail
 * saniyeler içinde düşer. Sunucunun kendi servisi (Exim) domainde SPF/DKIM
 * olmadığından Gmail tarafından bekletiliyor (2-3 dk).
 * SPF ve DKIM açıldıktan sonra 'server' yapılabilir.
 */
if (!defined('MAIL_TRANSPORT')) {
    define('MAIL_TRANSPORT', 'smtp');
}

/** 'server' yoluna düşülürse kullanılacak gönderen (Gmail adresi DMARC'ı bozar) */
if (!defined('MAIL_DOMAIN_FROM')) {
    define('MAIL_DOMAIN_FROM', 'noreply@bmcapitalakademi.com');
}
