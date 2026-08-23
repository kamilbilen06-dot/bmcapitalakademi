<?php
/**
 * Kurslarım — öğrencinin kayıtlı olduğu eğitimler ve ilerleme durumu.
 */
require_once __DIR__ . '/../api/student_account.php';
require_once __DIR__ . '/_layout.php';

start_student_session();
require_student_page('index.php');

$session = current_student();
$student = null;
$courses = [];
$loadError = '';

try {
    $pdo = db();
    students_ensure_schema($pdo);
    $student = student_find_by_id($pdo, $session['id']);
    if (!$student || ($student['status'] ?? 'active') !== 'active') {
        student_logout();
        header('Location: giris.php');
        exit;
    }
    require_once __DIR__ . '/../api/iyzico_client.php';
    payments_ensure_schema($pdo);
    payments_sync_iyzico_refunds($pdo, (int)$student['id']);
    $courses = student_courses($pdo, (int)$student['id']);
} catch (Throwable $e) {
    $loadError = 'Kurslarınız yüklenemedi. Veritabanı bağlantısını kontrol edin.';
    $student = $student ?: ['id' => $session['id'], 'name' => $session['name'], 'email' => $session['email']];
}

$paid = array_values(array_filter($courses, static fn($c) => ($c['payment_status'] ?? '') === 'paid'));
$pending = array_values(array_filter($courses, static function ($c) {
    $s = (string)($c['payment_status'] ?? '');
    return $s !== 'paid' && $s !== 'refunded';
}));
$completed = count(array_filter($paid, static fn($c) => (int)$c['progress_pct'] >= 100));

/** Tek kurs kartı */
function ogrenci_course_card(array $c): void {
    $isPaid = ($c['payment_status'] ?? '') === 'paid';
    $courseRef = 'course-' . (int)$c['course_id'];
    $progress = max(0, min(100, (int)$c['progress_pct']));
    $duration = student_format_duration($c['duration_sec'] ?? 0);
    $playHref = $isPaid
        ? ('kurs.php?id=' . ogrenci_e($courseRef) . (!empty($c['last_lecture_id']) ? '&ders=' . (int)$c['last_lecture_id'] : ''))
        : ('../odeme.php?course=' . ogrenci_e($courseRef));
    ?>
    <article class="course-card">
      <a class="course-card-cover" href="<?= $playHref ?>" aria-label="<?= ogrenci_e(($c['title'] ?: 'Eğitim') . ($isPaid ? ' — eğitime git' : ' — ödemeyi tamamla')) ?>"></a>
      <div class="course-media">
        <span class="course-badge <?= $isPaid ? 'paid' : 'pending' ?>">
          <?= $isPaid ? 'Erişim açık' : 'Ödeme bekliyor' ?>
        </span>
        <?php if (!empty($c['image_path'])): ?>
          <img src="../<?= ogrenci_e(ltrim((string)$c['image_path'], '/')) ?>" alt="<?= ogrenci_e($c['title']) ?>" loading="lazy">
        <?php else: ?>
          <i class="fa-solid fa-chart-line ph"></i>
        <?php endif; ?>
      </div>

      <div class="course-body">
        <h3><?= ogrenci_e($c['title'] ?: 'Eğitim') ?></h3>
        <p class="course-inst">
          <?php if (!empty($c['instructor_name'])): ?>
            Eğitmen:
            <?php if (!empty($c['instructor_slug'])): ?>
              <a href="../egitmen-profil.html?id=<?= ogrenci_e($c['instructor_slug']) ?>"><?= ogrenci_e($c['instructor_name']) ?></a>
            <?php else: ?>
              <?= ogrenci_e($c['instructor_name']) ?>
            <?php endif; ?>
          <?php else: ?>
            &nbsp;
          <?php endif; ?>
        </p>

        <div class="course-meta">
          <?php if ((int)($c['lecture_count'] ?? 0) > 0): ?>
            <span><i class="fa-solid fa-list-check"></i> <?= (int)$c['lecture_count'] ?> ders</span>
          <?php endif; ?>
          <?php if ($duration !== ''): ?>
            <span><i class="fa-regular fa-clock"></i> <?= ogrenci_e($duration) ?></span>
          <?php endif; ?>
          <?php if (!empty($c['level'])): ?>
            <span><i class="fa-solid fa-signal"></i> <?= ogrenci_e($c['level']) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($isPaid): ?>
          <div class="progress">
            <div class="progress-head">
              <span><?= $progress >= 100 ? 'Tamamlandı' : 'İlerleme' ?></span>
              <span><?= $progress ?>%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill <?= $progress >= 100 ? 'done' : '' ?>" style="width: <?= $progress ?>%"></div>
            </div>
          </div>
          <div class="course-actions">
            <a class="btn btn-primary btn-sm" href="kurs.php?id=<?= ogrenci_e($courseRef) ?><?= !empty($c['last_lecture_id']) ? '&ders=' . (int)$c['last_lecture_id'] : '' ?>">
              <i class="fa-solid fa-circle-play"></i> <?= $progress > 0 ? 'Eğitime devam et' : 'Eğitime başla' ?>
            </a>
            <a class="btn btn-dark btn-sm" href="../egitim-detay.html?id=<?= ogrenci_e($courseRef) ?>">
              <i class="fa-solid fa-circle-info"></i> Eğitim sayfası
            </a>
          </div>
        <?php else: ?>
          <div class="course-actions">
            <a class="btn btn-primary btn-sm" href="../odeme.php?course=<?= ogrenci_e($courseRef) ?>">
              <i class="fa-solid fa-credit-card"></i> Ödemeyi tamamla
            </a>
          </div>
          <p class="pending-note">
            <i class="fa-solid fa-hourglass-half"></i>
            Kaydınız alındı, ödeme onayı bekleniyor. Havale yaptıysanız dekontu iletmeniz yeterli.
          </p>
        <?php endif; ?>
      </div>
    </article>
    <?php
}

