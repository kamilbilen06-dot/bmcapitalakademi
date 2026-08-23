/*
 * LMS oynatıcı — ders değiştirme, YouTube tarzı klavye, ilerleme kaydı.
 */
(function () {
  var dataEl = document.getElementById("player-data");
  if (!dataEl) return;
  var data;
  try {
    data = JSON.parse(dataEl.textContent || "{}");
  } catch (e) {
    return;
  }
  var lectures = data.lectures || [];
  var byId = {};
  lectures.forEach(function (l) {
    byId[String(l.id)] = l;
  });
  var currentId = data.activeId || 0;
  var wrap = document.getElementById("player-video-wrap");
  var nowEl = document.getElementById("player-now");
  var titleEl = document.getElementById("player-info-title");
  var descEl = document.getElementById("player-desc");
  var resEl = document.getElementById("player-res");
  var resList = document.getElementById("player-res-list");
  var rail = document.getElementById("player-rail");
  var hud = document.getElementById("player-seek-hud");
  var markBtn = document.getElementById("player-mark-done");
  var hudTimer = 0;
  var saveTimer = 0;
  var lastSaved = -1;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function getVideo() {
    return document.getElementById("player-video");
  }

  function fullscreenEl() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }

  function exitFullscreenSafe() {
    var ex = document.exitFullscreen || document.webkitExitFullscreen;
    if (!ex) return;
    var p = ex.call(document);
    if (p && p.catch) p.catch(function () {});
  }

  function requestVideoFullscreen() {
    var v = getVideo();
    if (!v) return;
    try {
      if (v.requestFullscreen) {
        var p = v.requestFullscreen();
        if (p && p.catch) p.catch(function () {});
        return;
      }
      if (v.webkitEnterFullscreen) {
        v.webkitEnterFullscreen();
        return;
      }
      if (v.webkitRequestFullscreen) {
        v.webkitRequestFullscreen();
      }
    } catch (e) {}
  }

  function toggleFullscreen() {
    var fs = fullscreenEl();
    var v = getVideo();
    if (fs) {
      exitFullscreenSafe();
      return;
    }
    if (v && v.webkitDisplayingFullscreen) {
      try {
        v.webkitExitFullscreen();
      } catch (e) {}
      return;
    }
    requestVideoFullscreen();
  }

  function currentLec() {
    return byId[String(currentId)] || null;
  }

  function isTypingTarget(el) {
    if (!el) return false;
    var tag = (el.tagName || "").toLowerCase();
    if (tag === "input" || tag === "textarea" || tag === "select") return true;
    if (el.isContentEditable) return true;
    return false;
  }

  function lockHtml(locked) {
    if (locked) {
      return (
        '<div class="player-lock" id="player-lock">' +
        '<i class="fa-solid fa-lock"></i>' +
        "<h2>Bu ders kilitli</h2>" +
        "<p>Tüm müfredata erişmek için eğitimi satın alın.</p>" +
        '<a class="btn btn-primary" href="' +
        escapeHtml(data.buyUrl || "#") +
        '">Satın al</a></div>'
      );
    }
    return (
      '<div class="player-lock" id="player-lock">' +
      '<i class="fa-regular fa-circle-play"></i>' +
      "<h2>İzlenecek video yok</h2>" +
      "<p>Bu derse henüz video eklenmemiş.</p></div>"
    );
  }

  function refreshLecMarks() {
    document.querySelectorAll(".player-lec").forEach(function (a) {
      var lec = byId[a.getAttribute("data-lec")];
      var locked = a.classList.contains("is-locked");
      a.classList.toggle("is-done", !!(lec && lec.completed));
      var icon = a.querySelector("i");
      if (!icon) return;
      if (locked) icon.className = "fa-solid fa-lock";
      else if (lec && lec.completed) icon.className = "fa-solid fa-circle-check";
      else icon.className = "fa-solid fa-circle-play";
    });
    if (markBtn) {
      var lec = currentLec();
      markBtn.hidden = !data.trackProgress || !lec || lec.locked || !!lec.completed;
    }
  }

  function showSeekHud(delta) {
    if (!hud) return;
    var abs = Math.abs(delta);
    hud.hidden = false;
    hud.classList.add("is-on");
    hud.innerHTML =
      (delta < 0 ? '<i class="fa-solid fa-backward"></i> ' : "") +
      abs +
      " sn" +
      (delta > 0 ? ' <i class="fa-solid fa-forward"></i>' : "");
    clearTimeout(hudTimer);
    hudTimer = setTimeout(function () {
      hud.classList.remove("is-on");
    }, 700);
  }

  var lastSeekAt = 0;

  function seekBy(delta) {
    var v = getVideo();
    if (!v || !isFinite(v.duration) || v.duration <= 0) return;
    var now = Date.now();
    if (now - lastSeekAt < 80) return;
    lastSeekAt = now;
    var next = Math.min(Math.max(0, v.currentTime + delta), v.duration);
    v.currentTime = next;
    showSeekHud(delta);
    var lec = currentLec();
    if (lec) lec.startAt = Math.floor(next);
    sendProgress(false);
  }

  function togglePlay() {
    var v = getVideo();
    if (!v) return;
    if (v.paused) {
      var p = v.play();
      if (p && p.catch) p.catch(function () {});
    } else {
      v.pause();
    }
  }

  function sendProgress(completed) {
    if (!data.trackProgress || !data.progressUrl || !data.csrf) return;
    var lec = currentLec();
    var v = getVideo();
    if (!lec || lec.locked) return;
    var seconds = v && isFinite(v.currentTime) ? Math.floor(v.currentTime) : lec.startAt || 0;
    var duration = v && isFinite(v.duration) ? Math.floor(v.duration) : 0;
    if (!completed && seconds === lastSaved && lastSaved >= 0) return;
    lastSaved = seconds;
    lec.startAt = seconds;
    var body = {
      csrf: data.csrf,
      lecture_id: lec.id,
      seconds: seconds,
      duration: duration,
      completed: completed ? 1 : 0,
    };
    fetch(data.progressUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      credentials: "same-origin",
      keepalive: true,
      body: JSON.stringify(body),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (!res || !res.ok) return;
        if (res.completed) {
          lec.completed = true;
          refreshLecMarks();
        }
      })
      .catch(function () {});
  }

  function bindVideo(v, startAt) {
    if (!v) return;
    var applied = false;
    var applyStart = function () {
      if (applied) return;
      var t = parseInt(startAt, 10) || 0;
      if (t > 1 && isFinite(v.duration) && t < v.duration - 1) {
        applied = true;
        v.currentTime = t;
      }
    };
    v.addEventListener("loadedmetadata", applyStart);
    v.addEventListener("pause", function () {
      sendProgress(false);
    });
    v.addEventListener("ended", function () {
      sendProgress(true);
    });
    v.addEventListener("timeupdate", function () {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () {
        sendProgress(false);
      }, 8000);
    });
    v.addEventListener("keydown", onPlayerKey, true);
  }

  function showLecture(lec, push) {
    if (!lec || !wrap) return;
    sendProgress(false);
    currentId = lec.id;
    lastSaved = -1;
    document.querySelectorAll(".player-lec").forEach(function (a) {
      a.classList.toggle("is-active", a.getAttribute("data-lec") === String(lec.id));
    });
    if (nowEl) nowEl.textContent = lec.title || "";
    if (titleEl) titleEl.textContent = lec.title || "";
    if (descEl) {
      var d = (lec.description || "").trim();
      descEl.hidden = !d;
      descEl.innerHTML = d ? escapeHtml(d).replace(/\n/g, "<br>") : "";
    }
    if (resEl && resList) {
      var res = lec.resources || [];
      resEl.hidden = !res.length;
      resList.innerHTML = res
        .map(function (r) {
          var icon = r.type === "link" ? "fa-link" : "fa-paperclip";
          return (
            "<li><a href=\"" +
            escapeHtml(r.href) +
            '" target="_blank" rel="noopener"><i class="fa-solid ' +
            icon +
            '"></i> ' +
            escapeHtml(r.name) +
            "</a></li>"
          );
        })
        .join("");
    }

    if (lec.locked) {
      wrap.innerHTML = lockHtml(true);
    } else if (!lec.src) {
      wrap.innerHTML = lockHtml(false);
    } else {
      wrap.innerHTML =
        '<video id="player-video" controls playsinline controlslist="nodownload" src="' +
        escapeHtml(lec.src) +
        '"></video>';
      var v = getVideo();
      bindVideo(v, lec.startAt || 0);
      if (v && v.play) {
        var p = v.play();
        if (p && p.catch) p.catch(function () {});
      }
    }

    refreshLecMarks();

    if (push && data.courseRef) {
      history.replaceState(
        { ders: lec.id },
        "",
        "kurs.php?id=" + encodeURIComponent(data.courseRef) + "&ders=" + lec.id
      );
    }
    if (rail) rail.classList.remove("is-open");
  }

  document.querySelectorAll(".player-lec").forEach(function (a) {
    a.addEventListener("click", function (ev) {
      var id = a.getAttribute("data-lec");
      var lec = byId[id];
      if (!lec) return;
      ev.preventDefault();
      showLecture(lec, true);
    });
  });

  var toggle = document.getElementById("player-toggle-rail");
  var closeBtn = document.getElementById("player-rail-close");
  if (toggle && rail) {
    toggle.addEventListener("click", function () {
      rail.classList.toggle("is-open");
    });
  }
  if (closeBtn && rail) {
    closeBtn.addEventListener("click", function () {
      rail.classList.remove("is-open");
    });
  }

  if (markBtn) {
    markBtn.addEventListener("click", function () {
      sendProgress(true);
      var lec = currentLec();
      if (lec) lec.completed = true;
      refreshLecMarks();
    });
  }

  function onPlayerKey(ev) {
    if (ev.altKey || ev.ctrlKey || ev.metaKey) return;
    if (isTypingTarget(ev.target)) return;
    var v = getVideo();
    if (!v && ev.key !== "f" && ev.key !== "F") return;
    switch (ev.key) {
      case "ArrowLeft":
        ev.preventDefault();
        ev.stopPropagation();
        if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
        seekBy(-5);
        break;
      case "ArrowRight":
        ev.preventDefault();
        ev.stopPropagation();
        if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
        seekBy(5);
        break;
      case "j":
      case "J":
        ev.preventDefault();
        seekBy(-10);
        break;
      case "l":
      case "L":
        ev.preventDefault();
        seekBy(10);
        break;
      case " ":
      case "k":
      case "K":
        ev.preventDefault();
        togglePlay();
        break;
      case "f":
      case "F":
        ev.preventDefault();
        toggleFullscreen();
        break;
      default:
        break;
    }
  }

  window.addEventListener("keydown", onPlayerKey, true);

  window.addEventListener("pagehide", function () {
    sendProgress(false);
  });

  var fsBtn = document.getElementById("player-fs-btn");
  if (fsBtn) {
    fsBtn.addEventListener("click", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      toggleFullscreen();
    });
  }

  bindVideo(getVideo(), (currentLec() && currentLec().startAt) || 0);
  refreshLecMarks();
})();
