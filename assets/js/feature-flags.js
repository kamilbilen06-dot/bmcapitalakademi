/**
 * Geçici özellik bayrakları (frontend).
 *
 * Dr. Mete Akyol + sosyal + kitap/WhatsApp hediyesini tekrar göstermek için:
 *   meteAkyolActive: true
 * ve api/feature_flags.php içinde FEATURE_METE_AKYOL_ACTIVE → true
 */
window.BM_FLAGS = {
  meteAkyolActive: false,
};

(function applyMeteFeatureFlag() {
  var flags = window.BM_FLAGS || {};
  var active = !!flags.meteAkyolActive;

  function apply() {
    if (!active) {
      document.documentElement.classList.add("bm-mete-off");
      document.querySelectorAll('[data-bm-feature="mete-akyol"]').forEach(function (el) {
        el.hidden = true;
        el.setAttribute("aria-hidden", "true");
        el.style.display = "none";
      });
      document.querySelectorAll(".bm-mete-off-only").forEach(function (el) {
        el.hidden = false;
        el.removeAttribute("aria-hidden");
        el.style.display = "";
      });
    } else {
      document.documentElement.classList.remove("bm-mete-off");
      document.querySelectorAll(".bm-mete-off-only").forEach(function (el) {
        el.hidden = true;
        el.setAttribute("aria-hidden", "true");
        el.style.display = "none";
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", apply);
  } else {
    apply();
  }
})();
