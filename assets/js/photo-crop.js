/**
 * Yuvarlak profil fotoğrafı kırpıcı — pan + zoom, 1200×1200 JPEG çıktı.
 * Kullanım: BM_PHOTO_CROP.open({ file, onDone(blob, filename), onCancel })
 */
(function () {
  "use strict";

  var OUT = 1200;
  var VIEW = 360;

  function ensureStyles() {
    if (document.getElementById("bmPhotoCropStyles")) return;
    var s = document.createElement("style");
    s.id = "bmPhotoCropStyles";
    s.textContent =
      ".bm-crop-overlay{position:fixed;inset:0;background:rgba(15,23,35,.72);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;}" +
      ".bm-crop-modal{background:#fff;border-radius:14px;width:min(440px,100%);box-shadow:0 24px 60px rgba(0,0,0,.35);overflow:hidden;}" +
      ".bm-crop-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef1f5;}" +
      ".bm-crop-head h3{margin:0;font-size:16px;}" +
      ".bm-crop-head button{border:0;background:none;font-size:20px;cursor:pointer;color:#6a6f73;line-height:1;}" +
      ".bm-crop-body{padding:16px;}" +
      ".bm-crop-stage{position:relative;width:" +
      VIEW +
      "px;height:" +
      VIEW +
      "px;max-width:100%;margin:0 auto;background:#111;border-radius:50%;overflow:hidden;touch-action:none;cursor:grab;}" +
      ".bm-crop-stage:active{cursor:grabbing;}" +
      ".bm-crop-stage canvas{display:block;width:100%;height:100%;}" +
      ".bm-crop-hint{text-align:center;color:#6a6f73;font-size:13px;margin:12px 0 8px;}" +
      ".bm-crop-zoom{display:flex;align-items:center;gap:10px;margin-top:8px;}" +
      ".bm-crop-zoom label{font-size:13px;font-weight:600;color:#48566a;min-width:42px;}" +
      ".bm-crop-zoom input{flex:1;}" +
      ".bm-crop-actions{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px 16px;}" +
      ".bm-crop-actions button{border:1px solid #d5dde7;background:#fff;border-radius:8px;padding:9px 14px;font-weight:600;cursor:pointer;font:inherit;font-size:14px;}" +
      ".bm-crop-actions .primary{background:#1f4f8a;border-color:#1f4f8a;color:#fff;}";
    document.head.appendChild(s);
  }

  function open(opts) {
    opts = opts || {};
    var file = opts.file;
    if (!file) return;
    ensureStyles();

    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      buildUI(img, url, opts);
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      if (opts.onCancel) opts.onCancel();
      alert("Görsel yüklenemedi");
    };
    img.src = url;
  }

  function buildUI(img, objectUrl, opts) {
    var overlay = document.createElement("div");
    overlay.className = "bm-crop-overlay";
    overlay.innerHTML =
      '<div class="bm-crop-modal" role="dialog" aria-modal="true" aria-label="Fotoğraf kırp">' +
      '<div class="bm-crop-head"><h3>Profil fotoğrafını ayarla</h3><button type="button" class="bm-crop-x" aria-label="Kapat">✕</button></div>' +
      '<div class="bm-crop-body">' +
      '<div class="bm-crop-stage"><canvas width="' +
      VIEW +
      '" height="' +
      VIEW +
      '"></canvas></div>' +
      '<p class="bm-crop-hint">Sürükleyerek kaydırın, kaydırıcı ile yakınlaştırın. Yuvarlak alanda görünen kısım kaydedilir.</p>' +
      '<div class="bm-crop-zoom"><label>Zoom</label><input type="range" min="100" max="300" value="100" class="bm-crop-range"></div>' +
      "</div>" +
      '<div class="bm-crop-actions"><button type="button" class="bm-crop-cancel">İptal</button><button type="button" class="primary bm-crop-ok">Kırp ve yükle</button></div>' +
      "</div>";
    document.body.appendChild(overlay);

    var canvas = overlay.querySelector("canvas");
    var ctx = canvas.getContext("2d");
    var range = overlay.querySelector(".bm-crop-range");
    var stage = overlay.querySelector(".bm-crop-stage");

    var minScale = Math.max(VIEW / img.naturalWidth, VIEW / img.naturalHeight);
    var zoomFactor = 1;
    var scale = minScale;
    var ox =
      (VIEW - img.naturalWidth * scale) / 2;
    var oy =
      (VIEW - img.naturalHeight * scale) / 2;

    function clamp() {
      scale = minScale * zoomFactor;
      var dw = img.naturalWidth * scale;
      var dh = img.naturalHeight * scale;
      if (dw <= VIEW) ox = (VIEW - dw) / 2;
      else {
        ox = Math.min(0, Math.max(VIEW - dw, ox));
      }
      if (dh <= VIEW) oy = (VIEW - dh) / 2;
      else {
        oy = Math.min(0, Math.max(VIEW - dh, oy));
      }
    }

    function draw() {
      clamp();
      ctx.save();
      ctx.clearRect(0, 0, VIEW, VIEW);
      ctx.beginPath();
      ctx.arc(VIEW / 2, VIEW / 2, VIEW / 2, 0, Math.PI * 2);
      ctx.clip();
      ctx.fillStyle = "#111";
      ctx.fillRect(0, 0, VIEW, VIEW);
      ctx.drawImage(
        img,
        ox,
        oy,
        img.naturalWidth * scale,
        img.naturalHeight * scale
      );
      ctx.restore();
      // subtle ring
      ctx.beginPath();
      ctx.arc(VIEW / 2, VIEW / 2, VIEW / 2 - 1, 0, Math.PI * 2);
      ctx.strokeStyle = "rgba(255,255,255,0.35)";
      ctx.lineWidth = 2;
      ctx.stroke();
    }

    var dragging = false;
    var lastX = 0;
    var lastY = 0;

    function onDown(e) {
      dragging = true;
      var p = point(e);
      lastX = p.x;
      lastY = p.y;
      e.preventDefault();
    }
    function onMove(e) {
      if (!dragging) return;
      var p = point(e);
      ox += p.x - lastX;
      oy += p.y - lastY;
      lastX = p.x;
      lastY = p.y;
      draw();
      e.preventDefault();
    }
    function onUp() {
      dragging = false;
    }
    function point(e) {
      var t = e.touches && e.touches[0] ? e.touches[0] : e;
      var r = canvas.getBoundingClientRect();
      var sx = VIEW / r.width;
      var sy = VIEW / r.height;
      return { x: (t.clientX - r.left) * sx, y: (t.clientY - r.top) * sy };
    }

    stage.addEventListener("mousedown", onDown);
    stage.addEventListener("touchstart", onDown, { passive: false });
    window.addEventListener("mousemove", onMove);
    window.addEventListener("touchmove", onMove, { passive: false });
    window.addEventListener("mouseup", onUp);
    window.addEventListener("touchend", onUp);
    range.addEventListener("input", function () {
      zoomFactor = parseInt(range.value, 10) / 100;
      draw();
    });

    function cleanup() {
      window.removeEventListener("mousemove", onMove);
      window.removeEventListener("touchmove", onMove);
      window.removeEventListener("mouseup", onUp);
      window.removeEventListener("touchend", onUp);
      URL.revokeObjectURL(objectUrl);
      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }

    function close(cancel) {
      cleanup();
      if (cancel && opts.onCancel) opts.onCancel();
    }

    overlay.querySelector(".bm-crop-x").onclick = function () {
      close(true);
    };
    overlay.querySelector(".bm-crop-cancel").onclick = function () {
      close(true);
    };
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) close(true);
    });

    overlay.querySelector(".bm-crop-ok").onclick = function () {
      clamp();
      var out = document.createElement("canvas");
      out.width = OUT;
      out.height = OUT;
      var octx = out.getContext("2d");
      octx.fillStyle = "#fff";
      octx.fillRect(0, 0, OUT, OUT);
      var ratio = OUT / VIEW;
      octx.drawImage(
        img,
        ox * ratio,
        oy * ratio,
        img.naturalWidth * scale * ratio,
        img.naturalHeight * scale * ratio
      );
      out.toBlob(
        function (blob) {
          if (!blob) {
            alert("Kırpma başarısız");
            return;
          }
          cleanup();
          var name =
            "profile_" + Date.now() + ".jpg";
          if (opts.onDone) opts.onDone(blob, name);
        },
        "image/jpeg",
        0.93
      );
    };

    draw();
  }

  window.BM_PHOTO_CROP = {
    open: open,
    OUTPUT_SIZE: OUT,
  };
})();
