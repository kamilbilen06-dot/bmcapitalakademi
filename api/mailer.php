<?php
/**
 * SMTP e-posta gönderimi (Composer/PHPMailer yok).
 *
 * Öncelik: api/mail_config.local.php sabitleri → settings tablosu.
 * Gönderim başarısız olsa bile kayıt/ödeme akışı durmaz.
 */
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/site_brand.php';

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
        return ['ok' => false, 'error' => 'E-posta gönderilemedi'];
    }
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
    }
    $ctx = stream_context_create(['ssl' => $ssl]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException('SMTP bağlantısı kurulamadı: ' . $errstr);
    }
    stream_set_timeout($fp, 15);
    stream_set_write_buffer($fp, 0);

    try {
        mailer_smtp_expect($fp, 220);
        $ehloHost = preg_replace('/^.*@/', '', $c['from']) ?: 'localhost';
        mailer_smtp_cmd($fp, 'EHLO ' . $ehloHost, 250);

        if ($secure === 'tls') {
            mailer_smtp_cmd($fp, 'STARTTLS', 220);
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
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

function mailer_send_instructor_invite(array $instructor, string $link, string $purpose = 'invite'): array {
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
    $inner .= '<p style="margin:18px 0 0;font-size:13px;color:#8a93a0;">Bağlantı ' . ($isReset ? '60 dakika' : '7 gün') . ' geçerlidir. Giriş: '
        . mailer_e(rtrim(function_exists('site_mail_public_url') ? site_mail_public_url() : 'https://www.bmcapitalakademi.com', '/') . '/egitmen/login.php')
        . '</p>';
    if ($hello !== '') {
        $inner = '<p style="margin:0 0 8px;font-size:14px;color:#5b6572;">Merhaba ' . $hello . ',</p>' . $inner;
    }
    $html = mailer_layout($title, $inner);
    $text = ($isReset ? 'Şifre sıfırlama' : 'Eğitmen paneli daveti') . ":\n" . $mailLink;
    $email = (string)($instructor['email'] ?? '');
    return mailer_send($email, $brand . ' · ' . $title, $html, $text);
}

function mailer_send_reset(array $student, string $link): array {
    $brand = defined('BRAND_NAME') ? BRAND_NAME : 'BM Capital Akademi';
    $cta = mailer_cta($link, 'Şifreyi Sıfırla');
    $inner = '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#111;">Şifreni Sıfırla</h1>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#5b6572;">Hesabın için bir şifre sıfırlama talebi aldık. Bu isteği sen yapmadıysan e-postayı yok sayabilirsin.</p>';
    if ($cta !== '') {
        $inner .= $cta;
    } else {
        $inner .= '<p style="margin:0;font-size:15px;line-height:1.7;color:#5b6572;">Sitedeki şifre sıfırlama ekranından işleme devam edebilirsin.</p>';
    }
    $html = mailer_layout('Şifre sıfırlama', $inner);
    $text = mailer_url_is_safe($link) ? ("Şifre sıfırlama bağlantısı:\n" . $link) : 'Sitedeki şifre sıfırlama ekranını kullanın.';
    return mailer_send((string)$student['email'], $brand . ' şifre sıfırlama', $html, $text);
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
