<?php
/**
 * Sepetim — tarayıcıdaki sepeti listeler, ödemeye yönlendirir.
 */
require_once __DIR__ . '/_layout.php';

list($student, $loadError) = ogrenci_panel_student('sepetim.php');

$ownedIds = [];
try {
    $pdo = db();
    $ownedIds = student_paid_course_ids($pdo, (int)$student['id']);
} catch (Throwable $e) {
    $ownedIds = [];
}

ogrenci_head('Sepetim', 'page-app');
ogrenci_app_bar($student);
ogrenci_panel_start($student, 'sepet', 'Sepetim', 'Her eğitim ayrı ödenir; sepeti tek seferde toplu ödemezsiniz.');
?>

<?php if ($loadError !== ''): ?>
  <div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><span><?= ogrenci_e($loadError) ?></span></div>
<?php endif; ?>

<div id="cartRoot"></div>

<?php
ogrenci_panel_end();
?>
  <script src="../assets/js/cart.js"></script>
  <script>
  (function () {
    var root = document.getElementById("cartRoot");
    if (!root || !window.BM_CART) return;

    var owned = <?= json_encode(array_values($ownedIds), JSON_UNESCAPED_UNICODE) ?>;
    if (owned && owned.length && window.BM_CART.pruneOwned) {
      BM_CART.pruneOwned(owned);
    }

    function esc(s) {
      return String(s == null ? "" : s)
        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function render() {
      var items = BM_CART.list();
      if (!items.length) {
        root.innerHTML =
          '<div class="empty">' +
          '<div class="ico"><i class="fa-solid fa-cart-shopping"></i></div>' +
          "<h3>Sepetiniz boş</h3>" +
          "<p>Satın almak istediğiniz eğitimi açıp <b>Sepete ekle</b> deyin. Ödeme her kurs için ayrı alınır.</p>" +
          '<div class="empty-actions">' +
          '<a class="btn btn-primary" href="../egitimler.html">Eğitimlere git</a>' +
          "</div></div>";
        return;
      }

      var rows = items.map(function (it) {
        var img = it.image
          ? '<img src="../' + esc(it.image.replace(/^\//, "")) + '" alt="">'
          : '<span class="cart-ph"><i class="fa-solid fa-graduation-cap"></i></span>';
        var href = it.href ? "../" + it.href.replace(/^\.\.\//, "") : "../egitimler.html";
        var pay = "../odeme.php?course=" + encodeURIComponent(it.id);
        return (
          '<article class="cart-row">' +
          '<a class="cart-media" href="' + esc(href) + '">' + img + "</a>" +
          '<div class="cart-meta">' +
          '<a href="' + esc(href) + '"><b>' + esc(it.title) + "</b></a>" +
          "<span>Süresiz erişim</span>" +
          "</div>" +
          '<div class="cart-price">' + esc(it.price) + "</div>" +
          '<div class="cart-actions">' +
          '<a class="btn btn-primary btn-sm" href="' + esc(pay) + '">Bu eğitimi öde</a>' +
          '<button type="button" class="btn-text" data-remove="' + esc(it.id) + '">Kaldır</button>' +
          "</div></article>"
        );
      }).join("");

      root.innerHTML =
        '<p class="hint" style="margin:0 0 14px">Birden fazla eğitim varsa her birini ayrı ödersiniz. Toplu “sepeti öde” yoktur.</p>' +
        '<div class="cart-list">' + rows + "</div>";
      root.querySelectorAll("[data-remove]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          BM_CART.remove(btn.getAttribute("data-remove"));
          render();
        });
      });
    }

    render();
  })();
  </script>
<?php
ogrenci_foot();
