/**
 * Yönetilebilir içerik bayrakları (frontend).
 * Varsayılanlar pasiftir; api/public.php ayarları geldiğinde main.js günceller.
 */
window.BM_FLAGS = {
  nisan2026Active: false,
  meteAkyolActive: false,
  metematikselHediyeActive: false,
};

(function () {
  function setVisibility(el, visible) {
    el.hidden = !visible;
    if (visible) {
      el.removeAttribute("aria-hidden");
      el.style.display = "";
    } else {
      el.setAttribute("aria-hidden", "true");
      el.style.display = "none";
    }
  }

  function apply(next) {
    var flags = next || window.BM_FLAGS || {};
    window.BM_FLAGS = {
      nisan2026Active: !!flags.nisan2026Active,
      meteAkyolActive: !!flags.meteAkyolActive,
      metematikselHediyeActive: !!flags.metematikselHediyeActive,
    };

    document.documentElement.classList.toggle(
      "bm-mete-off",
      !window.BM_FLAGS.meteAkyolActive
    );
    document.querySelectorAll('[data-bm-feature="mete-akyol"]').forEach(function (el) {
      setVisibility(el, window.BM_FLAGS.meteAkyolActive);
    });
    document.querySelectorAll('[data-bm-feature="metematiksel-hediye"]').forEach(function (el) {
      setVisibility(el, window.BM_FLAGS.metematikselHediyeActive);
    });
    document.querySelectorAll('[data-bm-feature="nisan-2026"]').forEach(function (el) {
      setVisibility(el, window.BM_FLAGS.nisan2026Active);
    });
    document.querySelectorAll(".bm-mete-off-only").forEach(function (el) {
      setVisibility(el, !window.BM_FLAGS.meteAkyolActive);
    });
  }

  window.BM_FEATURES = { apply: apply };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () { apply(); });
  } else {
    apply();
  }
})();
