<?php
/**
 * PDO veritabanı bağlantısı (tekil).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core_schema.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        // Windows'ta localhost named pipe'a takılır; cPanel'de localhost soket gerekir.
        $host = DB_HOST;
        if ($host === 'localhost' && strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $host = '127.0.0.1';
        }
        $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
        try {
            site_db_apply_timezone($pdo);
        } catch (Throwable $e) {
            error_log('saat dilimi: ' . $e->getMessage());
        }
        try {
            site_bootstrap($pdo);
        } catch (Throwable $e) {
            error_log('bootstrap: ' . $e->getMessage());
        }
        try {
            site_migrate_datetimes_to_istanbul($pdo);
        } catch (Throwable $e) {
            error_log('tz migrate: ' . $e->getMessage());
        }
        try {
            site_bootstrap_admin($pdo);
        } catch (Throwable $e) {
            error_log('admin bootstrap: ' . $e->getMessage());
        }
    }
    return $pdo;
}
