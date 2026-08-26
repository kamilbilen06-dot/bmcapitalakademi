/**
 * BM Capital — Eğitmen paneli
 */
(function () {
  "use strict";

  var API = "/api/egitmen.php";
  var CSRF = window.BM_ADMIN_CSRF || "";
  var state = {
    view: "dashboard",
    courses: [],
    courseId: 0,
    course: null,
    me: null,
    dashStats: null,
  };

  var TITLES = {
    dashboard: "Genel Bakış",
    profile: "Profilim",
    courses: "Kurslarım",
    goals: "Hedef öğrenciler",
    curriculum: "Müfredat",
    landing: "Kurs açılış sayfası",
    pricing: "Fiyatlandırma",
    publish: "Yayın durumu",
    students: "Öğrenciler",
    subscribers: "Aboneler",
  };

  var expandedLecIds = {};

  var SOCIAL_PLATFORMS = [
    { id: "youtube", label: "YouTube" },
    { id: "instagram", label: "Instagram" },
    { id: "x", label: "X (Twitter)" },
    { id: "linkedin", label: "LinkedIn" },
    { id: "facebook", label: "Facebook" },
    { id: "web", label: "Web sitesi" },
    { id: "link", label: "Diğer link" },
  ];

  function $(id) {
    return document.getElementById(id);
  }

  function toast(msg, isErr) {
    var el = $("toast");
    if (!el) return;
    el.textContent = msg;
    el.hidden = false;
    el.classList.toggle("err", !!isErr);
    clearTimeout(toast._t);
    toast._t = setTimeout(function () {
      el.hidden = true;
    }, 2800);
  }

  function api(action, opts) {
    opts = opts || {};
    var url = API + "?action=" + encodeURIComponent(action);
    if (opts.query) url += opts.query;
    var ctrl = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = ctrl
      ? setTimeout(function () {
          ctrl.abort();
        }, 15000)
      : null;
    var init = { credentials: "same-origin", headers: {} };
    if (ctrl) init.signal = ctrl.signal;
    if (CSRF) init.headers["X-CSRF-Token"] = CSRF;
    if (opts.body !== undefined) {
      init.method = "POST";
      init.headers["Content-Type"] = "application/json";
      var body = opts.body;
      if (body && typeof body === "object" && !Array.isArray(body)) {
        body = Object.assign({}, body, { csrf: CSRF });
      }
      init.body = JSON.stringify(body);
    }
    if (opts.form) {
      init.method = "POST";
      if (CSRF && typeof opts.form.append === "function") {
        opts.form.append("csrf", CSRF);
      }
      init.body = opts.form;
    }
    return fetch(url, init)
      .then(function (r) {
        return r.text().then(function (text) {
          if (timer) clearTimeout(timer);
          var d = null;
          try {
            d = text ? JSON.parse(text) : null;
          } catch (e) {
            throw new Error(
              "Sunucu geçersiz yanıt verdi (HTTP " + r.status + ")"
            );
          }
          if (r.status === 401) {
            location.href = "login.php";
            throw new Error("Oturum gerekli");
          }
          if (d && d.csrf) CSRF = d.csrf;
          if (!r.ok || !d || d.ok === false) {
            throw new Error((d && d.error) || "İstek başarısız");
          }
          return d;
        });
      })
      .catch(function (e) {
        if (timer) clearTimeout(timer);
        if (e && e.name === "AbortError") {
          throw new Error("İstek zaman aşımına uğradı");
        }
        throw e;
      });
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function setCourseId(id) {
    state.courseId = parseInt(id, 10) || 0;
    try {
      localStorage.setItem("egitmen_course_id", String(state.courseId));
    } catch (e) {}
    refreshPicker();
    updateNavEnabled();
  }

  function refreshPicker() {
    var wrap = $("coursePickerWrap");
    var sel = $("coursePicker");
    if (!wrap || !sel) return;
    if (!state.courses.length) {
      wrap.hidden = true;
      return;
    }
    wrap.hidden = false;
    sel.innerHTML = state.courses
      .map(function (c) {
        return (
          '<option value="' +
          c.id +
          '"' +
          (Number(c.id) === Number(state.courseId) ? " selected" : "") +
          ">" +
          esc(c.title || "Adsız kurs") +
          "</option>"
        );
      })
      .join("");
  }

  function updateNavEnabled() {
    document.querySelectorAll("[data-needs-course]").forEach(function (a) {
      a.classList.toggle("is-disabled", !state.courseId);
    });
  }

  function loadCourses() {
    return api("courses_list").then(function (d) {
      state.courses = d.items || [];
      var saved = 0;
      try {
        saved = parseInt(localStorage.getItem("egitmen_course_id") || "0", 10);
      } catch (e) {}
      if (saved && state.courses.some(function (c) { return Number(c.id) === saved; })) {
        state.courseId = saved;
      } else if (state.courses[0]) {
        state.courseId = state.courses[0].id;
      } else {
        state.courseId = 0;
      }
      refreshPicker();
      updateNavEnabled();
    });
  }

  function loadCourse() {
    if (!state.courseId) {
      state.course = null;
      return Promise.resolve(null);
    }
    return api("course_get", { query: "&id=" + state.courseId }).then(function (d) {
      state.course = d.item;
      return d.item;
    });
  }

  function collectLines(containerId) {
    var box = $(containerId);
    if (!box) return [];
    return Array.prototype.map.call(box.querySelectorAll("input[type=text]"), function (inp) {
      return inp.value.trim();
    });
  }

  function renderLineList(containerId, rows, placeholder) {
    var box = $(containerId);
    if (!box) return;
    var list = rows && rows.length ? rows : [{ body: "" }];
    box.innerHTML = list
      .map(function (r, i) {
        return (
          '<div class="line-row">' +
          '<input type="text" value="' +
          esc(r.body || "") +
          '" placeholder="' +
          esc(placeholder || "Metin yazın") +
          '" data-i="' +
          i +
          '">' +
          '<button type="button" class="rm" data-rm="' +
          i +
          '" title="Sil">×</button>' +
          "</div>"
        );
      })
      .join("");
  }

  /* ---------- Dashboard ---------- */

  function formatInt(n) {
    return String(Math.round(n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function formatTry(n) {
    return "₺" + formatInt(n);
  }

  function formatTryKurus(k) {
    return formatTry(Math.round((k || 0) / 100));
  }

  function kpiDelta(current, previous) {
    if (!previous && !current) {
      return '<div class="dash-kpi-trend">Bu dönem henüz satış yok</div>';
    }
    if (!previous) {
      return '<div class="dash-kpi-trend up"><i class="fa-solid fa-arrow-up"></i> Bu ay başladı</div>';
    }
    var pct = Math.round(((current - previous) / previous) * 100);
    if (pct === 0) {
      return '<div class="dash-kpi-trend">Geçen aya göre aynı</div>';
    }
    var up = pct > 0;
    return (
      '<div class="dash-kpi-trend ' +
      (up ? "up" : "down") +
      '"><i class="fa-solid fa-arrow-' +
      (up ? "up" : "down") +
      '"></i> ' +
      (up ? "+" : "") +
      pct +
      "% geçen aya göre</div>"
    );
  }

  function firstName(full) {
    var s = String(full || "").trim();
    if (!s) return "Eğitmen";
    return s.split(/\s+/)[0];
  }

  function monthRangeLabel() {
    var now = new Date();
    var start = new Date(now.getFullYear(), now.getMonth(), 1);
    var months = [
      "Oca", "Şub", "Mar", "Nis", "May", "Haz",
      "Tem", "Ağu", "Eyl", "Eki", "Kas", "Ara",
    ];
    function fmt(d) {
      return (
        String(d.getDate()).padStart(2, "0") +
        " " +
        months[d.getMonth()] +
        " " +
        d.getFullYear()
      );
    }
    return fmt(start) + " — " + fmt(now);
  }

  function demoSeries(seed, len, base, swing) {
    var out = [];
    var v = base;
    for (var i = 0; i < len; i++) {
      v = Math.max(0, v + Math.sin(i * 0.7 + seed) * swing + ((i * 17 + seed) % 5) - 2);
      out.push(Math.round(v));
    }
    return out;
  }

  function drawLineChart(canvas, seriesA, seriesB, labels) {
    if (!canvas) return;
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var w = Math.max(280, rect.width || 600);
    var h = Math.max(200, rect.height || 260);
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    var ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);

    var pad = { t: 16, r: 12, b: 32, l: 44 };
    var plotW = w - pad.l - pad.r;
    var plotH = h - pad.t - pad.b;
    function maxOf(s) {
      var m = 1;
      (s || []).forEach(function (v) {
        if (v > m) m = v;
      });
      return Math.ceil(m * 1.15) || 1;
    }
    var maxA = maxOf(seriesA);
    var maxB = maxOf(seriesB);

    ctx.strokeStyle = "#e8eef5";
    ctx.lineWidth = 1;
    ctx.fillStyle = "#94a3b8";
    ctx.font = "600 11px Plus Jakarta Sans, sans-serif";
    ctx.textAlign = "right";
    ctx.textBaseline = "middle";
    for (var g = 0; g <= 4; g++) {
      var y = pad.t + (plotH * g) / 4;
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(pad.l + plotW, y);
      ctx.stroke();
      var val = Math.round(maxA * (1 - g / 4));
      ctx.fillText(val >= 1000 ? (val / 1000).toFixed(0) + "K" : String(val), pad.l - 8, y);
    }

    function path(series, color, fill, maxVal) {
      var n = series.length;
      if (!n) return;
      var mx = maxVal || 1;
      ctx.beginPath();
      for (var i = 0; i < n; i++) {
        var x = pad.l + (plotW * i) / Math.max(1, n - 1);
        var y = pad.t + plotH * (1 - series[i] / mx);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.strokeStyle = color;
      ctx.lineWidth = 2.5;
      ctx.lineJoin = "round";
      ctx.stroke();
      if (fill) {
        ctx.lineTo(pad.l + plotW, pad.t + plotH);
        ctx.lineTo(pad.l, pad.t + plotH);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();
      }
    }

    (function () {
      var n = seriesA.length;
      if (!n) return;
      ctx.beginPath();
      for (var i = 0; i < n; i++) {
        var x = pad.l + (plotW * i) / Math.max(1, n - 1);
        var y = pad.t + plotH * (1 - seriesA[i] / maxA);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.lineTo(pad.l + plotW, pad.t + plotH);
      ctx.lineTo(pad.l, pad.t + plotH);
      ctx.closePath();
      var grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + plotH);
      grad.addColorStop(0, "rgba(108,77,255,0.22)");
      grad.addColorStop(1, "rgba(108,77,255,0.01)");
      ctx.fillStyle = grad;
      ctx.fill();
    })();
    path(seriesB, "#c5cad8", null, maxB);
    path(seriesA, "#6c4dff", null, maxA);

    ctx.fillStyle = "#94a3b8";
    ctx.textAlign = "center";
    ctx.textBaseline = "top";
    labels.forEach(function (lb, i) {
      if (i % 2 !== 0 && labels.length > 8) return;
      var x = pad.l + (plotW * i) / Math.max(1, labels.length - 1);
      ctx.fillText(lb, x, pad.t + plotH + 10);
    });
  }

  function drawDonut(canvas, parts) {
    if (!canvas) return;
    var dpr = window.devicePixelRatio || 1;
    var size = 150;
    canvas.width = Math.floor(size * dpr);
    canvas.height = Math.floor(size * dpr);
    var ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var cx = size / 2;
    var cy = size / 2;
    var r = 62;
    var thickness = 18;
    var total = parts.reduce(function (a, p) { return a + p.value; }, 0) || 1;
    var start = -Math.PI / 2;
    parts.forEach(function (p) {
      var ang = (p.value / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(cx, cy, r, start, start + ang);
      ctx.strokeStyle = p.color;
      ctx.lineWidth = thickness;
      ctx.lineCap = "butt";
      ctx.stroke();
      start += ang;
    });
    ctx.beginPath();
    ctx.arc(cx, cy, r - thickness / 2 - 2, 0, Math.PI * 2);
    ctx.fillStyle = "#fff";
    ctx.fill();
  }

  function loadDashStats() {
    var courses = state.courses || [];
    if (!courses.length) {
      state.dashStats = {
        totalStudents: 0,
        byCourse: [],
        published: 0,
        draft: 0,
        salesKurus: 0,
        earnKurus: 0,
        daily: [],
        monthly: [],
        thisMonthKurus: 0,
        lastMonthKurus: 0,
        sharePct: 60,
      };
      return Promise.resolve(state.dashStats);
    }
    return Promise.all([
      Promise.all(
        courses.map(function (c) {
          return api("students_list", { query: "&course_id=" + c.id })
            .then(function (d) {
              return { course: c, students: d.items || [] };
            })
            .catch(function () {
              return { course: c, students: [] };
            });
        })
      ),
      api("sales_stats").catch(function () {
        return {
          sales_kurus: 0,
          earn_kurus: 0,
          by_course: [],
          daily: [],
          monthly: [],
          this_month_kurus: 0,
          last_month_kurus: 0,
          share_pct: 60,
        };
      }),
    ]).then(function (pair) {
      var rows = pair[0];
      var sales = pair[1] || {};
      var salesById = {};
      (sales.by_course || []).forEach(function (row) {
        salesById[String(row.course_id)] = row;
      });
      var total = 0;
      var byCourse = rows
        .map(function (r) {
          total += r.students.length;
          var s = salesById[String(r.course.id)] || {
            sales_kurus: 0,
            earn_kurus: 0,
            paid_count: 0,
          };
          return {
            course: r.course,
            count: r.students.length,
            salesKurus: s.sales_kurus || 0,
            earnKurus: s.earn_kurus || 0,
          };
        })
        .sort(function (a, b) {
          return b.salesKurus - a.salesKurus || b.count - a.count;
        });
      var published = courses.filter(function (c) { return c.status === "published"; }).length;
      var draft = courses.length - published;
      state.dashStats = {
        totalStudents: total,
        byCourse: byCourse,
        published: published,
        draft: draft,
        salesKurus: sales.sales_kurus || 0,
        earnKurus: sales.earn_kurus || 0,
        daily: sales.daily || [],
        monthly: sales.monthly || [],
        thisMonthKurus: sales.this_month_kurus || 0,
        lastMonthKurus: sales.last_month_kurus || 0,
        sharePct: sales.share_pct != null ? sales.share_pct : 60,
      };
      return state.dashStats;
    });
  }

  function createNewCourse() {
    return api("course_create", { body: { title: "Yeni Kurs" } })
      .then(function (d) {
        toast("Kurs oluşturuldu");
        setCourseId(d.id);
        return loadCourses();
      })
      .then(function () {
        go("landing");
      });
  }

  function photoSrc(path) {
    if (!path) return "";
    var p = String(path).trim().replace(/\\/g, "/");
    if (!p) return "";
    if (/^https?:\/\//i.test(p) || p.indexOf("data:") === 0) return p;
    if (p.charAt(0) === "/") return p;
    return "/" + p;
  }

  function applySideUser() {
    var nameEl = $("sideUserName");
    var roleEl = $("sideUserRole");
    var av = $("sideUserAvatar");
    var topName = $("topUserName");
    var topAv = $("topUserAvatar");
    var me = state.me || {};
    var ins = me.instructor || {};
    var name = ins.name || (me.user && me.user.username) || "Eğitmen";
    if (nameEl) nameEl.textContent = name;
    if (topName) topName.textContent = name;
    if (roleEl) roleEl.textContent = "Eğitmen";
    var src = photoSrc(ins.photo_path);
    var photoHtml = src
      ? '<img src="' + esc(src) + (src.indexOf("?") >= 0 ? "&" : "?") + "t=" + Date.now() + '" alt="">'
      : '<i class="fa-solid fa-user"></i>';
    if (av) av.innerHTML = photoHtml;
    if (topAv) topAv.innerHTML = photoHtml;
  }

  function syncMeFromProfile(item) {
    if (!item) return;
    if (!state.me) state.me = { user: {}, instructor: {} };
    state.me.instructor = {
      id: item.id,
      slug: item.slug,
      name: item.name,
      title: item.title,
      photo_path: item.photo_path,
    };
    applySideUser();
  }

  function viewDashboard() {
    var wrap = $("viewWrap");
    wrap.classList.add("is-dashboard");
    wrap.innerHTML = '<div class="loading">Özet yükleniyor…</div>';

    var meP = state.me
      ? Promise.resolve(state.me)
      : api("me").then(function (d) {
          state.me = d;
          applySideUser();
          return d;
        });

    Promise.all([meP, loadDashStats()])
      .then(function () {
        var stats = state.dashStats || {};
        var courses = state.courses || [];
        var name = firstName(
          (state.me && state.me.instructor && state.me.instructor.name) ||
            (state.me && state.me.user && state.me.user.username)
        );
        var students = stats.totalStudents || 0;
        var salesKurus = stats.salesKurus || 0;
        var earnKurus = stats.earnKurus || 0;
        var top = (stats.byCourse || []).slice(0, 4);
        if (!top.length) {
          top = courses.slice(0, 4).map(function (c) {
            return { course: c, count: 0, salesKurus: 0, earnKurus: 0 };
          });
        }
        var maxEarn = top.reduce(function (m, t) { return Math.max(m, t.earnKurus || t.salesKurus || 1); }, 1);

        wrap.innerHTML =
          '<div class="dash">' +
          '<div class="dash-hello">' +
          "<div><h2>Merhaba, " +
          esc(name) +
          ' 👋</h2><p>Bugün kursların nasıl gidiyor, bir bakışta gör.</p></div>' +
          '<div class="dash-range"><i class="fa-regular fa-calendar"></i> ' +
          esc(monthRangeLabel()) +
          "</div></div>" +
          '<div class="dash-kpi">' +
          '<div class="dash-kpi-card"><div class="dash-kpi-top"><span class="dash-kpi-icon purple"><i class="fa-solid fa-graduation-cap"></i></span></div>' +
          '<p class="dash-kpi-label">Toplam Kurs</p><p class="dash-kpi-value">' +
          formatInt(courses.length) +
          '</p><div class="dash-kpi-trend">Yayında ve taslak</div></div>' +
          '<div class="dash-kpi-card"><div class="dash-kpi-top"><span class="dash-kpi-icon green"><i class="fa-solid fa-users"></i></span></div>' +
          '<p class="dash-kpi-label">Toplam Öğrenci</p><p class="dash-kpi-value">' +
          formatInt(students) +
          "</p>" +
          '<div class="dash-kpi-trend">Ödemesi onaylı kayıtlar</div></div>' +
          '<div class="dash-kpi-card"><div class="dash-kpi-top"><span class="dash-kpi-icon blue"><i class="fa-solid fa-cart-shopping"></i></span></div>' +
          '<p class="dash-kpi-label">Toplam Satış</p><p class="dash-kpi-value">' +
          formatTryKurus(salesKurus) +
          "</p>" +
          kpiDelta(stats.thisMonthKurus || 0, stats.lastMonthKurus || 0) +
          "</div>" +
          '<div class="dash-kpi-card"><div class="dash-kpi-top"><span class="dash-kpi-icon orange"><i class="fa-solid fa-wallet"></i></span></div>' +
          '<p class="dash-kpi-label">Toplam Kazanç</p><p class="dash-kpi-value">' +
          formatTryKurus(earnKurus) +
          '</p><div class="dash-kpi-trend">Satışların %' +
          esc(String(stats.sharePct != null ? stats.sharePct : 60)) +
          "'ı (eğitmen payı)</div></div>" +
          "</div>" +
          '<div class="dash-grid">' +
          '<div class="dash-card"><div class="dash-card-head"><h3>Kazanç ve görüntülenme</h3></div>' +
          '<div class="dash-chart-wrap"><canvas id="dashLineChart"></canvas></div>' +
          '<div class="dash-legend"><span class="lg-earn">Kazanç (₺)</span><span class="lg-views">Görüntülenme</span></div></div>' +
          '<div class="dash-card"><div class="dash-card-head"><h3>En Çok Satan Kurslar</h3>' +
          '<button type="button" class="dash-link" id="dashGoCourses">Tümünü Gör</button></div>' +
          '<div class="dash-course-list" id="dashTopCourses">' +
          (top.length
            ? top
                .map(function (t) {
                  var c = t.course;
                  var thumb = c.image_path
                    ? '<img class="dash-course-thumb" src="' +
                      esc(mediaUrl(c.image_path)) +
                      '" alt="">'
                    : '<div class="dash-course-thumb"></div>';
                  var pct = Math.max(12, Math.round(((t.earnKurus || t.salesKurus || 1) / maxEarn) * 100));
                  return (
                    '<div class="dash-course-item"><div class="dash-course-row">' +
                    thumb +
                    '<div class="dash-course-meta"><strong>' +
                    esc(c.title || "Adsız kurs") +
                    "</strong><span>" +
                    formatInt(t.count) +
                    " öğrenci</span></div>" +
                    '<div class="dash-course-earn">' +
                    formatTryKurus(t.earnKurus) +
                    '</div></div><div class="dash-bar"><i style="width:' +
                    pct +
                    '%"></i></div></div>'
                  );
                })
                .join("")
            : '<p class="loading" style="padding:20px 0">Henüz kurs yok.</p>') +
          "</div></div></div>" +
          '<div class="dash-grid-3">' +
          '<div class="dash-card"><div class="dash-card-head"><h3>Kurs durumları</h3></div>' +
          '<ul class="dash-status-list">' +
          "<li><span class=\"left\"><i class=\"dot\" style=\"background:#22c55e\"></i>Yayında</span><span class=\"count\">" +
          formatInt(stats.published || 0) +
          "</span></li>" +
          "<li><span class=\"left\"><i class=\"dot\" style=\"background:#f97316\"></i>Taslak</span><span class=\"count\">" +
          formatInt(stats.draft || 0) +
          "</span></li>" +
          "</ul></div></div></div>";

        var monthLabels = (stats.monthly || []).map(function (m) {
          var parts = String(m.label || "").split("-");
          return parts.length === 2 ? parts[1] + "." + parts[0].slice(2) : m.label;
        });
        var monthSales = (stats.monthly || []).map(function (m) {
          return Math.round((m.sales_kurus || 0) / 100);
        });
        var monthEarn = (stats.monthly || []).map(function (m) {
          return Math.round((m.earn_kurus || 0) / 100);
        });
        var dayLabels = (stats.daily || []).map(function (d) { return d.label; });
        var daySales = (stats.daily || []).map(function (d) {
          return Math.round((d.sales_kurus || 0) / 100);
        });
        var dayEarn = (stats.daily || []).map(function (d) {
          return Math.round((d.earn_kurus || 0) / 100);
        });
        var monthViews = (stats.monthly || []).map(function (m) {
          return parseInt(m.views, 10) || 0;
        });
        var dayViews = (stats.daily || []).map(function (d) {
          return parseInt(d.views, 10) || 0;
        });
        drawLineChart($("dashLineChart"), monthEarn, monthViews, monthLabels.length ? monthLabels : ["—"]);

        var goC = $("dashGoCourses");
        if (goC) {
          goC.onclick = function () {
            go("courses");
          };
        }
        var range = $("dashChartRange");
        if (range) {
          range.onchange = function () {
            var weekly = range.value === "Haftalık";
            if (weekly) {
              drawLineChart(
                $("dashLineChart"),
                dayEarn.length ? dayEarn : [0],
                dayViews.length ? dayViews : [0],
                dayLabels.length ? dayLabels : ["—"]
              );
            } else {
              drawLineChart(
                $("dashLineChart"),
                monthEarn.length ? monthEarn : [0],
                monthViews.length ? monthViews : [0],
                monthLabels.length ? monthLabels : ["—"]
              );
            }
          };
        }
      })
      .catch(function (e) {
        wrap.innerHTML = '<p class="loading">' + esc(e.message) + "</p>";
      });
  }

  /* ---------- Views ---------- */

  function viewCourses() {
    var html =
      '<p class="page-lead">Kurslarınızı oluşturun ve düzenlemek için bir kurs seçin.</p>' +
      '<div class="actions-bar" style="justify-content:flex-start;margin-bottom:16px">' +
      '<button type="button" class="btn-primary sm" id="btnNewCourse">+ Yeni kurs</button>' +
      "</div>" +
      '<div class="course-list" id="courseList"></div>';
    $("viewWrap").innerHTML = html;

    var list = $("courseList");
    if (!state.courses.length) {
      list.innerHTML = '<p class="loading">Henüz kurs yok. Yeni kurs oluşturun.</p>';
    } else {
      list.innerHTML = state.courses
        .map(function (c) {
          var thumb = c.image_path
            ? '<img class="course-thumb" src="' + esc(mediaUrl(c.image_path)) + '" alt="">'
            : '<div class="course-thumb"></div>';
          return (
            '<div class="course-card' +
            (Number(c.id) === Number(state.courseId) ? " is-active" : "") +
            '" data-id="' +
            c.id +
            '">' +
            thumb +
            '<div class="course-meta"><strong>' +
            esc(c.title || "Adsız kurs") +
            "</strong><span>" +
            esc(
              c.subtitle ||
                (c.instructor_name ? "Eğitmen: " + c.instructor_name : "")
            ) +
            "</span></div>" +
            '<span class="pill ' +
            (c.status === "published" ? "published" : "draft") +
            '">' +
            (c.status === "published" ? "Yayında" : "Taslak") +
            "</span>" +
            '<button type="button" class="btn-danger" data-del="' +
            c.id +
            '">Sil</button>' +
            "</div>"
          );
        })
        .join("");
    }

    $("btnNewCourse").onclick = function () {
      createNewCourse().catch(function (e) {
        toast(e.message, true);
      });
    };

    list.onclick = function (e) {
      var del = e.target.closest("[data-del]");
      if (del) {
        e.stopPropagation();
        if (!confirm("Kurs silinsin mi? Videolar da silinir.")) return;
        var id = parseInt(del.getAttribute("data-del"), 10);
        api("course_delete", { body: { id: id } })
          .then(function () {
            if (Number(state.courseId) === id) setCourseId(0);
            toast("Silindi");
            return loadCourses().then(render);
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }
      var card = e.target.closest(".course-card");
      if (card) {
        setCourseId(card.getAttribute("data-id"));
        go("landing");
      }
    };
  }

  function viewLanding() {
    var c = state.course || {};
    $("viewWrap").innerHTML =
      '<p class="page-lead">Kurs açılış sayfanız, potansiyel öğrencilerin kursunuz hakkında karar vermesine yardımcı olur. Başlık, açıklama, kapak görseli ve tanıtım videosunu buradan düzenleyin.</p>' +
      '<div class="block">' +
      '<h2 class="block-title">Kurs başlığı</h2>' +
      '<p class="block-desc">Başlığınız ilgi çekici, bilgilendirici ve arama için uygun olmalıdır.</p>' +
      '<div class="input-wrap"><input class="block-input" type="text" id="fTitle" maxlength="120" value="' +
      esc(c.title || "") +
      '"><span class="char-count" id="ccTitle"></span></div>' +
      "</div>" +
      '<div class="block">' +
      '<h2 class="block-title">Kurs alt başlığı</h2>' +
      '<p class="block-desc">Özetinizde anahtar kelimeler kullanın ve her birinin kurs içeriğiniz için değerini vurgulayın.</p>' +
      '<div class="input-wrap"><input class="block-input" type="text" id="fSubtitle" maxlength="160" value="' +
      esc(c.subtitle || "") +
      '"><span class="char-count" id="ccSub"></span></div>' +
      "</div>" +
      '<div class="block">' +
      '<h2 class="block-title">Kurs açıklaması</h2>' +
      '<p class="block-desc">Kursunuzun neler sunduğunu net ve kapsamlı anlatın. En az birkaç paragraf yazmanız önerilir.</p>' +
      '<textarea class="block-textarea" id="fDesc">' +
      esc(c.description || "") +
      "</textarea>" +
      "</div>" +
      '<div class="block">' +
      '<h2 class="block-title">Kursunuzda temel olarak ne öğretiliyor?</h2>' +
      '<p class="block-desc">Konuyu seçin veya yazın. Bu bilgi, öğrencilerin kursunuzu bulmasına yardımcı olur.</p>' +
      (c.topic
        ? '<div class="topic-chip" style="margin-bottom:14px">' + esc(c.topic) + "</div>"
        : "") +
      '<div class="meta-grid">' +
      '<div><label>Dil</label><select class="block-select" id="fLang">' +
      opt("Türkçe", c.language) +
      opt("English", c.language) +
      "</select></div>" +
      '<div><label>Seviye</label><select class="block-select" id="fLevel">' +
      opt("Tüm Düzeyler", c.level) +
      opt("Başlangıç", c.level) +
      opt("Orta", c.level) +
      opt("İleri", c.level) +
      "</select></div>" +
      '<div><label>Kategori</label><input class="block-input" type="text" id="fCat" value="' +
      esc(c.category || "") +
      '" placeholder="Finans ve Yatırım"></div>' +
      '<div><label>Alt kategori</label><input class="block-input" type="text" id="fSubcat" value="' +
      esc(c.subcategory || "") +
      '" placeholder="Teknik Analiz"></div>' +
      '<div class="full"><label>Konu</label><input class="block-input" type="text" id="fTopic" value="' +
      esc(c.topic || "") +
      '" placeholder="Teknik Analiz (finans)"></div>' +
      "</div></div>" +
      '<div class="media-block">' +
      '<div class="media-preview" id="imgPrev">' +
      mediaPreviewHtml("image", c.image_path) +
      "</div>" +
      '<div class="media-copy">' +
      "<h3>Kurs görüntüsü</h3>" +
      "<p>Kurs görüntünüzü buraya yükleyin. Öğrencilerin dikkatini çekmek için önemli bir görseldir. 750×422 piksel; .jpg, .jpeg, .gif, .png veya .webp kullanın.</p>" +
      '<div class="media-actions">' +
      '<input type="file" id="imgFile" accept="image/*" hidden>' +
      '<button type="button" class="btn-outline" id="btnImg">Dosya Yükle</button>' +
      '<span class="file-name" id="imgName">' +
      (c.image_path ? fileBase(c.image_path) : "Seçili dosya yok") +
      "</span></div>" +
      '<div class="upload-progress" id="imgProgress" hidden><div class="upload-bar"><span id="imgBar"></span></div><div class="upload-pct" id="imgPct">0%</div></div>' +
      '<p class="upload-status" id="imgStatus" hidden></p>' +
      "</div></div>" +
      '<div class="media-block">' +
      '<div class="media-preview" id="vidPrev">' +
      mediaPreviewHtml("promo", c.promo_video_path) +
      "</div>" +
      '<div class="media-copy">' +
      "<h3>Tanıtım videosu</h3>" +
      "<p>Öğrencilerin kursunuzu tanıması için kısa bir tanıtım videosu yükleyin. mp4 veya webm · en fazla 256 MB.</p>" +
      '<div class="media-actions">' +
      '<input type="file" id="promoFile" accept="video/mp4,video/webm" hidden>' +
      '<button type="button" class="btn-outline" id="btnPromo">Dosya Yükle</button>' +
      '<span class="file-name" id="vidName">' +
      (c.promo_video_path ? fileBase(c.promo_video_path) : "Seçili dosya yok") +
      "</span></div>" +
      '<div class="upload-progress" id="vidProgress" hidden><div class="upload-bar"><span id="vidBar"></span></div><div class="upload-pct" id="vidPct">0%</div></div>' +
      '<p class="upload-status" id="vidStatus" hidden></p>' +
      "</div></div>" +
      '<div class="actions-bar"><button type="button" class="btn-dark" id="btnSaveLanding">Kaydet</button></div>';

    bindChar("fTitle", "ccTitle", 120);
    bindChar("fSubtitle", "ccSub", 160);
    $("btnSaveLanding").onclick = saveLanding;
    bindDirectUpload("btnImg", "imgFile", "image", "imgName");
    bindDirectUpload("btnPromo", "promoFile", "promo", "vidName");
  }

  function fileBase(path) {
    var p = String(path || "");
    var i = p.lastIndexOf("/");
    return i >= 0 ? p.slice(i + 1) : p;
  }

  function bindDirectUpload(btnId, inputId, kind, nameId) {
    var btn = $(btnId);
    var inp = $(inputId);
    if (!btn || !inp) return;
    btn.onclick = function () {
      inp.click();
    };
    inp.onchange = function () {
      var file = inp.files && inp.files[0];
      if (!file) return;
      if (nameId && $(nameId)) $(nameId).textContent = file.name;
      var box = $(kind === "image" ? "imgPrev" : "vidPrev");
      if (box) {
        var url = URL.createObjectURL(file);
        box.innerHTML =
          kind === "image"
            ? '<img src="' + url + '" alt="Önizleme">'
            : '<video src="' + url + '" controls></video>';
      }
      uploadFile(kind, file);
    };
  }

  function mediaStreamUrl(kind, id) {
    return "/api/media.php?kind=" + encodeURIComponent(kind) + "&id=" + encodeURIComponent(id);
  }

  function mediaUrl(path) {
    if (!path) return "";
    if (/^https?:\/\//i.test(path) || path.charAt(0) === "/") return path;
    return "/" + String(path).replace(/^\/+/, "");
  }

  function mediaPreviewHtml(kind, path) {
    var src = "";
    if (kind === "promo" && state.courseId && path) {
      src = mediaStreamUrl("promo", state.courseId);
    } else {
      src = mediaUrl(path);
    }
    if (!src) return kind === "image" ? "Kapak yok" : "Video yok";
    if (kind === "image") {
      return '<img src="' + esc(src) + "?t=" + Date.now() + '" alt="Kapak">';
    }
    return (
      '<video src="' +
      esc(src) +
      (src.indexOf("?") >= 0 ? "&" : "?") +
      "t=" +
      Date.now() +
      '" controls></video>'
    );
  }

  function setUploadUi(kind, pct, status, isErr) {
    var isImg = kind === "image";
    var progress = $(isImg ? "imgProgress" : "vidProgress");
    var bar = $(isImg ? "imgBar" : "vidBar");
    var pctEl = $(isImg ? "imgPct" : "vidPct");
    var statusEl = $(isImg ? "imgStatus" : "vidStatus");
    var btn = $(isImg ? "btnImg" : "btnPromo");
    if (progress) progress.hidden = pct == null;
    if (bar && pct != null) bar.style.width = Math.max(0, Math.min(100, pct)) + "%";
    if (pctEl && pct != null) pctEl.textContent = Math.round(pct) + "%";
    if (statusEl) {
      if (status) {
        statusEl.hidden = false;
        statusEl.textContent = status;
        statusEl.classList.toggle("err", !!isErr);
        statusEl.classList.toggle("ok", !isErr);
      } else {
        statusEl.hidden = true;
      }
    }
    if (btn) btn.disabled = pct != null && pct < 100 && !isErr;
  }

  function setLectureUploadUi(lectureId, pct, status, isErr) {
    var box = $("lecUpload" + lectureId);
    var bar = $("lecBar" + lectureId);
    var pctEl = $("lecPct" + lectureId);
    var statusEl = $("lecStatus" + lectureId);
    var btn = $("lecPickBtn" + lectureId);
    if (!box) return;
    if (pct == null) {
      box.hidden = true;
      if (btn) btn.disabled = false;
      return;
    }
    box.hidden = false;
    if (bar) bar.style.width = Math.max(0, Math.min(100, pct)) + "%";
    if (pctEl) pctEl.textContent = "%" + Math.round(pct);
    if (statusEl) {
      statusEl.textContent = status || "Yükleniyor…";
      statusEl.classList.toggle("err", !!isErr);
      statusEl.classList.toggle("ok", !isErr && pct >= 100);
    }
    if (btn) btn.disabled = pct < 100 && !isErr;
  }

  function opt(val, cur) {
    return (
      '<option value="' +
      esc(val) +
      '"' +
      (cur === val ? " selected" : "") +
      ">" +
      esc(val) +
      "</option>"
    );
  }

  function bindChar(inputId, countId, max) {
    var inp = $(inputId);
    var cc = $(countId);
    if (!inp || !cc) return;
    function tick() {
      cc.textContent = (inp.value || "").length;
    }
    inp.addEventListener("input", tick);
    tick();
  }

  function saveLanding() {
    if (!state.courseId) return;
    api("course_save", {
      body: {
        id: state.courseId,
        title: $("fTitle").value,
        subtitle: $("fSubtitle").value,
        description: $("fDesc").value,
        language: $("fLang").value,
        level: $("fLevel").value,
        category: $("fCat").value,
        subcategory: $("fSubcat").value,
        topic: $("fTopic").value,
        status: (state.course && state.course.status) || "draft",
        price: (state.course && state.course.price) || "",
        price_note: (state.course && state.course.price_note) || "",
      },
    })
      .then(function (d) {
        state.course = d.item;
        toast("Kaydedildi");
        return loadCourses();
      })
      .catch(function (e) {
        toast(e.message, true);
      });
  }

  function getVideoDurationFromFile(file) {
    return new Promise(function (resolve) {
      if (!file) {
        resolve(0);
        return;
      }
      var url = URL.createObjectURL(file);
      var v = document.createElement("video");
      v.preload = "metadata";
      var done = function (sec) {
        try {
          URL.revokeObjectURL(url);
        } catch (e) {}
        resolve(sec > 0 && isFinite(sec) ? Math.round(sec) : 0);
      };
      v.onloadedmetadata = function () {
        done(v.duration);
      };
      v.onerror = function () {
        done(0);
      };
      setTimeout(function () {
        if (!v.duration || !isFinite(v.duration)) done(0);
      }, 8000);
      v.src = url;
    });
  }

  function getVideoDurationFromUrl(src) {
    return new Promise(function (resolve) {
      if (!src) {
        resolve(0);
        return;
      }
      var v = document.createElement("video");
      v.preload = "metadata";
      v.onloadedmetadata = function () {
        var sec = v.duration;
        resolve(sec > 0 && isFinite(sec) ? Math.round(sec) : 0);
      };
      v.onerror = function () {
        resolve(0);
      };
      setTimeout(function () {
        resolve(0);
      }, 10000);
      v.src = mediaUrl(src);
    });
  }

  function formatDurationLabel(sec) {
    sec = parseInt(sec, 10) || 0;
    if (sec <= 0) return "";
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0 && m > 0) return h + " saat " + m + " dakika";
    if (h > 0) return h + " saat";
    if (m > 0 && s > 0) return m + " dk " + s + " sn";
    if (m > 0) return m + " dakika";
    return s + " sn";
  }

  function curriculumTotalSec(cur) {
    var total = 0;
    (cur || []).forEach(function (sec) {
      (sec.lectures || []).forEach(function (lec) {
        total += parseInt(lec.duration_sec, 10) || 0;
      });
    });
    return total;
  }

  function uploadFile(kind, file, lectureId, durationSec) {
    if (!file) {
      toast("Dosya seçin", true);
      return;
    }
    if (!state.courseId) return;

    var showBar = kind === "image" || kind === "promo";
    var isLecture = kind === "lecture";
    if (showBar) setUploadUi(kind, 0, "Yükleniyor…", false);
    if (isLecture && lectureId) {
      expandedLecIds[lectureId] = true;
      expandedLecIds[lectureId + ":content"] = true;
      setLectureUploadUi(lectureId, 0, "Hazırlanıyor… " + (file.name || ""), false);
    }

    var send = function (dur) {
      var fd = new FormData();
      fd.append("file", file);
      fd.append("course_id", String(state.courseId));
      fd.append("kind", kind);
      if (lectureId) fd.append("lecture_id", String(lectureId));
      if (dur > 0) fd.append("duration_sec", String(dur));

      var xhr = new XMLHttpRequest();
      xhr.open("POST", API + "?action=upload");
      xhr.withCredentials = true;
      if (CSRF) xhr.setRequestHeader("X-CSRF-Token", CSRF);
      fd.append("csrf", CSRF);
      xhr.upload.onprogress = function (e) {
        if (!e.lengthComputable) return;
        var pct = (e.loaded / e.total) * 100;
        if (showBar) {
          setUploadUi(kind, pct, "Yükleniyor… %" + Math.round(pct), false);
        }
        if (isLecture && lectureId) {
          setLectureUploadUi(
            lectureId,
            pct,
            "Yükleniyor… " + (file.name || "") + " · %" + Math.round(pct),
            false
          );
        }
      };
      xhr.onerror = function () {
        if (showBar) setUploadUi(kind, 0, "Bağlantı hatası", true);
        if (isLecture && lectureId) {
          setLectureUploadUi(lectureId, 0, "Bağlantı hatası", true);
        }
        toast("Yükleme başarısız", true);
      };
      xhr.onload = function () {
        var d = null;
        try {
          d = xhr.responseText ? JSON.parse(xhr.responseText) : null;
        } catch (err) {
          d = null;
        }
        if (xhr.status === 401) {
          location.href = "login.php";
          return;
        }
        if (xhr.status < 200 || xhr.status >= 300 || !d || d.ok === false) {
          var msg =
            (d && d.error) ||
            (xhr.status === 413
              ? "Dosya çok büyük"
              : "Yükleme başarısız (HTTP " + xhr.status + ")");
          if (showBar) setUploadUi(kind, 0, msg, true);
          if (isLecture && lectureId) {
            setLectureUploadUi(lectureId, 0, msg, true);
          }
          toast(msg, true);
          return;
        }
        state.course = d.item || state.course;
        if (showBar) {
          setUploadUi(kind, 100, "Yüklendi", false);
          if (kind === "image" && d.path) {
            $("imgPrev").innerHTML = mediaPreviewHtml("image", d.path);
            if ($("imgName")) $("imgName").textContent = fileBase(d.path);
          }
          if (kind === "promo" && d.path) {
            $("vidPrev").innerHTML = mediaPreviewHtml("promo", d.path);
            if ($("vidName")) $("vidName").textContent = fileBase(d.path);
          }
          toast(kind === "image" ? "Kapak yüklendi" : "Video yüklendi");
          loadCourses();
        } else if (isLecture) {
          setLectureUploadUi(lectureId, 100, "Yüklendi", false);
          toast(
            dur > 0
              ? "Video yüklendi · " + formatDurationLabel(dur)
              : "Video yüklendi"
          );
          if (d.item) state.course = d.item;
          setTimeout(function () {
            loadCourse().then(function () {
              viewCurriculum();
            });
          }, 450);
        } else {
          toast("Yüklendi");
          if (d.item) state.course = d.item;
          loadCourse().then(function () {
            viewCurriculum();
          });
        }
      };
      xhr.send(fd);
    };

    if (kind === "lecture") {
      if (durationSec > 0) {
        send(durationSec);
        return;
      }
      getVideoDurationFromFile(file).then(send);
      return;
    }
    send(0);
  }

  function viewGoals() {
    var c = state.course || {};
    $("viewWrap").innerHTML =
      '<p class="page-lead">Öğrencilerin kursu tamamlayınca kazanacakları hedefler, ön koşullar ve hedef kitleyi netleştirin.</p>' +
      '<div class="block">' +
      '<h2 class="block-title">Öğrenciler ne öğrenecek?</h2>' +
      '<p class="block-desc">En az 4 öğrenim hedefi eklemeniz önerilir. Her satır tek bir kazanımı ifade etsin.</p>' +
      '<div class="line-list" id="listObj"></div>' +
      '<button type="button" class="btn-link" id="addObj" style="margin-top:12px">+ Yanıtınıza daha fazla bilgi ekleyin</button>' +
      "</div>" +
      '<div class="block">' +
      '<h2 class="block-title">Gereksinimler veya ön koşullar</h2>' +
      '<p class="block-desc">Öğrencilerin kursa başlamadan önce bilmesi gerekenleri listeleyin.</p>' +
      '<div class="line-list" id="listReq"></div>' +
      '<button type="button" class="btn-link" id="addReq" style="margin-top:12px">+ Yanıtınıza daha fazla bilgi ekleyin</button>' +
      "</div>" +
      '<div class="block">' +
      '<h2 class="block-title">Bu kurs kime yönelik?</h2>' +
      '<p class="block-desc">Hedef kitlenizi tanımlayın; doğru öğrencileri çekmenize yardımcı olur.</p>' +
      '<div class="line-list" id="listAud"></div>' +
      '<button type="button" class="btn-link" id="addAud" style="margin-top:12px">+ Yanıtınıza daha fazla bilgi ekleyin</button>' +
      "</div>" +
      '<div class="actions-bar"><button type="button" class="btn-dark" id="btnSaveGoals">Kaydet</button></div>';

    renderLineList("listObj", c.objectives, "Örnek: Grafik okuma becerileri kazanmak");
    renderLineList("listReq", c.requirements, "Örnek: Deneyim gerekmez");
    renderLineList("listAud", c.audience, "Örnek: Başlangıç düzeyindeki yatırımcılar");

    wireLineList("listObj", "addObj");
    wireLineList("listReq", "addReq");
    wireLineList("listAud", "addAud");

    $("btnSaveGoals").onclick = function () {
      api("course_save", {
        body: {
          id: state.courseId,
          title: c.title || "Kurs",
          subtitle: c.subtitle || "",
          description: c.description || "",
          language: c.language,
          level: c.level,
          category: c.category,
          subcategory: c.subcategory,
          topic: c.topic,
          price: c.price,
          price_note: c.price_note,
          status: c.status || "draft",
          objectives: collectLines("listObj"),
          requirements: collectLines("listReq"),
          audience: collectLines("listAud"),
        },
      })
        .then(function (d) {
          state.course = d.item;
          toast("Kaydedildi");
        })
        .catch(function (e) {
          toast(e.message, true);
        });
    };
  }

  function wireLineList(listId, addId) {
    $(addId).onclick = function () {
      var box = $(listId);
      var row = document.createElement("div");
      row.className = "line-row";
      row.innerHTML =
        '<input type="text" value="" placeholder="Metin yazın"><button type="button" class="rm" title="Sil">×</button>';
      box.appendChild(row);
    };
    $(listId).onclick = function (e) {
      if (e.target.classList.contains("rm")) {
        var row = e.target.closest(".line-row");
        if (row && $(listId).querySelectorAll(".line-row").length > 1) row.remove();
        else if (row) row.querySelector("input").value = "";
      }
    };
  }

  function cleanAutoTitle(title, kind) {
    var t = String(title || "").trim();
    if (kind === "section") {
      t = t.replace(/^Bölüm\s*\d+\s*[:.\-]?\s*/i, "");
    } else {
      t = t.replace(/^Ders\s*\d+\s*[:.\-]?\s*/i, "");
      t = t.replace(/^Yeni Ders$/i, "");
      t = t.replace(/^Yeni Bölüm$/i, "");
    }
    return t;
  }

  function findLecture(cur, id) {
    id = parseInt(id, 10);
    var found = null;
    (cur || []).forEach(function (sec) {
      (sec.lectures || []).forEach(function (lec) {
        if (!found && parseInt(lec.id, 10) === id) found = lec;
      });
    });
    return found;
  }

  function resourcesListHtml(resources) {
    resources = resources || [];
    if (!resources.length) {
      return '<p class="lec-empty-hint">Henüz kaynak eklenmedi.</p>';
    }
    return (
      '<ul class="lec-res-list">' +
      resources
        .map(function (r, i) {
          var href = r.url || "#";
          if (href.indexOf("http") !== 0 && href.indexOf("/") !== 0) {
            href = "/" + href;
          }
          var badge =
            r.type === "file"
              ? '<span class="res-badge">Dosya</span>'
              : '<span class="res-badge link">Link</span>';
          return (
            "<li>" +
            badge +
            '<a href="' +
            esc(href) +
            '" target="_blank" rel="noopener">' +
            esc(r.name || r.url || "Kaynak") +
            '</a> <button type="button" class="icon-btn danger" data-res-del="' +
            i +
            '" title="Sil"><i class="fa-solid fa-trash"></i></button></li>'
          );
        })
        .join("") +
      "</ul>"
    );
  }

  function viewCurriculum() {
    var c = state.course || {};
    var cur = c.curriculum || [];
    var totalSec = curriculumTotalSec(cur);
    var totalLabel = formatDurationLabel(totalSec);
    $("viewWrap").innerHTML =
      '<p class="page-lead">Bölüm ve derslerinizi oluşturun. Derslerin üzerine gelince düzenle/sil görünür; sürükleyerek sıralayabilir veya başka bölüme taşıyabilirsiniz. Toplam süre videolardan otomatik hesaplanır.</p>' +
      '<div class="cur-toolbar">' +
      '<button type="button" class="btn-outline" id="btnAddSec">+ Bölüm ekle</button>' +
      '<span class="cur-total" id="curTotal">' +
      (totalLabel
        ? "Toplam süre: <strong>" + esc(totalLabel) + "</strong>"
        : "Toplam süre: video yüklendikçe hesaplanır") +
      "</span></div>" +
      '<div id="curWrap"></div>';

    var wrap = $("curWrap");
    if (!cur.length) {
      wrap.innerHTML =
        '<p class="loading">Henüz bölüm yok. “+ Bölüm ekle” ile başlayın.</p>';
    } else {
      wrap.innerHTML = cur
        .map(function (sec, si) {
          var secNum = si + 1;
          var secTitle = cleanAutoTitle(sec.title, "section");
          var lectures = (sec.lectures || [])
            .map(function (lec, li) {
              // Numara bölüm içinde 1'den başlar (başka bölüme taşınca yeniden numaralanır)
              var lecNum = li + 1;
              var lecTitle = cleanAutoTitle(lec.title, "lecture") || "Adsız ders";
              var lecDur = formatDurationLabel(lec.duration_sec);
              var open = !!expandedLecIds[lec.id];
              var hasVideo = !!lec.video_path;
              var resources = lec.resources || [];
              var desc = lec.description || "";
              var showDesc = open && (desc || expandedLecIds[lec.id + ":desc"]);
              var showRes = open && (resources.length || expandedLecIds[lec.id + ":res"]);
              var showContent = open && (hasVideo || expandedLecIds[lec.id + ":content"]);
              return (
                '<div class="lec-card' +
                (open ? " is-open" : "") +
                (hasVideo ? "" : " is-draft") +
                '" data-lec="' +
                lec.id +
                '" data-section="' +
                sec.id +
                '" draggable="true">' +
                '<div class="lec-row">' +
                (hasVideo
                  ? '<span class="lec-check" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>'
                  : '<span class="lec-warn" aria-hidden="true" title="Video eklenmedi"><i class="fa-solid fa-triangle-exclamation"></i></span>') +
                '<span class="lec-label">Ders ' +
                lecNum +
                ":</span>" +
                '<span class="lec-type"><i class="fa-regular fa-' +
                (hasVideo ? "circle-play" : "file-lines") +
                '"></i></span>' +
                '<span class="lec-title-group">' +
                '<span class="lec-title-text" data-lec-title-display="' +
                lec.id +
                '">' +
                esc(lecTitle) +
                "</span>" +
                '<input type="text" class="lec-title-input" hidden data-lec-title="' +
                lec.id +
                '" value="' +
                esc(lecTitle) +
                '">' +
                '<span class="lec-hover-actions">' +
                '<button type="button" class="icon-btn" data-lec-edit="' +
                lec.id +
                '" title="Adı değiştir"><i class="fa-solid fa-pencil"></i></button>' +
                '<button type="button" class="icon-btn danger" data-lec-del="' +
                lec.id +
                '" title="Sil"><i class="fa-solid fa-trash"></i></button>' +
                "</span></span>" +
                (lec.is_preview == 1
                  ? '<span class="lec-preview-tag">(Önizleme etkinleştirildi)</span>'
                  : "") +
                (hasVideo
                  ? '<span class="vid-ok">' +
                    (lecDur ? esc(lecDur) : "Video yüklü") +
                    "</span>"
                  : '<button type="button" class="btn-add-item" data-add-content="' +
                    lec.id +
                    '">+ İçerik</button>') +
                '<button type="button" class="icon-btn lec-chevron" data-lec-toggle="' +
                lec.id +
                '" title="Detay"><i class="fa-solid fa-chevron-' +
                (open ? "up" : "down") +
                '"></i></button>' +
                "</div>" +
                '<div class="lec-panel"' +
                (open ? "" : " hidden") +
                ">" +
                (showContent || hasVideo
                  ? '<div class="lec-media-row">' +
                    '<div class="lec-media-info">' +
                    (hasVideo
                      ? '<i class="fa-solid fa-film"></i> <span>' +
                        esc(fileBase(lec.video_path)) +
                        "</span>" +
                        (lecDur ? " · <strong>" + esc(lecDur) + "</strong>" : "")
                      : "<span>Video isteğe bağlıdır. Şimdi veya sonra ekleyebilirsiniz.</span>") +
                    "</div>" +
                    '<div class="lec-media-actions">' +
                    '<input type="file" accept="video/mp4,video/webm" hidden data-lec-file="' +
                    lec.id +
                    '">' +
                    '<button type="button" class="btn-outline" data-lec-pick="' +
                    lec.id +
                    '" id="lecPickBtn' +
                    lec.id +
                    '">' +
                    (hasVideo ? "Videoyu Değiştir" : "Video yükle") +
                    "</button>" +
                    '<label class="lec-preview-toggle"><input type="checkbox" data-lec-preview="' +
                    lec.id +
                    '"' +
                    (lec.is_preview == 1 ? " checked" : "") +
                    "> Ücretsiz önizleme</label>" +
                    "</div>" +
                    '<div class="lec-upload" id="lecUpload' +
                    lec.id +
                    '" hidden>' +
                    '<div class="upload-bar"><span id="lecBar' +
                    lec.id +
                    '"></span></div>' +
                    '<div class="upload-meta"><span class="upload-pct" id="lecPct' +
                    lec.id +
                    '">0%</span>' +
                    '<span class="upload-status" id="lecStatus' +
                    lec.id +
                    '">Yükleniyor…</span></div></div>' +
                    "</div>"
                  : '<div class="lec-content-cta">' +
                    '<button type="button" class="btn-add-item" data-add-content="' +
                    lec.id +
                    '">+ İçerik</button>' +
                    "<p class=\"lec-empty-hint\">Ders kaydı oluşturuldu. Video eklemek zorunlu değildir.</p>" +
                    "</div>") +
                '<div class="lec-extra" data-extra="' +
                lec.id +
                '">' +
                (showDesc
                  ? '<div class="lec-extra-block" data-desc-block="' +
                    lec.id +
                    '"><label>Açıklama</label><textarea rows="3" data-lec-desc="' +
                    lec.id +
                    '">' +
                    esc(desc) +
                    '</textarea><button type="button" class="btn-primary sm" data-desc-save="' +
                    lec.id +
                    '">Açıklamayı kaydet</button></div>'
                  : '<button type="button" class="btn-add-item" data-add-desc="' +
                    lec.id +
                    '">+ Açıklama</button>') +
                (showRes
                  ? '<div class="lec-extra-block" data-res-block="' +
                    lec.id +
                    '"><label>Kaynaklar</label>' +
                    resourcesListHtml(resources) +
                    '<div class="lec-res-add">' +
                    '<input type="text" placeholder="Kaynak adı (opsiyonel)" data-res-name="' +
                    lec.id +
                    '"><input type="url" placeholder="https://..." data-res-url="' +
                    lec.id +
                    '"><button type="button" class="btn-outline" data-res-add="' +
                    lec.id +
                    '">Link ekle</button>' +
                    '<input type="file" hidden data-res-file="' +
                    lec.id +
                    '" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv,image/*">' +
                    '<button type="button" class="btn-primary sm" data-res-upload="' +
                    lec.id +
                    '">Dosyadan yükle</button></div></div>'
                  : '<button type="button" class="btn-add-item" data-add-res="' +
                    lec.id +
                    '">+ Kaynaklar</button>') +
                "</div></div></div>"
              );
            })
            .join("");
          return (
            '<div class="section-block" data-sec="' +
            sec.id +
            '">' +
            '<div class="section-head">' +
            '<span class="sec-label">Bölüm ' +
            secNum +
            ":</span>" +
            '<input type="text" value="' +
            esc(secTitle) +
            '" placeholder="Bölüm başlığı" data-sec-title="' +
            sec.id +
            '">' +
            '<button type="button" class="btn-ghost" data-sec-save="' +
            sec.id +
            '">Kaydet</button>' +
            '<button type="button" class="btn-danger" data-sec-del="' +
            sec.id +
            '">Sil</button>' +
            "</div>" +
            '<div class="lecture-list" data-drop-sec="' +
            sec.id +
            '">' +
            lectures +
            "</div>" +
            '<div class="sec-foot">' +
            '<button type="button" class="btn-add-item" data-add-lec="' +
            sec.id +
            '">+ Müfredat öğesi</button>' +
            "</div></div>"
          );
        })
        .join("");
    }

    $("btnAddSec").onclick = function () {
      var next = (state.course.curriculum || []).length + 1;
      api("section_save", {
        body: { course_id: state.courseId, title: "" },
      })
        .then(function (d) {
          state.course.curriculum = d.curriculum;
          toast("Bölüm " + next + " eklendi");
          viewCurriculum();
        })
        .catch(function (e) {
          toast(e.message, true);
        });
    };

    function refreshCur(d) {
      if (d && d.curriculum) state.course.curriculum = d.curriculum;
      viewCurriculum();
    }

    function saveReorder() {
      var sections = [];
      wrap.querySelectorAll(".section-block").forEach(function (secEl) {
        var secId = parseInt(secEl.getAttribute("data-sec"), 10);
        var lectures = [];
        secEl.querySelectorAll(".lec-card").forEach(function (card) {
          lectures.push(parseInt(card.getAttribute("data-lec"), 10));
        });
        sections.push({ id: secId, lectures: lectures });
      });
      api("curriculum_reorder", {
        body: { course_id: state.courseId, sections: sections },
      })
        .then(function (d) {
          state.course.curriculum = d.curriculum;
          toast("Sıra güncellendi");
          viewCurriculum();
        })
        .catch(function (e) {
          toast(e.message, true);
          viewCurriculum();
        });
    }

    wrap.onclick = function (e) {
      var t = e.target.closest("[data-sec-save], [data-sec-del], [data-add-lec], [data-lec-del], [data-lec-edit], [data-lec-toggle], [data-lec-pick], [data-add-desc], [data-add-res], [data-desc-save], [data-res-add], [data-res-del], [data-lec-preview], [data-add-content], [data-res-upload]");
      if (!t) return;

      var secSave = t.getAttribute("data-sec-save");
      if (secSave) {
        var titleInp = wrap.querySelector('[data-sec-title="' + secSave + '"]');
        api("section_save", {
          body: {
            id: parseInt(secSave, 10),
            course_id: state.courseId,
            title: cleanAutoTitle(titleInp.value, "section"),
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            toast("Bölüm kaydedildi");
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var secDel = t.getAttribute("data-sec-del");
      if (secDel) {
        if (!confirm("Bu bölüm ve içindeki tüm dersler silinsin mi?")) return;
        api("section_delete", {
          body: { id: parseInt(secDel, 10), course_id: state.courseId },
        })
          .then(refreshCur)
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var addLec = t.getAttribute("data-add-lec");
      if (addLec) {
        // Video olmadan ders kaydı oluştur
        api("lecture_save", {
          body: {
            course_id: state.courseId,
            section_id: parseInt(addLec, 10),
            title: "Yeni ders",
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            if (d.id) expandedLecIds[d.id] = true;
            toast("Ders eklendi (video zorunlu değil)");
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var addContent = t.getAttribute("data-add-content");
      if (addContent) {
        expandedLecIds[addContent] = true;
        expandedLecIds[addContent + ":content"] = true;
        viewCurriculum();
        return;
      }

      var lecDel = t.getAttribute("data-lec-del");
      if (lecDel) {
        if (!confirm("Bu ders silinsin mi? Video da kalıcı olarak silinir.")) return;
        api("lecture_delete", {
          body: { id: parseInt(lecDel, 10), course_id: state.courseId },
        })
          .then(refreshCur)
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var lecEdit = t.getAttribute("data-lec-edit");
      if (lecEdit) {
        var card = wrap.querySelector('.lec-card[data-lec="' + lecEdit + '"]');
        if (!card) return;
        var disp = card.querySelector("[data-lec-title-display]");
        var inp = card.querySelector("[data-lec-title]");
        if (!disp || !inp) return;
        disp.hidden = true;
        inp.hidden = false;
        inp.focus();
        inp.select();
        var finish = function () {
          inp.onblur = null;
          inp.onkeydown = null;
          var newTitle = cleanAutoTitle(inp.value, "lecture");
          api("lecture_save", {
            body: {
              id: parseInt(lecEdit, 10),
              course_id: state.courseId,
              title: newTitle,
            },
          })
            .then(function (d) {
              state.course.curriculum = d.curriculum;
              toast("Ders adı güncellendi");
              viewCurriculum();
            })
            .catch(function (err) {
              toast(err.message, true);
            });
        };
        inp.onblur = finish;
        inp.onkeydown = function (ev) {
          if (ev.key === "Enter") {
            ev.preventDefault();
            inp.blur();
          }
          if (ev.key === "Escape") {
            inp.onblur = null;
            viewCurriculum();
          }
        };
        return;
      }

      var lecToggle = t.getAttribute("data-lec-toggle");
      if (lecToggle) {
        if (expandedLecIds[lecToggle]) delete expandedLecIds[lecToggle];
        else expandedLecIds[lecToggle] = true;
        viewCurriculum();
        return;
      }

      var lecPick = t.getAttribute("data-lec-pick");
      if (lecPick) {
        var fileInp = wrap.querySelector('[data-lec-file="' + lecPick + '"]');
        if (fileInp) fileInp.click();
        return;
      }

      var addDesc = t.getAttribute("data-add-desc");
      if (addDesc) {
        expandedLecIds[addDesc] = true;
        expandedLecIds[addDesc + ":desc"] = true;
        viewCurriculum();
        return;
      }

      var addRes = t.getAttribute("data-add-res");
      if (addRes) {
        expandedLecIds[addRes] = true;
        expandedLecIds[addRes + ":res"] = true;
        viewCurriculum();
        return;
      }

      var descSave = t.getAttribute("data-desc-save");
      if (descSave) {
        var ta = wrap.querySelector('[data-lec-desc="' + descSave + '"]');
        api("lecture_save", {
          body: {
            id: parseInt(descSave, 10),
            course_id: state.courseId,
            description: ta ? ta.value : "",
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            toast("Açıklama kaydedildi");
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var resUpload = t.getAttribute("data-res-upload");
      if (resUpload) {
        var fileRes = wrap.querySelector('[data-res-file="' + resUpload + '"]');
        if (fileRes) fileRes.click();
        return;
      }

      var resAdd = t.getAttribute("data-res-add");
      if (resAdd) {
        var lec = findLecture(state.course.curriculum, resAdd);
        var nameEl = wrap.querySelector('[data-res-name="' + resAdd + '"]');
        var urlEl = wrap.querySelector('[data-res-url="' + resAdd + '"]');
        var list = (lec && lec.resources ? lec.resources.slice() : []) || [];
        var name = nameEl ? nameEl.value.trim() : "";
        var url = urlEl ? urlEl.value.trim() : "";
        if (!url) {
          toast("Link girin veya dosya yükleyin", true);
          return;
        }
        list.push({ name: name || url, url: url, type: "link" });
        api("lecture_save", {
          body: {
            id: parseInt(resAdd, 10),
            course_id: state.courseId,
            resources: list,
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            expandedLecIds[resAdd + ":res"] = true;
            toast("Kaynak eklendi");
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }

      var resDelBtn = t.closest("[data-res-del]");
      if (resDelBtn) {
        var block = t.closest("[data-res-block]");
        if (!block) return;
        var lecIdRes = block.getAttribute("data-res-block");
        var idx = parseInt(resDelBtn.getAttribute("data-res-del"), 10);
        var lecR = findLecture(state.course.curriculum, lecIdRes);
        var listR = (lecR && lecR.resources ? lecR.resources.slice() : []) || [];
        listR.splice(idx, 1);
        api("lecture_save", {
          body: {
            id: parseInt(lecIdRes, 10),
            course_id: state.courseId,
            resources: listR,
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            expandedLecIds[lecIdRes + ":res"] = true;
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }
    };

    wrap.onchange = function (e) {
      var t = e.target;
      var preview = t.getAttribute("data-lec-preview");
      if (preview) {
        api("lecture_save", {
          body: {
            id: parseInt(preview, 10),
            course_id: state.courseId,
            is_preview: t.checked ? 1 : 0,
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
            toast(t.checked ? "Önizleme açıldı" : "Önizleme kapatıldı");
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }
      var resFile = t.getAttribute("data-res-file");
      if (resFile && t.type === "file") {
        var resLecId = parseInt(resFile, 10);
        var resF = t.files && t.files[0];
        if (!resF) return;
        expandedLecIds[resLecId] = true;
        expandedLecIds[resLecId + ":res"] = true;
        var fd = new FormData();
        fd.append("file", resF);
        fd.append("course_id", String(state.courseId));
        fd.append("kind", "resource");
        fd.append("lecture_id", String(resLecId));
        api("upload", { form: fd })
          .then(function (d) {
            if (d.curriculum) state.course.curriculum = d.curriculum;
            else if (d.item) state.course = d.item;
            toast("Kaynak dosyası yüklendi");
            viewCurriculum();
          })
          .catch(function (err) {
            toast(err.message, true);
          });
        return;
      }
      var lecFile = t.getAttribute("data-lec-file");
      if (!lecFile || t.type !== "file") return;
      var lecId = parseInt(lecFile, 10);
      var file = t.files && t.files[0];
      if (!file) return;
      expandedLecIds[lecId] = true;
      expandedLecIds[lecId + ":content"] = true;
      // İlerleme çubuğu DOM'da olsun diye paneli açık tut
      if (!$("lecUpload" + lecId)) {
        viewCurriculum();
      }
      setTimeout(function () {
        setLectureUploadUi(lecId, 1, "Dosya okunuyor… " + file.name, false);
        getVideoDurationFromFile(file).then(function (dur) {
          uploadFile("lecture", file, lecId, dur || 0);
        });
      }, 30);
    };

    wrap.addEventListener("focusout", function (e) {
      var t = e.target;
      if (!t || t.tagName !== "INPUT" || t.type !== "text") return;
      var secId = t.getAttribute("data-sec-title");
      if (secId) {
        api("section_save", {
          body: {
            id: parseInt(secId, 10),
            course_id: state.courseId,
            title: cleanAutoTitle(t.value, "section"),
          },
        })
          .then(function (d) {
            state.course.curriculum = d.curriculum;
          })
          .catch(function () {});
      }
    });

    // Drag & drop
    var dragCard = null;
    wrap.querySelectorAll(".lec-card").forEach(function (card) {
      card.addEventListener("dragstart", function (e) {
        // Buton / input / link üzerinden sürüklemeyi engelle; satırın geri kalanı sürüklenir
        if (
          e.target.closest(
            "button, input, textarea, a, label, .lec-hover-actions, .lec-panel"
          )
        ) {
          e.preventDefault();
          return;
        }
        dragCard = card;
        card.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
        try {
          e.dataTransfer.setData("text/plain", card.getAttribute("data-lec"));
        } catch (err) {}
      });
      card.addEventListener("dragend", function () {
        card.classList.remove("is-dragging");
        wrap.querySelectorAll(".lecture-list.is-drop").forEach(function (el) {
          el.classList.remove("is-drop");
        });
        dragCard = null;
      });
    });
    wrap.querySelectorAll(".lecture-list").forEach(function (list) {
      list.addEventListener("dragover", function (e) {
        e.preventDefault();
        list.classList.add("is-drop");
        e.dataTransfer.dropEffect = "move";
        if (!dragCard) return;
        var after = null;
        var cards = Array.prototype.slice.call(list.querySelectorAll(".lec-card:not(.is-dragging)"));
        for (var i = 0; i < cards.length; i++) {
          var rect = cards[i].getBoundingClientRect();
          if (e.clientY < rect.top + rect.height / 2) {
            after = cards[i];
            break;
          }
        }
        if (after) list.insertBefore(dragCard, after);
        else list.appendChild(dragCard);
      });
      list.addEventListener("dragleave", function (e) {
        if (!list.contains(e.relatedTarget)) list.classList.remove("is-drop");
      });
      list.addEventListener("drop", function (e) {
        e.preventDefault();
        list.classList.remove("is-drop");
        if (dragCard) saveReorder();
      });
    });

    probeMissingLectureDurations(cur);
  }

  function viewSubscribers() {
    var wrap = $("viewWrap");
    wrap.innerHTML = '<div class="loading">Aboneler yükleniyor…</div>';
    api("subscriptions_list")
      .then(function (d) {
        var items = d.items || [];
        var rows = items.length
          ? items
              .map(function (r) {
                return (
                  "<tr>" +
                  "<td><strong>" +
                  esc(r.student_name || "—") +
                  "</strong><div class='small'>" +
                  esc(r.student_email || "") +
                  "</div></td>" +
                  "<td>" +
                  esc(r.student_phone || "—") +
                  "</td>" +
                  "<td>" +
                  esc(r.status_label || r.status || "—") +
                  "</td>" +
                  "<td class='small'>" +
                  esc(r.current_period_end || "—") +
                  "</td>" +
                  "</tr>"
                );
              })
              .join("")
          : "<tr><td colspan='4' class='empty'>Bu plana bağlı abone yok. Yönetici ayarlardan sizi seçmiş olmalı.</td></tr>";
        wrap.innerHTML =
          '<p class="page-lead">WhatsApp grubu aboneliği tek plana bağlıdır. Grup ekleme siteden yapılmaz.</p>' +
          '<div class="panel"><div class="table-wrap"><table><thead><tr>' +
          "<th>Öğrenci</th><th>Telefon</th><th>Durum</th><th>Dönem sonu</th>" +
          "</tr></thead><tbody>" +
          rows +
          "</tbody></table></div></div>";
      })
      .catch(function (e) {
        wrap.innerHTML = '<p class="loading">' + esc(e.message) + "</p>";
      });
  }

  function viewStudents() {
    $("viewWrap").innerHTML = '<div class="loading">Öğrenciler yükleniyor…</div>';
    api("students_list", { query: "&course_id=" + state.courseId })
      .then(function (d) {
        var items = d.items || [];
        var rows = items.length
          ? items
              .map(function (s) {
                return (
                  "<tr>" +
                  "<td><strong>" +
                  esc(s.student_name) +
                  "</strong><div class='small'>" +
                  esc(s.student_email || "") +
                  (s.student_phone
                    ? " · " + esc(s.student_phone)
                    : "") +
                  "</div></td>" +
                  "<td>" +
                  '<div class="progress-cell"><div class="progress-bar"><span style="width:' +
                  (parseInt(s.progress_pct, 10) || 0) +
                  '%"></span></div><em>%' +
                  (parseInt(s.progress_pct, 10) || 0) +
                  "</em></div></td>" +
                  "<td>" +
                  esc(fmtDateTime(s.enrolled_at)) +
                  "</td>" +
                  "<td>" +
                  esc(s.last_visit_at ? fmtDateTime(s.last_visit_at) : "—") +
                  "</td></tr>"
                );
              })
              .join("")
          : '<tr><td colspan="4" class="loading">Henüz bu kursu satın alan öğrenci yok. Ödeme tamamlanınca burada görünür.</td></tr>';

        $("viewWrap").innerHTML =
          '<p class="page-lead">Bu kursu satın almış öğrenciler. İptal veya iade edilen kayıtlar listede yer almaz.</p>' +
          '<div class="panel"><div class="table-wrap"><table class="students-table"><thead><tr>' +
          "<th>Öğrenci</th><th>İzleme</th><th>Kayıt tarihi</th><th>Son ziyaret</th>" +
          "</tr></thead><tbody>" +
          rows +
          "</tbody></table></div></div>";
      })
      .catch(function (e) {
        $("viewWrap").innerHTML =
          '<p class="loading">' + esc(e.message) + "</p>";
      });
  }

  function fmtDateTime(s) {
    if (!s) return "";
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) return String(s);
    return d.toLocaleString("tr-TR", {
      timeZone: "Europe/Istanbul",
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function toLocalInput(s) {
    if (!s) return "";
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) return "";
    var pad = function (n) {
      return (n < 10 ? "0" : "") + n;
    };
    return (
      d.getFullYear() +
      "-" +
      pad(d.getMonth() + 1) +
      "-" +
      pad(d.getDate()) +
      "T" +
      pad(d.getHours()) +
      ":" +
      pad(d.getMinutes())
    );
  }


  var probedLectureIds = {};

  function probeMissingLectureDurations(cur) {
    var jobs = [];
    (cur || []).forEach(function (sec) {
      (sec.lectures || []).forEach(function (lec) {
        if (
          lec.video_path &&
          !(parseInt(lec.duration_sec, 10) > 0) &&
          !probedLectureIds[lec.id]
        ) {
          jobs.push(lec);
        }
      });
    });
    if (!jobs.length) return;
    var i = 0;
    var updated = false;
    function next() {
      if (i >= jobs.length) {
        if (updated && state.view === "curriculum") {
          loadCourse().then(function () {
            if (state.view === "curriculum") viewCurriculum();
          });
        }
        return;
      }
      var lec = jobs[i++];
      probedLectureIds[lec.id] = true;
      getVideoDurationFromUrl(mediaStreamUrl("lecture", lec.id)).then(function (dur) {
        if (dur <= 0) {
          next();
          return;
        }
        api("lecture_duration", {
          body: {
            id: lec.id,
            course_id: state.courseId,
            duration_sec: dur,
          },
        })
          .then(function (d) {
            updated = true;
            if (d.curriculum) state.course.curriculum = d.curriculum;
            var el = $("curTotal");
            if (el) {
              var label = formatDurationLabel(
                d.total_duration_sec || curriculumTotalSec(d.curriculum)
              );
              el.innerHTML = label
                ? "Toplam süre: <strong>" + esc(label) + "</strong>"
                : "Toplam süre: video yüklendikçe hesaplanır";
            }
            next();
          })
          .catch(function () {
            next();
          });
      });
    }
    next();
  }

  function priceDigits(s) {
    return String(s || "").replace(/\D/g, "");
  }

  /** 4990 → 4.990 */
  function formatPriceGrouped(s) {
    var d = priceDigits(s);
    if (!d) return "";
    return d.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  /** 4990 / 4.990 TL → 4.990 TL */
  function formatPriceTL(s) {
    var g = formatPriceGrouped(s);
    return g ? g + " TL" : "";
  }

  function viewPricing() {
    var c = state.course || {};
    $("viewWrap").innerHTML =
      '<p class="page-lead">Kurs fiyatını belirleyin. Ödeme altyapısı bu aşamada yoktur; bilgi amaçlıdır.</p>' +
      '<div class="panel"><div class="panel-body">' +
      '<div class="field"><label>Fiyat</label>' +
      '<div class="price-input-wrap">' +
      '<input type="text" id="fPrice" inputmode="numeric" autocomplete="off" value="' +
      esc(formatPriceGrouped(c.price || "")) +
      '" placeholder="4.990">' +
      '<span class="price-suffix">TL</span></div>' +
      '<p class="hint">Sadece rakam yazın; binlik ayracı ve TL otomatik eklenir.</p></div>' +
      '<div class="field"><label>Fiyat notu</label><input type="text" id="fPriceNote" value="' +
      esc(c.price_note || "") +
      '" placeholder="Peşin / taksit bilgisi"></div>' +
      '</div></div><div class="actions-bar"><button type="button" class="btn-primary sm" id="btnSavePrice">Kaydet</button></div>';

    var priceInput = $("fPrice");
    priceInput.addEventListener("input", function () {
      var start = priceInput.selectionStart;
      var before = priceInput.value;
      var digitsBefore = priceDigits(before.slice(0, start)).length;
      var formatted = formatPriceGrouped(before);
      priceInput.value = formatted;
      // imleci yaklaşık aynı rakam konumunda tut
      var pos = 0;
      var seen = 0;
      while (pos < formatted.length && seen < digitsBefore) {
        if (/\d/.test(formatted.charAt(pos))) seen++;
        pos++;
      }
      try {
        priceInput.setSelectionRange(pos, pos);
      } catch (e) {}
    });

    $("btnSavePrice").onclick = function () {
      saveCourseFields({
        price: formatPriceTL(priceInput.value),
        price_note: $("fPriceNote").value,
      });
    };
  }

  function viewPublish() {
    var c = state.course || {};
    var slug = "course-" + (c.id || state.courseId || "");
    var origin = (window.SITE_PUBLIC_URL || location.origin || "").replace(/\/$/, "");
    var courseUrl = origin + "/egitim-detay.html?id=" + encodeURIComponent(slug);
    var payUrl = origin + "/odeme.php?course=" + encodeURIComponent(slug);
    $("viewWrap").innerHTML =
      '<p class="page-lead">Kursu taslakta tutun veya yayına alın. <strong>Yayında</strong> olan kurslar sitedeki <strong>Eğitimler</strong> sayfasında görünür. Domain hazırsa aşağıdaki linkleri öğrencilerle paylaşın.</p>' +
      '<div class="panel"><div class="panel-body">' +
      '<div class="field"><label>Durum</label><select id="fStatus">' +
      '<option value="draft"' +
      (c.status !== "published" ? " selected" : "") +
      ">Taslak</option>" +
      '<option value="published"' +
      (c.status === "published" ? " selected" : "") +
      ">Yayında</option>" +
      "</select></div>" +
      '<p class="hint">Şu an: <strong class="pill ' +
      (c.status === "published" ? "published" : "draft") +
      '">' +
      (c.status === "published" ? "Yayında" : "Taslak") +
      "</strong></p>" +
      '<div class="field" style="margin-top:16px"><label>Kurs sayfası (paylaş)</label>' +
      '<div class="file-row"><input type="text" id="shareCourseUrl" readonly value="' +
      esc(courseUrl) +
      '"><button type="button" class="btn" id="btnCopyCourse">Kopyala</button></div></div>' +
      '<div class="field"><label>Ödeme / kayıt linki</label>' +
      '<div class="file-row"><input type="text" id="sharePayUrl" readonly value="' +
      esc(payUrl) +
      '"><button type="button" class="btn" id="btnCopyPay">Kopyala</button></div>' +
      '<p class="hint">Kart ödemesi iyzico ile alınır. Havale / EFT ödeme sayfasında durur.</p></div>' +
      '</div></div><div class="actions-bar"><button type="button" class="btn-primary sm" id="btnSavePub">Kaydet</button></div>';
    $("btnSavePub").onclick = function () {
      saveCourseFields({ status: $("fStatus").value });
    };
    function copyField(id) {
      var inp = $(id);
      if (!inp) return;
      var val = inp.value || "";
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(function () {
          toast("Link kopyalandı");
        }).catch(function () {
          inp.select();
          document.execCommand("copy");
          toast("Link kopyalandı");
        });
      } else {
        inp.select();
        document.execCommand("copy");
        toast("Link kopyalandı");
      }
    }
    $("btnCopyCourse").onclick = function () { copyField("shareCourseUrl"); };
    $("btnCopyPay").onclick = function () { copyField("sharePayUrl"); };
  }

  function saveCourseFields(extra) {
    var c = state.course || {};
    var body = {
      id: state.courseId,
      title: c.title || "Kurs",
      subtitle: c.subtitle || "",
      description: c.description || "",
      language: c.language || "Türkçe",
      level: c.level || "Tüm Düzeyler",
      category: c.category || "",
      subcategory: c.subcategory || "",
      topic: c.topic || "",
      price: c.price || "",
      price_note: c.price_note || "",
      status: c.status || "draft",
      objectives: (c.objectives || []).map(function (x) { return x.body; }),
      requirements: (c.requirements || []).map(function (x) { return x.body; }),
      audience: (c.audience || []).map(function (x) { return x.body; }),
    };
    Object.keys(extra).forEach(function (k) {
      body[k] = extra[k];
    });
    api("course_save", { body: body })
      .then(function (d) {
        state.course = d.item;
        toast("Kaydedildi");
        return loadCourses().then(render);
      })
      .catch(function (e) {
        toast(e.message, true);
      });
  }

  function viewProfile() {
    $("viewWrap").innerHTML = '<div class="loading">Profil yükleniyor…</div>';
    api("profile_get")
      .then(function (d) {
        var m = d.item || {};
        var socials =
          m.socials && m.socials.length
            ? m.socials
            : [{ platform: "linkedin", url: "" }];
        var slug = m.slug || "";
        $("viewWrap").innerHTML =
          '<div class="panel">' +
          '<p class="page-lead">Profil bilgileriniz sitedeki eğitmen sayfasında görünür. Sosyal hesap ekledikçe fotoğrafınızın altında listelenir.</p>' +
          (slug
            ? '<p class="block-desc"><a href="/egitmen-profil.html?id=' +
              encodeURIComponent(slug) +
              '" target="_blank" rel="noopener">Profil sayfasını gör →</a></p>'
            : "") +
          '<form id="profileForm">' +
          '<div class="form-grid">' +
          '<div class="field full"><label>Ad Soyad</label><input name="name" value="' +
          esc(m.name || "") +
          '" required></div>' +
          '<div class="field full"><label>Unvan</label><input name="title" value="' +
          esc(m.title || "") +
          '" placeholder="Analiz & Yatırım Eğitmeni"></div>' +
          '<div class="field full"><label>Profil fotoğrafı</label>' +
          '<div class="file-row">' +
          '<input name="photo_path" id="profPhoto" value="' +
          esc(m.photo_path || "") +
          '">' +
          '<input type="file" id="profPhotoFile" accept="image/*" hidden>' +
          '<button type="button" class="btn" id="profPhotoBtn">Yükle / Kırp</button></div>' +
          '<p class="block-desc">Sürükleyip zoom ile istediğiniz kısmı seçin. 1200×1200 kaydedilir. Önizlemeye tıklayınca tam boy açılır.</p>' +
          '<div class="prof-preview" id="profPreview">' +
          (m.photo_path
            ? '<img src="/' + esc(m.photo_path) + '" alt="">'
            : "<span>Önizleme yok</span>") +
          "</div></div>" +
          '<div class="field full"><label>Açıklama / biyografi</label><textarea name="bio" rows="7">' +
          esc(m.bio || "") +
          "</textarea></div>" +
          '<div class="field full"><label>Sosyal medya hesapları</label><div id="profSocialRows"></div>' +
          '<button type="button" class="btn" id="profAddSocial" style="margin-top:8px">+ Hesap ekle</button></div>' +
          "</div>" +
          '<div class="form-actions"><button type="submit" class="btn-primary">Profili kaydet</button></div>' +
          "</form>" +
          '<hr class="sep">' +
          "<h2 class=\"block-title\">Şifre değiştir</h2>" +
          '<form id="passForm" class="form-grid">' +
          '<div class="field"><label>Mevcut şifre</label><input type="password" name="current" required autocomplete="current-password"></div>' +
          '<div class="field"><label>Yeni şifre</label><input type="password" name="new" required minlength="6" autocomplete="new-password"></div>' +
          '<div class="field full form-actions" style="justify-content:flex-start"><button type="submit" class="btn">Şifreyi güncelle</button></div>' +
          "</form></div>";

        var box = $("profSocialRows");
        function platformOptions(selected) {
          return SOCIAL_PLATFORMS.map(function (p) {
            return (
              '<option value="' +
              p.id +
              '"' +
              (p.id === selected ? " selected" : "") +
              ">" +
              p.label +
              "</option>"
            );
          }).join("");
        }
        function addRow(item) {
          item = item || { platform: "linkedin", url: "" };
          var row = document.createElement("div");
          row.className = "social-row";
          row.innerHTML =
            "<select>" +
            platformOptions(item.platform || "link") +
            '</select><input type="url" placeholder="https://..." value="' +
            esc(item.url || "") +
            '"><button type="button" class="btn danger rm">Sil</button>';
          row.querySelector(".rm").onclick = function () {
            if (box.querySelectorAll(".social-row").length > 1) row.remove();
            else row.querySelector("input").value = "";
          };
          box.appendChild(row);
        }
        socials.forEach(addRow);
        $("profAddSocial").onclick = function () {
          addRow({ platform: "instagram", url: "" });
        };

        function openPhotoLightbox(src) {
          if (!src) return;
          var old = document.getElementById("photoLightbox");
          if (old) old.remove();
          var box = document.createElement("div");
          box.id = "photoLightbox";
          box.className = "photo-lightbox";
          box.innerHTML =
            '<div class="photo-lightbox-card">' +
            '<button type="button" class="photo-lightbox-close" aria-label="Kapat">✕</button>' +
            '<img src="' +
            esc(src) +
            '" alt="Profil fotoğrafı">' +
            "</div>";
          document.body.appendChild(box);
          function close() {
            box.remove();
            document.removeEventListener("keydown", onKey);
          }
          function onKey(e) {
            if (e.key === "Escape") close();
          }
          box.addEventListener("click", function (e) {
            if (
              e.target === box ||
              e.target.classList.contains("photo-lightbox-close")
            )
              close();
          });
          document.addEventListener("keydown", onKey);
        }

        function wireProfPreview() {
          var img = document.querySelector("#profPreview img");
          if (!img) return;
          img.style.cursor = "zoom-in";
          img.onclick = function () {
            openPhotoLightbox(img.src);
          };
        }
        wireProfPreview();

        $("profPhotoBtn").onclick = function () {
          $("profPhotoFile").click();
        };
        $("profPhotoFile").onchange = function () {
          var file = this.files && this.files[0];
          this.value = "";
          if (!file) return;
          if (!window.BM_PHOTO_CROP) {
            toast("Kırpma aracı yüklenemedi", true);
            return;
          }
          BM_PHOTO_CROP.open({
            file: file,
            onDone: function (blob, filename) {
              var fd = new FormData();
              fd.append("file", blob, filename || "profile.jpg");
              api("profile_upload", { form: fd })
                .then(function (res) {
                  $("profPhoto").value = res.path;
                  $("profPreview").innerHTML =
                    '<img src="' +
                    esc(photoSrc(res.path)) +
                    "?t=" +
                    Date.now() +
                    '" alt="">';
                  wireProfPreview();
                  if (!state.me) state.me = { user: {}, instructor: {} };
                  if (!state.me.instructor) state.me.instructor = {};
                  state.me.instructor.photo_path = res.path;
                  applySideUser();
                  toast("Fotoğraf yüklendi (1200px)");
                })
                .catch(function (e) {
                  toast(e.message, true);
                });
            },
          });
        };

        $("profileForm").onsubmit = function (e) {
          e.preventDefault();
          var f = e.target;
          var socialPayload = [];
          box.querySelectorAll(".social-row").forEach(function (row) {
            socialPayload.push({
              platform: row.querySelector("select").value,
              url: row.querySelector("input").value,
            });
          });
          api("profile_save", {
            body: {
              name: f.name.value,
              title: f.title.value,
              photo_path: f.photo_path.value,
              bio: f.bio.value,
              socials: socialPayload,
            },
          })
            .then(function (d) {
              syncMeFromProfile(d.item);
              toast("Profil kaydedildi");
            })
            .catch(function (err) {
              toast(err.message, true);
            });
        };

        $("passForm").onsubmit = function (e) {
          e.preventDefault();
          var f = e.target;
          api("change_password", {
            body: { current: f.current.value, new: f.new.value },
          })
            .then(function () {
              toast("Şifre güncellendi");
              f.reset();
            })
            .catch(function (err) {
              toast(err.message, true);
            });
        };
      })
      .catch(function (e) {
        $("viewWrap").innerHTML =
          '<p class="loading">' + esc(e.message) + "</p>";
        toast(e.message, true);
      });
  }

  function render() {
    $("viewTitle").textContent = TITLES[state.view] || "";
    document.querySelectorAll(".nav-item").forEach(function (a) {
      a.classList.toggle("active", a.getAttribute("data-view") === state.view);
    });
    var wrap = $("viewWrap");
    if (wrap && state.view !== "dashboard") wrap.classList.remove("is-dashboard");

    if (state.view === "dashboard") {
      viewDashboard();
      return;
    }
    if (state.view === "profile") {
      viewProfile();
      return;
    }
    if (state.view === "courses") {
      viewCourses();
      return;
    }
    if (state.view === "subscribers") {
      viewSubscribers();
      return;
    }
    if (!state.courseId) {
      wrap.innerHTML =
        '<p class="loading">Önce <a href="#" id="goCourses">Kurslarım</a> üzerinden bir kurs seçin veya oluşturun.</p>';
      var g = $("goCourses");
      if (g)
        g.onclick = function (e) {
          e.preventDefault();
          go("courses");
        };
      return;
    }

    wrap.innerHTML = '<div class="loading">Yükleniyor…</div>';
    loadCourse()
      .then(function () {
        if (state.view === "landing") viewLanding();
        else if (state.view === "goals") viewGoals();
        else if (state.view === "curriculum") viewCurriculum();
        else if (state.view === "pricing") viewPricing();
        else if (state.view === "publish") viewPublish();
        else if (state.view === "students") viewStudents();
      })
      .catch(function (e) {
        wrap.innerHTML = '<p class="loading">' + esc(e.message) + "</p>";
        toast(e.message, true);
      });
  }

  function go(view) {
    state.view = view;
    try {
      if (location.hash.replace(/^#/, "") !== view) {
        history.replaceState(null, "", "#" + view);
      }
    } catch (e) {}
    render();
  }

  /* ---------- boot ---------- */
  window.__egitmenBooted = true;

  document.querySelectorAll(".nav-item[data-view]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      e.preventDefault();
      if (a.classList.contains("is-disabled")) return;
      go(a.getAttribute("data-view"));
      var side = $("sidebar");
      if (side) side.classList.remove("open");
    });
  });

  var picker = $("coursePicker");
  if (picker) {
    picker.addEventListener("change", function () {
      setCourseId(this.value);
      if (state.view === "courses") go("landing");
      else render();
    });
  }

  var menuToggle = $("menuToggle");
  if (menuToggle) {
    menuToggle.addEventListener("click", function () {
      var shell = document.querySelector(".shell");
      var side = $("sidebar");
      if (window.matchMedia && window.matchMedia("(max-width: 800px)").matches) {
        if (side) side.classList.toggle("open");
      } else if (shell) {
        shell.classList.toggle("sidebar-collapsed");
      }
    });
  }

  var btnTopNew = $("btnTopNewCourse");
  if (btnTopNew) {
    btnTopNew.addEventListener("click", function () {
      createNewCourse().catch(function (e) {
        toast(e.message, true);
      });
    });
  }
  var btnNotify = $("btnTopNotify");
  if (btnNotify) {
    btnNotify.addEventListener("click", function () {
      toast("Bildirimler yakında");
    });
  }
  var btnMsg = $("btnTopMsg");
  if (btnMsg) {
    btnMsg.addEventListener("click", function () {
      toast("Mesajlar yakında");
    });
  }
  var btnTopUser = $("btnTopUser");
  if (btnTopUser) {
    btnTopUser.addEventListener("click", function () {
      go("profile");
    });
  }
  var sideUser = $("sideUser");
  if (sideUser) {
    sideUser.addEventListener("click", function (e) {
      e.preventDefault();
      go("profile");
      var side = $("sidebar");
      if (side) side.classList.remove("open");
    });
  }

  var hash = (location.hash || "").replace(/^#/, "");
  if (hash && TITLES[hash]) state.view = hash;

  // Önce boş arayüzü göster (menüler çalışsın), sonra API'den doldur
  updateNavEnabled();
  render();

  api("me")
    .then(function (d) {
      state.me = d;
      if (d.instructor && d.instructor.photo_path) {
        applySideUser();
        return null;
      }
      // Admin veya boş me: profil API'sinden tamamla
      return api("profile_get")
        .then(function (p) {
          syncMeFromProfile(p.item);
        })
        .catch(function () {
          applySideUser();
        });
    })
    .catch(function () {});

  loadCourses()
    .then(function () {
      render();
    })
    .catch(function (e) {
      console.error("[egitmen]", e);
      var msg = e && e.message ? e.message : String(e);
      $("viewWrap").innerHTML =
        '<p class="loading">Yüklenemedi: ' +
        esc(msg) +
        '. <a href="index.php?t=' +
        Date.now() +
        '">Yenile</a> · <a href="login.php">Giriş</a></p>';
      toast(msg || "Yüklenemedi", true);
    });
})();