ogrenci_head('Kurslarım', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'kurslarim', 'Kurslarım');
?>

<?php if ($loadError !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($loadError) ?></span></div>
<?php endif; ?>

<?php if (isset($_GET['welcome'])): ?>
  <div class="alert alert-ok">
    <i class="fa-solid fa-circle-check"></i>
    <span>Hesabınız oluşturuldu, hoş geldiniz! Eğitimlerinizi bu sayfadan takip edebilirsiniz.</span>
  </div>
<?php endif; ?>

<?php if (!$courses): ?>
  <div class="empty">
    <div class="ico"><i class="fa-solid fa-graduation-cap"></i></div>
    <h3>Henüz bir eğitiminiz yok</h3>
    <p>Hesabınız hazır. Kataloğa göz atın, size uygun eğitimi seçin; satın aldığınız anda burada görünecek.</p>
    <div class="empty-actions">
      <a class="btn btn-primary btn-lg" href="../egitimler.html"><i class="fa-solid fa-compass"></i> Eğitimleri keşfet</a>
      <a class="btn btn-outline btn-lg" href="../iletisim.html">Bize danışın</a>
    </div>
  </div>
<?php else: ?>
  <?php if ($paid): ?>
    <div class="section-bar">
      <div>
        <h2>Eğitimlerim <span class="count-chip"><?= count($paid) ?></span></h2>
        <p><?= $completed > 0 ? $completed . ' eğitimi tamamladınız.' : 'Erişiminiz açık olan eğitimler.' ?></p>
      </div>
    </div>
    <div class="course-grid">
      <?php foreach ($paid as $c) ogrenci_course_card($c); ?>
    </div>
  <?php endif; ?>

  <?php if ($pending): ?>
    <div class="section-bar" style="margin-top: <?= $paid ? '44px' : '0' ?>;">
      <div>
        <h2>Ödeme bekleyen kayıtlar <span class="count-chip"><?= count($pending) ?></span></h2>
        <p>Ödeme onaylandığında erişiminiz otomatik açılır.</p>
      </div>
    </div>
    <div class="course-grid">
      <?php foreach ($pending as $c) ogrenci_course_card($c); ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
ogrenci_panel_end();
ogrenci_foot();
