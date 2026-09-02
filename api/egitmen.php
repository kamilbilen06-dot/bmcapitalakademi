<?php
/**
 * Eğitmen paneli API — her eğitmen kendi kursları + profili.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/auth_schema.php';
require_once __DIR__ . '/image_upload.php';
require_once __DIR__ . '/paytr_schema.php';
require_once __DIR__ . '/students_schema.php';
require_once __DIR__ . '/payments_schema.php';
require_once __DIR__ . '/subscriptions.php';

require_egitmen_access();
// API yanıtı beklerken başka isteklerin session kilidine takılmasını önle
$sessionUserId = (int)($_SESSION['admin_id'] ?? 0);
$sessionRole = current_role();
$sessionInstructorId = current_instructor_id();
$sessionUsername = (string)($_SESSION['admin_user'] ?? '');
$csrfToken = admin_csrf_token();
admin_csrf_protect();
session_write_close();

$pdo = db();
egitmen_ensure_schema($pdo);
instructors_ensure_schema($pdo);
auth_ensure_schema($pdo);
paytr_ensure_schema($pdo);
students_ensure_schema($pdo);
payments_ensure_schema($pdo);
subscriptions_ensure_schema($pdo);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'me': {
            $ins = null;
            $insId = 0;
            if ($sessionRole === 'egitmen') {
                $insId = (int)$sessionInstructorId;
            } else {
                // Admin: bağlı profil veya ilk eğitmen (panelde fotoğraf/isim için)
                $insId = (int)$sessionInstructorId;
                if ($insId <= 0) {
                    $insId = (int)$pdo->query("SELECT id FROM instructors ORDER BY id ASC LIMIT 1")->fetchColumn();
                }
            }
            if ($insId > 0) {
                $st = $pdo->prepare("SELECT id, slug, name, title, photo_path FROM instructors WHERE id = ?");
                $st->execute([$insId]);
                $ins = $st->fetch() ?: null;
            }
            json_out([
                'ok' => true,
                'csrf' => $csrfToken,
                'user' => [
                    'id' => $sessionUserId,
                    'username' => $sessionUsername,
                    'role' => $sessionRole,
                    'instructor_id' => $sessionInstructorId,
                    'is_site_admin' => $sessionRole === 'admin',
                ],
                'instructor' => $ins,
            ]);
        }

        case 'profile_get': {
            $insId = egitmen_profile_id($sessionRole, $sessionInstructorId, $pdo);
            $st = $pdo->prepare("SELECT * FROM instructors WHERE id = ?");
            $st->execute([$insId]);
            $row = $st->fetch();
            if (!$row) json_out(['ok' => false, 'error' => 'Profil bulunamadı'], 404);
            $row['socials'] = instructor_decode_socials($row['socials'] ?? '[]');
            json_out(['ok' => true, 'item' => $row]);
        }

        case 'profile_save': {
            $insId = egitmen_profile_id($sessionRole, $sessionInstructorId, $pdo);
            $in = body_json();
            $name = clean($in['name'] ?? '');
            if ($name === '') json_out(['ok' => false, 'error' => 'Ad gerekli'], 422);
            $title = clean($in['title'] ?? '');
            $photo = clean($in['photo_path'] ?? '');
            $bio = (string)($in['bio'] ?? '');
            $socialsIn = $in['socials'] ?? [];
            if (!is_array($socialsIn)) $socialsIn = [];
            $socials = [];
            foreach ($socialsIn as $s) {
                if (!is_array($s)) continue;
                $url = clean($s['url'] ?? '');
                $platform = clean($s['platform'] ?? 'link');
                if ($url === '') continue;
                $socials[] = ['platform' => $platform !== '' ? $platform : 'link', 'url' => $url];
            }
            $socialsJson = json_encode($socials, JSON_UNESCAPED_UNICODE);
            $pdo->prepare(
                "UPDATE instructors SET name=?, title=?, photo_path=?, bio=?, socials=? WHERE id=?"
            )->execute([$name, $title, $photo, $bio, $socialsJson, $insId]);
            $st = $pdo->prepare("SELECT * FROM instructors WHERE id = ?");
            $st->execute([$insId]);
            $row = $st->fetch();
            $row['socials'] = instructor_decode_socials($row['socials'] ?? '[]');
            json_out(['ok' => true, 'item' => $row]);
        }

        case 'profile_upload': {
            $overflow = upload_multipart_overflow_message();
            if ($overflow !== null) {
                json_out(['ok' => false, 'error' => $overflow], 413);
            }
            egitmen_profile_id($sessionRole, $sessionInstructorId, $pdo);
            $res = save_instructor_photo($_FILES['file'] ?? []);
            if (empty($res['ok'])) {
                json_out(['ok' => false, 'error' => $res['error'] ?? 'Yükleme hatası'], (int)($res['code'] ?? 400));
            }
            json_out(['ok' => true, 'path' => $res['path']]);
        }

        case 'change_password': {
            $in = body_json();
            $cur = (string)($in['current'] ?? '');
            $new = (string)($in['new'] ?? '');
            if (strlen($new) < 6) json_out(['ok' => false, 'error' => 'Yeni şifre en az 6 karakter olmalı'], 422);
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
            $stmt->execute([$sessionUserId]);
            $u = $stmt->fetch();
            if (!$u || !password_verify($cur, $u['password_hash'])) {
                json_out(['ok' => false, 'error' => 'Mevcut şifre hatalı'], 422);
            }
            $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $sessionUserId]);
            json_out(['ok' => true]);
        }

        case 'courses_list': {
            if ($sessionRole === 'admin') {
                $rows = $pdo->query(
                    "SELECT c.id, c.title, c.subtitle, c.status, c.price, c.image_path, c.updated_at, c.instructor_id,
                            i.name AS instructor_name
                     FROM courses c
                     LEFT JOIN instructors i ON i.id = c.instructor_id
                     ORDER BY c.updated_at DESC, c.id DESC"
                )->fetchAll();
            } else {
                $st = $pdo->prepare(
                    "SELECT id, title, subtitle, status, price, image_path, updated_at, instructor_id
                     FROM courses WHERE instructor_id = ? ORDER BY updated_at DESC, id DESC"
                );
                $st->execute([$sessionInstructorId]);
                $rows = $st->fetchAll();
            }
            json_out(['ok' => true, 'items' => $rows]);
        }

        case 'sales_stats': {
            $sharePct = instructor_share_pct_for($pdo, (int)$sessionInstructorId);
            $ids = egitmen_accessible_course_ids($pdo, $sessionRole, $sessionInstructorId);
            if ($ids !== null && !$ids) {
                json_out([
                    'ok' => true,
                    'sales_kurus' => 0,
                    'earn_kurus' => 0,
                    'share_pct' => $sharePct,
                    'paid_count' => 0,
                    'by_course' => [],
                    'daily' => [],
                    'monthly' => [],
                    'this_month_kurus' => 0,
                    'last_month_kurus' => 0,
                ]);
            }
            $where = "status = 'paid'";
            $params = [];
            if ($ids !== null) {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $where .= " AND course_id IN ($place)";
                $params = $ids;
            }

            $sumSt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount_kurus),0) tot, COUNT(*) cnt
                 FROM payment_orders WHERE $where"
            );
            $sumSt->execute($params);
            $sumRow = $sumSt->fetch();
            $salesKurus = (int)($sumRow['tot'] ?? 0);
            $paidCount = (int)($sumRow['cnt'] ?? 0);

            $bySt = $pdo->prepare(
                "SELECT course_id, COALESCE(SUM(amount_kurus),0) tot, COUNT(*) cnt
                 FROM payment_orders WHERE $where GROUP BY course_id"
            );
            $bySt->execute($params);
            $courseIns = [];
            try {
                $ci = $pdo->query('SELECT id, instructor_id FROM courses');
                foreach ($ci as $c) {
                    $courseIns[(int)$c['id']] = (int)$c['instructor_id'];
                }
            } catch (Throwable $e) {
                $courseIns = [];
            }
            $pctCache = [];
            $pctForCourse = static function (int $courseId) use ($pdo, $sessionRole, $sharePct, $courseIns, &$pctCache): float {
                if ($sessionRole !== 'admin') {
                    return $sharePct;
                }
                $insId = $courseIns[$courseId] ?? 0;
                if (!isset($pctCache[$insId])) {
                    $pctCache[$insId] = instructor_share_pct_for($pdo, $insId);
                }
                return $pctCache[$insId];
            };
            $byCourse = [];
            $earnTotal = 0;
            foreach ($bySt->fetchAll() as $r) {
                $tot = (int)$r['tot'];
                $cid = (int)$r['course_id'];
                $earn = instructor_earn_kurus($tot, $pctForCourse($cid));
                $earnTotal += $earn;
                $byCourse[] = [
                    'course_id' => $cid,
                    'sales_kurus' => $tot,
                    'earn_kurus' => $earn,
                    'paid_count' => (int)$r['cnt'],
                ];
            }

            $monthSt = $pdo->prepare(
                "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') ym,
                        COALESCE(SUM(amount_kurus),0) tot
                 FROM payment_orders
                 WHERE $where AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                 GROUP BY ym ORDER BY ym"
            );
            $monthSt->execute($params);
            $monthlyMap = [];
            foreach ($monthSt->fetchAll() as $r) {
                $monthlyMap[(string)$r['ym']] = (int)$r['tot'];
            }
            $monthly = [];
            $cursor = new DateTime('first day of this month');
            $cursor->modify('-5 months');
            for ($i = 0; $i < 6; $i++) {
                $ym = $cursor->format('Y-m');
                $monthly[] = [
                    'label' => $ym,
                    'sales_kurus' => $monthlyMap[$ym] ?? 0,
                    'earn_kurus' => instructor_earn_kurus((int)($monthlyMap[$ym] ?? 0), $sharePct),
                    'views' => 0,
                ];
                $cursor->modify('+1 month');
            }

            $daySt = $pdo->prepare(
                "SELECT DATE(COALESCE(paid_at, created_at)) d, COALESCE(SUM(amount_kurus),0) tot
                 FROM payment_orders
                 WHERE $where AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY d ORDER BY d"
            );
            $daySt->execute($params);
            $dayMap = [];
            foreach ($daySt->fetchAll() as $r) {
                $dayMap[(string)$r['d']] = (int)$r['tot'];
            }
            $daily = [];
            $dayNames = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
            for ($i = 6; $i >= 0; $i--) {
                $dt = date('Y-m-d', strtotime("-$i days"));
                $w = (int)date('w', strtotime($dt));
                $daily[] = [
                    'label' => $dayNames[$w],
                    'sales_kurus' => $dayMap[$dt] ?? 0,
                    'earn_kurus' => instructor_earn_kurus((int)($dayMap[$dt] ?? 0), $sharePct),
                    'views' => 0,
                ];
            }

            $thisYm = date('Y-m');
            $lastYm = date('Y-m', strtotime('first day of last month'));
            $thisMonth = 0;
            $lastMonth = 0;
            foreach ($monthly as $m) {
                if ($m['label'] === $thisYm) {
                    $thisMonth = $m['sales_kurus'];
                }
                if ($m['label'] === $lastYm) {
                    $lastMonth = $m['sales_kurus'];
                }
            }

            $viewSql = '1=1';
            $viewParams = [];
            if ($ids !== null) {
                $viewSql = 'course_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $viewParams = $ids;
            }
            try {
                $vm = $pdo->prepare(
                    "SELECT DATE_FORMAT(updated_at, '%Y-%m') ym, COUNT(*) c
                     FROM course_lecture_progress
                     WHERE $viewSql AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                     GROUP BY ym"
                );
                $vm->execute($viewParams);
                $vmMap = [];
                foreach ($vm->fetchAll() as $r) {
                    $vmMap[(string)$r['ym']] = (int)$r['c'];
                }
                $em = $pdo->prepare(
                    "SELECT DATE_FORMAT(last_visit_at, '%Y-%m') ym, COUNT(*) c
                     FROM course_enrollments
                     WHERE $viewSql AND payment_status = 'paid' AND last_visit_at IS NOT NULL
                       AND last_visit_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                     GROUP BY ym"
                );
                $em->execute($viewParams);
                foreach ($em->fetchAll() as $r) {
                    $key = (string)$r['ym'];
                    $vmMap[$key] = ($vmMap[$key] ?? 0) + (int)$r['c'];
                }
                foreach ($monthly as &$mrow) {
                    $mrow['views'] = $vmMap[$mrow['label']] ?? 0;
                }
                unset($mrow);

                $vd = $pdo->prepare(
                    "SELECT DATE(updated_at) d, COUNT(*) c
                     FROM course_lecture_progress
                     WHERE $viewSql AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                     GROUP BY d"
                );
                $vd->execute($viewParams);
                $vdMap = [];
                foreach ($vd->fetchAll() as $r) {
                    $vdMap[(string)$r['d']] = (int)$r['c'];
                }
                $ed = $pdo->prepare(
                    "SELECT DATE(last_visit_at) d, COUNT(*) c
                     FROM course_enrollments
                     WHERE $viewSql AND payment_status = 'paid' AND last_visit_at IS NOT NULL
                       AND last_visit_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                     GROUP BY d"
                );
                $ed->execute($viewParams);
                foreach ($ed->fetchAll() as $r) {
                    $key = (string)$r['d'];
                    $vdMap[$key] = ($vdMap[$key] ?? 0) + (int)$r['c'];
                }
                for ($i = 0; $i < 7; $i++) {
                    $iso = date('Y-m-d', strtotime('-' . (6 - $i) . ' days'));
                    if (isset($daily[$i])) {
                        $daily[$i]['views'] = $vdMap[$iso] ?? 0;
                    }
                }
            } catch (Throwable $e) {
                // Görüntülenme tablosu yoksa 0 kalsın
            }

            json_out([
                'ok' => true,
                'sales_kurus' => $salesKurus,
                'earn_kurus' => $earnTotal,
                'share_pct' => $sharePct,
                'paid_count' => $paidCount,
                'by_course' => $byCourse,
                'daily' => $daily,
                'monthly' => $monthly,
                'this_month_kurus' => $thisMonth,
                'last_month_kurus' => $lastMonth,
            ]);
        }

        case 'course_get': {
            $id = (int)($_GET['id'] ?? 0);
            assert_course_access($pdo, $id, $sessionRole, $sessionInstructorId);
            $course = fetch_course($pdo, $id);
            if (!$course) json_out(['ok' => false, 'error' => 'Kurs bulunamadı'], 404);
            json_out(['ok' => true, 'item' => $course]);
        }

        case 'course_create': {
            $in = body_json();
            $title = clean($in['title'] ?? 'Yeni Kurs');
            if ($title === '') $title = 'Yeni Kurs';
            $ownerId = $sessionInstructorId;
            if ($sessionRole === 'admin') {
                $ownerId = (int)($in['instructor_id'] ?? 0);
                if ($ownerId <= 0) {
                    $ownerId = (int)$pdo->query("SELECT id FROM instructors ORDER BY id ASC LIMIT 1")->fetchColumn();
                }
            }
            if ($ownerId <= 0) {
                json_out(['ok' => false, 'error' => 'Kurs için eğitmen profili gerekli'], 422);
            }
            $stmt = $pdo->prepare(
                "INSERT INTO courses (instructor_id, title, status) VALUES (?, ?, 'draft')"
            );
            $stmt->execute([$ownerId, $title]);
            $id = (int)$pdo->lastInsertId();
            seed_default_lines($pdo, $id);
            json_out(['ok' => true, 'id' => $id, 'item' => fetch_course($pdo, $id)]);
        }

        case 'course_save': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) json_out(['ok' => false, 'error' => 'Kurs id gerekli'], 422);
            assert_course_access($pdo, $id, $sessionRole, $sessionInstructorId);

            $fields = [
                'title' => clean($in['title'] ?? ''),
                'subtitle' => clean($in['subtitle'] ?? ''),
                'description' => (string)($in['description'] ?? ''),
                'language' => clean($in['language'] ?? 'Türkçe'),
                'level' => clean($in['level'] ?? 'Tüm Düzeyler'),
                'category' => clean($in['category'] ?? ''),
                'subcategory' => clean($in['subcategory'] ?? ''),
                'topic' => clean($in['topic'] ?? ''),
                'price' => clean($in['price'] ?? ''),
                'price_note' => clean($in['price_note'] ?? ''),
                'status' => in_array(($in['status'] ?? ''), ['draft', 'published'], true)
                    ? $in['status'] : 'draft',
            ];
            if ($fields['title'] === '') {
                json_out(['ok' => false, 'error' => 'Kurs başlığı gerekli'], 422);
            }

            $set = implode(', ', array_map(function ($k) { return "$k = :$k"; }, array_keys($fields)));
            $fields['id'] = $id;
            $pdo->prepare("UPDATE courses SET $set WHERE id = :id")->execute($fields);

            if (isset($in['objectives']) && is_array($in['objectives'])) {
                replace_lines($pdo, 'course_objectives', $id, $in['objectives']);
            }
            if (isset($in['requirements']) && is_array($in['requirements'])) {
                replace_lines($pdo, 'course_requirements', $id, $in['requirements']);
            }
            if (isset($in['audience']) && is_array($in['audience'])) {
                replace_lines($pdo, 'course_audience', $id, $in['audience']);
            }

            json_out(['ok' => true, 'item' => fetch_course($pdo, $id)]);
        }

        case 'course_delete': {
            $in = body_json();
            $id = (int)($in['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) json_out(['ok' => false, 'error' => 'id gerekli'], 422);
            assert_course_access($pdo, $id, $sessionRole, $sessionInstructorId);
            delete_course_files($id);
            $pdo->prepare("DELETE FROM course_lectures WHERE course_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM course_sections WHERE course_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM course_objectives WHERE course_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM course_requirements WHERE course_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM course_audience WHERE course_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
            json_out(['ok' => true]);
        }

        case 'section_save': {
            $in = body_json();
            $courseId = (int)($in['course_id'] ?? 0);
            $id = (int)($in['id'] ?? 0);
            $title = clean($in['title'] ?? 'Bölüm');
            if ($courseId <= 0) json_out(['ok' => false, 'error' => 'course_id gerekli'], 422);
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);

            if ($id > 0) {
                $pdo->prepare("UPDATE course_sections SET title = ? WHERE id = ? AND course_id = ?")
                    ->execute([$title, $id, $courseId]);
            } else {
                $order = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM course_sections WHERE course_id = $courseId")->fetchColumn();
                $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order) VALUES (?,?,?)")
                    ->execute([$courseId, $title, $order]);
                $id = (int)$pdo->lastInsertId();
            }
            json_out(['ok' => true, 'id' => $id, 'curriculum' => fetch_curriculum($pdo, $courseId)]);
        }

        case 'section_delete': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $courseId = (int)($in['course_id'] ?? 0);
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            $pdo->prepare("DELETE FROM course_lectures WHERE section_id = ? AND course_id = ?")
                ->execute([$id, $courseId]);
            $pdo->prepare("DELETE FROM course_sections WHERE id = ? AND course_id = ?")
                ->execute([$id, $courseId]);
            sync_course_duration($pdo, $courseId);
            json_out(['ok' => true, 'curriculum' => fetch_curriculum($pdo, $courseId)]);
        }

        case 'lecture_save': {
            $in = body_json();
            $courseId = (int)($in['course_id'] ?? 0);
            $sectionId = (int)($in['section_id'] ?? 0);
            $id = (int)($in['id'] ?? 0);
            $title = clean($in['title'] ?? 'Ders');
            if ($courseId <= 0) {
                json_out(['ok' => false, 'error' => 'course_id gerekli'], 422);
            }
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);

            if ($id > 0) {
                $fields = [];
                $params = [];
                if (array_key_exists('title', $in)) {
                    $fields[] = 'title = ?';
                    $params[] = $title;
                }
                if (array_key_exists('description', $in)) {
                    $fields[] = 'description = ?';
                    $params[] = (string)($in['description'] ?? '');
                }
                if (array_key_exists('resources', $in)) {
                    $res = $in['resources'];
                    if (!is_array($res)) $res = [];
                    $cleanRes = [];
                    foreach ($res as $r) {
                        if (!is_array($r)) continue;
                        $name = clean($r['name'] ?? '');
                        $url = clean($r['url'] ?? '');
                        if ($name === '' && $url === '') continue;
                        $cleanRes[] = ['name' => $name !== '' ? $name : $url, 'url' => $url];
                    }
                    $fields[] = 'resources = ?';
                    $params[] = json_encode($cleanRes, JSON_UNESCAPED_UNICODE);
                }
                if (array_key_exists('is_preview', $in)) {
                    $fields[] = 'is_preview = ?';
                    $params[] = !empty($in['is_preview']) ? 1 : 0;
                }
                if ($fields) {
                    $params[] = $id;
                    $params[] = $courseId;
                    $pdo->prepare(
                        "UPDATE course_lectures SET " . implode(', ', $fields) . " WHERE id = ? AND course_id = ?"
                    )->execute($params);
                }
            } else {
                if ($sectionId <= 0) {
                    json_out(['ok' => false, 'error' => 'section_id gerekli'], 422);
                }
                $order = (int)$pdo->query(
                    "SELECT COALESCE(MAX(sort_order),0)+1 FROM course_lectures WHERE section_id = $sectionId"
                )->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO course_lectures (section_id, course_id, title, sort_order) VALUES (?,?,?,?)"
                )->execute([$sectionId, $courseId, $title, $order]);
                $id = (int)$pdo->lastInsertId();
            }
            json_out(['ok' => true, 'id' => $id, 'curriculum' => fetch_curriculum($pdo, $courseId)]);
        }

        case 'lecture_delete': {
            $in = body_json();
            $id = (int)($in['id'] ?? 0);
            $courseId = (int)($in['course_id'] ?? 0);
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            $stmt = $pdo->prepare("SELECT video_path FROM course_lectures WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && $row['video_path']) {
                $abs = realpath(__DIR__ . '/../' . $row['video_path']);
                if ($abs && is_file($abs)) @unlink($abs);
            }
            $pdo->prepare("DELETE FROM course_lectures WHERE id = ?")->execute([$id]);
            sync_course_duration($pdo, $courseId);
            json_out(['ok' => true, 'curriculum' => fetch_curriculum($pdo, $courseId)]);
        }

        case 'curriculum_reorder': {
            $in = body_json();
            $courseId = (int)($in['course_id'] ?? 0);
            $sections = $in['sections'] ?? [];
            if ($courseId <= 0 || !is_array($sections)) {
                json_out(['ok' => false, 'error' => 'Geçersiz sıralama'], 422);
            }
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            $pdo->beginTransaction();
            try {
                $si = 0;
                foreach ($sections as $sec) {
                    if (!is_array($sec)) continue;
                    $secId = (int)($sec['id'] ?? 0);
                    if ($secId <= 0) continue;
                    $pdo->prepare(
                        "UPDATE course_sections SET sort_order = ? WHERE id = ? AND course_id = ?"
                    )->execute([$si++, $secId, $courseId]);
                    $lectures = $sec['lectures'] ?? [];
                    if (!is_array($lectures)) continue;
                    $li = 0;
                    foreach ($lectures as $lecId) {
                        $lecId = (int)$lecId;
                        if ($lecId <= 0) continue;
                        $pdo->prepare(
                            "UPDATE course_lectures SET section_id = ?, sort_order = ? WHERE id = ? AND course_id = ?"
                        )->execute([$secId, $li++, $lecId, $courseId]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_out(['ok' => true, 'curriculum' => fetch_curriculum($pdo, $courseId)]);
        }

        case 'students_list': {
            $courseId = (int)($_GET['course_id'] ?? 0);
            if ($courseId <= 0) json_out(['ok' => false, 'error' => 'course_id gerekli'], 422);
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            $st = $pdo->prepare(
                "SELECT e.id, e.course_id, e.student_id, e.progress_pct, e.source, e.payment_status,
                        e.enrolled_at, e.last_visit_at,
                        COALESCE(NULLIF(s.name, ''), e.student_name) AS student_name,
                        COALESCE(NULLIF(s.email, ''), e.student_email) AS student_email,
                        COALESCE(NULLIF(s.phone, ''), e.student_phone) AS student_phone
                 FROM course_enrollments e
                 LEFT JOIN students s ON s.id = e.student_id
                 WHERE e.course_id = ?
                   AND e.payment_status = 'paid'
                 ORDER BY e.enrolled_at DESC, e.id DESC"
            );
            $st->execute([$courseId]);
            json_out(['ok' => true, 'items' => $st->fetchAll()]);
        }

        case 'subscriptions_list': {
            $iid = (int)$sessionInstructorId;
            if ($sessionRole === 'admin' && (int)($_GET['instructor_id'] ?? 0) > 0) {
                $iid = (int)$_GET['instructor_id'];
            }
            if ($iid <= 0) {
                json_out(['ok' => true, 'items' => []]);
            }
            json_out(['ok' => true, 'items' => subscription_list_for_instructor($pdo, $iid)]);
        }

        case 'upload': {
            $overflow = upload_multipart_overflow_message();
            if ($overflow !== null) {
                json_out(['ok' => false, 'error' => $overflow], 413);
            }
            $courseId = (int)($_POST['course_id'] ?? 0);
            $kind = clean($_POST['kind'] ?? ''); // image | promo | lecture
            $lectureId = (int)($_POST['lecture_id'] ?? 0);
            if ($courseId <= 0) {
                json_out(['ok' => false, 'error' => 'Kurs seçilemedi. Sayfayı yenileyip tekrar deneyin.'], 422);
            }
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $err = (int)($_FILES['file']['error'] ?? -1);
                $map = [
                    UPLOAD_ERR_INI_SIZE => 'Dosya PHP limitini aşıyor (upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'Dosya form limitini aşıyor',
                    UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
                    UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi',
                    UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör yok',
                    UPLOAD_ERR_CANT_WRITE => 'Diske yazılamadı',
                    UPLOAD_ERR_EXTENSION => 'Uzantı engelledi',
                ];
                json_out(['ok' => false, 'error' => $map[$err] ?? 'Dosya yüklenemedi'], 400);
            }

            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $allowedVid = ['mp4', 'webm'];
            $allowedRes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp'];

            if ($kind === 'image') {
                if (!in_array($ext, $allowedImg, true)) {
                    json_out(['ok' => false, 'error' => 'Kapak için jpg/png/webp kullanın'], 422);
                }
            } elseif ($kind === 'promo' || $kind === 'lecture') {
                if (!in_array($ext, $allowedVid, true)) {
                    json_out(['ok' => false, 'error' => 'Video için mp4 veya webm kullanın'], 422);
                }
            } elseif ($kind === 'resource') {
                if (!in_array($ext, $allowedRes, true)) {
                    json_out(['ok' => false, 'error' => 'Kaynak için pdf/doc/ppt/zip/görsel kullanın'], 422);
                }
                if ($lectureId <= 0) json_out(['ok' => false, 'error' => 'lecture_id gerekli'], 422);
            } else {
                json_out(['ok' => false, 'error' => 'Geçersiz kind'], 422);
            }

            $dirRel = 'uploads/courses/' . $courseId . ($kind === 'resource' ? '/resources' : '');
            $dirAbs = __DIR__ . '/../' . $dirRel;
            if (!is_dir($dirAbs)) {
                mkdir($dirAbs, 0755, true);
            }

            $name = $kind . '_' . (($kind === 'lecture' || $kind === 'resource') ? $lectureId . '_' : '') . time() . '.' . $ext;
            $destAbs = $dirAbs . '/' . $name;
            $destRel = $dirRel . '/' . $name;

            if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
                json_out(['ok' => false, 'error' => 'Dosya kaydedilemedi'], 500);
            }

            if ($kind === 'image') {
                $pdo->prepare("UPDATE courses SET image_path = ? WHERE id = ?")->execute([$destRel, $courseId]);
            } elseif ($kind === 'promo') {
                $pdo->prepare("UPDATE courses SET promo_video_path = ? WHERE id = ?")->execute([$destRel, $courseId]);
            } elseif ($kind === 'lecture') {
                if ($lectureId <= 0) json_out(['ok' => false, 'error' => 'lecture_id gerekli'], 422);
                $duration = (int)($_POST['duration_sec'] ?? 0);
                if ($duration < 0) $duration = 0;
                if ($duration > 0) {
                    $pdo->prepare(
                        "UPDATE course_lectures SET video_path = ?, duration_sec = ? WHERE id = ? AND course_id = ?"
                    )->execute([$destRel, $duration, $lectureId, $courseId]);
                } else {
                    $pdo->prepare(
                        "UPDATE course_lectures SET video_path = ? WHERE id = ? AND course_id = ?"
                    )->execute([$destRel, $lectureId, $courseId]);
                }
                sync_course_duration($pdo, $courseId);
            } elseif ($kind === 'resource') {
                $st = $pdo->prepare("SELECT resources FROM course_lectures WHERE id = ? AND course_id = ?");
                $st->execute([$lectureId, $courseId]);
                $row = $st->fetch();
                if (!$row) json_out(['ok' => false, 'error' => 'Ders bulunamadı'], 404);
                $res = json_decode((string)($row['resources'] ?? '[]'), true);
                if (!is_array($res)) $res = [];
                $origName = clean(pathinfo($file['name'], PATHINFO_FILENAME));
                if ($origName === '') $origName = 'Kaynak';
                $res[] = [
                    'name' => $origName . '.' . $ext,
                    'url' => $destRel,
                    'type' => 'file',
                ];
                $pdo->prepare(
                    "UPDATE course_lectures SET resources = ? WHERE id = ? AND course_id = ?"
                )->execute([json_encode($res, JSON_UNESCAPED_UNICODE), $lectureId, $courseId]);
            }

            json_out([
                'ok' => true,
                'path' => $destRel,
                'item' => fetch_course($pdo, $courseId),
                'curriculum' => fetch_curriculum($pdo, $courseId),
            ]);
        }

        case 'lecture_duration': {
            $in = body_json();
            $courseId = (int)($in['course_id'] ?? 0);
            $id = (int)($in['id'] ?? 0);
            $duration = (int)($in['duration_sec'] ?? 0);
            if ($courseId <= 0 || $id <= 0) json_out(['ok' => false, 'error' => 'id gerekli'], 422);
            assert_course_access($pdo, $courseId, $sessionRole, $sessionInstructorId);
            if ($duration < 0) $duration = 0;
            $pdo->prepare(
                "UPDATE course_lectures SET duration_sec = ? WHERE id = ? AND course_id = ?"
            )->execute([$duration, $id, $courseId]);
            $total = sync_course_duration($pdo, $courseId);
            json_out([
                'ok' => true,
                'duration_sec' => $duration,
                'total_duration_sec' => $total,
                'curriculum' => fetch_curriculum($pdo, $courseId),
            ]);
        }

        default:
            json_out(['ok' => false, 'error' => 'Bilinmeyen action'], 400);
    }
} catch (Throwable $e) {
    error_log('egitmen.php: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Sunucu hatası'], 500);
}

/* ---------- helpers ---------- */

