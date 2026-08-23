<?php
/**
 * Ders izleme ilerlemesi — oynatıcıdan periyodik kayıt.
 *
 * POST JSON: lecture_id, seconds, duration, completed (0/1)
 */
require_once __DIR__ . '/student_account.php';

start_student_session();
require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Yalnızca POST'], 405);
}

$in = body_json();
if ($in === []) {
    $in = $_POST;
}

if (!student_csrf_valid((string)($in['csrf'] ?? ''))) {
    json_out(['ok' => false, 'error' => 'Oturum doğrulanamadı. Sayfayı yenileyin.'], 403);
}

$studentId = current_student_id();
session_write_close();

$lectureId = (int)($in['lecture_id'] ?? 0);
$seconds = (int)($in['seconds'] ?? 0);
$duration = (int)($in['duration'] ?? 0);
$forceComplete = !empty($in['completed']);

if ($lectureId <= 0) {
    json_out(['ok' => false, 'error' => 'Ders gerekli'], 422);
}
$seconds = max(0, $seconds);
$duration = max(0, $duration);

try {
    $pdo = db();
    progress_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id, course_id, is_preview, video_path, duration_sec FROM course_lectures WHERE id = ?'
    );
    $st->execute([$lectureId]);
    $lec = $st->fetch();
    if (!$lec) {
        json_out(['ok' => false, 'error' => 'Ders bulunamadı'], 404);
    }
    $courseId = (int)$lec['course_id'];
    $preview = (int)($lec['is_preview'] ?? 0) === 1;
    $paid = student_has_paid_access($pdo, $studentId, $courseId);
    if (!$paid && !$preview) {
        json_out(['ok' => false, 'error' => 'Bu derse erişim yok'], 403);
    }

    $dur = $duration > 0 ? $duration : (int)($lec['duration_sec'] ?? 0);
    if ($dur > 0 && $seconds > $dur + 2) {
        $seconds = $dur;
    }

    $complete = $forceComplete;
    if (!$complete && $dur > 0 && $seconds >= (int)floor($dur * 0.9)) {
        $complete = true;
    }

    $completedAt = $complete ? date('Y-m-d H:i:s') : null;

    $pdo->prepare(
        "INSERT INTO course_lecture_progress
            (student_id, lecture_id, course_id, position_sec, max_sec, duration_sec, completed_at)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            position_sec = VALUES(position_sec),
            max_sec = GREATEST(max_sec, VALUES(max_sec)),
            duration_sec = IF(VALUES(duration_sec) > 0, VALUES(duration_sec), duration_sec),
            completed_at = COALESCE(completed_at, VALUES(completed_at))"
    )->execute([
        $studentId,
        $lectureId,
        $courseId,
        $seconds,
        $seconds,
        $dur,
        $completedAt,
    ]);

    if ($paid) {
        $pdo->prepare(
            "UPDATE course_enrollments
             SET last_lecture_id = ?, last_seconds = ?, last_visit_at = NOW()
             WHERE student_id = ? AND course_id = ? AND payment_status = 'paid'"
        )->execute([$lectureId, $seconds, $studentId, $courseId]);
        $pct = student_recalc_course_progress($pdo, $studentId, $courseId);
    } else {
        $pct = 0;
    }

    $done = $pdo->prepare(
        'SELECT completed_at FROM course_lecture_progress WHERE student_id = ? AND lecture_id = ?'
    );
    $done->execute([$studentId, $lectureId]);
    $row = $done->fetch();

    json_out([
        'ok' => true,
        'progress_pct' => $pct,
        'completed' => !empty($row['completed_at']),
        'seconds' => $seconds,
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'İlerleme kaydedilemedi'], 500);
}
