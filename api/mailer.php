<?php
/**
 * SMTP e-posta gönderimi (Composer/PHPMailer yok).
 *
 * Öncelik: api/mail_config.local.php sabitleri → settings tablosu.
 * Gönderim başarısız olsa bile kayıt/ödeme akışı durmaz.
 */
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/site_brand.php';

function mailer_finish_response(): void {
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
        return;
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
}

function mailer_queue_dir(): string {
    $d = dirname(__DIR__) . '/uploads/mail-outbox';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    return $d;
}

function mailer_worker_key(): string {
    $c = mailer_config();
    $seed = ($c['pass'] !== '' ? $c['pass'] : $c['from']) . '|bmcap-mail';
    return substr(hash('sha256', $seed), 0, 32);
}

function mailer_queue_put(string $to, string $subject, string $html, string $text = ''): string {
    $id = bin2hex(random_bytes(12));
    $path = mailer_queue_dir() . '/' . $id . '.json';
    $ok = @file_put_contents($path, json_encode([
        'id' => $id,
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'created' => time(),
        'tries' => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $ok ? $id : '';
}

function mailer_disabled_functions(): array {
    return array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
}

function mailer_spawn_cli(string $jobId): void {
    $script = __DIR__ . '/mail_worker.php';
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $disabled = mailer_disabled_functions();
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        if (in_array('popen', $disabled, true)) {
            return;
        }
        $cmd = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId);
        @pclose(@popen($cmd, 'r'));
        return;
    }
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId) . ' >/dev/null 2>&1 &';
    if (!in_array('exec', $disabled, true)) {
        @exec($cmd);
        return;
    }
    if (!in_array('shell_exec', $disabled, true)) {
        @shell_exec($cmd);
    }
}

function mailer_http_kick(string $jobId): void {
    if (PHP_SAPI === 'cli' || defined('MAILER_WORKER')) {
        return;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $path = '/api/mail_worker.php?job=' . rawurlencode($jobId) . '&k=' . rawurlencode(mailer_worker_key());
    $port = $https ? 443 : (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if ($port <= 0) {
        $port = $https ? 443 : 80;
    }
    $remote = ($https ? 'ssl://' : 'tcp://') . '127.0.0.1:' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 1.2, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        $remote = ($https ? 'ssl://' : 'tcp://') . preg_replace('/:\\d+$/', '', $host) . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 1.2, STREAM_CLIENT_CONNECT, $ctx);
    }
    if (!$fp) {
        return;
    }
    stream_set_timeout($fp, 1);
    @fwrite($fp, "GET {$path} HTTP/1.1\r\nHost: {$host}\r\nConnection: Close\r\n\r\n");
    @fclose($fp);
}

function mailer_queue_flush(?string $onlyId = null): void {
    $dir = mailer_queue_dir();
    $files = [];
    if ($onlyId !== null && $onlyId !== '') {
        $files[] = $dir . '/' . preg_replace('/[^a-f0-9]/', '', $onlyId) . '.json';
    } else {
        $files = glob($dir . '/*.json') ?: [];
    }
    foreach ($files as $path) {
        if (!is_file($path)) {
            continue;
        }
        $fp = @fopen($path, 'c+');
        if (!$fp) {
            continue;
        }
        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            continue;
        }
        $raw = stream_get_contents($fp);
        $job = json_decode((string) $raw, true);
        if (!is_array($job) || empty($job['to'])) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            continue;
        }
        $tries = (int) ($job['tries'] ?? 0);
        if ($tries >= 6) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            @rename($path, $path . '.fail');
            continue;
        }
        $job['tries'] = $tries + 1;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        $res = mailer_send((string) $job['to'], (string) $job['subject'], (string) $job['html'], (string) ($job['text'] ?? ''));
        @flock($fp, LOCK_UN);
        fclose($fp);
        if (!empty($res['ok'])) {
            @unlink($path);
        }
    }
}

