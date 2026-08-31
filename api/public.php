<?php
/**
 * Public içerik API'si - site tarafı bu endpoint'ten eğitim/ürün/SSS verilerini çeker.
 * Yönetim paneli modülleri + eğitmen panelinde "Yayında" olan kurslar birleşir.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/feature_flags.php';
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/instructors_schema.php';
require_once __DIR__ . '/auth_schema.php';

header('Access-Control-Allow-Origin: *');

try {
    $pdo = db();
    egitmen_ensure_schema($pdo);
    instructors_ensure_schema($pdo);
    auth_ensure_schema($pdo);

    $rows = $pdo->query("SELECT * FROM modules ORDER BY sort_order ASC, id ASC")->fetchAll();
    $egitimler = [];
    $urunler = [];
    foreach ($rows as $r) {
        $m = module_to_public($r);
        if ($r['type'] === 'urun') $urunler[] = $m; else $egitimler[] = $m;
    }

    // Eğitmen panelinden yayınlanan kurslar
    $published = $pdo->query(
        "SELECT * FROM courses WHERE status = 'published' ORDER BY sort_order ASC, updated_at DESC, id DESC"
    )->fetchAll();
    foreach ($published as $cr) {
        $egitimler[] = course_to_public($pdo, $cr);
    }

    $sss = [];
    foreach ($pdo->query("SELECT question, answer FROM faqs ORDER BY sort_order ASC, id ASC") as $f) {
        $sss[] = ['soru' => $f['question'], 'cevap' => $f['answer']];
    }

    $settings = [];
    foreach ($pdo->query("SELECT k, v FROM settings") as $s) { $settings[$s['k']] = $s['v']; }

    require_once __DIR__ . '/site_brand.php';
    $brand = site_brand_payload();

    $site = [
        'marka' => trim((string)($settings['marka'] ?? '')) !== ''
            ? $settings['marka']
            : $brand['marka'],
        'brandMark' => $brand['mark'],
        'brandWord' => $brand['word'],
        'brandTagline' => $brand['tagline'],
        'brandShort' => $brand['short'],
        'publicUrl' => $brand['publicUrl'],
        'telefon' => $settings['telefon'] ?? '',
        'whatsapp' => $settings['whatsapp'] ?? '',
        'instagram' => $settings['instagram'] ?? '',
        'twitter' => $settings['twitter'] ?? '',
        'iban' => $settings['iban'] ?? '',
        'banka' => $settings['banka'] ?? '',
        'hesapAdi' => $settings['hesap_adi'] ?? '',
        'sehir' => trim((string)($settings['sehir'] ?? '')) !== ''
            ? $settings['sehir']
            : $brand['sehir'],
        'saticiUnvan' => trim((string)($settings['satici_unvan'] ?? '')),
        'saticiAdres' => trim((string)($settings['satici_adres'] ?? '')),
        'saticiVergi' => trim((string)($settings['satici_vergi'] ?? '')),
        'saticiMersis' => trim((string)($settings['satici_mersis'] ?? '')),
        'navHakkimizda' => (($settings['nav_hakkimizda'] ?? '0') === '1'),
        'navSss' => (($settings['nav_sss'] ?? '0') === '1'),
        'navIletisim' => (($settings['nav_iletisim'] ?? '0') === '1'),
        'navAraclar' => (($settings['nav_araclar'] ?? '1') === '1'),
        'emailjs' => [
            'publicKey' => $settings['emailjs_public'] ?? '',
            'serviceId' => $settings['emailjs_service'] ?? '',
            'templateId' => $settings['emailjs_template'] ?? '',
            'toEmail' => $settings['emailjs_to'] ?? '',
        ],
    ];

    $egitmenProfilleri = [];
    foreach ($pdo->query(
        "SELECT * FROM instructors WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
    ) as $ins) {
        $egitmenProfilleri[] = instructor_to_public($ins);
    }

    $payload = [
        'ok' => true,
        'egitimler' => $egitimler,
        'urunler' => $urunler,
        'sss' => $sss,
        'site' => $site,
        'egitmenProfilleri' => $egitmenProfilleri,
    ];
    feature_filter_public_payload($payload);
    json_out($payload);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Veri alınamadı'], 500);
}

function module_to_public($r) {
    $data = json_decode($r['data'] ?? '{}', true) ?: [];
    return [
        'id' => $r['slug'],
        'tip' => $r['type'],
        'oneCikan' => (bool)$r['featured'],
        'baslik' => $r['title'],
        'kisaAciklama' => $r['short_desc'],
        'gorsel' => $r['image'],
        'video' => $r['video'] ?: null,
        'videoPoster' => $r['video_poster'] ?: null,
        'fiyat' => ($r['price'] === '' ? null : $r['price']),
        'fiyatNot' => $r['price_note'],
        'sure' => $r['duration'],
        'egitimTuru' => $r['egitim_turu'],
        'egitmenler' => $r['instructors'],
        'etiket' => $r['etiket'],
        'katilimNot' => $r['katilim_not'],
        'tarihNot' => $r['tarih_not'],
        'ozellikler' => $data['ozellikler'] ?? [],
        'aciklama' => $data['aciklama'] ?? [],
        'hediye' => $data['hediye'] ?? [],
        'hediyeGorsel' => $data['hediyeGorsel'] ?? '',
        'tarihler' => $data['tarihler'] ?? [],
        'mufredat' => $data['mufredat'] ?? [],
    ];
}

function course_to_public(PDO $pdo, array $r) {
    $id = (int)$r['id'];
    $objectives = course_line_bodies($pdo, 'course_objectives', $id);
    $requirements = course_line_bodies($pdo, 'course_requirements', $id);
    $audience = course_line_bodies($pdo, 'course_audience', $id);
    $mufredat = course_mufredat_public($pdo, $id);
    // Süre: ders videolarının toplamından hesaplanır (manuel yazılmaz)
    $totalSec = course_total_duration_sec($pdo, $id);
    if ($totalSec <= 0) {
        $totalSec = (int)($r['duration_sec'] ?? 0);
    }

    $desc = trim((string)($r['description'] ?? ''));
    $aciklama = [];
    if ($desc !== '') {
        $parts = preg_split('/\r\n\r\n|\n\n/', $desc) ?: [$desc];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') $aciklama[] = $p;
        }
    }

    $subtitle = trim((string)($r['subtitle'] ?? ''));
    $kisa = $subtitle !== '' ? $subtitle : mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 180);
    if ($kisa === '') $kisa = 'BM Capital Akademi eğitimi';

    $gorsel = trim((string)($r['image_path'] ?? ''));
    $video = trim((string)($r['promo_video_path'] ?? ''));
    $tur = trim((string)($r['category'] ?? ''));
    if ($tur === '') $tur = trim((string)($r['level'] ?? ''));
    if ($tur === '') $tur = 'Online Eğitim';

    $ozellikler = $objectives;
    if (!$ozellikler && $audience) {
        $ozellikler = $audience;
    }

    $egitmenAd = 'Dr. Kamil BİLEN';
    $insId = (int)($r['instructor_id'] ?? 0);
    if ($insId > 0) {
        $st = $pdo->prepare("SELECT name, slug FROM instructors WHERE id = ? AND is_active = 1");
        $st->execute([$insId]);
        $ins = $st->fetch();
        if ($ins && trim((string)$ins['name']) !== '') {
            $egitmenAd = $ins['name'];
        }
    }

    return [
        'id' => 'course-' . $id,
        'tip' => 'egitim',
        'kaynak' => 'egitmen',
        'oneCikan' => false,
        'baslik' => $r['title'] !== '' ? $r['title'] : 'Adsız Kurs',
        'kisaAciklama' => $kisa,
        'gorsel' => $gorsel !== '' ? $gorsel : null,
        'video' => $video !== '' ? ('api/media.php?kind=promo&id=' . $id) : null,
        'videoPoster' => $gorsel !== '' ? $gorsel : null,
        'fiyat' => ($r['price'] === '' ? null : $r['price']),
        'fiyatNot' => $r['price_note'] ?? '',
        'sure' => format_duration_tr($totalSec),
        'sureSn' => $totalSec,
        'egitimTuru' => $tur,
        'egitmenler' => $egitmenAd,
        'etiket' => $r['topic'] ?? '',
        'katilimNot' => $requirements ? implode(' · ', $requirements) : '',
        'tarihNot' => '',
        'ozellikler' => $ozellikler,
        'aciklama' => $aciklama,
        'hediye' => [],
        'hediyeGorsel' => '',
        'tarihler' => [],
        'mufredat' => $mufredat,
    ];
}

function course_total_duration_sec(PDO $pdo, $courseId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_sec), 0) FROM course_lectures WHERE course_id = ?");
    $stmt->execute([(int)$courseId]);
    return (int)$stmt->fetchColumn();
}

function format_duration_tr($seconds) {
    $seconds = (int)$seconds;
    if ($seconds <= 0) return '';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0 && $m > 0) return $h . ' saat ' . $m . ' dakika';
    if ($h > 0) return $h . ' saat';
    return $m . ' dakika';
}

function course_line_bodies(PDO $pdo, $table, $courseId) {
    $stmt = $pdo->prepare("SELECT body FROM `$table` WHERE course_id = ? ORDER BY sort_order, id");
    $stmt->execute([(int)$courseId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $b = trim((string)($row['body'] ?? ''));
        if ($b !== '') $out[] = $b;
    }
    return $out;
}

function course_mufredat_public(PDO $pdo, $courseId) {
    $secs = $pdo->prepare("SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order, id");
    $secs->execute([(int)$courseId]);
    $sections = $secs->fetchAll();
    if (!$sections) return [];

    $lec = $pdo->prepare(
        "SELECT * FROM course_lectures WHERE course_id = ? ORDER BY sort_order, id"
    );
    $lec->execute([(int)$courseId]);
    $bySec = [];
    foreach ($lec->fetchAll() as $l) {
        $bySec[(int)$l['section_id']][] = $l;
    }

    $mufredat = [];
    $secNum = 0;
    $lecNum = 0;
    foreach ($sections as $sec) {
        $secNum++;
        $title = trim((string)($sec['title'] ?? ''));
        $baslik = $title !== '' ? ('Bölüm ' . $secNum . ': ' . $title) : ('Bölüm ' . $secNum);

        $maddeler = [];
        foreach ($bySec[(int)$sec['id']] ?? [] as $lecture) {
            $lecNum++;
            $lt = trim((string)($lecture['title'] ?? ''));
            $maddeler[] = [
                'baslik' => $lt !== '' ? ('Ders ' . $lecNum . ': ' . $lt) : ('Ders ' . $lecNum),
                'lectureId' => (int)$lecture['id'],
                'preview' => (int)($lecture['is_preview'] ?? 0) === 1,
            ];
        }

        $mufredat[] = [
            'baslik' => $baslik,
            'bolumler' => [[
                'baslik' => 'İçerik',
                'maddeler' => $maddeler,
            ]],
        ];
    }
    return $mufredat;
}
