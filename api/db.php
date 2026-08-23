<?php
/**
 * PDO veritabanı bağlantısı (tekil).
 */
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        // Windows'ta "localhost" named pipe'a takılabiliyor; TCP daha güvenilir.
        $host = DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST;
        $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    }
    return $pdo;
}
