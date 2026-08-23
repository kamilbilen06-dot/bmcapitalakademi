<?php
/**
 * Kaba brute-force koruması — IP + hesap anahtarı, dosya sayacı.
 * 8 deneme / 15 dakika. Başarılı giriş sayacı sıfırlar.
 */
function login_guard_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'login_guard';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function login_guard_client_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?: '0.0.0.0';
}

function login_guard_file(string $bucket, string $identity): string {
    $key = strtolower(trim($bucket) . '|' . login_guard_client_ip() . '|' . strtolower(trim($identity)));
    return login_guard_dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
}

/**
 * @return array{ok:bool, error:string, wait:int}
 */
function login_guard_check(string $bucket, string $identity): array {
    $path = login_guard_file($bucket, $identity);
    if (!is_file($path)) {
        return ['ok' => true, 'error' => '', 'wait' => 0];
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['ok' => true, 'error' => '', 'wait' => 0];
    }
    $until = (int)($data['until'] ?? 0);
    $n = (int)($data['n'] ?? 0);
    if ($until < time()) {
        @unlink($path);
        return ['ok' => true, 'error' => '', 'wait' => 0];
    }
    if ($n >= 8) {
        $wait = max(1, (int)ceil(($until - time()) / 60));
        return [
            'ok' => false,
            'error' => 'Çok fazla deneme. Lütfen ' . $wait . ' dakika sonra tekrar deneyin.',
            'wait' => $wait,
        ];
    }
    return ['ok' => true, 'error' => '', 'wait' => 0];
}

function login_guard_fail(string $bucket, string $identity): void {
    $path = login_guard_file($bucket, $identity);
    $n = 0;
    $until = time() + 900;
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && (int)($data['until'] ?? 0) > time()) {
            $n = (int)($data['n'] ?? 0);
            $until = (int)$data['until'];
        }
    }
    $n++;
    @file_put_contents($path, json_encode(['n' => $n, 'until' => $until]), LOCK_EX);
}

function login_guard_clear(string $bucket, string $identity): void {
    $path = login_guard_file($bucket, $identity);
    if (is_file($path)) {
        @unlink($path);
    }
}
