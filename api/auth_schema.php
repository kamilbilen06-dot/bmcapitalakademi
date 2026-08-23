<?php
/**
 * Kullanıcı rolleri + kurs sahipliği kolonları.
 */
function auth_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    // admin_users.role
    if (!column_exists($pdo, 'admin_users', 'role')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password_hash");
    }
    // admin_users.instructor_id
    if (!column_exists($pdo, 'admin_users', 'instructor_id')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN instructor_id INT NULL DEFAULT NULL AFTER role");
    }
    if (!column_exists($pdo, 'admin_users', 'last_login_at')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER instructor_id");
    }

    // courses.instructor_id
    if (!column_exists($pdo, 'courses', 'instructor_id')) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN instructor_id INT NOT NULL DEFAULT 0 AFTER id");
        $pdo->exec("ALTER TABLE courses ADD INDEX idx_instructor (instructor_id)");
    }

    // Orphan kursları ilk eğitmente bağla
    try {
        $insId = (int)$pdo->query("SELECT id FROM instructors ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($insId > 0) {
            $pdo->prepare("UPDATE courses SET instructor_id = ? WHERE instructor_id = 0 OR instructor_id IS NULL")
                ->execute([$insId]);
        }
    } catch (Throwable $e) {
        // instructors henüz yoksa geç
    }

    // Eski admin hesaplarını role=admin yap
    $pdo->exec("UPDATE admin_users SET role = 'admin' WHERE role = '' OR role IS NULL");
}

function column_exists(PDO $pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        // Fallback: SELECT deneyerek
        try {
            $pdo->query("SELECT `$column` FROM `$table` LIMIT 1");
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}
