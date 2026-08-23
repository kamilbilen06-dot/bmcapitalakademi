<?php
/**
 * LMS oynatıcı — müfredat + korumalı video.
 * Ödemiş öğrenci tüm dersleri izler; misafir yalnızca ücretsiz önizlemeyi.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/../api/media_lib.php';
require_once __DIR__ . '/_layout.php';

start_student_session();
$session = current_student();

$rawId = trim((string)($_GET['id'] ?? $_GET['course'] ?? ''));
$courseId = 0;
if (preg_match('/^course-(\d+)$/i', $rawId, $m)) {
    $courseId = (int)$m[1];
} else {
    $courseId = (int)$rawId;
}
$wantLecture = (int)($_GET['ders'] ?? $_GET['lecture'] ?? 0);

$course = null;
$sections = [];
$error = '';
$paid = false;
$instructor = false;
$student = $session;
$progressMap = [];
$resumeLectureId = 0;
$trackProgress = false;

try {
    $pdo = db();
    students_ensure_schema($pdo);
    if ($courseId <= 0) {
        $error = 'Kurs bulunamadı.';
    } else {
        $st = $pdo->prepare(
            'SELECT c.*, i.name AS instructor_name
             FROM courses c
             LEFT JOIN instructors i ON i.id = c.instructor_id
             WHERE c.id = ?'
        );
        $st->execute([$courseId]);
        $course = $st->fetch() ?: null;
        if (!$course) {
            $error = 'Kurs bulunamadı.';
        } else {
            $sid = $session ? (int)$session['id'] : 0;
            $paid = $sid > 0 && student_has_paid_access($pdo, $sid, $courseId);
            $instructor = media_instructor_owns($pdo, $courseId);
            $published = (string)($course['status'] ?? '') === 'published';
            if (!$paid && !$instructor && !$published) {
                $error = 'Bu eğitim yayında değil.';
                $course = null;
            }
        }
    }

    if ($course) {
        $secSt = $pdo->prepare(
            'SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order, id'
        );
        $secSt->execute([$courseId]);
        $sections = $secSt->fetchAll();
        $lecSt = $pdo->prepare(
            'SELECT * FROM course_lectures WHERE course_id = ? ORDER BY sort_order, id'
        );
        $lecSt->execute([$courseId]);
        $bySec = [];
        foreach ($lecSt->fetchAll() as $l) {
            $bySec[(int)$l['section_id']][] = $l;
        }
        foreach ($sections as &$sec) {
            $sec['lectures'] = $bySec[(int)$sec['id']] ?? [];
        }
        unset($sec);

        if ($session) {
            $row = student_find_by_id($pdo, (int)$session['id']);
            if ($row) {
                $student = $row;
            }
        }
    }
} catch (Throwable $e) {
    $error = 'Oynatıcı yüklenemedi.';
    $course = null;
}

$hasAccess = $paid || $instructor;
$trackProgress = $paid && $session;
$courseRef = 'course-' . $courseId;
$lecturesFlat = [];
$active = null;

if ($course && $trackProgress) {
    $progressMap = student_lecture_progress_map($pdo, (int)$session['id'], $courseId);
    $resume = student_enrollment_resume($pdo, (int)$session['id'], $courseId);
    $resumeLectureId = (int)($resume['last_lecture_id'] ?? 0);
}

if ($course) {
    foreach ($sections as $sec) {
        foreach ($sec['lectures'] as $lec) {
            $lecId = (int)$lec['id'];
            $preview = (int)($lec['is_preview'] ?? 0) === 1;
            $hasVideo = trim((string)($lec['video_path'] ?? '')) !== '';
            $unlocked = $hasAccess || $preview;
            $src = '';
            if ($unlocked && $hasVideo) {
                $src = '../' . media_signed_query('lecture', $lecId);
            }
            $resources = [];
            $rawRes = $lec['resources'] ?? '[]';
            $decoded = is_string($rawRes) ? json_decode($rawRes, true) : $rawRes;
            if ($unlocked && is_array($decoded)) {
                foreach ($decoded as $i => $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $url = trim((string)($r['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    $type = (string)($r['type'] ?? 'file');
                    $href = $url;
                    if ($type !== 'link' && !preg_match('#^https?://#i', $url)) {
                        $href = '../' . media_signed_query('resource', $lecId, 14400, $i + 1);
                    }
                    $resources[] = [
                        'name' => (string)($r['name'] ?? 'Kaynak'),
                        'href' => $href,
                        'type' => $type === 'link' ? 'link' : 'file',
                    ];
                }
            }
            $item = [
                'id' => $lecId,
                'sectionId' => (int)$sec['id'],
                'title' => trim((string)($lec['title'] ?? '')) ?: ('Ders ' . $lecId),
                'description' => (string)($lec['description'] ?? ''),
                'duration' => student_format_duration($lec['duration_sec'] ?? 0),
                'preview' => $preview,
                'hasVideo' => $hasVideo,
                'locked' => !$unlocked,
                'src' => $src,
                'resources' => $resources,
                'completed' => !empty($progressMap[$lecId]['completed_at']),
                'startAt' => (int)($progressMap[$lecId]['position_sec'] ?? 0),
            ];
            $lecturesFlat[] = $item;
            if ($wantLecture === $lecId) {
                $active = $item;
            }
        }
    }
    if (!$active && $resumeLectureId > 0) {
        foreach ($lecturesFlat as $item) {
            if ((int)$item['id'] === $resumeLectureId && empty($item['locked'])) {
                $active = $item;
                break;
            }
        }
    }
    if (!$active) {
        foreach ($lecturesFlat as $item) {
            if (!$item['locked'] && $item['hasVideo'] && empty($item['completed'])) {
                $active = $item;
                break;
            }
        }
    }
    if (!$active) {
        foreach ($lecturesFlat as $item) {
            if (!$item['locked'] && $item['hasVideo']) {
                $active = $item;
                break;
            }
        }
    }
    if (!$active && $lecturesFlat) {
        $active = $lecturesFlat[0];
    }
}

$buyUrl = '../odeme.php?course=' . rawurlencode($courseRef);
$loginUrl = 'giris.php?next=' . rawurlencode('kurs.php?id=' . $courseRef . ($wantLecture ? '&ders=' . $wantLecture : ''));

$payload = [
    'courseId' => $courseId,
    'courseRef' => $courseRef,
    'title' => $course['title'] ?? '',
    'paid' => $hasAccess,
    'buyUrl' => $buyUrl,
    'activeId' => is_array($active) ? (int)$active['id'] : 0,
    'trackProgress' => $trackProgress,
    'csrf' => $trackProgress ? student_csrf_token() : '',
    'progressUrl' => '../api/progress.php',
    'lectures' => $lecturesFlat,
];

ogrenci_head($course ? ($course['title'] . ' · Oynatıcı') : 'Oynatıcı', 'page-player');
?>
  <header class="player-top">
    <div class="player-top-inner">
      <?php if ($hasAccess && $session): ?>
        <a class="player-back" href="index.php"><i class="fa-solid fa-arrow-left"></i> Kurslarım</a>
      <?php else: ?>
        <a class="player-back" href="../egitim-detay.html?id=<?= ogrenci_e($courseRef) ?>"><i class="fa-solid fa-arrow-left"></i> Eğitim sayfası</a>
      <?php endif; ?>
      <div class="player-top-title">
        <b><?= ogrenci_e($course['title'] ?? 'Eğitim') ?></b>
        <span id="player-now"><?= ogrenci_e(is_array($active) ? $active['title'] : '') ?></span>
      </div>
      <div class="player-top-actions">
        <?php if ($instructor && !$paid): ?>
          <span class="player-chip">Eğitmen önizleme</span>
        <?php endif; ?>
        <?php if (!$hasAccess && $course): ?>
          <?php if (!$session): ?>
            <a class="btn btn-ghost btn-sm" href="<?= ogrenci_e($loginUrl) ?>">Giriş yap</a>
          <?php endif; ?>
          <a class="btn btn-primary btn-sm" href="<?= ogrenci_e($buyUrl) ?>">Satın al</a>
        <?php elseif ($session): ?>
          <span class="player-user"><?= ogrenci_e($student['name'] ?? $session['name'] ?? '') ?></span>
        <?php endif; ?>
      </div>
    </div>
  </header>

<?php if ($error !== '' || !$course): ?>
  <div class="player-empty">
    <div class="empty">
      <div class="ico"><i class="fa-solid fa-circle-exclamation"></i></div>
      <h3><?= ogrenci_e($error !== '' ? $error : 'Kurs bulunamadı') ?></h3>
      <p>Kataloğa dönüp eğitimi seçebilirsiniz.</p>
      <div class="empty-actions">
        <a class="btn btn-primary" href="../egitimler.html">Eğitimlere git</a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="player-layout">
    <aside class="player-rail" id="player-rail">
      <div class="player-rail-head">
        <strong>Müfredat</strong>
        <span><?= count($lecturesFlat) ?> ders</span>
        <button type="button" class="player-rail-close" id="player-rail-close" aria-label="Müfredatı kapat"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="player-sections">
        <?php
        $n = 0;
        foreach ($sections as $si => $sec):
            $secTitle = trim((string)($sec['title'] ?? ''));
            $secTitle = $secTitle !== '' ? $secTitle : ('Bölüm ' . ($si + 1));
            $open = $active && (int)$sec['id'] === (int)$active['sectionId'];
            ?>
          <details class="player-sec" <?= $open ? 'open' : '' ?>>
            <summary>
              <span>Bölüm <?= $si + 1 ?>: <?= ogrenci_e($secTitle) ?></span>
              <small><?= count($sec['lectures']) ?> ders</small>
            </summary>
            <ul>
              <?php foreach ($sec['lectures'] as $lec):
                  $n++;
                  $lecId = (int)$lec['id'];
                  $isActive = $active && $lecId === (int)$active['id'];
                  $preview = (int)($lec['is_preview'] ?? 0) === 1;
                  $unlocked = $hasAccess || $preview;
                  $dur = student_format_duration($lec['duration_sec'] ?? 0);
                  $done = !empty($progressMap[$lecId]['completed_at']);
                  $icon = !$unlocked ? 'fa-lock' : ($done ? 'fa-circle-check' : 'fa-circle-play');
                  ?>
                <li>
                  <a class="player-lec<?= $isActive ? ' is-active' : '' ?><?= $unlocked ? '' : ' is-locked' ?><?= $done ? ' is-done' : '' ?>"
                     href="kurs.php?id=<?= ogrenci_e($courseRef) ?>&ders=<?= $lecId ?>"
                     data-lec="<?= $lecId ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span class="player-lec-body">
                      <b><?= $n ?>. <?= ogrenci_e(trim((string)$lec['title']) ?: ('Ders ' . $n)) ?></b>
                      <small>
                        <?php if ($dur !== ''): ?><?= ogrenci_e($dur) ?><?php endif; ?>
                        <?php if ($preview): ?> · Önizleme<?php endif; ?>
                      </small>
                    </span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
        <?php if (!$sections): ?>
          <p class="player-rail-empty">Bu eğitime henüz ders eklenmedi.</p>
        <?php endif; ?>
      </div>
    </aside>

    <main class="player-stage">
      <button type="button" class="player-toggle-rail" id="player-toggle-rail">
        <i class="fa-solid fa-list-ul"></i> Müfredat
      </button>
      <div class="player-media">
      <div class="player-video-wrap" id="player-video-wrap">
        <?php if ($active && !$active['locked'] && $active['src'] !== ''): ?>
          <video id="player-video" controls playsinline controlslist="nodownload" src="<?= ogrenci_e($active['src']) ?>"></video>
        <?php elseif ($active && $active['locked']): ?>
          <div class="player-lock" id="player-lock">
            <i class="fa-solid fa-lock"></i>
            <h2>Bu ders kilitli</h2>
            <p>Tüm müfredata erişmek için eğitimi satın alın.</p>
            <a class="btn btn-primary" href="<?= ogrenci_e($buyUrl) ?>">Satın al</a>
          </div>
        <?php else: ?>
          <div class="player-lock" id="player-lock">
            <i class="fa-regular fa-circle-play"></i>
            <h2>İzlenecek video yok</h2>
            <p><?= $hasAccess ? 'Eğitmen henüz video yüklememiş olabilir.' : 'Ücretsiz önizleme dersi yok. Satın alarak tüm içeriği açabilirsiniz.' ?></p>
            <?php if (!$hasAccess): ?>
              <a class="btn btn-primary" href="<?= ogrenci_e($buyUrl) ?>">Satın al</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <button type="button" class="player-fs-btn" id="player-fs-btn" title="Tam ekran" aria-label="Tam ekran">
        <i class="fa-solid fa-expand"></i>
      </button>
      <div class="player-seek-hud" id="player-seek-hud" hidden></div>
      </div>

      <section class="player-info">
        <div class="player-info-top">
          <h1 id="player-info-title"><?= ogrenci_e(is_array($active) ? $active['title'] : ($course['title'] ?? '')) ?></h1>
          <?php if (!empty($course['instructor_name'])): ?>
            <p class="player-inst">Eğitmen: <?= ogrenci_e($course['instructor_name']) ?></p>
          <?php endif; ?>
          <?php if ($trackProgress): ?>
            <button type="button" class="player-mark-done" id="player-mark-done"<?= (is_array($active) && !empty($active['completed'])) ? ' hidden' : '' ?>>
              <i class="fa-solid fa-circle-check"></i> Dersi tamamlandı işaretle
            </button>
          <?php endif; ?>
        </div>
        <div class="player-desc" id="player-desc" <?= (is_array($active) && ($active['description'] ?? '') !== '') ? '' : 'hidden' ?>>
          <?= nl2br(ogrenci_e(is_array($active) ? ($active['description'] ?? '') : '')) ?>
        </div>
        <div class="player-res" id="player-res" <?= (is_array($active) && !empty($active['resources'])) ? '' : 'hidden' ?>>
          <h2>Kaynaklar</h2>
          <ul id="player-res-list">
            <?php foreach ((is_array($active) ? ($active['resources'] ?? []) : []) as $res): ?>
              <li>
                <a href="<?= ogrenci_e($res['href']) ?>" target="_blank" rel="noopener">
                  <i class="fa-solid <?= $res['type'] === 'link' ? 'fa-link' : 'fa-paperclip' ?>"></i>
                  <?= ogrenci_e($res['name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>
    </main>
  </div>
  <script type="application/json" id="player-data"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="assets/player.js"></script>
<?php endif; ?>
<?php
ogrenci_foot();