/** Sayfa bitti; SMTP kullanıcı beklemeden gider. */
function mailer_after_page(string $jobId): void {
    if ($jobId === '') {
        return;
    }
    ignore_user_abort(true);
    @set_time_limit(120);
    mailer_spawn_cli($jobId);
    mailer_finish_response();
    mailer_http_kick($jobId);
    mailer_queue_flush($jobId);
}

/**
 * @return array{host:string,port:int,secure:string,user:string,pass:string,from:string,from_name:string}
 */
function mailer_config(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $fromName = defined('BRAND_NAME') ? BRAND_NAME : 'BM Capital Akademi';
    $cfg = [
        'host' => defined('SMTP_HOST') ? (string)SMTP_HOST : '',
        'port' => defined('SMTP_PORT') ? (int)SMTP_PORT : 587,
        'secure' => defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'tls',
        'user' => defined('SMTP_USER') ? (string)SMTP_USER : '',
        'pass' => defined('SMTP_PASS') ? (string)SMTP_PASS : '',
        'from' => defined('SMTP_FROM') ? (string)SMTP_FROM : '',
        'from_name' => defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : $fromName,
    ];

    try {
        if (function_exists('db')) {
            $pdo = db();
            $rows = $pdo->query("SELECT k, v FROM settings WHERE k LIKE 'smtp_%'");
            if ($rows) {
                $s = [];
                foreach ($rows as $r) {
                    $s[$r['k']] = (string)$r['v'];
                }
                if ($cfg['host'] === '' && !empty($s['smtp_host'])) {
                    $cfg['host'] = trim($s['smtp_host']);
                }
                if (!defined('SMTP_PORT') && !empty($s['smtp_port'])) {
                    $cfg['port'] = (int)$s['smtp_port'];
                }
                if (!defined('SMTP_SECURE') && !empty($s['smtp_secure'])) {
                    $cfg['secure'] = strtolower(trim($s['smtp_secure']));
                }
                if ($cfg['user'] === '' && isset($s['smtp_user'])) {
                    $cfg['user'] = trim($s['smtp_user']);
                }
                if ($cfg['pass'] === '' && isset($s['smtp_pass'])) {
                    $cfg['pass'] = (string)$s['smtp_pass'];
                }
                if ($cfg['from'] === '' && !empty($s['smtp_from'])) {
                    $cfg['from'] = trim($s['smtp_from']);
                }
                if (!defined('SMTP_FROM_NAME') && !empty($s['smtp_from_name'])) {
                    $cfg['from_name'] = trim($s['smtp_from_name']);
                }
            }
        }
    } catch (Throwable $e) {
        // Ayar tablosu yoksa local dosya yeter.
    }

    if ($cfg['from'] === '' && $cfg['user'] !== '' && filter_var($cfg['user'], FILTER_VALIDATE_EMAIL)) {
        $cfg['from'] = $cfg['user'];
    }
    if ($cfg['from_name'] === '') {
        $cfg['from_name'] = $fromName;
    }
    if (!in_array($cfg['secure'], ['tls', 'ssl', 'none'], true)) {
        $cfg['secure'] = 'tls';
    }
    if ($cfg['port'] <= 0) {
        $cfg['port'] = $cfg['secure'] === 'ssl' ? 465 : 587;
    }

    $cached = $cfg;
    return $cached;
}

/** Satış / abonelik bildirimlerinin gideceği akademi kutusu */
function mailer_ops_inbox(): string {
    if (defined('SMTP_NOTIFY')) {
        $n = trim((string)SMTP_NOTIFY);
        if (filter_var($n, FILTER_VALIDATE_EMAIL)) {
            return $n;
        }
    }
    try {
        if (function_exists('db')) {
            $st = db()->query("SELECT v FROM settings WHERE k = 'smtp_notify' LIMIT 1");
            $v = trim((string)($st ? $st->fetchColumn() : ''));
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return $v;
            }
        }
    } catch (Throwable $e) {
        // yoksa varsayılan
    }
    $from = defined('SMTP_FROM') ? trim((string)SMTP_FROM) : '';
    if (filter_var($from, FILTER_VALIDATE_EMAIL) && str_contains(strtolower($from), 'bmcapitalakademi')) {
        return $from;
    }
    return 'bmcapitalakademi@gmail.com';
}

