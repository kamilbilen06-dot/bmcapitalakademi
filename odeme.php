<?php
/**
 * Ödeme sayfası — öğrenci girişi zorunlu.
 *
 * Kart ile ödeme iyzico Checkout Form üzerinden yapılır. Tutar her zaman
 * veritabanındaki kurs fiyatından okunur; sayfadaki hiçbir alan fiyatı
 * etkilemez.
 *
 * Kabul edilen adres: /odeme.php?course=12 veya ?course=course-12 (?id= de olur)
 */
require_once __DIR__ . '/api/student_account.php';
require_once __DIR__ . '/api/payments_schema.php';
require_once __DIR__ . '/api/iyzico_config.php';

start_student_session();

$courseRaw = trim((string)($_GET['course'] ?? $_GET['id'] ?? ''));
$selfUrl = 'odeme.php' . ($courseRaw !== '' ? '?course=' . rawurlencode($courseRaw) : '');

if (!is_student()) {
    header('Location: ogrenci/giris.php?next=' . rawurlencode('../' . $selfUrl));
    exit;
}

$error = isset($_GET['err']) ? mb_substr(clean($_GET['err']), 0, 300) : '';
$student = null;
$course = null;
$courses = [];
$amountKurus = 0;
$alreadyOwned = false;

$courseId = 0;
if (is_numeric($courseRaw)) {
    $courseId = (int)$courseRaw;
} elseif (preg_match('/^course-(\d+)$/', $courseRaw, $m)) {
    $courseId = (int)$m[1];
}

try {
    $pdo = db();
    students_ensure_schema($pdo);
    payments_ensure_schema($pdo);

    $session = current_student();
    $student = student_find_by_id($pdo, $session['id']);
    if (!$student || ($student['status'] ?? 'active') !== 'active') {
        student_logout();
        header('Location: ogrenci/giris.php');
        exit;
    }

    $courses = $pdo->query(
        "SELECT id, title, price, price_note, image_path FROM courses
         WHERE status = 'published' ORDER BY sort_order, title"
    )->fetchAll();

    if ($courseId > 0) {
        foreach ($courses as $c) {
            if ((int)$c['id'] === $courseId) {
                $course = $c;
                break;
            }
        }
    }
    if ($course) {
        $amountKurus = payments_amount_kurus($course['price']);
        $alreadyOwned = student_has_paid_access($pdo, (int)$student['id'], (int)$course['id']);
    }
} catch (Throwable $e) {
    $error = $error !== '' ? $error : 'Sayfa yüklenemedi. Veritabanı bağlantısını kontrol edin.';
    $student = $student ?: ['id' => 0, 'name' => '', 'email' => '', 'phone' => ''];
}

