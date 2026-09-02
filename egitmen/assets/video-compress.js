/**
 * Tarayıcıda ders/tanıtım videosunu 720p mp4'e sıkıştırır (FFmpeg.wasm).
 * Sunucu post_max_size limitine takılmadan yükleme için.
 */
import { FFmpeg } from "https://cdn.jsdelivr.net/npm/@ffmpeg/ffmpeg@0.12.10/+esm";
import { fetchFile, toBlobURL } from "https://cdn.jsdelivr.net/npm/@ffmpeg/util@0.12.1/+esm";

var SKIP_BYTES = 28 * 1024 * 1024;
var SAFE_UPLOAD_BYTES = 118 * 1024 * 1024;

var ffmpeg = null;
var loadPromise = null;

function getDuration(file) {
  return new Promise(function (resolve) {
    var url = URL.createObjectURL(file);
    var v = document.createElement("video");
    v.preload = "metadata";
    var done = function (sec) {
      try {
        URL.revokeObjectURL(url);
      } catch (e) {}
      resolve(sec > 0 && isFinite(sec) ? sec : 0);
    };
    v.onloadedmetadata = function () {
      done(v.duration);
    };
    v.onerror = function () {
      done(0);
    };
    setTimeout(function () {
      done(0);
    }, 12000);
    v.src = url;
  });
}

function targetVideoKbps(durationSec) {
  if (!durationSec || durationSec <= 0) return 2200;
  var audioKbps = 128;
  var totalKbps =
    Math.floor((SAFE_UPLOAD_BYTES * 8) / durationSec / 1000) - audioKbps - 64;
  return Math.min(2800, Math.max(900, totalKbps));
}

function outName(name) {
  var base = String(name || "video").replace(/\.[^.]+$/, "");
  if (base.length > 80) base = base.slice(0, 80);
  return base + "_720p.mp4";
}

function ensureFfmpeg(onProgress) {
  if (loadPromise) return loadPromise;
  loadPromise = (async function () {
    if (onProgress) onProgress(0, "Sıkıştırma aracı yükleniyor…");
    var workerUrl =
      (typeof location !== "undefined" ? location.origin : "") +
      "/egitmen/assets/ffmpeg/esm/worker.js";
    ffmpeg = new FFmpeg({ classWorkerURL: workerUrl });
    ffmpeg.on("progress", function (ev) {
      var p = ev && ev.progress;
      if (onProgress && p >= 0 && p <= 1) {
        onProgress(Math.round(p * 100), "720p'ye dönüştürülüyor…");
      }
    });
    var base = "https://cdn.jsdelivr.net/npm/@ffmpeg/core@0.12.6/dist/umd";
    await ffmpeg.load({
      coreURL: await toBlobURL(base + "/ffmpeg-core.js", "text/javascript"),
      wasmURL: await toBlobURL(base + "/ffmpeg-core.wasm", "application/wasm"),
    });
  })();
  return loadPromise;
}

window.BMVideoCompress = {
  shouldCompress: function (file) {
    if (!file) return false;
    var type = String(file.type || "").toLowerCase();
    if (type.indexOf("video/") !== 0) {
      var n = String(file.name || "").toLowerCase();
      if (!/\.(mp4|webm|mov|m4v|mkv)$/.test(n)) return false;
    }
    return file.size > SKIP_BYTES;
  },

  compress: function (file, onProgress) {
    return ensureFfmpeg(onProgress).then(function () {
      return getDuration(file).then(function (duration) {
        var kbps = targetVideoKbps(duration);
        var inName = "in_" + Date.now() + ".mp4";
        var outFile = "out_" + Date.now() + ".mp4";
        if (onProgress) onProgress(1, "Dosya okunuyor…");
        return fetchFile(file).then(function (data) {
          return ffmpeg.writeFile(inName, data);
        }).then(function () {
          if (onProgress) onProgress(3, "720p'ye dönüştürülüyor…");
          return ffmpeg.exec([
            "-i",
            inName,
            "-vf",
            "scale='min(1280,iw)':-2",
            "-c:v",
            "libx264",
            "-preset",
            "fast",
            "-crf",
            "24",
            "-maxrate",
            String(kbps) + "k",
            "-bufsize",
            String(kbps * 2) + "k",
            "-c:a",
            "aac",
            "-b:a",
            "128k",
            "-movflags",
            "+faststart",
            "-y",
            outFile,
          ]);
        }).then(function () {
          return ffmpeg.readFile(outFile);
        }).then(function (data) {
          return Promise.all([
            ffmpeg.deleteFile(inName).catch(function () {}),
            ffmpeg.deleteFile(outFile).catch(function () {}),
          ]).then(function () {
            var blob = new Blob([data.buffer], { type: "video/mp4" });
            if (blob.size > SAFE_UPLOAD_BYTES) {
              throw new Error(
                "Sıkıştırma sonrası dosya hâlâ büyük (" +
                  Math.round(blob.size / 1048576) +
                  " MB). Videoyu kısaltın."
              );
            }
            return new File([blob], outName(file.name), { type: "video/mp4" });
          });
        });
      });
    });
  },
};
