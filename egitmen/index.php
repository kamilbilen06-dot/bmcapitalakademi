<?php
require_once __DIR__ . '/../api/helpers.php';
require_once __DIR__ . '/../api/site_brand.php';
start_admin_session();
if (!is_logged_in()) { header('Location: login.php'); exit; }
$adminUser = htmlspecialchars($_SESSION['admin_user'] ?? 'egitmen');
$isSiteAdmin = is_site_admin();
$instructorId = current_instructor_id();
if (!$isSiteAdmin && $instructorId <= 0) {
    header('Location: login.php');
    exit;
}
$csrfToken = admin_csrf_token();
session_write_close();

$publicUrl = htmlspecialchars(site_public_url(), ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8');

$jsPath = __DIR__ . '/assets/egitmen.js';
$js = is_file($jsPath) ? file_get_contents($jsPath) : '';
$js = str_replace('</script>', '<\/script>', $js);
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eğitmen Paneli — <?= $brandName ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/egitmen/assets/egitmen.css?v=20260824a">
</head>
<body>
  <div class="shell">
    <aside class="sidebar" id="sidebar">
      <div class="side-brand">
        <span class="side-brand-mark"><i class="fa-solid fa-graduation-cap"></i></span>
        <div class="side-brand-text">
          <strong>Akademi</strong>
          <span>Eğitmen Paneli</span>
        </div>
      </div>

      <div class="side-course" id="coursePickerWrap" hidden>
        <label>Aktif kurs</label>
        <select id="coursePicker"></select>
      </div>

      <nav class="side-nav">
        <a href="#dashboard" class="nav-item active" data-view="dashboard" title="Genel Bakış">
          <i class="fa-solid fa-border-all"></i><span>Genel Bakış</span>
        </a>
        <a href="#courses" class="nav-item" data-view="courses" title="Kurslarım">
          <i class="fa-solid fa-graduation-cap"></i><span>Kurslarım</span>
        </a>
        <a href="#curriculum" class="nav-item" data-view="curriculum" data-needs-course title="İçerik Yönetimi">
          <i class="fa-solid fa-folder-open"></i><span>İçerik Yönetimi</span>
        </a>
        <a href="#students" class="nav-item" data-view="students" data-needs-course title="Öğrenciler">
          <i class="fa-solid fa-users"></i><span>Öğrenciler</span>
        </a>
        <a href="#subscribers" class="nav-item" data-view="subscribers" title="Aboneler">
          <i class="fa-brands fa-whatsapp"></i><span>Aboneler</span>
        </a>
        <a href="#pricing" class="nav-item" data-view="pricing" data-needs-course title="Fiyatlandırma">
          <i class="fa-solid fa-chart-line"></i><span>Fiyatlandırma</span>
        </a>
        <a href="#landing" class="nav-item" data-view="landing" data-needs-course title="Açılış Sayfası">
          <i class="fa-solid fa-display"></i><span>Açılış Sayfası</span>
        </a>
        <a href="#goals" class="nav-item" data-view="goals" data-needs-course title="Hedef Öğrenciler">
          <i class="fa-solid fa-bullseye"></i><span>Hedef Öğrenciler</span>
        </a>
        <a href="#publish" class="nav-item" data-view="publish" data-needs-course title="Yayın Durumu">
          <i class="fa-solid fa-rocket"></i><span>Yayın Durumu</span>
        </a>
        <a href="#profile" class="nav-item" data-view="profile" title="Ayarlar">
          <i class="fa-solid fa-gear"></i><span>Ayarlar</span>
        </a>
        <a href="/egitmen/logout.php" class="nav-logout" title="Çıkış">
          <i class="fa-solid fa-right-from-bracket"></i><span>Çıkış</span>
        </a>
      </nav>

      <div class="side-foot">
        <a href="/egitmen/logout.php" class="side-logout">Çıkış yap</a>
        <a href="#profile" class="side-user" id="sideUser" title="Profil">
          <div class="side-user-avatar" id="sideUserAvatar"><i class="fa-solid fa-user"></i></div>
          <div class="side-user-meta">
            <strong id="sideUserName"><?= $adminUser ?></strong>
            <span id="sideUserRole">Eğitmen</span>
          </div>
        </a>
      </div>
    </aside>

    <main class="content">
      <header class="topbar">
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Menü">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-spacer"></div>
        <div class="topbar-actions">
          <button type="button" class="btn-primary sm" id="btnTopNewCourse">
            <i class="fa-solid fa-plus"></i> <span>Yeni Kurs Oluştur</span>
          </button>
          <a href="/egitmen/logout.php" class="top-logout">Çıkış yap</a>
          <button type="button" class="top-user" id="btnTopUser" title="Profil">
            <span id="topUserName"><?= $adminUser ?></span>
            <span class="top-user-avatar" id="topUserAvatar"><i class="fa-solid fa-user"></i></span>
          </button>
        </div>
        <h1 id="viewTitle" class="sr-only">Genel Bakış</h1>
      </header>
      <div class="view-wrap" id="viewWrap">
        <div class="loading" id="bootStatus">Panel yükleniyor…</div>
      </div>
    </main>
  </div>

  <div class="toast" id="toast" hidden></div>
  <script>
    window.SITE_PUBLIC_URL = <?= json_encode(site_public_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.SITE_BRAND_NAME = <?= json_encode(BRAND_NAME, JSON_UNESCAPED_UNICODE) ?>;
    window.__egitmenBooted = false;
    window.__egitmenErr = null;
    window.onerror = function (msg) {
      window.__egitmenErr = String(msg || "bilinmeyen hata");
    };
    setTimeout(function () {
      if (window.__egitmenBooted) return;
      var el = document.getElementById("viewWrap");
      if (!el) return;
      var detail = window.__egitmenErr ? (" (" + window.__egitmenErr + ")") : "";
      el.innerHTML = 'Panel scripti çalışmadı' + detail + '. <a href="/egitmen/index.php?t=' + Date.now() + '">Yenile</a>';
    }, 3000);
  </script>
  <script src="/assets/js/photo-crop.js?v=20260801a"></script>
  <script type="module" src="/egitmen/assets/video-compress.js?v=20260902a"></script>
  <script>window.BM_ADMIN_CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
  <script>
<?= $js ?>
  </script>
</body>
</html>
