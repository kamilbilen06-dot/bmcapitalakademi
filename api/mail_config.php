<?php
/**
 * SMTP ayarları — local dosya (Git'e girmez) veya settings tablosu.
 */
if (is_file(__DIR__ . '/mail_config.local.php')) {
    require __DIR__ . '/mail_config.local.php';
}

/**
 * Gmail SMTP (smtp.gmail.com). cPanel Exim / GoDaddy rölesi kullanılmaz;
 * o yol Gmail kutusunda ~1 dk bekletiyor.
 */
if (!defined('MAIL_TRANSPORT')) {
    define('MAIL_TRANSPORT', 'smtp');
}

/** 'server' yoluna düşülürse kullanılacak gönderen (Gmail adresi DMARC'ı bozar) */
if (!defined('MAIL_DOMAIN_FROM')) {
    define('MAIL_DOMAIN_FROM', 'noreply@bmcapitalakademi.com');
}
