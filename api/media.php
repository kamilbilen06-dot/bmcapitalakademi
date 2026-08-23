<?php
/**
 * Korumalı medya akışı — ders videosu, tanıtım filmi, ders kaynağı.
 *
 * GET /api/media.php?kind=lecture|promo|resource&id=...&exp=...&sig=...
 * Range (HTTP 206) desteklenir; videoda ileri-geri sarma bununla çalışır.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/media_lib.php';

$kind = strtolower(trim((string)($_GET['kind'] ?? 'lecture')));
$id = (int)($_GET['id'] ?? 0);
$exp = (int)($_GET['exp'] ?? 0);
$sig = trim((string)($_GET['sig'] ?? ''));
$index = (int)($_GET['i'] ?? 0);

if (!in_array($kind, ['lecture', 'promo', 'resource'], true) || $id <= 0) {
    media_deny(400, 'Geçersiz istek.');
}

$sigOk = media_sig_ok($kind, $id, $exp, $sig, $index);

try {
    $pdo = db();
} catch (Throwable $e) {
    media_deny(500, 'Servis geçici olarak kullanılamıyor.');
}

if ($kind === 'promo') {
    $st = $pdo->prepare('SELECT id, promo_video_path, status FROM courses WHERE id = ?');
    $st->execute([$id]);
    $course = $st->fetch();
    if (!$course) {
        media_deny(404, 'Kurs bulunamadı.');
    }
    $published = (string)($course['status'] ?? '') === 'published';
    $allowed = $published || media_instructor_owns($pdo, $id);
    if (!$allowed) {
        media_deny(403, 'Bu videoya erişim yok.');
    }
    $abs = media_abs_path((string)$course['promo_video_path']);
    if ($abs === null) {
        media_deny(404, 'Video bulunamadı.');
    }
    media_stream_file($abs);
}

$st = $pdo->prepare('SELECT * FROM course_lectures WHERE id = ?');
$st->execute([$id]);
$lecture = $st->fetch();
if (!$lecture) {
    media_deny(404, 'Ders bulunamadı.');
}

if (!media_can_access_lecture($pdo, $lecture, $sigOk)) {
    media_deny(403, 'Bu derse erişim yok.');
}

if ($kind === 'lecture') {
    $abs = media_abs_path((string)($lecture['video_path'] ?? ''));
    if ($abs === null) {
        media_deny(404, 'Video bulunamadı.');
    }
    media_stream_file($abs);
}

// resource
$raw = $lecture['resources'] ?? '[]';
$resources = is_string($raw) ? json_decode($raw, true) : $raw;
if (!is_array($resources) || $index < 1 || $index > count($resources)) {
    media_deny(404, 'Kaynak bulunamadı.');
}
$res = $resources[$index - 1];
if (!is_array($res)) {
    media_deny(404, 'Kaynak bulunamadı.');
}
$type = (string)($res['type'] ?? 'file');
$url = trim((string)($res['url'] ?? ''));
if ($type === 'link' || preg_match('#^https?://#i', $url)) {
    media_deny(400, 'Harici bağlantı bu uçtan indirmez.');
}
$abs = media_abs_path($url);
if ($abs === null) {
    media_deny(404, 'Kaynak dosyası bulunamadı.');
}
$name = trim((string)($res['name'] ?? ''));
media_stream_file($abs, $name !== '' ? $name : basename($abs), true);
