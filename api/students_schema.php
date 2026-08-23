<?php
/**
 * Öğrenci hesabı tabloları — ilk çağrıda otomatik oluşur.
 *
 * students        : siteden kayıt olan öğrenciler (admin_users'dan tamamen ayrı)
 * student_tokens  : şifre sıfırlama / e-posta doğrulama jetonları (hash'li)
 *
 * course_enrollments tablosuna student_id eklenir; eski e-posta bazlı kayıtlar
 * hesap açıldığında otomatik bağlanır.
 */
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/paytr_schema.php';

function students_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    // courses / course_enrollments + payment_status, merchant_oid kolonları
    paytr_ensure_schema($pdo);
    instructors_ensure_schema($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL DEFAULT '',
        name VARCHAR(160) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
        email_verified_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        UNIQUE KEY uq_students_email (email),
        INDEX idx_students_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // KVKK açık rızası (pazarlama) — kayıt formundaki isteğe bağlı onay
    egitmen_add_column_if_missing(
        $pdo,
        'students',
        'marketing_consent',
        'TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
    );

    // Sosyal girişten gelen profil fotoğrafı
    egitmen_add_column_if_missing(
        $pdo,
        'students',
        'avatar_url',
        "VARCHAR(400) NOT NULL DEFAULT '' AFTER phone"
    );

    // Sosyal giriş kimlikleri (Google, ileride Apple vb.)
    // Bir hesap birden fazla sağlayıcıya bağlanabilir.
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_identities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        provider VARCHAR(20) NOT NULL,
        provider_uid VARCHAR(191) NOT NULL,
        email VARCHAR(190) NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        UNIQUE KEY uq_identity (provider, provider_uid),
        INDEX idx_identity_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        code_hash CHAR(64) NOT NULL DEFAULT '',
        purpose VARCHAR(20) NOT NULL DEFAULT 'reset',
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_token (token_hash),
        INDEX idx_student_purpose (student_id, purpose)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing(
        $pdo,
        'student_tokens',
        'code_hash',
        "CHAR(64) NOT NULL DEFAULT '' AFTER token_hash"
    );

    // Daha önce giriş yapmış hesapları doğrulanmış say (yeni kayıtlar last_login_at boş kalır).
    try {
        $pdo->exec(
            "UPDATE students
             SET email_verified_at = COALESCE(email_verified_at, created_at, NOW())
             WHERE email_verified_at IS NULL AND last_login_at IS NOT NULL"
        );
    } catch (Throwable $e) {
        // Kolon yoksa şema kurulumu zaten yukarıda ekler.
    }

    egitmen_add_column_if_missing(
        $pdo,
        'course_enrollments',
        'student_id',
        'INT NULL AFTER course_id'
    );
    students_add_index_if_missing($pdo, 'course_enrollments', 'idx_enroll_student', 'student_id');

    students_link_orphan_enrollments($pdo);
    progress_ensure_schema($pdo);
}

/** Ders izleme kaydı — kaldığın yer ve tamamlanma. */
function progress_ensure_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_lecture_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        lecture_id INT NOT NULL,
        course_id INT NOT NULL,
        position_sec INT NOT NULL DEFAULT 0,
        max_sec INT NOT NULL DEFAULT 0,
        duration_sec INT NOT NULL DEFAULT 0,
        completed_at DATETIME NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_lecture (student_id, lecture_id),
        INDEX idx_progress_course (student_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing(
        $pdo,
        'course_enrollments',
        'last_lecture_id',
        'INT NULL AFTER last_visit_at'
    );
    egitmen_add_column_if_missing(
        $pdo,
        'course_enrollments',
        'last_seconds',
        'INT NOT NULL DEFAULT 0 AFTER last_lecture_id'
    );
}

function students_add_index_if_missing(PDO $pdo, $table, $indexName, $columns) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics
                             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $st->execute([$table, $indexName]);
        if ((int)$st->fetchColumn() > 0) {
            return;
        }
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)");
    } catch (Throwable $e) {
        // İndeks kritik değil; şema kurulumunu bloklamasın.
    }
}

/**
 * Hesap açılmadan önce (havale/PayTR ile) oluşmuş kayıtları e-posta üzerinden
 * öğrenci hesabına bağla. Bağlanacak kayıt yoksa hiç UPDATE çalışmaz.
 */
function students_link_orphan_enrollments(PDO $pdo) {
    try {
        $pending = (int)$pdo->query(
            "SELECT COUNT(*) FROM course_enrollments
             WHERE (student_id IS NULL OR student_id = 0) AND student_email <> ''"
        )->fetchColumn();
        if ($pending === 0) {
            return;
        }
        $pdo->exec(
            "UPDATE course_enrollments e
             JOIN students s ON s.email = e.student_email
             SET e.student_id = s.id
             WHERE (e.student_id IS NULL OR e.student_id = 0)"
        );
    } catch (Throwable $e) {
        // Sessiz geç: eşleştirme opsiyonel bir iyileştirme.
    }
}
