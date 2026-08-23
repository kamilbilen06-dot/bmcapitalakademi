<?php
/**
 * Çekirdek tablolar + tohum içerik.
 * İlk DB bağlantısında çalışır; install.php canlıda kapalı olsa da yeter.
 */
require_once __DIR__ . '/install_seed.php';
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/auth_schema.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/subscriptions_schema.php';

function site_core_ensure_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'admin',
        instructor_id INT NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(10) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        short_desc TEXT,
        image VARCHAR(255),
        video VARCHAR(255),
        video_poster VARCHAR(255),
        price VARCHAR(60),
        price_note VARCHAR(120),
        duration VARCHAR(120),
        egitim_turu VARCHAR(120),
        instructors VARCHAR(255),
        etiket VARCHAR(120),
        katilim_not TEXT,
        tarih_not VARCHAR(255),
        featured TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        data LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(255) NOT NULL,
        answer TEXT NOT NULL,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120),
        email VARCHAR(160),
        phone VARCHAR(60),
        subject VARCHAR(160),
        message TEXT,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45),
        path VARCHAR(255),
        ua VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(60) PRIMARY KEY,
        v TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function site_bootstrap_admin(PDO $pdo): void {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($n > 0) {
        return;
    }
    $user = defined('BOOTSTRAP_ADMIN_USER') ? trim((string) BOOTSTRAP_ADMIN_USER) : '';
    $pass = defined('BOOTSTRAP_ADMIN_PASS') ? (string) BOOTSTRAP_ADMIN_PASS : '';
    if ($user === '' || strlen($pass) < 6) {
        return;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, 'admin')"
    );
    $stmt->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
}

function site_bootstrap(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    site_core_ensure_tables($pdo);
    egitmen_ensure_schema($pdo);
    instructors_ensure_schema($pdo);
    auth_ensure_schema($pdo);
    students_ensure_schema($pdo);
    payments_ensure_schema($pdo);
    subscriptions_ensure_schema($pdo);
    seed_settings($pdo);
    seed_modules($pdo);
    seed_faqs($pdo);
    site_bootstrap_admin($pdo);
}
