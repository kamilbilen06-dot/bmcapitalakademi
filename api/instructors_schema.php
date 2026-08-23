<?php
/**
 * Eğitmen profilleri tablosu — admin ve public API ilk çağrıda oluşturur.
 */
function instructors_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    require_once __DIR__ . '/feature_flags.php';

    $pdo->exec("CREATE TABLE IF NOT EXISTS instructors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        photo_path VARCHAR(255) NOT NULL DEFAULT '',
        bio MEDIUMTEXT,
        socials LONGTEXT,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM instructors")->fetchColumn();
    if ($cnt === 0) {
        $socials = json_encode([
            ['platform' => 'linkedin', 'url' => ''],
            ['platform' => 'x', 'url' => ''],
        ], JSON_UNESCAPED_UNICODE);
        $bio = 'Teknik Analiz, Takas Analizi, AKD Analizi ve Algoritmik Trade konularında uygulamalı eğitimler verir. SPL Düzey 3 ve Türev Piyasaları Lisansı sahibidir; sermaye piyasalarında uzun yıllara dayanan kurumsal deneyime sahiptir.';
        $pdo->prepare(
            "INSERT INTO instructors (slug, name, title, photo_path, bio, socials, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,1)"
        )->execute([
            'kamil-bilen',
            'Dr. Kamil BİLEN',
            'Analiz & Yatırım Eğitmeni · SPL Düzey 3 & Türev',
            '',
            $bio,
            $socials,
            0,
        ]);
    }

    require_once __DIR__ . '/egitmen_schema.php';
    egitmen_add_column_if_missing(
        $pdo,
        'instructors',
        'email',
        "VARCHAR(255) NOT NULL DEFAULT '' AFTER name"
    );
    egitmen_add_column_if_missing(
        $pdo,
        'instructors',
        'share_pct',
        'DECIMAL(5,2) NULL DEFAULT NULL AFTER is_active'
    );

    feature_sync_mete_instructor($pdo);
}

function instructor_decode_socials($raw) {
    if (is_array($raw)) return $raw;
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function instructor_to_public(array $r) {
    $socials = array_values(array_filter(instructor_decode_socials($r['socials'] ?? '[]'), function ($s) {
        return is_array($s) && trim((string)($s['url'] ?? '')) !== '';
    }));
    return [
        'id' => $r['slug'],
        'name' => $r['name'],
        'title' => $r['title'] ?? '',
        'photo' => $r['photo_path'] ?? '',
        'bio' => $r['bio'] ?? '',
        'socials' => array_map(function ($s) {
            return [
                'platform' => clean($s['platform'] ?? 'link'),
                'url' => clean($s['url'] ?? ''),
            ];
        }, $socials),
    ];
}
