<?php
/**
 * BM Capital / yeni akademi — Veritabanı ve panel yapılandırması
 *
 * Canlı MySQL bilgilerini api/config.local.php içine yazın (Git'e girmez).
 * GitHub'daki bu dosya üzerine kopyalansa bile local dosya ezilmez.
 * Örnek: api/config.local.example.php
 *
 * Marka/domain: api/site_brand.local.php
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'bmcapital');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('INSTALL_LOCKED')) {
    define('INSTALL_LOCKED', true);
}

if (!defined('CRON_KEY')) {
    define('CRON_KEY', '');
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'bmcap_admin');
}

if (!defined('STUDENT_SESSION_NAME')) {
    define('STUDENT_SESSION_NAME', 'bmcap_student');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', '');
}

if (!defined('SITE_TIMEZONE')) {
    define('SITE_TIMEZONE', 'Europe/Istanbul');
}
date_default_timezone_set(SITE_TIMEZONE);

require_once __DIR__ . '/site_brand.php';

if (SITE_URL === '' && PUBLIC_SITE_URL !== '') {
    // SITE_URL const yeniden tanımlanamaz; paytr_site_base zaten PUBLIC_SITE_URL okur.
}
