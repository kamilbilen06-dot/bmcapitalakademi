<?php
require_once __DIR__ . '/../api/helpers.php';
start_admin_session();
if (!is_logged_in()) { header('Location: login.php'); exit; }
if (!is_site_admin()) { header('Location: ../egitmen/'); exit; }
$adminUser = htmlspecialchars($_SESSION['admin_user'] ?? 'admin');
$csrfToken = admin_csrf_token();
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yönetim Paneli — BM Capital</title>
  <link rel="stylesheet" href="assets/admin.css?v=20260825f">
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <div class="side-brand"><span class="bm">BM</span> <span>Capital</span></div>
      <nav class="side-nav">
        <a href="#" class="nav-item active" data-view="dashboard"><span class="ic">📊</span> Panel</a>
        <a href="#" class="nav-item" data-view="egitimler"><span class="ic">🎓</span> Eğitimler</a>
        <a href="#" class="nav-item" data-view="egitmenler"><span class="ic">👤</span> Eğitmenler</a>
        <a href="#" class="nav-item" data-view="ogrenciler"><span class="ic">👥</span> Öğrenciler</a>
        <a href="#" class="nav-item" data-view="satislar"><span class="ic">💰</span> Satışlar</a>
        <a href="#" class="nav-item" data-view="urunler"><span class="ic">🤖</span> Ürünler</a>
        <a href="#" class="nav-item" data-view="sss"><span class="ic">❓</span> S.S.S.</a>
        <a href="#" class="nav-item" data-view="odemeler"><span class="ic">🧾</span> Ödemeler</a>
        <a href="#" class="nav-item" data-view="abonelikler"><span class="ic">💬</span> Abonelikler</a>
        <a href="#" class="nav-item" data-view="ayarlar"><span class="ic">⚙️</span> Ayarlar</a>
      </nav>
      <div class="side-foot">
        <a href="../egitmen/" class="side-link">🎓 Eğitmen paneli</a>
        <a href="../index.html" target="_blank" class="side-link">🌐 Siteyi Gör</a>
        <a href="logout.php" class="side-link">🚪 Çıkış (<?= $adminUser ?>)</a>
      </div>
    </aside>

    <main class="content">
      <header class="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Menü">☰</button>
        <h1 id="viewTitle">Panel</h1>
        <button class="btn-primary sm" id="topAction" hidden></button>
      </header>

      <div class="view-wrap" id="viewWrap">
        <div class="loading">Yükleniyor…</div>
      </div>
    </main>
  </div>

  <!-- Modal -->
  <div class="modal-overlay" id="modal" hidden>
    <div class="modal">
      <div class="modal-head">
        <h2 id="modalTitle">Düzenle</h2>
        <button class="modal-close" id="modalClose">✕</button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <div class="toast" id="toast" hidden></div>

  <script src="../assets/js/photo-crop.js"></script>
  <script>window.BM_ADMIN_CSRF = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="assets/admin.js?v=20260830d"></script>
</body>
</html>
