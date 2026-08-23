<?php
/**
 * SMTP ayarları — local dosya (Git'e girmez) veya settings tablosu.
 */
if (is_file(__DIR__ . '/mail_config.local.php')) {
    require __DIR__ . '/mail_config.local.php';
}
