/*
 * Öğrenci alanı — küçük arayüz davranışları.
 * Sayfalar sunucu tarafında çalışır; buradaki her şey isteğe bağlı iyileştirmedir.
 */
(function () {
  function initPasswordToggles() {
    document.querySelectorAll(".pw-toggle").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var input = btn.parentNode.querySelector("input");
        if (!input) return;
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.innerHTML = show
          ? '<i class="fa-regular fa-eye-slash"></i>'
          : '<i class="fa-regular fa-eye"></i>';
        btn.setAttribute("aria-label", show ? "Şifreyi gizle" : "Şifreyi göster");
      });
    });
  }

  // Çift gönderimi engelle
  function initSubmitGuard() {
    document.querySelectorAll("form[data-guard]").forEach(function (form) {
      form.addEventListener("submit", function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        btn.disabled = true;
        btn.dataset.label = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Gönderiliyor';
        // Sunucu doğrulama hatası dönerse buton kilitli kalmasın
        setTimeout(function () {
          btn.disabled = false;
          if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
        }, 25000);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initPasswordToggles();
    initSubmitGuard();
  });
})();