function mailer_is_configured(): bool {
    $c = mailer_config();
    return $c['host'] !== '' && $c['from'] !== '';
}

function mailer_encode_header(string $text): string {
    $text = str_replace(["\r", "\n"], '', $text);
    if ($text === '' || preg_match('/^[\x20-\x7E]+$/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/** SMTP DATA için CRLF. str_replace(\\r\\n / \\r / \\n) sırası her satırın arasına boş satır sokar. */
function mailer_crlf(string $raw): string {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    return str_replace("\n", "\r\n", $raw);
}

/**
 * @return array{ok:bool, error:string}
 */
function mailer_send(string $to, string $subject, string $html, string $text = ''): array {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Geçersiz alıcı'];
    }
    if (!mailer_is_configured()) {
        return ['ok' => false, 'error' => 'SMTP yapılandırılmadı'];
    }

    $c = mailer_config();
    $html = (string)$html;
    if ($text === '') {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    }

    $boundary = 'b' . bin2hex(random_bytes(8));
    $fromHdr = mailer_encode_header($c['from_name']) . ' <' . $c['from'] . '>';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromHdr,
        'Reply-To: ' . $c['from'],
        'To: ' . $to,
        'Subject: ' . mailer_encode_header($subject),
        'MIME-Version: 1.0',
        'List-Unsubscribe: <mailto:' . $c['from'] . '?subject=unsubscribe>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body =
        '--' . $boundary . "\n" .
        "Content-Type: text/plain; charset=UTF-8\n" .
        "Content-Transfer-Encoding: quoted-printable\n\n" .
        quoted_printable_encode($text) . "\n" .
        '--' . $boundary . "\n" .
        "Content-Type: text/html; charset=UTF-8\n" .
        "Content-Transfer-Encoding: quoted-printable\n\n" .
        quoted_printable_encode($html) . "\n" .
        '--' . $boundary . "--\n";
    $raw = implode("\n", $headers) . "\n\n" . $body;

    try {
        mailer_smtp_send($c, $to, $raw);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        error_log('mailer smtp: ' . $e->getMessage());
    }
    $alt = $c;
    $alt['port'] = 465;
    $alt['secure'] = 'ssl';
    if ((int) $c['port'] !== 465) {
        try {
            mailer_smtp_send($alt, $to, $raw);
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            error_log('mailer smtp 465: ' . $e->getMessage());
        }
    }
    if (mailer_php_mail($to, $subject, $html, $c)) {
        return ['ok' => true, 'error' => ''];
    }
    return ['ok' => false, 'error' => 'E-posta gönderilemedi'];
}

function mailer_php_mail(string $to, string $subject, string $html, array $c): bool {
    $from = $c['from'];
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mailer_encode_header($c['from_name']) . ' <' . $from . '>',
        'Reply-To: ' . $from,
    ]);
    return @mail($to, mailer_encode_header($subject), $html, $headers);
}

