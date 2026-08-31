<?php
/**
 * Admin API - yönetim paneli tüm CRUD işlemlerini buradan yapar. Oturum korumalı.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/auth_schema.php';
require_once __DIR__ . '/image_upload.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/instructor_account.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/student_account.php';
require_once __DIR__ . '/analytics.php';

require_site_admin();
$sessionAdminId = (int)($_SESSION['admin_id'] ?? 0);
$csrfToken = admin_csrf_token();
admin_csrf_protect();
session_write_close();

$action = $_GET['action'] ?? '';
$pdo = db();
egitmen_ensure_schema($pdo);
instructors_ensure_schema($pdo);
auth_ensure_schema($pdo);
payments_ensure_schema($pdo);
subscriptions_ensure_schema($pdo);
students_ensure_schema($pdo);

try {
    switch ($action) {

        // ---------- DASHBOARD ----------
        case 'stats': {
            $an = analytics_dashboard($pdo);
            $unread = (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE is_read = 0")->fetchColumn();
            $modCount = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
            $faqCount = (int)$pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
            $x = $an['cardsExtra'];

            json_out(['ok' => true, 'csrf' => $csrfToken, 'cards' => [
                'today' => $x['today'], 'uniqueToday' => $x['uniqueToday'], 'week' => $x['week'],
                'total' => $x['total'], 'unread' => $unread, 'modules' => $modCount, 'faqs' => $faqCount,
            ], 'series' => $an['series'], 'topPages' => $an['topPages'],
               'sources' => $an['sources'], 'cities' => $an['cities'], 'visitors' => $an['visitors']]);
        }

        case 'visitor_detail': {
            $id = (string)($_GET['id'] ?? '');
            $item = analytics_visitor_detail($pdo, $id);
            if (!$item) {
                json_out(['ok' => false, 'error' => 'Ziyaretçi bulunamadı'], 404);
            }
            json_out(['ok' => true, 'item' => $item]);
        }

        case 'csrf': {
            json_out(['ok' => true, 'csrf' => $csrfToken]);
        }

        // ---------- MODULES (eğitim / ürün) ----------
        case 'modules_list': {
            $rows = $pdo->query("SELECT id, type, slug, title, price, featured, sort_order, image FROM modules ORDER BY type, sort_order, id")->fetchAll();
            json_out(['ok' => true, 'items' => $rows]);
        }

        case 'module_get': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) json_out(['ok' => false, 'error' => 'Bulunamadı'], 404);
            $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
            json_out(['ok' => true, 'item' => $row]);
        }

        case 'module_save': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);

            $data = [
                'ozellikler' => lines_to_array($in['ozellikler'] ?? ''),
                'aciklama' => lines_to_array($in['aciklama'] ?? ''),
                'hediye' => lines_to_array($in['hediye'] ?? ''),
                'hediyeGorsel' => clean($in['hediyeGorsel'] ?? ''),
                'tarihler' => lines_to_array($in['tarihler'] ?? ''),
                'mufredat' => parse_mufredat($in['mufredat'] ?? ''),
            ];

            $slug = clean($in['slug'] ?? '');
            if ($slug === '') $slug = slugify($in['title'] ?? 'kayit');

            $fields = [
                'type' => in_array(($in['type'] ?? ''), ['egitim','urun']) ? $in['type'] : 'egitim',
                'slug' => $slug,
                'title' => clean($in['title'] ?? ''),
                'short_desc' => clean($in['short_desc'] ?? ''),
                'image' => clean($in['image'] ?? ''),
                'video' => clean($in['video'] ?? ''),
                'video_poster' => clean($in['video_poster'] ?? ''),
                'price' => clean($in['price'] ?? ''),
                'price_note' => clean($in['price_note'] ?? ''),
                'duration' => clean($in['duration'] ?? ''),
                'egitim_turu' => clean($in['egitim_turu'] ?? ''),
                'instructors' => clean($in['instructors'] ?? ''),
                'etiket' => clean($in['etiket'] ?? ''),
                'katilim_not' => clean($in['katilim_not'] ?? ''),
                'tarih_not' => clean($in['tarih_not'] ?? ''),
                'featured' => !empty($in['featured']) ? 1 : 0,
                'sort_order' => (int)($in['sort_order'] ?? 0),
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];

            if ($fields['title'] === '') json_out(['ok' => false, 'error' => 'Başlık gerekli'], 422);

            if ($id > 0) {
                $set = implode(', ', array_map(function ($k) { return "$k = :$k"; }, array_keys($fields)));
                $stmt = $pdo->prepare("UPDATE modules SET $set WHERE id = :id");
                $fields['id'] = $id;
                $stmt->execute($fields);
                json_out(['ok' => true, 'id' => $id]);
            } else {
                $cols = implode(', ', array_keys($fields));
                $ph = implode(', ', array_map(function ($k) { return ":$k"; }, array_keys($fields)));
                $stmt = $pdo->prepare("INSERT INTO modules ($cols) VALUES ($ph)");
                $stmt->execute($fields);
                json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            }
        }

        case 'module_delete': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $pdo->prepare("DELETE FROM modules WHERE id = ?")->execute([$id]);
            json_out(['ok' => true]);
        }

        // ---------- INSTRUCTORS ----------
        case 'instructors_list': {
            $defaultShare = instructor_share_pct($pdo);
            $rows = $pdo->query(
                "SELECT i.id, i.slug, i.name, i.email, i.title, i.photo_path, i.bio, i.socials, i.sort_order, i.is_active, i.share_pct, i.updated_at,
                        u.id AS user_id, u.username AS panel_username, u.password_hash, u.last_login_at,
                        (SELECT COUNT(*) FROM courses c WHERE c.instructor_id = i.id) AS course_count,
                        (SELECT COUNT(*) FROM courses c WHERE c.instructor_id = i.id AND c.status = 'published') AS published_count,
                        (SELECT COUNT(DISTINCT COALESCE(NULLIF(e.student_id, 0), e.student_email))
                           FROM course_enrollments e
                           INNER JOIN courses c ON c.id = e.course_id
                          WHERE c.instructor_id = i.id AND e.payment_status = 'paid') AS student_count,
                        (SELECT COALESCE(SUM(o.amount_kurus), 0)
                           FROM payment_orders o
                           INNER JOIN courses c ON c.id = o.course_id
                          WHERE c.instructor_id = i.id AND o.status = 'paid') AS sales_kurus
                 FROM instructors i
                 LEFT JOIN admin_users u ON u.instructor_id = i.id AND u.role = 'egitmen'
                 ORDER BY i.sort_order ASC, i.id ASC"
            )->fetchAll();
            foreach ($rows as &$r) {
                $r['socials'] = instructor_decode_socials($r['socials'] ?? '[]');
                $r['has_account'] = !empty($r['user_id']);
                $r['has_password'] = instructor_has_usable_password($r);
                unset($r['password_hash']);
                $r['course_count'] = (int)$r['course_count'];
                $r['published_count'] = (int)$r['published_count'];
                $r['student_count'] = (int)$r['student_count'];
                $r['sales_kurus'] = (int)$r['sales_kurus'];
                $r['share_pct'] = instructor_share_pct_for($pdo, (int)$r['id']);
                $r['earn_kurus'] = instructor_earn_kurus($r['sales_kurus'], (float)$r['share_pct']);
            }
            unset($r);
            json_out(['ok' => true, 'items' => $rows, 'share_pct' => $defaultShare]);
        }

        case 'instructor_watch': {
            $id = (int)($_GET['id'] ?? 0);
            $st = $pdo->prepare(
                "SELECT i.id, i.slug, i.name, i.title, i.photo_path, i.is_active,
                        u.username AS panel_username, u.last_login_at
                 FROM instructors i
                 LEFT JOIN admin_users u ON u.instructor_id = i.id AND u.role = 'egitmen'
                 WHERE i.id = ? LIMIT 1"
            );
            $st->execute([$id]);
            $ins = $st->fetch();
            if (!$ins) {
                json_out(['ok' => false, 'error' => 'Eğitmen bulunamadı'], 404);
            }
            $cst = $pdo->prepare(
                "SELECT c.id, c.title, c.status, c.price,
                        (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.payment_status = 'paid') AS student_count,
                        (SELECT COALESCE(SUM(o.amount_kurus), 0) FROM payment_orders o WHERE o.course_id = c.id AND o.status = 'paid') AS sales_kurus
                 FROM courses c
                 WHERE c.instructor_id = ?
                 ORDER BY c.id DESC"
            );
            $cst->execute([$id]);
            $courses = $cst->fetchAll();
            $sharePct = instructor_share_pct_for($pdo, $id);
            foreach ($courses as &$c) {
                $c['student_count'] = (int)$c['student_count'];
                $c['sales_kurus'] = (int)$c['sales_kurus'];
                $c['earn_kurus'] = instructor_earn_kurus($c['sales_kurus'], $sharePct);
            }
            unset($c);
            json_out(['ok' => true, 'instructor' => $ins, 'courses' => $courses, 'share_pct' => $sharePct]);
        }

        case 'instructor_get': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM instructors WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) json_out(['ok' => false, 'error' => 'Eğitmen bulunamadı'], 404);
            $row['socials'] = instructor_decode_socials($row['socials'] ?? '[]');
            $u = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE instructor_id = ? AND role = 'egitmen' LIMIT 1");
            $u->execute([$id]);
            $acc = $u->fetch();
            $row['panel_username'] = $acc ? $acc['username'] : '';
            $row['user_id'] = $acc ? (int)$acc['id'] : 0;
            $row['has_account'] = !!$acc;
            $row['has_password'] = $acc ? instructor_has_usable_password($acc) : false;
            $row['default_share_pct'] = instructor_share_pct($pdo);
            json_out(['ok' => true, 'item' => $row]);
        }

        case 'instructor_save': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $name = clean($in['name'] ?? '');
            if ($name === '') json_out(['ok' => false, 'error' => 'Ad soyad gerekli'], 422);

            $email = instructor_normalize_email($in['email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(['ok' => false, 'error' => 'Geçerli bir e-posta girin'], 422);
            }

            $shareIn = array_key_exists('share_pct', $in) ? $in['share_pct'] : '';
            $sharePct = null;
            if (trim((string)$shareIn) !== '') {
                $sharePct = instructor_share_pct_parse($shareIn);
                if ($sharePct === null) {
                    json_out(['ok' => false, 'error' => 'Eğitmen payı 0–100 arasında olmalı'], 422);
                }
            }

            $emailTaken = $pdo->prepare(
                "SELECT id FROM instructors WHERE email = ? AND email <> '' AND id <> ? LIMIT 1"
            );
            $emailTaken->execute([$email, $id]);
            if ($emailTaken->fetch()) {
                json_out(['ok' => false, 'error' => 'Bu e-posta başka bir eğitmende kayıtlı'], 422);
            }
            $userTaken = $pdo->prepare(
                "SELECT id, role, instructor_id FROM admin_users WHERE username = ? LIMIT 1"
            );
            $userTaken->execute([$email]);
            $userRow = $userTaken->fetch();
            if ($userRow) {
                $linked = (int)($userRow['instructor_id'] ?? 0);
                if ($userRow['role'] === 'admin' || ($linked > 0 && $linked !== $id)) {
                    json_out(['ok' => false, 'error' => 'Bu e-posta başka bir hesapta kullanılıyor'], 422);
                }
            }

            $slug = clean($in['slug'] ?? '');
            if ($slug === '') $slug = slugify($name);
            $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($slug));
            $slug = trim($slug, '-') ?: ('egitmen-' . time());
            $baseSlug = $slug;
            $n = 2;
            while (true) {
                $chk = $pdo->prepare("SELECT id FROM instructors WHERE slug = ? AND id <> ?");
                $chk->execute([$slug, $id]);
                if (!$chk->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $n;
                $n++;
            }

            $active = array_key_exists('is_active', $in) ? (!empty($in['is_active']) ? 1 : 0) : 1;

            if ($id > 0) {
                $st = $pdo->prepare("SELECT id FROM instructors WHERE id = ?");
                $st->execute([$id]);
                if (!$st->fetch()) {
                    json_out(['ok' => false, 'error' => 'Eğitmen bulunamadı'], 404);
                }
                $pdo->prepare(
                    "UPDATE instructors SET slug=?, name=?, email=?, share_pct=?, is_active=? WHERE id=?"
                )->execute([$slug, $name, $email, $sharePct, $active, $id]);

                $linked = $pdo->prepare(
                    "SELECT id FROM admin_users WHERE instructor_id = ? AND role = 'egitmen' LIMIT 1"
                );
                $linked->execute([$id]);
                $accId = (int)$linked->fetchColumn();
                if ($accId > 0) {
                    $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?")->execute([$email, $accId]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO admin_users (username, password_hash, role, instructor_id) VALUES (?, '*', 'egitmen', ?)"
                    )->execute([$email, $id]);
                    $accId = (int)$pdo->lastInsertId();
                    $invite = instructor_deliver_invite($pdo, $accId, 'invite');
                    json_out([
                        'ok' => true,
                        'id' => $id,
                        'slug' => $slug,
                        'mail_sent' => !empty($invite['ok']),
                        'invite_link' => $invite['local_link'] ?? '',
                    ]);
                }
                json_out(['ok' => true, 'id' => $id, 'slug' => $slug]);
            }

            $pdo->prepare(
                "INSERT INTO instructors (slug, name, email, title, photo_path, bio, socials, sort_order, is_active, share_pct)
                 VALUES (?,?,?, '', '', '', '[]', 0, 1, ?)"
            )->execute([$slug, $name, $email, $sharePct]);
            $id = (int)$pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO admin_users (username, password_hash, role, instructor_id) VALUES (?, '*', 'egitmen', ?)"
            )->execute([$email, $id]);
            $userId = (int)$pdo->lastInsertId();
            $invite = instructor_deliver_invite($pdo, $userId, 'invite');
            json_out([
                'ok' => true,
                'id' => $id,
                'slug' => $slug,
                'created' => true,
                'mail_sent' => !empty($invite['ok']),
                        'invite_link' => $invite['local_link'] ?? '',
            ]);
        }

        case 'instructor_invite': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $st = $pdo->prepare(
                "SELECT u.id FROM admin_users u WHERE u.instructor_id = ? AND u.role = 'egitmen' LIMIT 1"
            );
            $st->execute([$id]);
            $userId = (int)$st->fetchColumn();
            if ($userId <= 0) {
                json_out(['ok' => false, 'error' => 'Bu eğitmenin panel hesabı yok'], 404);
            }
            $invite = instructor_deliver_invite($pdo, $userId, 'invite');
            json_out([
                'ok' => true,
                'mail_sent' => !empty($invite['ok']),
                        'invite_link' => $invite['local_link'] ?? '',
            ]);
        }

        case 'instructor_save_bio': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) json_out(['ok' => false, 'error' => 'Eğitmen id gerekli'], 422);
            $bio = (string)($in['bio'] ?? '');
            $st = $pdo->prepare("SELECT id, slug, name FROM instructors WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) json_out(['ok' => false, 'error' => 'Eğitmen bulunamadı'], 404);
            $pdo->prepare("UPDATE instructors SET bio = ? WHERE id = ?")->execute([$bio, $id]);
            json_out([
                'ok' => true,
                'id' => $id,
                'slug' => $row['slug'],
                'profile_url' => '/egitmen-profil.html?id=' . rawurlencode($row['slug']),
            ]);
        }

        case 'instructor_delete': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) {
                json_out(['ok' => false, 'error' => 'Eğitmen id gerekli'], 422);
            }
            instructor_tokens_ensure($pdo);
            $uids = $pdo->prepare("SELECT id FROM admin_users WHERE instructor_id = ? AND role = 'egitmen'");
            $uids->execute([$id]);
            foreach ($uids->fetchAll() as $u) {
                $pdo->prepare("DELETE FROM instructor_tokens WHERE user_id = ?")->execute([(int)$u['id']]);
            }
            $pdo->prepare("DELETE FROM admin_users WHERE instructor_id = ? AND role = 'egitmen'")->execute([$id]);
            $pdo->prepare("UPDATE courses SET instructor_id = 0 WHERE instructor_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM instructors WHERE id = ?")->execute([$id]);
            json_out(['ok' => true]);
        }

        case 'instructor_upload': {
            $res = save_instructor_photo($_FILES['file'] ?? []);
            if (empty($res['ok'])) {
                json_out(['ok' => false, 'error' => $res['error'] ?? 'Yükleme hatası'], (int)($res['code'] ?? 400));
            }
            json_out(['ok' => true, 'path' => $res['path']]);
        }

        // ---------- FAQ ----------
        case 'faqs_list': {
            $rows = $pdo->query("SELECT * FROM faqs ORDER BY sort_order, id")->fetchAll();
            json_out(['ok' => true, 'items' => $rows]);
        }

        case 'faq_save': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $q = clean($in['question'] ?? '');
            $a = clean($in['answer'] ?? '');
            $so = (int)($in['sort_order'] ?? 0);
            if ($q === '' || $a === '') json_out(['ok' => false, 'error' => 'Soru ve cevap gerekli'], 422);
            if ($id > 0) {
                $pdo->prepare("UPDATE faqs SET question=?, answer=?, sort_order=? WHERE id=?")->execute([$q, $a, $so, $id]);
                json_out(['ok' => true, 'id' => $id]);
            } else {
                $pdo->prepare("INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)")->execute([$q, $a, $so]);
                json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            }
        }

        case 'faq_delete': {
            $in = body_json();
            $pdo->prepare("DELETE FROM faqs WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
            json_out(['ok' => true]);
        }

        // ---------- CONTACTS ----------
        case 'contacts_list': {
            $rows = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 500")->fetchAll();
            json_out(['ok' => true, 'items' => $rows]);
        }

        case 'contact_read': {
            $in = body_json();
            $pdo->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
            json_out(['ok' => true]);
        }

        case 'contact_delete': {
            $in = body_json();
            $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
            json_out(['ok' => true]);
        }

        // ---------- SETTINGS ----------
        case 'settings_get': {
            $s = [];
            foreach ($pdo->query("SELECT k, v FROM settings") as $r) { $s[$r['k']] = $r['v']; }
            $s['smtp_pass_set'] = !empty($s['smtp_pass']);
            $s['smtp_pass'] = '';
            $s['iyzico_running_test'] = iyzico_is_sandbox() ? '1' : '0';
            $s['iyzico_key_source'] = defined('IYZICO_KEY_SOURCE') ? IYZICO_KEY_SOURCE : 'none';
            $s['iyzico_ready'] = iyzico_ready() ? '1' : '0';
            if (!isset($s['instructor_share_pct']) || trim((string)$s['instructor_share_pct']) === '') {
                $s['instructor_share_pct'] = '60';
            }
            if (!isset($s['sub_enabled']) || trim((string)$s['sub_enabled']) === '') {
                $s['sub_enabled'] = '1';
            }
            if (!isset($s['sub_title']) || trim((string)$s['sub_title']) === '') {
                $s['sub_title'] = 'WhatsApp analiz grubu';
            }
            if (!isset($s['sub_price']) || trim((string)$s['sub_price']) === '') {
                $s['sub_price'] = '199';
            }
            if (!isset($s['sub_blurb'])) {
                $s['sub_blurb'] = 'Aylık WhatsApp analiz grubu üyeliği.';
            }
            $s['sub_interval'] = subscription_interval();
            $s['sub_interval_label'] = subscription_interval_label();
            if (!isset($s['sub_instructor_id'])) {
                $s['sub_instructor_id'] = '0';
            }
            foreach (['nav_hakkimizda', 'nav_sss', 'nav_iletisim', 'nav_araclar'] as $nk) {
                if (!isset($s[$nk]) || trim((string)$s[$nk]) === '') {
                    $s[$nk] = $nk === 'nav_araclar' ? '1' : '0';
                }
            }
            foreach (['feature_mete_akyol', 'feature_metematiksel_hediye'] as $fk) {
                if (!isset($s[$fk]) || trim((string)$s[$fk]) === '') {
                    $s[$fk] = '0';
                }
            }
            json_out(['ok' => true, 'settings' => $s, 'instructors' => admin_filter_instructors($pdo)]);
        }

        case 'settings_save': {
            $in = body_json();
            $allowed = ['marka','sehir','telefon','whatsapp','instagram','twitter','iban','banka','hesap_adi','emailjs_public','emailjs_service','emailjs_template','emailjs_to','smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','smtp_from','smtp_from_name','instructor_share_pct','sub_enabled','sub_title','sub_price','sub_blurb','sub_instructor_id','satici_unvan','satici_adres','satici_vergi','satici_mersis','nav_hakkimizda','nav_sss','nav_iletisim','nav_araclar','feature_mete_akyol','feature_metematiksel_hediye'];
            $stmt = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
            foreach ($allowed as $k) {
                if (!array_key_exists($k, $in)) {
                    continue;
                }
                if ($k === 'smtp_pass' && trim((string)$in[$k]) === '') {
                    continue;
                }
                if ($k === 'instructor_share_pct') {
                    $raw = str_replace(',', '.', trim((string)$in[$k]));
                    if ($raw === '') {
                        $raw = '60';
                    }
                    if (!is_numeric($raw)) {
                        json_out(['ok' => false, 'error' => 'Eğitmen payı sayı olmalı'], 422);
                    }
                    $n = (float)$raw;
                    if ($n < 0 || $n > 100) {
                        json_out(['ok' => false, 'error' => 'Eğitmen payı 0–100 arasında olmalı'], 422);
                    }
                    $store = abs($n - round($n)) < 0.001 ? (string)(int)round($n) : (string)round($n, 2);
                    $stmt->execute([$k, $store]);
                    continue;
                }
                if ($k === 'sub_enabled' || str_starts_with($k, 'nav_')) {
                    $stmt->execute([$k, trim((string)$in[$k]) === '1' ? '1' : '0']);
                    continue;
                }
                if (in_array($k, ['feature_mete_akyol', 'feature_metematiksel_hediye'], true)) {
                    $stmt->execute([$k, trim((string)$in[$k]) === '1' ? '1' : '0']);
                    continue;
                }
                if ($k === 'sub_price') {
                    $kurus = payments_amount_kurus($in[$k]);
                    if ($kurus < 100) {
                        json_out(['ok' => false, 'error' => 'Abonelik fiyatı en az 1 TL olmalı'], 422);
                    }
                    $tl = $kurus / 100;
                    $store = abs($tl - round($tl)) < 0.001 ? (string)(int)round($tl) : number_format($tl, 2, '.', '');
                    $stmt->execute([$k, $store]);
                    continue;
                }
                if ($k === 'sub_instructor_id') {
                    $stmt->execute([$k, (string)max(0, (int)$in[$k])]);
                    continue;
                }
                $stmt->execute([$k, clean($in[$k])]);
            }
            json_out(['ok' => true]);
        }

        case 'admin_grant_access': {
            $in = body_json();
            $courseId = (int)($in['course_id'] ?? 0);
            $email = student_normalize_email($in['email'] ?? '');
            $name = mb_substr(trim(clean($in['name'] ?? '')), 0, 160);
            $phone = mb_substr(trim(clean($in['phone'] ?? '')), 0, 40);
            if ($courseId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(['ok' => false, 'error' => 'Kurs ve geçerli e-posta gerekli'], 422);
            }
            $cst = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
            $cst->execute([$courseId]);
            $course = $cst->fetch();
            if (!$course) {
                json_out(['ok' => false, 'error' => 'Kurs bulunamadı'], 404);
            }
            $student = null;
            try {
                require_once __DIR__ . '/student_account.php';
                $student = student_find_by_email($pdo, $email);
            } catch (Throwable $e) {
                $student = null;
            }
            $studentId = $student ? (int)$student['id'] : null;
            if ($name === '' && $student) {
                $name = (string)$student['name'];
            }
            if ($phone === '' && $student) {
                $phone = (string)($student['phone'] ?? '');
            }
            if ($name === '') {
                $name = 'Öğrenci';
            }
            $order = [
                'id' => 0,
                'course_id' => $courseId,
                'student_id' => $studentId,
                'student_email' => $email,
                'student_name' => $name,
                'student_phone' => $phone,
                'merchant_oid' => 'MANUAL-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)),
            ];
            $enrollId = payments_grant_enrollment($pdo, $order, 'manual');
            try {
                require_once __DIR__ . '/mailer.php';
                mailer_notify_manual_access($email, $name, (string)$course['title']);
            } catch (Throwable $e) {
                error_log('elle erisim maili: ' . $e->getMessage());
            }
            json_out(['ok' => true, 'enrollment_id' => $enrollId]);
        }

        case 'admin_student_status': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $status = trim((string)($in['status'] ?? ''));
            if ($id <= 0 || !in_array($status, ['active', 'suspended'], true)) {
                json_out(['ok' => false, 'error' => 'Geçersiz istek'], 422);
            }
            $pdo->prepare("UPDATE students SET status = ? WHERE id = ?")->execute([$status, $id]);
            json_out(['ok' => true]);
        }

        /**
         * Ödemeler ekranı: kurs tahsilatları + abonelik çekimleri tek listede.
         *
         * Her satırda iyzico'da aranacak referans (`ref`) ve ödeme numarası
         * (`iyzicoPaymentId`) döner; İşlem Listesi'nde tarih filtresine
         * takılmadan arama yapılabilsin.
         */
        case 'payments_overview': {
            $q = trim((string)($_GET['q'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));
            $tur = trim((string)($_GET['tur'] ?? ''));
            $from = trim((string)($_GET['from'] ?? ''));
            $to = trim((string)($_GET['to'] ?? ''));
            if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $from = '';
            }
            if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $to = '';
            }
            $siteY = (int) date('Y');
            if ($from !== '' && (int) substr($from, 0, 4) !== $siteY) {
                $from = $siteY . substr($from, 4);
            }
            if ($to !== '' && (int) substr($to, 0, 4) !== $siteY) {
                $to = $siteY . substr($to, 4);
            }
            if ($from !== '' && $to !== '' && $from > $to) {
                [$from, $to] = [$to, $from];
            }
            $today = date('Y-m-d');
            if ($to !== '' && $to > $today) {
                $to = $today;
            }

            @set_time_limit(45);
            $spanFrom = $from !== '' ? $from : date('Y-m-d', strtotime('-7 days'));
            $spanTo = $today;
            if ($to !== '') {
                $spanTo = $to < $today ? $to : $today;
            }
            if (strtotime($spanTo . ' 12:00:00') - strtotime($spanFrom . ' 12:00:00') > 8 * 86400) {
                $spanFrom = date('Y-m-d', strtotime($spanTo . ' -7 days'));
            }
            $iyzIdx = [];
            try {
                subscription_dedupe_invoices_by_payment_id($pdo);
                subscription_sync_invoice_buyers($pdo, microtime(true) + 22.0);
                $iyzIdx = iyzico_tx_index_by_dates($spanFrom, $spanTo);
                subscription_import_iyzico_payments(
                    $pdo,
                    date('Y-m-d', strtotime('-3 days')),
                    $today,
                    microtime(true) + 3.0,
                    0
                );
                subscription_dedupe_invoices_by_payment_id($pdo);
                subscription_sync_invoice_buyers($pdo, microtime(true) + 4.0);
                if ($iyzIdx !== []) {
                    $tm = $pdo->prepare(
                        "UPDATE subscription_invoices SET created_at = ? WHERE provider_payment_id = ? AND created_at <> ?"
                    );
                    $rf = $pdo->prepare(
                        "UPDATE subscription_invoices SET status = 'refunded'
                         WHERE provider_payment_id = ? AND status = 'paid'"
                    );
                    foreach ($iyzIdx as $pid => $fact) {
                        $at = (string) ($fact['paidAt'] ?? '');
                        if ($at !== '') {
                            $tm->execute([$at, $pid, $at]);
                        }
                        if (!empty($fact['refunded'])) {
                            $rf->execute([$pid]);
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('odeme iyzico senkron: ' . $e->getMessage());
            }
            $like = '%' . $q . '%';
            $loadKurs = $tur !== 'abonelik';
            $loadSub = $tur !== 'kurs';

            $dateSql = static function (string $col) use ($from, $to): array {
                $sql = '';
                $params = [];
                if ($from !== '') {
                    $sql .= " AND DATE($col) >= ?";
                    $params[] = $from;
                }
                if ($to !== '') {
                    $sql .= " AND DATE($col) <= ?";
                    $params[] = $to;
                }
                return [$sql, $params];
            };

            $params = [];
            $where = "o.provider = 'iyzico'";
            if ($status !== '') {
                $where .= " AND o.status = ?";
                $params[] = $status;
            }
            if ($q !== '') {
                $where .= " AND (o.merchant_oid LIKE ? OR o.provider_payment_id LIKE ?"
                    . " OR o.student_email LIKE ? OR o.student_name LIKE ?)";
                array_push($params, $like, $like, $like, $like);
            }
            list($datePart, $dateParams) = $dateSql('COALESCE(o.paid_at, o.created_at)');
            $where .= $datePart;
            $params = array_merge($params, $dateParams);

            $items = [];
            if ($loadKurs) {
            $st = $pdo->prepare(
                "SELECT o.id, o.merchant_oid, o.status, o.amount_kurus, o.paid_price,
                        o.provider_payment_id, o.provider_transaction_id, o.error_message,
                        o.created_at, o.paid_at, o.student_name, o.student_email,
                        c.title AS course_title
                 FROM payment_orders o
                 LEFT JOIN courses c ON c.id = o.course_id
                 WHERE $where
                 ORDER BY COALESCE(o.paid_at, o.created_at) DESC, o.id DESC
                 LIMIT 300"
            );
            $st->execute($params);

            foreach ($st->fetchAll() as $o) {
                $payId = trim((string)$o['provider_payment_id']);
                $oid = (string)$o['merchant_oid'];
                $items[] = [
                    'tur' => 'Kurs',
                    'ref' => $payId !== '' ? $payId : $oid,
                    'aramaRef' => $payId !== '' ? $payId : $oid,
                    'iyzicoPaymentId' => $payId,
                    'student_name' => (string)$o['student_name'],
                    'student_email' => (string)$o['student_email'],
                    'aciklama' => (string)($o['course_title'] ?? ''),
                    'amount_kurus' => (int)$o['amount_kurus'],
                    'status' => (string)$o['status'],
                    'error_message' => (string)$o['error_message'],
                    'created_at' => (string)$o['created_at'],
                    'paid_at' => (string)($o['paid_at'] ?? ''),
                    'tahsilEdildi' => (string)$o['status'] === 'paid'
                        && $payId !== '',
                ];
            }
            }

            // Abonelik çekimleri — listede iyzico ödeme numarası gösterilir
            $sparams = [];
            $swhere = '1=1';
            if ($status !== '') {
                $swhere .= " AND i.status = ?";
                $sparams[] = $status;
            }
            if ($q !== '') {
                $swhere .= " AND (i.order_reference LIKE ? OR i.provider_payment_id LIKE ?"
                    . " OR i.iyzico_buyer_name LIKE ? OR i.iyzico_buyer_email LIKE ?"
                    . " OR s.conversation_id LIKE ? OR s.iyzico_subscription_ref LIKE ?"
                    . " OR s.student_email LIKE ? OR s.student_name LIKE ?)";
                array_push($sparams, $like, $like, $like, $like, $like, $like, $like, $like);
            }
            list($sDatePart, $sDateParams) = $dateSql('i.created_at');
            $swhere .= $sDatePart;
            $sparams = array_merge($sparams, $sDateParams);
            if ($loadSub) {
            try {
                $sst = $pdo->prepare(
                    "SELECT i.id, i.order_reference, i.provider_payment_id, i.amount_kurus, i.status, i.created_at,
                            i.iyzico_buyer_name, i.iyzico_buyer_email,
                            s.student_name, s.student_email, s.conversation_id, s.interval_unit,
                            s.iyzico_subscription_ref
                     FROM subscription_invoices i
                     LEFT JOIN subscriptions s ON s.id = i.subscription_id
                     WHERE $swhere
                     ORDER BY i.id DESC
                     LIMIT 300"
                );
                $sst->execute($sparams);
                $subRows = $sst->fetchAll();
                foreach ($subRows as $inv) {
                    $payId = iyzico_normalize_payment_id($inv['provider_payment_id'] ?? '');
                    $buyerName = trim((string) ($inv['iyzico_buyer_name'] ?? ''));
                    $buyerEmail = trim((string) ($inv['iyzico_buyer_email'] ?? ''));
                    $items[] = [
                        'tur' => 'Abonelik',
                        'ref' => $payId,
                        'aramaRef' => $payId,
                        'iyzicoPaymentId' => $payId,
                        'student_name' => $buyerName !== '' ? $buyerName : (string)($inv['student_name'] ?? ''),
                        'student_email' => $buyerEmail !== '' ? $buyerEmail : (string)($inv['student_email'] ?? ''),
                        'aciklama' => 'WhatsApp grubu ('
                            . ((string)($inv['interval_unit'] ?? '') === 'DAILY' ? 'günlük' : 'aylık') . ')',
                        'amount_kurus' => (int)$inv['amount_kurus'],
                        'status' => (string)$inv['status'],
                        'error_message' => '',
                        'created_at' => (string)$inv['created_at'],
                        'paid_at' => (string)$inv['status'] === 'paid' ? (string)$inv['created_at'] : '',
                        'tahsilEdildi' => (string)$inv['status'] === 'paid',
                    ];
                }
            } catch (Throwable $e) {
                // subscription_invoices henüz yoksa sessiz geç
            }
            }

            $buyerMap = [];
            try {
                $buyerMap = iyzico_payment_buyer_map(0.0);
            } catch (Throwable $e) {
                $buyerMap = [];
            }
            $uniqueName = [];
            $byEmail = [];
            try {
                $idx = subscription_buyer_indexes(
                    $pdo->query("SELECT * FROM subscriptions ORDER BY id DESC LIMIT 400")->fetchAll() ?: []
                );
                $uniqueName = $idx['uniqueName'];
                $byEmail = $idx['byEmail'];
            } catch (Throwable $e) {
            }
            foreach ($items as &$it) {
                $pid = iyzico_normalize_payment_id($it['iyzicoPaymentId'] ?? '');
                if ($pid === '') {
                    continue;
                }
                if (isset($buyerMap[$pid])) {
                    $bn = trim((string) ($buyerMap[$pid]['name'] ?? ''));
                    $be = subscription_norm_email((string) ($buyerMap[$pid]['email'] ?? ''));
                    if ($be !== '' && isset($byEmail[$be])) {
                        $cands = subscription_canonical_rows($byEmail[$be]);
                        if ($cands !== []) {
                            if ($bn === '') {
                                $bn = trim((string) ($cands[0]['student_name'] ?? ''));
                            }
                            if ($be === '') {
                                $be = subscription_norm_email((string) ($cands[0]['student_email'] ?? ''));
                            }
                        }
                    }
                    if ($bn !== '') {
                        $it['student_name'] = $bn;
                    }
                    if ($be !== '') {
                        $it['student_email'] = $be;
                    }
                }
                if (!isset($iyzIdx[$pid])) {
                    continue;
                }
                $fact = $iyzIdx[$pid];
                $iyzName = trim((string) ($fact['name'] ?? ''));
                if ($iyzName !== '') {
                    $it['student_name'] = $iyzName;
                    $nm = subscription_norm_person_name($iyzName);
                    if (isset($uniqueName[$nm]['student_email'])) {
                        $it['student_email'] = (string) $uniqueName[$nm]['student_email'];
                    } elseif ((string) ($fact['email'] ?? '') !== '') {
                        $it['student_email'] = (string) $fact['email'];
                    }
                }
                $at = (string) ($fact['paidAt'] ?? '');
                if ($at !== '') {
                    $it['paid_at'] = $at;
                    $it['created_at'] = $at;
                }
                if (!empty($fact['refunded']) && (string) $it['tur'] === 'Abonelik') {
                    $it['status'] = 'refunded';
                    $it['tahsilEdildi'] = false;
                }
            }
            unset($it);

            usort($items, static function ($a, $b) {
                $ta = strtotime($a['paid_at'] !== '' ? $a['paid_at'] : $a['created_at']) ?: 0;
                $tb = strtotime($b['paid_at'] !== '' ? $b['paid_at'] : $b['created_at']) ?: 0;
                return $tb <=> $ta;
            });

            $summary = [
                'tahsilEdilen' => 0,
                'tahsilEdilenKurus' => 0,
                'iadeEdilen' => 0,
                'bekleyen' => 0,
                'basarisiz' => 0,
                'inceleme' => 0,
            ];
            foreach ($items as $it) {
                $stt = (string)$it['status'];
                if (!empty($it['tahsilEdildi'])) {
                    $summary['tahsilEdilen']++;
                    $summary['tahsilEdilenKurus'] += (int)$it['amount_kurus'];
                } elseif ($stt === 'refunded') {
                    $summary['iadeEdilen']++;
                } elseif ($stt === 'pending') {
                    $summary['bekleyen']++;
                } elseif ($stt === 'review') {
                    $summary['inceleme']++;
                } elseif ($stt === 'failed' || $stt === 'cancelled') {
                    $summary['basarisiz']++;
                }
            }

            $subCounts = [];
            try {
                foreach ($pdo->query(
                    "SELECT status, COUNT(*) c FROM subscriptions GROUP BY status"
                )->fetchAll() as $r) {
                    $subCounts[(string)$r['status']] = (int)$r['c'];
                }
            } catch (Throwable $e) {
                $subCounts = [];
            }

            json_out([
                'ok' => true,
                'items' => $items,
                'summary' => $summary,
                'subCounts' => $subCounts,
                'duplicates' => subscription_duplicate_charges($pdo, $from, $to),
                'from' => $from,
                'to' => $to,
                'tur' => $tur,
                'sandbox' => iyzico_is_sandbox(),
                'keySource' => defined('IYZICO_KEY_SOURCE') ? IYZICO_KEY_SOURCE : 'none',
                'transactionsUrl' => iyzico_is_sandbox()
                    ? 'https://sandbox-merchant.iyzipay.com/transactions'
                    : 'https://merchant.iyzipay.com/transactions',
            ]);
        }

        case 'subscriptions_list': {
            json_out(['ok' => true, 'items' => subscription_admin_list($pdo)]);
        }

        case 'subscription_wa_set': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $on = !empty($in['wa_added']) ? 1 : 0;
            if ($id <= 0) {
                json_out(['ok' => false, 'error' => 'Kayıt yok'], 422);
            }
            $pdo->prepare("UPDATE subscriptions SET wa_added = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$on, $id]);
            json_out(['ok' => true]);
        }

        case 'admin_students_list': {
            $instructorId = (int)($_GET['instructor_id'] ?? 0);
            $courseId = (int)($_GET['course_id'] ?? 0);
            $q = trim((string)($_GET['q'] ?? ''));

            $sql = "SELECT e.id, e.course_id, e.progress_pct, e.payment_status, e.enrolled_at, e.last_visit_at,
                           COALESCE(NULLIF(s.name, ''), e.student_name) AS student_name,
                           COALESCE(NULLIF(s.email, ''), e.student_email) AS student_email,
                           COALESCE(NULLIF(s.phone, ''), e.student_phone) AS student_phone,
                           s.status AS account_status,
                           s.id AS student_account_id,
                           c.title AS course_title,
                           c.instructor_id,
                           COALESCE(i.name, '') AS instructor_name
                    FROM course_enrollments e
                    LEFT JOIN students s ON s.email = e.student_email
                    LEFT JOIN courses c ON c.id = e.course_id
                    LEFT JOIN instructors i ON i.id = c.instructor_id
                    WHERE 1=1";
            $params = [];
            if ($instructorId > 0) {
                $sql .= " AND c.instructor_id = ?";
                $params[] = $instructorId;
            }
            if ($courseId > 0) {
                $sql .= " AND e.course_id = ?";
                $params[] = $courseId;
            }
            if ($q !== '') {
                $sql .= " AND (e.student_name LIKE ? OR e.student_email LIKE ? OR e.student_phone LIKE ? OR s.name LIKE ? OR s.email LIKE ?)";
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
            $sql .= " ORDER BY e.enrolled_at DESC, e.id DESC LIMIT 500";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            json_out([
                'ok' => true,
                'items' => $st->fetchAll(),
                'instructors' => admin_filter_instructors($pdo),
                'courses' => admin_filter_courses($pdo),
            ]);
        }

        case 'admin_sales_report': {
            $from = admin_parse_ymd($_GET['from'] ?? '');
            $to = admin_parse_ymd($_GET['to'] ?? '');
            if ($from !== '' && $to !== '' && $from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }
            $instructorId = (int)($_GET['instructor_id'] ?? 0);
            $courseId = (int)($_GET['course_id'] ?? 0);

            $where = "o.status = 'paid'";
            $params = [];
            if ($from !== '') {
                $where .= " AND DATE(COALESCE(o.paid_at, o.created_at)) >= ?";
                $params[] = $from;
            }
            if ($to !== '') {
                $where .= " AND DATE(COALESCE(o.paid_at, o.created_at)) <= ?";
                $params[] = $to;
            }
            if ($instructorId > 0) {
                $where .= " AND c.instructor_id = ?";
                $params[] = $instructorId;
            }
            if ($courseId > 0) {
                $where .= " AND o.course_id = ?";
                $params[] = $courseId;
            }

            $st = $pdo->prepare(
                "SELECT o.id, o.course_id, o.student_name, o.student_email, o.student_phone,
                        o.amount_kurus, o.paid_at, o.created_at,
                        c.title AS course_title, c.instructor_id,
                        COALESCE(i.name, '') AS instructor_name
                 FROM payment_orders o
                 LEFT JOIN courses c ON c.id = o.course_id
                 LEFT JOIN instructors i ON i.id = c.instructor_id
                 WHERE $where
                 ORDER BY COALESCE(o.paid_at, o.created_at) DESC, o.id DESC
                 LIMIT 800"
            );
            $st->execute($params);
            $items = $st->fetchAll();

            $totalKurus = 0;
            $earnKurus = 0;
            $byInstructor = [];
            $byCourse = [];
            $pctCache = [];
            foreach ($items as &$r) {
                $kurus = (int)$r['amount_kurus'];
                $insId = (int)($r['instructor_id'] ?? 0);
                if (!isset($pctCache[$insId])) {
                    $pctCache[$insId] = instructor_share_pct_for($pdo, $insId);
                }
                $pct = $pctCache[$insId];
                $earn = instructor_earn_kurus($kurus, $pct);
                $r['amount_kurus'] = $kurus;
                $r['earn_kurus'] = $earn;
                $r['share_pct'] = $pct;
                $totalKurus += $kurus;
                $earnKurus += $earn;

                $ikey = $insId > 0 ? (string)$insId : '0';
                if (!isset($byInstructor[$ikey])) {
                    $byInstructor[$ikey] = [
                        'instructor_id' => $insId,
                        'name' => (string)($r['instructor_name'] ?: 'Atanmamış'),
                        'count' => 0,
                        'sales_kurus' => 0,
                        'earn_kurus' => 0,
                    ];
                }
                $byInstructor[$ikey]['count']++;
                $byInstructor[$ikey]['sales_kurus'] += $kurus;
                $byInstructor[$ikey]['earn_kurus'] += $earn;

                $ckey = (string)((int)$r['course_id']);
                if (!isset($byCourse[$ckey])) {
                    $byCourse[$ckey] = [
                        'course_id' => (int)$r['course_id'],
                        'title' => (string)($r['course_title'] ?: 'Kurs #' . $r['course_id']),
                        'instructor_name' => (string)($r['instructor_name'] ?: 'Atanmamış'),
                        'count' => 0,
                        'sales_kurus' => 0,
                    ];
                }
                $byCourse[$ckey]['count']++;
                $byCourse[$ckey]['sales_kurus'] += $kurus;
            }
            unset($r);

            json_out([
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'count' => count($items),
                'sales_kurus' => $totalKurus,
                'earn_kurus' => $earnKurus,
                'items' => $items,
                'by_instructor' => array_values($byInstructor),
                'by_course' => array_values($byCourse),
                'instructors' => admin_filter_instructors($pdo),
                'courses' => admin_filter_courses($pdo),
            ]);
        }

        case 'change_password': {
            $in = body_json();
            $cur = (string)($in['current'] ?? '');
            $new = (string)($in['new'] ?? '');
            if (strlen($new) < 6) json_out(['ok' => false, 'error' => 'Yeni şifre en az 6 karakter olmalı'], 422);
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
            $stmt->execute([$sessionAdminId]);
            $u = $stmt->fetch();
            if (!$u || !password_verify($cur, $u['password_hash'])) json_out(['ok' => false, 'error' => 'Mevcut şifre hatalı'], 422);
            $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $sessionAdminId]);
            json_out(['ok' => true]);
        }

        default:
            json_out(['ok' => false, 'error' => 'Geçersiz işlem'], 400);
    }
} catch (Throwable $e) {
    error_log('admin.php: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Sunucu hatası'], 500);
}

