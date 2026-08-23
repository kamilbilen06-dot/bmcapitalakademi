<?php
/**
 * Eğitmen paneli tabloları — ilk API çağrısında otomatik oluşur.
 */
function egitmen_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    // Bazı paylaşımlı hostinglerde FK sorun çıkarabilir; indeksler yeterli.
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        instructor_id INT NOT NULL DEFAULT 0,
        title VARCHAR(255) NOT NULL DEFAULT '',
        subtitle VARCHAR(255) NOT NULL DEFAULT '',
        description MEDIUMTEXT,
        language VARCHAR(40) NOT NULL DEFAULT 'Türkçe',
        level VARCHAR(60) NOT NULL DEFAULT 'Tüm Düzeyler',
        category VARCHAR(120) NOT NULL DEFAULT '',
        subcategory VARCHAR(120) NOT NULL DEFAULT '',
        topic VARCHAR(120) NOT NULL DEFAULT '',
        image_path VARCHAR(255) NOT NULL DEFAULT '',
        promo_video_path VARCHAR(255) NOT NULL DEFAULT '',
        price VARCHAR(60) NOT NULL DEFAULT '',
        price_note VARCHAR(120) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        sort_order INT NOT NULL DEFAULT 0,
        duration_sec INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Mevcut kurulumlarda kolon yoksa ekle
    try {
        $pdo->query("SELECT duration_sec FROM courses LIMIT 1");
    } catch (Throwable $e) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN duration_sec INT NOT NULL DEFAULT 0 AFTER sort_order");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_objectives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        body VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_requirements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        body VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_audience (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        body VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_lectures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_id INT NOT NULL,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        description MEDIUMTEXT,
        resources LONGTEXT,
        video_path VARCHAR(255) NOT NULL DEFAULT '',
        duration_sec INT NOT NULL DEFAULT 0,
        is_preview TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_section (section_id),
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing($pdo, 'course_lectures', 'description', "MEDIUMTEXT NULL AFTER title");
    egitmen_add_column_if_missing($pdo, 'course_lectures', 'resources', "LONGTEXT NULL AFTER description");
    egitmen_add_column_if_missing($pdo, 'course_lectures', 'is_preview', "TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_sec");

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        student_name VARCHAR(255) NOT NULL DEFAULT '',
        student_email VARCHAR(255) NOT NULL DEFAULT '',
        student_phone VARCHAR(60) NOT NULL DEFAULT '',
        progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        source VARCHAR(40) NOT NULL DEFAULT 'site',
        enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_visit_at DATETIME NULL,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing($pdo, 'course_enrollments', 'student_phone', "VARCHAR(60) NOT NULL DEFAULT '' AFTER student_email");
    egitmen_add_column_if_missing($pdo, 'course_enrollments', 'source', "VARCHAR(40) NOT NULL DEFAULT 'site' AFTER progress_pct");
}

function egitmen_add_column_if_missing(PDO $pdo, $table, $column, $definition) {
    try {
        $pdo->query("SELECT `$column` FROM `$table` LIMIT 1");
    } catch (Throwable $e) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
