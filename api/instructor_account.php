<?php
/**
 * Eğitmen davet / şifre jetonları.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_brand.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/auth_schema.php';

const INSTRUCTOR_MIN_PASSWORD = 8;
const INSTRUCTOR_INVITE_TTL_MIN = 10080;
const INSTRUCTOR_RESET_TTL_MIN = 1440;

function instructor_tokens_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS instructor_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        purpose VARCHAR(20) NOT NULL DEFAULT 'invite',
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_it_user (user_id),
        INDEX idx_it_hash (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function instructor_normalize_email($email): string {
    $email = trim((string) $email);
    return function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email);
}

function instructor_has_usable_password(?array $row): bool {
    $hash = (string)($row['password_hash'] ?? '');
    return $hash !== '' && isset($hash[0]) && $hash[0] === '$';
}

function instructor_issue_token(PDO $pdo, int $userId, string $purpose, int $ttlMin): string {
    instructor_tokens_ensure($pdo);
    $raw = bin2hex(random_bytes(32));
    $pdo->prepare(
        'UPDATE instructor_tokens SET used_at = NOW()
         WHERE user_id = ? AND purpose = ? AND used_at IS NULL'
    )->execute([$userId, $purpose]);
    $pdo->prepare(
        'INSERT INTO instructor_tokens (user_id, token_hash, purpose, expires_at)
         VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    )->execute([$userId, hash('sha256', $raw), $purpose, $ttlMin]);
    return $raw;
}

function instructor_token_row(PDO $pdo, string $rawToken): ?array {
    $rawToken = trim($rawToken);
    if ($rawToken === '') {
        return null;
    }
    instructor_tokens_ensure($pdo);
    $st = $pdo->prepare(
        "SELECT id, user_id, purpose FROM instructor_tokens
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
           AND purpose IN ('invite','reset')
         LIMIT 1"
    );
    $st->execute([hash('sha256', $rawToken)]);
    $row = $st->fetch();
    return $row ?: null;
}

function instructor_consume_token(PDO $pdo, string $rawToken): int {
    $row = instructor_token_row($pdo, $rawToken);
    if (!$row) {
        return 0;
    }
    $pdo->prepare('UPDATE instructor_tokens SET used_at = NOW() WHERE id = ?')
        ->execute([(int)$row['id']]);
    return (int)$row['user_id'];
}

function instructor_invite_link(string $token): string {
    return rtrim(site_mail_public_url(), '/') . '/egitmen/sifre-belirle.php?token=' . rawurlencode($token);
}

function instructor_invite_local_link(string $token): string {
    return rtrim(site_public_url(), '/') . '/egitmen/sifre-belirle.php?token=' . rawurlencode($token);
}

function instructor_invite_is_local(string $link): bool {
    require_once __DIR__ . '/mailer.php';
    return !mailer_url_is_safe($link);
}

/**
 * Davet veya sıfırlama maili atar.
 *
 * @return array{ok:bool, link:string}
 */
function instructor_deliver_invite(PDO $pdo, int $userId, string $purpose = 'invite'): array {
    require_once __DIR__ . '/mailer.php';
    $ttl = $purpose === 'reset' ? INSTRUCTOR_RESET_TTL_MIN : INSTRUCTOR_INVITE_TTL_MIN;
    $st = $pdo->prepare(
        'SELECT u.id, u.username, i.name, i.email
         FROM admin_users u
         LEFT JOIN instructors i ON i.id = u.instructor_id
         WHERE u.id = ? LIMIT 1'
    );
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'link' => ''];
    }
    $token = instructor_issue_token($pdo, $userId, $purpose, $ttl);
    $link = instructor_invite_link($token);
    $local = instructor_invite_local_link($token);
    $email = instructor_normalize_email($row['email'] ?: $row['username']);
    $sent = ['ok' => false];
    if (mailer_is_configured()) {
        $sent = mailer_send_instructor_invite([
            'name' => (string)($row['name'] ?? ''),
            'email' => $email,
        ], $link, $purpose);
    }
    return [
        'ok' => !empty($sent['ok']),
        'link' => $link,
        'local_link' => instructor_invite_is_local($local) ? $local : '',
    ];
}