function slugify($t) {
    $t = mb_strtolower(trim($t), 'UTF-8');
    $tr = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','İ'=>'i'];
    $t = strtr($t, $tr);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    return $t !== '' ? $t : 'kayit-' . time();
}

function admin_parse_ymd($raw): string {
    $s = trim((string)$raw);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function admin_filter_instructors(PDO $pdo): array {
    try {
        return $pdo->query("SELECT id, name FROM instructors ORDER BY name, id")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function admin_filter_courses(PDO $pdo): array {
    try {
        return $pdo->query("SELECT id, title, instructor_id FROM courses ORDER BY title, id")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Müfredat metnini yapıya çevirir.
 * Biçim:
 *   # Gün / Bölüm başlığı
 *   ## Alt başlık
 *   - madde
 */
function parse_mufredat($text) {
    if (!is_string($text) || trim($text) === '') return [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $gunler = [];
    $gunIdx = -1;
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') continue;
        if (strpos($t, '##') === 0) {
            if ($gunIdx < 0) { $gunler[] = ['baslik' => '', 'bolumler' => []]; $gunIdx = 0; }
            $gunler[$gunIdx]['bolumler'][] = ['baslik' => trim(substr($t, 2)), 'maddeler' => []];
        } elseif (strpos($t, '#') === 0) {
            $gunler[] = ['baslik' => trim(substr($t, 1)), 'bolumler' => []];
            $gunIdx = count($gunler) - 1;
        } elseif (strpos($t, '-') === 0) {
            if ($gunIdx >= 0 && !empty($gunler[$gunIdx]['bolumler'])) {
                $bIdx = count($gunler[$gunIdx]['bolumler']) - 1;
                $gunler[$gunIdx]['bolumler'][$bIdx]['maddeler'][] = trim(substr($t, 1));
            }
        }
    }
    return $gunler;
}