function mailer_smtp_send(array $c, string $to, string $raw): void {
    $host = $c['host'];
    $port = (int)$c['port'];
    $secure = $c['secure'];
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $ca = dirname(__DIR__) . '/.tools/php/extras/ssl/cacert.pem';
    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
    ];
    if (is_file($ca)) {
        $ssl['cafile'] = $ca;
    } else {
        $iniCa = trim((string) ini_get('openssl.cafile'));
        if ($iniCa !== '' && is_file($iniCa)) {
            $ssl['cafile'] = $iniCa;
        }
    }
    $ctx = stream_context_create(['ssl' => $ssl]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException('SMTP bağlantısı kurulamadı: ' . $errstr);
    }
    stream_set_timeout($fp, 12);
    stream_set_write_buffer($fp, 0);

    try {
        mailer_smtp_expect($fp, 220);
        $ehloHost = preg_replace('/^.*@/', '', $c['from']) ?: 'localhost';
        mailer_smtp_cmd($fp, 'EHLO ' . $ehloHost, 250);

        if ($secure === 'tls') {
            mailer_smtp_cmd($fp, 'STARTTLS', 220);
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                stream_context_set_option($fp, 'ssl', 'verify_peer', false);
                stream_context_set_option($fp, 'ssl', 'verify_peer_name', false);
                $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            if ($crypto !== true) {
                throw new RuntimeException('STARTTLS başarısız');
            }
            mailer_smtp_cmd($fp, 'EHLO ' . $ehloHost, 250);
        }

        if ($c['user'] !== '') {
            mailer_smtp_cmd($fp, 'AUTH LOGIN', 334);
            mailer_smtp_cmd($fp, base64_encode($c['user']), 334);
            mailer_smtp_cmd($fp, base64_encode($c['pass']), 235);
        }

        mailer_smtp_cmd($fp, 'MAIL FROM:<' . $c['from'] . '>', 250);
        mailer_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        mailer_smtp_cmd($fp, 'DATA', 354);
        mailer_smtp_data($fp, $raw);
        mailer_smtp_expect($fp, 250);
        mailer_smtp_cmd($fp, 'QUIT', [221, 250]);
    } finally {
        fclose($fp);
    }
}

function mailer_smtp_data($fp, string $raw): void {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    foreach ($lines as $line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
        fwrite($fp, $line . "\r\n");
    }
    fwrite($fp, ".\r\n");
}

function mailer_smtp_cmd($fp, string $cmd, $expect): string {
    fwrite($fp, $cmd . "\r\n");
    return mailer_smtp_expect($fp, $expect);
}