$csrf = student_csrf_token();
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$payable = $course && $amountKurus >= 100 && !$alreadyOwned;
$phone = trim((string)($student['phone'] ?? ''));
$needPhone = $phone === '';
$priceLabel = number_format($amountKurus / 100, 2, ',', '.') . ' TL';
$img = trim((string)($course['image_path'] ?? ''));
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ödeme | <?= $e(BRAND_NAME) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css?v=chrome1">
  <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body class="page-checkout">
  <div id="site-header"></div>

  <main class="ck-page">
    <div class="container">
      <nav class="ck-crumbs" aria-label="Konum">
        <a href="index.html">Anasayfa</a>
        <span>/</span>
        <a href="ogrenci/sepetim.php">Sepetim</a>
        <span>/</span>
        <b>Ödeme</b>
      </nav>
      <h1 class="ck-title">Ödeme</h1>

      <?php if ($error !== ''): ?>
        <div class="ck-alert err"><i class="fa-solid fa-circle-exclamation"></i><span><?= $e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($alreadyOwned): ?>
        <div class="ck-card" style="max-width:560px;">
          <div class="ck-alert ok" style="margin:0 0 16px;"><i class="fa-solid fa-circle-check"></i><span>Bu eğitime erişiminiz zaten açık.</span></div>
          <a class="ck-pay" href="ogrenci/index.php"><i class="fa-solid fa-graduation-cap"></i> Kurslarıma git</a>
        </div>

      <?php elseif (!$course): ?>
        <div class="ck-card" style="max-width:640px;">
          <h2 style="margin:0 0 8px;">Eğitim seçin</h2>
          <p class="lead">Ödemesini yapmak istediğiniz eğitimi seçin.</p>
          <?php if (!$courses): ?>
            <div class="ck-alert warn"><i class="fa-solid fa-circle-info"></i><span>Şu an yayında ödeme alınabilir bir eğitim yok.</span></div>
          <?php else: ?>
            <div style="display:grid;gap:10px;">
              <?php foreach ($courses as $c): ?>
                <a href="odeme.php?course=course-<?= (int)$c['id'] ?>" class="ck-method" style="justify-content:space-between;">
                  <span><?= $e($c['title'] ?: 'Eğitim') ?></span>
                  <b><?= $e($c['price'] ?: 'Bilgi al') ?></b>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($amountKurus < 100): ?>
        <div class="ck-card" style="max-width:560px;">
          <div class="ck-alert warn" style="margin:0 0 16px;">
            <i class="fa-solid fa-circle-info"></i>
            <span>Bu eğitim için online ödeme tutarı tanımlı değil. Kayıt için bizimle iletişime geçin.</span>
          </div>
          <a class="ck-pay" href="iletisim.html">İletişime geç</a>
        </div>

      <?php else: ?>
        <form method="post" action="api/iyzico_checkout.php" id="payForm" data-guard>
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
          <input type="hidden" name="course_id" value="course-<?= (int)$course['id'] ?>">
          <?php if (!$needPhone): ?>
            <input type="hidden" name="phone" value="<?= $e($phone) ?>">
          <?php endif; ?>

          <div class="ck-grid">
            <div>
              <div class="ck-section">
                <h2>Fatura adresi</h2>
                <p class="lead">Bu sipariş için aşağıdaki fatura bilgileriniz kullanılacak.</p>
                <div class="ck-card">
                  <?php if ($needPhone): ?>
                    <div class="ck-field">
                      <label>Telefon *</label>
                      <input type="tel" name="phone" required autocomplete="tel" placeholder="05xx xxx xx xx">
                    </div>
                  <?php endif; ?>
                  <div class="ck-field">
                    <label>Adres</label>
                    <input type="text" name="address" autocomplete="street-address" placeholder="Mahalle, sokak, no">
                  </div>
                  <div class="ck-field" style="margin:0;">
                    <label>İl</label>
                    <input type="text" name="city" autocomplete="address-level1"
                           placeholder="<?= $e(defined('BRAND_CITY') ? BRAND_CITY : 'İzmir') ?>">
                  </div>
                </div>
              </div>

              <div class="ck-section">
                <h2>Ödeme bilgileri</h2>
                <p class="lead">Kullanılacak ödeme yöntemini seçin.</p>
                <div class="ck-methods">
                  <label class="ck-method is-on" data-pay="card">
                    <input type="radio" name="pay_ui" value="card" checked>
                    Kredi / banka kartı
                  </label>
                  <label class="ck-method" data-pay="havale">
                    <input type="radio" name="pay_ui" value="havale">
                    Havale / EFT
                  </label>
                </div>
              </div>

              <div class="ck-section" id="paneCard">
                <div class="ck-card">
                  <div class="ck-secure"><i class="fa-solid fa-lock"></i> Güvenli ve şifrelenmiş</div>
                  <p class="ck-note">Kart bilgileriniz iyzico’nun güvenli sayfasında girilir; bu sitede kart numarası tutulmaz. Onayladığınızda 3D Secure adımına yönlendirilirsiniz.</p>
                  <?php if (!iyzico_ready()): ?>
                    <div class="ck-alert warn" style="margin:0;"><i class="fa-solid fa-triangle-exclamation"></i><span>Kart ile ödeme henüz yapılandırılmadı.</span></div>
                  <?php elseif (iyzico_is_sandbox()): ?>
                    <div class="ck-alert warn" style="margin:0;"><i class="fa-solid fa-flask"></i><span>Test modu: gerçek para çekilmez, yalnızca iyzico test kartları çalışır.</span></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="ck-section" id="paneHavale" hidden>
                <div class="ck-card ck-iban">
                  <div class="row"><span>Banka</span><b>Yapı Kredi</b></div>
                  <div class="row"><span>Hesap adı</span><b>Marmara Revizyon A.Ş.</b></div>
                  <div class="row"><span>IBAN</span><b>TR61 0006 7010 0000 0076 3994 01</b></div>
                  <div class="row"><span>Açıklama</span><b>Ad soyad + eğitim adı</b></div>
                  <a class="ck-wa" href="https://wa.me/905334490966" target="_blank" rel="noopener">
                    <i class="fa-brands fa-whatsapp"></i> Dekontu WhatsApp’tan gönder
                  </a>
                </div>
              </div>
            </div>

            <aside class="ck-side">
              <h3>Sipariş detayı</h3>
              <div class="ck-item">
                <?php if ($img !== ''): ?>
                  <img src="<?= $e(ltrim($img, '/')) ?>" alt="">
                <?php else: ?>
                  <span class="ph"><i class="fa-solid fa-graduation-cap"></i></span>
                <?php endif; ?>
                <div>
                  <b><?= $e($course['title'] ?: 'Eğitim') ?></b>
                  <em><?= $e($priceLabel) ?></em>
                </div>
              </div>
              <div class="ck-total">
                <span>Ödenecek tutar</span>
                <b><?= $e($priceLabel) ?></b>
              </div>
              <label class="ck-legal">
                <input type="checkbox" name="terms" value="1" id="ckTerms">
                <span>
                  <a href="yasal/on-bilgilendirme.html" target="_blank" rel="noopener">Ön Bilgilendirme Formu</a>’nu ve
                  <a href="yasal/mesafeli-satis.html" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>’ni okudum ve onaylıyorum.
                </span>
              </label>
              <button type="submit" class="ck-pay" id="ckPayBtn" disabled>
                <i class="fa-solid fa-lock"></i> Ödemeyi tamamla
              </button>
              <div class="ck-guarantee">
                <b>Ödeme onayında erişim açılır</b>
                Kart çekimi başarılı olduğunda eğitim Kurslarım sayfanızda görünür.
              </div>
              <?php if (iyzico_ready() && iyzico_is_sandbox()): ?>
                <div class="ck-test">
                  Test (sandbox) · gerçek para yok · kart <b>5528 7900 0000 0008</b> · 12/30 · CVC 123 · SMS 123456<br>
                  İşlemler yalnızca <b>sandbox-merchant.iyzipay.com</b> panelinde görünür.
                </div>
              <?php endif; ?>
            </aside>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <div id="site-footer"></div>
  <div id="site-floaters"></div>

  <script src="assets/js/feature-flags.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/catalog.js"></script>
  <script src="assets/js/main.js?v=20260831a"></script>
  <script>
  (function () {
    var form = document.getElementById("payForm");
    if (!form) return;
    var terms = document.getElementById("ckTerms");
    var btn = document.getElementById("ckPayBtn");
    var paneCard = document.getElementById("paneCard");
    var paneHavale = document.getElementById("paneHavale");
    var method = "card";

    function sync() {
      if (btn) btn.disabled = !(terms && terms.checked) || method !== "card";
    }
    if (terms) terms.addEventListener("change", sync);

    document.querySelectorAll(".ck-method").forEach(function (lab) {
      lab.addEventListener("click", function () {
        document.querySelectorAll(".ck-method").forEach(function (x) { x.classList.remove("is-on"); });
        lab.classList.add("is-on");
        method = lab.getAttribute("data-pay") || "card";
        if (paneCard) paneCard.hidden = method !== "card";
        if (paneHavale) paneHavale.hidden = method !== "havale";
        sync();
      });
    });

    form.addEventListener("submit", function (ev) {
      if (method !== "card") {
        ev.preventDefault();
        return;
      }
      if (!terms || !terms.checked) {
        ev.preventDefault();
      }
    });
    sync();
  })();
  </script>
</body>
</html>