function assert_course_access(PDO $pdo, $courseId, $role, $instructorId) {
    $courseId = (int)$courseId;
    if ($courseId <= 0) json_out(['ok' => false, 'error' => 'Kurs bulunamadı'], 404);
    if ($role === 'admin') return;
    $st = $pdo->prepare("SELECT instructor_id FROM courses WHERE id = ?");
    $st->execute([$courseId]);
    $row = $st->fetch();
    if (!$row) json_out(['ok' => false, 'error' => 'Kurs bulunamadı'], 404);
    if ((int)$row['instructor_id'] !== (int)$instructorId) {
        json_out(['ok' => false, 'error' => 'Bu kursa erişim yetkiniz yok'], 403);
    }
}

/** Eğitmen kendi profili; admin GET instructor_id veya bağlı/ilk profil */
function egitmen_profile_id($role, $instructorId, PDO $pdo) {
    if ($role === 'egitmen') {
        if ($instructorId <= 0) {
            json_out(['ok' => false, 'error' => 'Profil bağlı değil'], 403);
        }
        return (int)$instructorId;
    }
    $req = (int)($_GET['instructor_id'] ?? 0);
    if ($req > 0) return $req;
    if ($instructorId > 0) return (int)$instructorId;
    $first = (int)$pdo->query("SELECT id FROM instructors ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($first <= 0) json_out(['ok' => false, 'error' => 'Önce admin panelinden eğitmen profili oluşturun'], 422);
    return $first;
}

function fetch_lines(PDO $pdo, $table, $courseId) {
    $stmt = $pdo->prepare("SELECT id, body, sort_order FROM `$table` WHERE course_id = ? ORDER BY sort_order, id");
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

function replace_lines(PDO $pdo, $table, $courseId, array $lines) {
    $pdo->prepare("DELETE FROM `$table` WHERE course_id = ?")->execute([$courseId]);
    $ins = $pdo->prepare("INSERT INTO `$table` (course_id, body, sort_order) VALUES (?,?,?)");
    $i = 0;
    foreach ($lines as $line) {
        $body = is_array($line) ? clean($line['body'] ?? '') : clean($line);
        if ($body === '') continue;
        $ins->execute([$courseId, mb_substr($body, 0, 500), $i++]);
    }
}

function fetch_curriculum(PDO $pdo, $courseId) {
    $secs = $pdo->prepare("SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order, id");
    $secs->execute([$courseId]);
    $sections = $secs->fetchAll();
    $lec = $pdo->prepare(
        "SELECT * FROM course_lectures WHERE course_id = ? ORDER BY sort_order, id"
    );
    $lec->execute([$courseId]);
    $lectures = $lec->fetchAll();
    $bySec = [];
    foreach ($lectures as $l) {
        $raw = $l['resources'] ?? '[]';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $l['resources'] = is_array($decoded) ? $decoded : [];
        $l['description'] = (string)($l['description'] ?? '');
        $l['is_preview'] = (int)($l['is_preview'] ?? 0);
        $bySec[$l['section_id']][] = $l;
    }
    foreach ($sections as &$s) {
        $s['lectures'] = $bySec[$s['id']] ?? [];
    }
    return $sections;
}

function sync_course_duration(PDO $pdo, $courseId) {
    $courseId = (int)$courseId;
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(duration_sec), 0) FROM course_lectures WHERE course_id = ?"
    );
    $stmt->execute([$courseId]);
    $sum = (int)$stmt->fetchColumn();
    $pdo->prepare("UPDATE courses SET duration_sec = ? WHERE id = ?")->execute([$sum, $courseId]);
    return $sum;
}

