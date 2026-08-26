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

/** Türkiye saati (yaz/kış farkı yok). */
function site_timezone(): string {
    return defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'Europe/Istanbul';
}

function site_now(): string {
    return date('Y-m-d H:i:s');
}

function site_format_dt($value, string $format = 'd.m.Y H:i'): string {
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts ? date($format, $ts) : '';
}

/**
 * iyzico tarihini Türkiye duvar saatine çevir.
 * Epoch (ms/s), ISO-8601 veya TZ'siz UTC metin kabul eder.
 */
function site_from_iyzico_dt(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $tz = new DateTimeZone(site_timezone());
    if (preg_match('/^\d{10,13}$/', $raw)) {
        $n = (int)$raw;
        if ($n > 20000000000) {
            $n = (int)round($n / 1000);
        }
        $dt = new DateTime('@' . $n);
        $dt->setTimezone($tz);
        return $dt->format('Y-m-d H:i:s');
    }
    try {
        if (preg_match('/[zZ]|[+\-]\d{2}:?\d{2}$/', $raw) || str_contains($raw, 'T')) {
            $dt = new DateTime($raw);
        } else {
            $dt = new DateTime($raw, new DateTimeZone('UTC'));
        }
        $dt->setTimezone($tz);
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : '';
    }
}

function site_db_apply_timezone(PDO $pdo): void {
    try {
        $pdo->exec("SET time_zone = '+03:00'");
    } catch (Throwable $e) {
        error_log('saat dilimi: ' . $e->getMessage());
    }
}

/**
 * Eski DATETIME değerleri sunucu saatinden Türkiye'ye bir kez kaydırır.
 * MySQL NOW() ile yazılanlar sistem TZ, current_period_end PHP UTC idi.
 */
function site_migrate_datetimes_to_istanbul(PDO $pdo): void {
    try {
        $st = $pdo->query("SELECT v FROM settings WHERE k = 'tz_istanbul' LIMIT 1");
        if ($st && (string)$st->fetchColumn() === '1') {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    $delta = 0;
    try {
        $pdo->exec("SET time_zone = 'SYSTEM'");
        $sysNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
        $pdo->exec("SET time_zone = '+03:00'");
        $istNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
        $delta = (int)(strtotime($istNow) - strtotime($sysNow));
    } catch (Throwable $e) {
        try {
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (Throwable $e2) {
        }
    }

    if (abs($delta) >= 60) {
        $sec = $delta;
        $cols = [
            ['subscription_invoices', 'created_at'],
            ['subscriptions', 'last_paid_at'],
            ['subscriptions', 'created_at'],
            ['subscriptions', 'updated_at'],
            ['subscriptions', 'cancelled_at'],
            ['subscriptions', 'last_failure_at'],
            ['subscriptions', 'mail_sent_at'],
            ['subscriptions', 'cancel_mail_at'],
            ['subscriptions', 'past_due_mail_at'],
            ['payment_orders', 'created_at'],
            ['payment_orders', 'paid_at'],
            ['payment_orders', 'refunded_at'],
            ['payment_orders', 'mail_sent_at'],
        ];
        foreach ($cols as [$table, $col]) {
            try {
                $pdo->exec(
                    "UPDATE `$table` SET `$col` = DATE_ADD(`$col`, INTERVAL $sec SECOND)
                     WHERE `$col` IS NOT NULL AND `$col` <> '0000-00-00 00:00:00'"
                );
            } catch (Throwable $e) {
                // tablo/kolon yoksa geç
            }
        }
    }

    try {
        $rows = $pdo->query(
            "SELECT id, current_period_end FROM subscriptions
             WHERE current_period_end IS NOT NULL AND current_period_end <> '0000-00-00 00:00:00'"
        );
        if ($rows) {
            $upd = $pdo->prepare('UPDATE subscriptions SET current_period_end = ? WHERE id = ?');
            $tz = new DateTimeZone(site_timezone());
            foreach ($rows as $r) {
                try {
                    $dt = new DateTime((string)$r['current_period_end'], new DateTimeZone('UTC'));
                    $dt->setTimezone($tz);
                    $upd->execute([$dt->format('Y-m-d H:i:s'), (int)$r['id']]);
                } catch (Throwable $e) {
                }
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $pdo->prepare("INSERT INTO settings (k, v) VALUES ('tz_istanbul', '1') ON DUPLICATE KEY UPDATE v = '1'")
            ->execute();
    } catch (Throwable $e) {
    }
}
