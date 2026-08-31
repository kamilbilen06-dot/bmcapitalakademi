/**
 * Anonim ziyaretçi takibi (yönetim paneli).
 * bm_vid çerezi aynı tarayıcıyı sonraki ziyaretlerde birleştirir.
 */
(function () {
  if (/\/(admin|egitmen)\//i.test(location.pathname)) return;
  if (!window.fetch) return;

  function visitorId() {
    var k = "bm_vid";
    var id = "";
    try {
      id = localStorage.getItem(k) || "";
    } catch (e) {}
    if (!/^[A-F0-9]{8}$/i.test(id)) {
      var m = document.cookie.match(/(?:^|; )bm_vid=([A-F0-9]{8})/i);
      if (m) id = m[1];
    }
    if (!/^[A-F0-9]{8}$/i.test(id)) {
      id = "";
      var hex = "0123456789ABCDEF";
      for (var i = 0; i < 8; i++) id += hex.charAt(Math.floor(Math.random() * 16));
    }
    id = id.toUpperCase();
    try {
      localStorage.setItem(k, id);
    } catch (e) {}
    document.cookie = "bm_vid=" + id + ";path=/;max-age=34560000;samesite=lax";
    return id;
  }

  try {
    fetch("/api/track.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        vid: visitorId(),
        path: location.pathname + (location.search || ""),
        title: document.title || "",
        referrer: document.referrer || "",
      }),
      credentials: "same-origin",
      keepalive: true,
    }).catch(function () {});
  } catch (e) {}
})();