function fetch_course(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['objectives'] = fetch_lines($pdo, 'course_objectives', $id);
    $row['requirements'] = fetch_lines($pdo, 'course_requirements', $id);
    $row['audience'] = fetch_lines($pdo, 'course_audience', $id);
    $row['curriculum'] = fetch_curriculum($pdo, $id);
    $sumStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(duration_sec), 0) FROM course_lectures WHERE course_id = ?"
    );
    $sumStmt->execute([$id]);
    $row['total_duration_sec'] = (int)$sumStmt->fetchColumn();
    return $row;
}

function seed_default_lines(PDO $pdo, $courseId) {
    $ins = $pdo->prepare("INSERT INTO course_objectives (course_id, body, sort_order) VALUES (?,?,?)");
    for ($i = 0; $i < 4; $i++) {
        $ins->execute([$courseId, '', $i]);
    }
    $pdo->prepare("INSERT INTO course_requirements (course_id, body, sort_order) VALUES (?,?,?)")
        ->execute([$courseId, '', 0]);
    $pdo->prepare("INSERT INTO course_audience (course_id, body, sort_order) VALUES (?,?,?)")
        ->execute([$courseId, '', 0]);
}

function delete_course_files($courseId) {
    $dir = __DIR__ . '/../uploads/courses/' . (int)$courseId;
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/** Eğitmen kendi kursları; admin için null = tüm kurslar. */
function egitmen_accessible_course_ids(PDO $pdo, $role, $instructorId): ?array {
    if ($role !== 'egitmen') {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM courses WHERE instructor_id = ?');
    $st->execute([(int)$instructorId]);
    return array_map('intval', array_column($st->fetchAll(), 'id'));
}
