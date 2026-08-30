<?php
/**
 * SMTP ayarları — local dosya (Git'e girmez) veya settings tablosu.
 */
if (is_file(__DIR__ . '/mail_config.local.php')) {
    require __DIR__ . '/mail_config.local.php';
}

/**
 * Mail: Resend HTTPS (443) — GoDaddy SMTP/Exim rölesi yok.
 * Anahtar yoksa canlıda cPanel Exim (yavaş ama gider).
 */
if (!defined('MAIL_TRANSPORT')) {
    define('MAIL_TRANSPORT', 'http');
}

/** 'server' yoluna düşülürse kullanılacak gönderen (Gmail adresi DMARC'ı bozar) */
if (!defined('MAIL_DOMAIN_FROM')) {
    define('MAIL_DOMAIN_FROM', 'noreply@bmcapitalakademi.com');
}