function mailer_smtp_expect($fp, $expect): string {
    $data = '';
    while (($line = fgets($fp, 2048)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int)substr($data, 0, 3);
    $ok = is_array($expect) ? in_array($code, $expect, true) : ($code === (int)$expect);
    if (!$ok) {
        throw new RuntimeException('SMTP ' . (is_array($expect) ? implode('/', $expect) : $expect) . ' beklenirken ' . $code);
    }
    return $data;
}

function mailer_e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function mailer_layout(string $title, string $innerHtml): string {
    $brand = mailer_e(defined('BRAND_NAME') ? BRAND_NAME : 'BM Capital Akademi');
    $year = date('Y');
    return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . mailer_e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#2b3440;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:28px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e9ee;">'
        . '<tr><td style="background:#1f2d3d;padding:18px 28px;font-size:15px;font-weight:700;color:#f39c12;letter-spacing:.3px;">' . $brand . '</td></tr>'
        . '<tr><td style="padding:36px 32px 28px;">' . $innerHtml . '</td></tr>'
        . '<tr><td style="padding:0 32px 28px;font-size:12px;color:#8a93a0;line-height:1.6;">Bu e-posta ' . $brand . ' hesabınızla ilgili otomatik bir bildirimdir. © ' . $year . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mailer_button(string $href, string $label): string {
    $href = mailer_e($href);
    $label = mailer_e($label);
    return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:8px 0 18px;"><tr><td align="center">'
        . '<a href="' . $href . '" style="display:inline-block;background:#1f2d3d;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:10px;">'
        . $label . '</a></td></tr></table>';
}

/** Gmail spam filtresi 127.0.0.1 / localhost linklerini oltalama sayar. */
function mailer_url_is_safe(string $url): bool {
    $p = parse_url($url);
    $scheme = strtolower((string)($p['scheme'] ?? ''));
    $host = strtolower((string)($p['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return false;
    }
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }
    if (preg_match('/\.(local|test|localhost)$/', $host)) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ok = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return (bool)$ok;
    }
    return true;
}

function mailer_cta(string $url, string $label): string {
    if (!mailer_url_is_safe($url)) {
        return '';
    }
    return mailer_button($url, $label)
        . '<p style="margin:0 0 8px;font-size:13px;color:#8a93a0;">Tıklamakta zorluk yaşıyorsanız aşağıdaki bağlantıyı deneyebilirsiniz.</p>'
        . '<p style="margin:0;font-size:13px;"><a href="' . mailer_e($url) . '" style="color:#2563eb;text-decoration:underline;">' . mailer_e($label) . '</a></p>';
}

function mailer_send_verify(array $student, string $code, string $link): array {
    $brand = defined('BRAND_SHORT') ? BRAND_SHORT : 'BM Capital';
    $name = trim((string)($student['name'] ?? ''));
    $hello = $name !== '' ? mailer_e($name) : '';
    $cta = mailer_cta($link, 'E-Posta Adresini Doğrula');
    $inner = '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#111;">' . mailer_e($brand) . '\'e Hoşgeldin!</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5b6572;">Hesabını açmak için aşağıdaki 6 haneli kodu doğrulama sayfasına yazman yeterli.</p>'
        . '<p style="margin:0 0 22px;text-align:center;font-size:32px;font-weight:800;letter-spacing:10px;color:#1f2d3d;">' . mailer_e($code) . '</p>';
    if ($cta !== '') {
        $inner .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.7;color:#5b6572;">İstersen bağlantıya tıklayarak da doğrulayabilirsin.</p>' . $cta;
    } else {
        $inner .= '<p style="margin:0;font-size:13px;color:#8a93a0;">Kodu sitedeki e-posta doğrulama ekranına gir.</p>';
    }
    if ($hello !== '') {
        $inner = '<p style="margin:0 0 8px;font-size:14px;color:#5b6572;">Merhaba ' . $hello . ',</p>' . $inner;
    }
    $html = mailer_layout($brand . '\'e Hoşgeldin', $inner);
    $text = 'Doğrulama kodun: ' . $code;
    if (mailer_url_is_safe($link)) {
        $text .= "\n\n" . $link;
    }
    return mailer_send((string)$student['email'], 'Doğrulama kodun: ' . $code, $html, $text);
}

function mailer_instructor_payload(array $instructor, string $link, string $purpose = 'invite'): array {
    $brand = defined('BRAND_NAME') ? BRAND_NAME : 'BM Capital Akademi';
    $name = trim((string)($instructor['name'] ?? ''));
    $hello = $name !== '' ? mailer_e($name) : '';
    $isReset = $purpose === 'reset';
    $title = $isReset ? 'Şifreni sıfırla' : 'Eğitmen paneline davet';
    $ctaLabel = $isReset ? 'Şifreyi Belirle' : 'Şifreni Belirle ve Gir';
    $lead = $isReset
        ? 'Eğitmen paneli şifren için bir sıfırlama talebi aldık. Bu isteği sen yapmadıysan e-postayı yok sayabilirsin.'
        : 'Senin için bir eğitmen hesabı açıldı. Aşağıdaki bağlantıdan şifreni kendin belirle; ardından panele girebilirsin.';
    $mailLink = $link;
    if (!mailer_url_is_safe($mailLink) && function_exists('site_mail_public_url')) {
        $path = (string)(parse_url($link, PHP_URL_PATH) ?? '/egitmen/sifre-belirle.php');
        $query = (string)(parse_url($link, PHP_URL_QUERY) ?? '');
        $mailLink = rtrim(site_mail_public_url(), '/') . $path . ($query !== '' ? '?' . $query : '');
    }
    $cta = mailer_cta($mailLink, $ctaLabel);
    $inner = '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#111;">' . mailer_e($title) . '</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5b6572;">' . mailer_e($lead) . '</p>';
    if ($cta !== '') {
        $inner .= $cta;
    } else {
        $inner .= mailer_button($mailLink, $ctaLabel)
            . '<p style="margin:0 0 8px;font-size:13px;"><a href="' . mailer_e($mailLink) . '" style="color:#2563eb;text-decoration:underline;">' . mailer_e($mailLink) . '</a></p>';
    }
    $inner .= '<p style="margin:18px 0 0;font-size:13px;color:#8a93a0;">Giriş: '
        . mailer_e(rtrim(function_exists('site_mail_public_url') ? site_mail_public_url() : 'https://www.bmcapitalakademi.com', '/') . '/egitmen/login.php')
        . '</p>';
    if ($hello !== '') {
        $inner = '<p style="margin:0 0 8px;font-size:14px;color:#5b6572;">Merhaba ' . $hello . ',</p>' . $inner;
    }
    $html = mailer_layout($title, $inner);
    $text = ($isReset ? 'Şifre sıfırlama' : 'Eğitmen paneli daveti') . ":\n" . $mailLink;
    $email = (string)($instructor['email'] ?? '');
    return ['to' => $email, 'subject' => $brand . ' · ' . $title, 'html' => $html, 'text' => $text];
}

function mailer_send_instructor_invite(array $instructor, string $link, string $purpose = 'invite'): array {
    $p = mailer_instructor_payload($instructor, $link, $purpose);
    return mailer_send($p['to'], $p['subject'], $p['html'], $p['text']);
}

function mailer_queue_instructor_invite(array $instructor, string $link, string $purpose = 'invite'): string {
    $p = mailer_instructor_payload($instructor, $link, $purpose);
    return mailer_queue_put($p['to'], $p['subject'], $p['html'], $p['text']);
}

function mailer_reset_payload(array $student, string $link): array {
    $brand = defined('BRAND_NAME') ? BRAND_NAME : 'BM Capital Akademi';
    $mailLink = $link;
    if (!mailer_url_is_safe($mailLink) && function_exists('site_mail_public_url')) {
        $path = (string) (parse_url($link, PHP_URL_PATH) ?: '/ogrenci/sifre-sifirla.php');
        $query = (string) (parse_url($link, PHP_URL_QUERY) ?? '');
        $mailLink = rtrim(site_mail_public_url(), '/') . $path . ($query !== '' ? '?' . $query : '');
    }
    $cta = mailer_cta($mailLink, 'Şifreyi Sıfırla');
    $inner = '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#111;">Şifreni Sıfırla</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5b6572;">Hesabın için bir şifre sıfırlama talebi aldık. Bu isteği sen yapmadıysan e-postayı yok sayabilirsin.</p>';
    if ($cta !== '') {
        $inner .= $cta;
    } else {
        $inner .= mailer_button($mailLink, 'Şifreyi Sıfırla')
            . '<p style="margin:0 0 8px;font-size:13px;"><a href="' . mailer_e($mailLink) . '" style="color:#2563eb;text-decoration:underline;">' . mailer_e($mailLink) . '</a></p>';
    }
    $html = mailer_layout('Şifre sıfırlama', $inner);
    $text = "Şifre sıfırlama bağlantısı:\n" . $mailLink;
    return [
        'to' => (string)$student['email'],
        'subject' => $brand . ' şifre sıfırlama',
        'html' => $html,
        'text' => $text,
    ];
}

function mailer_send_reset(array $student, string $link): array {
    $p = mailer_reset_payload($student, $link);
    return mailer_send($p['to'], $p['subject'], $p['html'], $p['text']);
}

function mailer_queue_reset(array $student, string $link): string {
    $p = mailer_reset_payload($student, $link);
    return mailer_queue_put($p['to'], $p['subject'], $p['html'], $p['text']);
}

function mailer_notify_order_paid(PDO $pdo, array $order): void {
    $orderId = (int)($order['id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $lock = $pdo->prepare("UPDATE payment_orders SET mail_sent_at = NOW() WHERE id = ? AND mail_sent_at IS NULL");
    $lock->execute([$orderId]);
    if ($lock->rowCount() === 0) {
        return;
    }

    $courseTitle = '';
    try {
        $st = $pdo->prepare("SELECT title FROM courses WHERE id = ? LIMIT 1");
        $st->execute([(int)$order['course_id']]);
        $courseTitle = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $courseTitle = '';
    }
    if ($courseTitle === '') {
        $courseTitle = 'Eğitim';
    }

    $kurus = (int)($order['amount_kurus'] ?? 0);
    $amount = number_format($kurus / 100, 2, ',', '.') . ' TL';
    $name = trim((string)($order['student_name'] ?? ''));
    $email = (string)($order['student_email'] ?? '');
    $panel = rtrim(site_public_url(), '/') . '/ogrenci/index.php';
    $cta = mailer_cta($panel, 'Eğitime Git');

    $inner = '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111;">Ödemen alındı</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5b6572;">'
        . ($name !== '' ? 'Merhaba ' . mailer_e($name) . ', ' : '')
        . '<strong>' . mailer_e($courseTitle) . '</strong> eğitimine erişimin açıldı. Öğrenci panelinden izlemeye başlayabilirsin.</p>'
        . '<p style="margin:0 0 22px;font-size:14px;color:#5b6572;">Tutar: <strong>' . mailer_e($amount) . '</strong></p>'
        . $cta;
    $html = mailer_layout('Ödeme başarılı', $inner);
    mailer_send($email, 'Erişimin açıldı - ' . $courseTitle, $html);

    $notify = mailer_ops_inbox();
    $phone = trim((string)($order['student_phone'] ?? ''));
    if ($notify !== '' && strcasecmp($notify, $email) !== 0) {
        $adminInner = '<h1 style="margin:0 0 14px;font-size:22px;color:#111;">Yeni eğitim satışı</h1>'
            . '<p style="margin:0;font-size:15px;line-height:1.7;color:#5b6572;">'
            . mailer_e($name !== '' ? $name : $email) . '<br>'
            . mailer_e($email) . ($phone !== '' ? '<br>' . mailer_e($phone) : '') . '<br><br>'
            . mailer_e($courseTitle) . ' · ' . mailer_e($amount) . '</p>';
        mailer_send($notify, 'Yeni satış - ' . $courseTitle, mailer_layout('Yeni satış', $adminInner));
    }
}

function mailer_notify_subscription_new(array $row): void {
    $notify = mailer_ops_inbox();
    if ($notify === '') {
        return;
    }
    $name = trim((string)($row['student_name'] ?? ''));
    $email = trim((string)($row['student_email'] ?? ''));
    $phone = trim((string)($row['student_phone'] ?? ''));
    $kurus = (int)($row['amount_kurus'] ?? 0);
    $amount = number_format($kurus / 100, 2, ',', '.') . ' TL';
    $interval = ((string)($row['interval_unit'] ?? '') === 'DAILY') ? 'günlük' : 'aylık';
    $title = 'WhatsApp analiz grubu';
    try {
        if (function_exists('db') && function_exists('subscription_title')) {
            $title = subscription_title(db());
        }
    } catch (Throwable $e) {
        // varsayılan başlık
    }

    $inner = '<h1 style="margin:0 0 14px;font-size:22px;color:#111;">Yeni abonelik</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.7;color:#5b6572;">'
        . mailer_e($name !== '' ? $name : $email) . '<br>'
        . mailer_e($email) . ($phone !== '' ? '<br>' . mailer_e($phone) : '') . '<br><br>'
        . mailer_e($title) . ' · ' . mailer_e($amount) . ' / ' . mailer_e($interval) . '</p>';
    mailer_send($notify, 'Yeni abonelik - ' . $title, mailer_layout('Yeni abonelik', $inner));
}

function mailer_subscription_title(array $row): string {
    $title = 'WhatsApp analiz grubu';
    try {
        if (function_exists('db') && function_exists('subscription_title')) {
            $title = subscription_title(db());
        }
    } catch (Throwable $e) {
        // varsayılan
    }
    return $title;
}

function mailer_notify_subscription_student_new(array $row): void {
    $email = trim((string)($row['student_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $name = trim((string)($row['student_name'] ?? ''));
    $title = mailer_subscription_title($row);
    $panel = rtrim(site_public_url(), '/') . '/ogrenci/aboneliklerim.php';
    $inner = '<h1 style="margin:0 0 16px;font-size:24px;color:#111;">Aboneliğiniz başladı</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5b6572;">'
        . ($name !== '' ? 'Merhaba ' . mailer_e($name) . ', ' : '')
        . '<strong>' . mailer_e($title) . '</strong> üyeliğiniz aktif. Yönetici sizi WhatsApp grubuna elle ekleyecektir. İptal için öğrenci panelini kullanabilirsiniz.</p>'
        . mailer_cta($panel, 'Aboneliğim');
    mailer_send($email, 'Aboneliğiniz başladı - ' . $title, mailer_layout('Abonelik onayı', $inner));
}

function mailer_notify_subscription_cancelled(array $row): void {
    $email = trim((string)($row['student_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $name = trim((string)($row['student_name'] ?? ''));
    $title = mailer_subscription_title($row);
    $end = trim((string)($row['current_period_end'] ?? ''));
    $endLabel = $end !== '' ? date('d.m.Y H:i', strtotime($end)) : 'dönem sonu';
    $panel = rtrim(site_public_url(), '/') . '/ogrenci/aboneliklerim.php';
    $inner = '<h1 style="margin:0 0 16px;font-size:24px;color:#111;">Abonelik iptal edildi</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5b6572;">'
        . ($name !== '' ? 'Merhaba ' . mailer_e($name) . ', ' : '')
        . mailer_e($title) . ' aboneliğiniz iptal edildi. Üyeliğiniz <strong>' . mailer_e($endLabel) . '</strong> tarihine kadar açık kalır; sonraki kart çekimi yapılmaz.</p>'
        . mailer_cta($panel, 'Aboneliğim');
    mailer_send($email, 'Abonelik iptali - ' . $title, mailer_layout('Abonelik iptali', $inner));
}

function mailer_notify_subscription_past_due(array $row): void {
    $email = trim((string)($row['student_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $name = trim((string)($row['student_name'] ?? ''));
    $title = mailer_subscription_title($row);
    $panel = rtrim(site_public_url(), '/') . '/ogrenci/aboneliklerim.php';
    $inner = '<h1 style="margin:0 0 16px;font-size:24px;color:#111;">Kart çekimi başarısız</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5b6572;">'
        . ($name !== '' ? 'Merhaba ' . mailer_e($name) . ', ' : '')
        . mailer_e($title) . ' dönemsel ödemesi alınamadı. Kartınızı güncellemek veya iptal etmek için panele gidin. Birkaç deneme sonra üyelik kapanabilir.</p>'
        . mailer_cta($panel, 'Aboneliğim');
    mailer_send($email, 'Kart ödemesi alınamadı - ' . $title, mailer_layout('Ödeme alınamadı', $inner));
}

function mailer_notify_review_order(array $order): void {
    $notify = mailer_ops_inbox();
    if ($notify === '') {
        return;
    }
    $ref = (string)($order['merchant_oid'] ?? '');
    $email = (string)($order['student_email'] ?? '');
    $name = (string)($order['student_name'] ?? '');
    $msg = (string)($order['error_message'] ?? 'İnceleme');
    $inner = '<h1 style="margin:0 0 14px;font-size:22px;color:#111;">İnceleme siparişi</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.7;color:#5b6572;">Para çekilmiş olabilir; erişim açılmadı.</p>'
        . '<p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#5b6572;">'
        . mailer_e($ref) . '<br>'
        . mailer_e($name !== '' ? $name : $email) . '<br>'
        . mailer_e($email) . '<br>'
        . mailer_e($msg) . '</p>';
    mailer_send($notify, 'İnceleme siparişi - ' . $ref, mailer_layout('İnceleme siparişi', $inner));
}

function mailer_notify_manual_access(string $email, string $name, string $courseTitle): void {
    if ($email === '') {
        return;
    }
    if ($courseTitle === '') {
        $courseTitle = 'Eğitim';
    }
    $panel = rtrim(site_public_url(), '/') . '/ogrenci/index.php';
    $inner = '<h1 style="margin:0 0 16px;font-size:24px;color:#111;">Erişimin açıldı</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5b6572;">'
        . ($name !== '' ? 'Merhaba ' . mailer_e($name) . ', ' : '')
        . '<strong>' . mailer_e($courseTitle) . '</strong> eğitimine erişimin yönetici tarafından açıldı.</p>'
        . mailer_cta($panel, 'Eğitime Git');
    mailer_send($email, 'Erişimin açıldı - ' . $courseTitle, mailer_layout('Erişim açıldı', $inner));
}
