/* BM Capital - Yönetim Paneli */
(function () {
  "use strict";
  var API = "../api/admin.php";

  var el = {
    wrap: document.getElementById("viewWrap"),
    title: document.getElementById("viewTitle"),
    topAction: document.getElementById("topAction"),
    modal: document.getElementById("modal"),
    modalTitle: document.getElementById("modalTitle"),
    modalBody: document.getElementById("modalBody"),
    modalClose: document.getElementById("modalClose"),
    toast: document.getElementById("toast"),
    sidebar: document.getElementById("sidebar"),
    unreadBadge: document.getElementById("unreadBadge"),
  };

  var VIEWS = {
    dashboard: "Panel",
    egitimler: "Eğitimler",
    egitmenler: "Eğitmenler",
    ogrenciler: "Öğrenciler",
    satislar: "Satışlar",
    urunler: "Ürünler",
    sss: "S.S.S.",
    odemeler: "Ödemeler",
    abonelikler: "Abonelikler",
    ayarlar: "Ayarlar",
  };

  var SOCIAL_PLATFORMS = [
    { id: "youtube", label: "YouTube" },
    { id: "instagram", label: "Instagram" },
    { id: "x", label: "X (Twitter)" },
    { id: "linkedin", label: "LinkedIn" },
    { id: "facebook", label: "Facebook" },
    { id: "web", label: "Web sitesi" },
    { id: "link", label: "Diğer link" },
  ];

  // ---------- API ----------
  var CSRF = window.BM_ADMIN_CSRF || "";
  function rememberCsrf(d) {
    if (d && d.csrf) CSRF = d.csrf;
    return d;
  }
  function req(action, method, body, query) {
    var opt = { method: method || "GET", headers: {}, credentials: "same-origin" };
    if (CSRF) opt.headers["X-CSRF-Token"] = CSRF;
    if (body) {
      opt.headers["Content-Type"] = "application/json";
      var payload = body && typeof body === "object" ? Object.assign({}, body, { csrf: CSRF }) : body;
      opt.body = JSON.stringify(payload);
    }
    var url = API + "?action=" + encodeURIComponent(action);
    if (query) {
      Object.keys(query).forEach(function (k) {
        var v = query[k];
        if (v === undefined || v === null || String(v) === "") return;
        url += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(v);
      });
    }
    return fetch(url, opt).then(function (r) {
      if (r.status === 401) { location.href = "login.php"; throw new Error("auth"); }
      return r.json().then(function (d) {
        rememberCsrf(d);
        if (r.status === 403) {
          toast((d && d.error) || "Oturum doğrulaması başarısız", "err");
        }
        return d;
      });
    });
  }
  function get(a, query) { return req(a, "GET", null, query); }
  function post(a, b) { return req(a, "POST", b || {}); }

  // ---------- helpers ----------
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function bioExcerpt(text, max) {
    var t = String(text || "").replace(/\s+/g, " ").trim();
    if (!t) return "—";
    max = max || 90;
    return t.length > max ? t.slice(0, max) + "…" : t;
  }
  function instructorProfileHref(slug) {
    return "../egitmen-profil.html?id=" + encodeURIComponent(slug || "");
  }
  function toast(msg, type) {
    el.toast.textContent = msg;
    el.toast.className = "toast " + (type || "ok");
    el.toast.hidden = false;
    clearTimeout(el.toast._t);
    el.toast._t = setTimeout(function () { el.toast.hidden = true; }, 2800);
  }
  function openModal(title, html) {
    el.modalTitle.textContent = title;
    el.modalBody.innerHTML = html;
    el.modal.hidden = false;
  }
  function closeModal() { el.modal.hidden = true; el.modalBody.innerHTML = ""; }
  el.modalClose.addEventListener("click", closeModal);
  el.modal.addEventListener("click", function (e) { if (e.target === el.modal) closeModal(); });

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
      if (e.target === box || e.target.classList.contains("photo-lightbox-close")) close();
    });
    document.addEventListener("keydown", onKey);
  }

  function fmtDate(s) {
    if (!s) return "";
    if (/^\d{2}\.\d{2}\.\d{4}/.test(String(s))) return s;
    var raw = String(s).trim().replace(" ", "T");
    if (!/[zZ]|[+\-]\d{2}:?\d{2}$/.test(raw)) raw += "+03:00";
    var d = new Date(raw);
    if (isNaN(d)) return s;
    return d.toLocaleString("tr-TR", {
      timeZone: "Europe/Istanbul",
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }
  function formatTryKurus(k) {
    k = parseInt(k, 10) || 0;
    return (k / 100).toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " ₺";
  }
  function ymd(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1);
    var day = String(d.getDate());
    if (m.length < 2) m = "0" + m;
    if (day.length < 2) day = "0" + day;
    return y + "-" + m + "-" + day;
  }
  function lastDays(n) {
    var to = new Date();
    var from = new Date();
    from.setDate(from.getDate() - n);
    return { from: ymd(from), to: ymd(to) };
  }
  function selectOpts(items, valueKey, labelKey, selected) {
    var html = '<option value="">Tümü</option>';
    (items || []).forEach(function (it) {
      var v = String(it[valueKey] || "");
      html += '<option value="' + esc(v) + '"' + (String(selected) === v ? " selected" : "") + ">" + esc(it[labelKey] || ("#" + v)) + "</option>";
    });
    return html;
  }
  function payPill(st) {
    if (st === "paid") return '<span class="pill ok">Ödendi</span>';
    if (st === "pending") return '<span class="pill urun">Bekliyor</span>';
    if (st === "refunded" || st === "cancelled") return '<span class="pill off">Kapalı</span>';
    return '<span class="pill off">' + esc(st || "—") + "</span>";
  }

  // ---------- ROUTER ----------
  var current = "dashboard";
  function setView(name) {
    current = name;
    el.title.textContent = VIEWS[name] || name;
    document.querySelectorAll(".nav-item").forEach(function (a) {
      a.classList.toggle("active", a.getAttribute("data-view") === name);
    });
    el.topAction.hidden = true;
    el.wrap.innerHTML = '<div class="loading">Yükleniyor…</div>';
    if (window.innerWidth <= 860) el.sidebar.classList.remove("open");
    (renderers[name] || renderers.dashboard)();
  }
  document.querySelectorAll(".nav-item").forEach(function (a) {
    a.addEventListener("click", function (e) { e.preventDefault(); setView(a.getAttribute("data-view")); });
  });
  document.getElementById("menuToggle").addEventListener("click", function () { el.sidebar.classList.toggle("open"); });

  function setTopAction(label, fn) {
    el.topAction.hidden = false;
    el.topAction.textContent = label;
    el.topAction.onclick = fn;
  }

  // ---------- DASHBOARD ----------
  var renderers = {};
  renderers.dashboard = function () {
    get("stats").then(function (d) {
      if (!d.ok) return;
      var c = d.cards;
      updateUnread(c.unread);
      var max = 1;
      d.series.forEach(function (s) { if (s.views > max) max = s.views; });
      var bars = d.series.map(function (s) {
        var h = Math.round((s.views / max) * 100);
        var lbl = s.date.slice(8) + "." + s.date.slice(5, 7);
        return '<div class="bar" style="height:' + h + '%"><span class="tip">' + lbl + ": " + s.views + " görüntüleme · " + s.unique + " tekil</span></div>";
      }).join("");
      var first = d.series[0].date, last = d.series[d.series.length - 1].date;
      var topRows = d.topPages.length
        ? d.topPages.map(function (p) { return "<tr><td>" + esc(p.path || "/") + '</td><td style="text-align:right">' + p.c + "</td></tr>"; }).join("")
        : '<tr><td colspan="2" class="empty">Henüz veri yok</td></tr>';

      el.wrap.innerHTML =
        '<div class="stat-grid">' +
        card("Bugün", c.today, c.uniqueToday + " tekil ziyaretçi", true) +
        card("Son 7 Gün", c.week, "sayfa görüntüleme") +
        card("Toplam", c.total, "tüm zamanlar") +
        card("İçerik", c.modules, c.faqs + " S.S.S.") +
        "</div>" +
        '<div class="panel"><div class="panel-head"><h3>Son 30 Gün · Günlük Görüntüleme</h3></div><div class="panel-body">' +
        '<div class="chart">' + bars + "</div>" +
        '<div class="chart-x"><span>' + first.slice(5) + "</span><span>" + last.slice(5) + "</span></div>" +
        "</div></div>" +
        '<div class="panel"><div class="panel-head"><h3>En Çok Görüntülenen Sayfalar</h3></div><div class="panel-body table-wrap">' +
        '<table><thead><tr><th>Sayfa</th><th style="text-align:right">Görüntüleme</th></tr></thead><tbody>' + topRows + "</tbody></table>" +
        "</div></div>";
    });
  };
  function card(label, num, sub, gold) {
    return '<div class="stat-card' + (gold ? " gold" : "") + '"><div class="label">' + label + '</div><div class="num">' + num + '</div><div class="sub">' + sub + "</div></div>";
  }
  function updateUnread(n) {
    if (!el.unreadBadge) return;
    if (n > 0) { el.unreadBadge.hidden = false; el.unreadBadge.textContent = n; }
    else el.unreadBadge.hidden = true;
  }

  // ---------- MODULES ----------
  function renderModules(type) {
    var isEgitim = type === "egitim";
    setTopAction("+ Yeni " + (isEgitim ? "Eğitim" : "Ürün"), function () { moduleForm(null, type); });
    get("modules_list").then(function (d) {
      if (!d.ok) return;
      var items = d.items.filter(function (m) { return m.type === type; });
      var rows = items.length ? items.map(function (m) {
        return "<tr>" +
          "<td><div class='row-title'>" + esc(m.title) + "</div><div class='small'>" + esc(m.slug) + "</div></td>" +
          "<td>" + (m.price ? esc(m.price) : "<span class='small'>Bilgi Al</span>") + "</td>" +
          "<td><span class='pill " + (m.featured == 1 ? "on" : "off") + "'>" + (m.featured == 1 ? "Öne çıkan" : "Normal") + "</span></td>" +
          "<td>" + m.sort_order + "</td>" +
          "<td class='row-actions'>" +
          "<button class='btn tiny' data-edit='" + m.id + "'>Düzenle</button>" +
          "<button class='btn tiny danger' data-del='" + m.id + "'>Sil</button>" +
          "</td></tr>";
      }).join("") : "<tr><td colspan='5' class='empty'>Henüz kayıt yok. Sağ üstten yeni ekleyin.</td></tr>";

      el.wrap.innerHTML = '<div class="panel"><div class="table-wrap"><table><thead><tr><th>Başlık</th><th>Fiyat</th><th>Durum</th><th>Sıra</th><th></th></tr></thead><tbody>' + rows + "</tbody></table></div></div>";

      el.wrap.querySelectorAll("[data-edit]").forEach(function (b) {
        b.onclick = function () { moduleForm(b.getAttribute("data-edit"), type); };
      });
      el.wrap.querySelectorAll("[data-del]").forEach(function (b) {
        b.onclick = function () {
          if (!confirm("Bu kaydı silmek istediğinize emin misiniz?")) return;
          post("module_delete", { id: +b.getAttribute("data-del") }).then(function (r) {
            if (r.ok) { toast("Silindi"); renderModules(type); } else toast(r.error || "Hata", "err");
          });
        };
      });
    });
  }
  renderers.egitimler = function () { renderModules("egitim"); };
  renderers.urunler = function () { renderModules("urun"); };

  // ---------- EĞİTMENLER ----------
  renderers.egitmenler = function () {
    setTopAction("+ Yeni Eğitmen", function () { instructorForm(null); });
    get("instructors_list").then(function (d) {
      if (!d.ok) { el.wrap.innerHTML = '<p class="empty">Yüklenemedi</p>'; return; }
      var sharePct = d.share_pct != null ? d.share_pct : 60;
      var rows = d.items.length
        ? d.items
            .map(function (ins) {
              var thumb = ins.photo_path
                ? '<img class="ins-thumb" src="../' + esc(ins.photo_path) + '" alt="">'
                : '<span class="ins-thumb placeholder">' + esc((ins.name || "?").charAt(0)) + "</span>";
              var acc = ins.has_password
                ? '<span class="pill ok">' + esc(ins.email || ins.panel_username || "hesap") + "</span>"
                : ins.has_account
                  ? '<span class="pill">Davet bekliyor</span>'
                  : '<span class="pill off">Yok</span>';
              return (
                "<tr>" +
                "<td>" +
                thumb +
                "</td>" +
                "<td><div class='row-title'>" +
                esc(ins.name) +
                "</div><div class='small'>" +
                esc(ins.email || ins.title || "") +
                "</div></td>" +
                "<td>" +
                acc +
                "</td>" +
                "<td class='num'> %" +
                esc(String(ins.share_pct != null ? ins.share_pct : sharePct)) +
                "</td>" +
                "<td class='num'>" +
                esc(String(ins.published_count || 0)) +
                " / " +
                esc(String(ins.course_count || 0)) +
                "</td>" +
                "<td class='num'>" +
                esc(String(ins.student_count || 0)) +
                "</td>" +
                "<td class='num'>" +
                esc(formatTryKurus(ins.sales_kurus)) +
                "</td>" +
                "<td class='num'>" +
                esc(formatTryKurus(ins.earn_kurus)) +
                "</td>" +
                "<td class='small'>" +
                (ins.last_login_at ? esc(fmtDate(ins.last_login_at)) : "—") +
                "</td>" +
                "<td>" +
                (ins.is_active == 1 ? '<span class="pill ok">Aktif</span>' : '<span class="pill">Pasif</span>') +
                "</td>" +
                "<td class='row-actions'>" +
                "<button class='btn tiny primary' data-watch='" +
                ins.id +
                "'>İzle</button>" +
                "<button class='btn tiny' data-bio='" +
                ins.id +
                "'>Açıklama</button>" +
                "<button class='btn tiny' data-edit='" +
                ins.id +
                "'>Düzenle</button>" +
                "<a class='btn tiny' href='" +
                instructorProfileHref(ins.slug) +
                "' target='_blank' rel='noopener'>Profil</a>" +
                "<button class='btn tiny danger' data-del='" +
                ins.id +
                "'>Sil</button>" +
                "</td></tr>"
              );
            })
            .join("")
        : "<tr><td colspan='11' class='empty'>Henüz eğitmen yok.</td></tr>";
      el.wrap.innerHTML =
        '<p class="page-hint">Öğrenci ve satış yalnızca <strong>ödenmiş</strong> kayıtlardır. Kazanç her eğitmenin kendi payıyla hesaplanır (Düzenle). Varsayılan %' +
        esc(String(sharePct)) +
        " Ayarlar’dadır. Silinen eğitmenin e-postası ile yeniden davet atılabilir.</p>" +
        '<div class="panel"><div class="table-wrap"><table><thead><tr><th></th><th>Eğitmen</th><th>Panel</th><th>Pay</th><th>Kurs</th><th>Öğrenci</th><th>Satış</th><th>Kazanç</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead><tbody>' +
        rows +
        "</tbody></table></div></div>";
      el.wrap.querySelectorAll("[data-watch]").forEach(function (b) {
        b.onclick = function () {
          instructorWatch(+b.getAttribute("data-watch"));
        };
      });
      el.wrap.querySelectorAll("[data-bio]").forEach(function (b) {
        b.onclick = function () {
          instructorBioForm(+b.getAttribute("data-bio"));
        };
      });
      el.wrap.querySelectorAll("[data-edit]").forEach(function (b) {
        b.onclick = function () {
          instructorForm(+b.getAttribute("data-edit"));
        };
      });
      el.wrap.querySelectorAll("[data-del]").forEach(function (b) {
        b.onclick = function () {
          if (!confirm("Eğitmen silinsin mi? Panel hesabı kapanır. Aynı e-posta ile sonra yeniden ekleyebilirsiniz. Kurslar sahipsiz kalır.")) return;
          post("instructor_delete", { id: +b.getAttribute("data-del") }).then(function (r) {
            if (r.ok) {
              toast("Silindi");
              renderers.egitmenler();
            } else toast(r.error || "Hata", "err");
          });
        };
      });
    });
  };

  function instructorWatch(id) {
    get("instructor_watch&id=" + id).then(function (d) {
      if (!d.ok) {
        toast(d.error || "Yüklenemedi", "err");
        return;
      }
      var ins = d.instructor || {};
      var courses = d.courses || [];
      var courseRows = courses.length
        ? courses
            .map(function (c) {
              return (
                "<tr><td>" +
                esc(c.title) +
                "</td><td>" +
                (c.status === "published" ? '<span class="pill ok">Yayında</span>' : '<span class="pill">Taslak</span>') +
                "</td><td class='num'>" +
                esc(String(c.student_count || 0)) +
                "</td><td class='num'>" +
                esc(formatTryKurus(c.sales_kurus)) +
                "</td><td class='num'>" +
                esc(formatTryKurus(c.earn_kurus)) +
                "</td></tr>"
              );
            })
            .join("")
        : "<tr><td colspan='5' class='empty'>Kurs yok.</td></tr>";
      openModal(
        ins.name || "Eğitmen",
        '<div class="detail-line"><strong>Panel</strong><span>' +
          esc(ins.panel_username || "hesap yok") +
          "</span></div>" +
          '<div class="detail-line"><strong>Son giriş</strong><span>' +
          (ins.last_login_at ? esc(fmtDate(ins.last_login_at)) : "—") +
          "</span></div>" +
          '<div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Kurs</th><th>Durum</th><th>Öğrenci</th><th>Satış</th><th>Kazanç</th></tr></thead><tbody>' +
          courseRows +
          "</tbody></table></div>"
      );
    });
  }

  function instructorBioForm(id) {
    fetch(API + "?action=instructor_get&id=" + id, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.item) {
          toast(d.error || "Yüklenemedi", "err");
          return;
        }
        var m = d.item;
        var profileUrl = instructorProfileHref(m.slug);
        openModal(
          "Açıklama · " + (m.name || "Eğitmen"),
          '<form id="bioForm">' +
            '<p class="hint">Bu metin sitede <strong>Açıklama</strong> bölümünde görünür.</p>' +
            '<p class="hint"><a href="' +
            profileUrl +
            '" target="_blank" rel="noopener">Profili önizle →</a></p>' +
            '<div class="field full"><label>Biyografi / açıklama</label>' +
            '<textarea name="bio" rows="10" placeholder="Eğitmenin deneyimi, uzmanlık alanları, sertifikalar…">' +
            esc(m.bio || "") +
            "</textarea></div>" +
            '<div class="form-actions"><button type="button" class="btn-ghost" id="cancelBio">İptal</button>' +
            '<button type="submit" class="btn-primary sm">Kaydet</button></div></form>'
        );
        document.getElementById("cancelBio").onclick = closeModal;
        document.getElementById("bioForm").onsubmit = function (e) {
          e.preventDefault();
          post("instructor_save_bio", {
            id: id,
            bio: e.target.bio.value,
          }).then(function (r) {
            if (r.ok) {
              toast("Açıklama kaydedildi");
              closeModal();
              renderers.egitmenler();
            } else toast(r.error || "Hata", "err");
          });
        };
      });
  }

  function showInstructorInvite(r) {
    var mailOk = !!r.mail_sent;
    var html =
      "<p>" +
      (mailOk
        ? "Davet e-postası gönderildi. Eğitmen kendi şifresini belirleyecek."
        : "Kayıt oluştu; e-posta gönderilemedi. Aşağıdaki bağlantıyı iletebilirsiniz.") +
      "</p>" +
      '<p class="hint">Panel girişi: <code>/egitmen/login.php</code></p>';
    if (r.invite_link) {
      html +=
        '<p><a href="' +
        esc(r.invite_link) +
        '" target="_blank" rel="noopener">Şifre belirleme bağlantısı (yerel)</a></p>';
    }
    html +=
      '<div class="form-actions"><button type="button" class="btn-primary sm" id="inviteDone">Tamam</button></div>';
    openModal("Eğitmen eklendi", html);
    var done = document.getElementById("inviteDone");
    if (done) done.onclick = closeModal;
  }

  function instructorForm(id) {
    if (!id) {
      openModal(
        "Yeni Eğitmen",
        '<form id="insForm"><div class="form-grid">' +
          '<div class="field full"><label>Ad Soyad *</label><input name="name" required autofocus></div>' +
          '<div class="field full"><label>E-posta *</label><input type="email" name="email" required autocomplete="off" placeholder="ornek@email.com"></div>' +
          '<p class="hint" style="margin:0">Şifreyi sen yazmazsın. Eğitmen mailindeki bağlantıdan kendi belirler. Silip aynı e-posta ile sonra yeniden ekleyebilirsin.</p>' +
          '</div><div class="form-actions"><button type="button" class="btn-ghost" id="cancelBtn">İptal</button>' +
          '<button type="submit" class="btn-primary sm">Davet gönder</button></div></form>'
      );
      document.getElementById("cancelBtn").onclick = closeModal;
      document.getElementById("insForm").onsubmit = function (e) {
        e.preventDefault();
        var f = e.target;
        post("instructor_save", { name: f.name.value, email: f.email.value }).then(function (r) {
          if (!r.ok) {
            toast(r.error || "Hata", "err");
            return;
          }
          toast(r.mail_sent ? "Davet gönderildi" : "Eğitmen eklendi");
          renderers.egitmenler();
          showInstructorInvite(r);
        });
      };
      return;
    }

    fetch(API + "?action=instructor_get&id=" + id, { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (d) {
        if (!d.ok || !d.item) {
          toast(d.error || "Yüklenemedi", "err");
          return;
        }
        var m = d.item;
        var defShare = m.default_share_pct != null ? m.default_share_pct : 60;
        var shareVal = m.share_pct != null && m.share_pct !== "" ? String(m.share_pct) : "";
        openModal(
          "Düzenle · Eğitmen",
          '<form id="insForm"><div class="form-grid">' +
            '<div class="field full"><label>Ad Soyad *</label><input name="name" value="' +
            esc(m.name || "") +
            '" required></div>' +
            '<div class="field full"><label>E-posta *</label><input type="email" name="email" value="' +
            esc(m.email || m.panel_username || "") +
            '" required autocomplete="off"></div>' +
            '<div class="field"><label>Eğitmen payı (%)</label><input type="number" name="share_pct" min="0" max="100" step="1" value="' +
            esc(shareVal) +
            '" placeholder="Varsayılan ' +
            esc(String(defShare)) +
            '"></div>' +
            '<div class="field check"><input type="checkbox" name="is_active" id="insActive" ' +
            (m.is_active == 0 ? "" : "checked") +
            '><label for="insActive">Aktif (sitede görünsün)</label></div>' +
            '<div class="field full"><label>Slug <span class="hint">(profil URL)</span></label><input name="slug" value="' +
            esc(m.slug || "") +
            '"></div>' +
            '<p class="hint" style="margin:0">Pay boşsa site varsayılanı (%' +
            esc(String(defShare)) +
            ') kullanılır. Fotoğraf ve kursları eğitmen kendi panelinden doldurur.</p>' +
            "</div>" +
            '<div class="form-actions">' +
            '<button type="button" class="btn-ghost" id="cancelBtn">İptal</button>' +
            '<button type="button" class="btn tiny" id="resendInvite">Davet mailini tekrar gönder</button>' +
            '<button type="submit" class="btn-primary sm">Kaydet</button></div></form>'
        );
        document.getElementById("cancelBtn").onclick = closeModal;
        document.getElementById("resendInvite").onclick = function () {
          post("instructor_invite", { id: id }).then(function (r) {
            if (!r.ok) {
              toast(r.error || "Hata", "err");
              return;
            }
            toast(r.mail_sent ? "Davet gönderildi" : "Mail gitmedi");
            if (r.invite_link) showInstructorInvite(r);
          });
        };
        document.getElementById("insForm").onsubmit = function (e) {
          e.preventDefault();
          var f = e.target;
          post("instructor_save", {
            id: id,
            name: f.name.value,
            email: f.email.value,
            share_pct: f.share_pct.value,
            slug: f.slug.value,
            is_active: f.is_active.checked ? 1 : 0,
          }).then(function (r) {
            if (r.ok) {
              toast("Kaydedildi");
              closeModal();
              renderers.egitmenler();
            } else toast(r.error || "Hata", "err");
          });
        };
      });
  }

  function moduleForm(id, type) {
    var load = id ? get("module_get&id=" + id) : Promise.resolve({ ok: true, item: null });
    load.then(function (d) {
      var m = d.item;
      var isEgitim = type === "egitim";
      var data = (m && m.data) || {};
      var mufredatText = mufredatToText(data.mufredat || []);
      function v(x) { return m && m[x] != null ? esc(m[x]) : ""; }
      function arr(x) { return (data[x] || []).map(esc).join("\n"); }

      var egitimFields = isEgitim ?
        '<div class="field"><label>Süre</label><input name="duration" value="' + v("duration") + '" placeholder="20 saat"></div>' +
        '<div class="field"><label>Eğitim Türü</label><input name="egitim_turu" value="' + v("egitim_turu") + '" placeholder="Canlı Online Eğitim"></div>' +
        '<div class="field full"><label>Eğitmenler</label><input name="instructors" value="' + v("instructors") + '" placeholder="Dr. Mete AKYOL, Dr. Kamil BİLEN"></div>' +
        '<div class="field full"><label>Katılım Notu</label><input name="katilim_not" value="' + v("katilim_not") + '"></div>' +
        '<div class="field full"><label>Tarih Notu</label><input name="tarih_not" value="' + v("tarih_not") + '" placeholder="Eğitim 5 gün sürecektir."></div>' +
        '<div class="field full"><label>Tarihler <span class="hint">(her satıra bir tarih)</span></label><textarea name="tarihler" rows="3">' + arr("tarihler") + '</textarea></div>' +
        '<div class="field full"><label>Hediyeler <span class="hint">(her satıra bir madde)</span></label><textarea name="hediye" rows="2">' + arr("hediye") + '</textarea></div>' +
        '<div class="field full"><label>Hediye Görsel URL</label><input name="hediyeGorsel" value="' + esc(data.hediyeGorsel || "") + '"></div>'
        :
        '<div class="field"><label>Etiket</label><input name="etiket" value="' + v("etiket") + '" placeholder="Algoritmik Trade"></div>' +
        '<div class="field"><label>Video URL <span class="hint">(opsiyonel)</span></label><input name="video" value="' + v("video") + '" placeholder="assets/video/...mp4"></div>' +
        '<div class="field full"><label>Video Poster URL</label><input name="video_poster" value="' + v("video_poster") + '"></div>';

      var mufHint = isEgitim
        ? '<span class="hint"># Gün başlığı · ## Bölüm başlığı · - madde</span>'
        : '<span class="hint">Ürünler için genelde boş bırakılır</span>';

      openModal((id ? "Düzenle" : "Yeni") + " · " + (isEgitim ? "Eğitim" : "Ürün"),
        '<form id="modForm"><div class="form-grid">' +
        '<div class="field full"><label>Başlık *</label><input name="title" value="' + v("title") + '" required></div>' +
        '<div class="field"><label>Kısa URL (slug) <span class="hint">(boşsa otomatik)</span></label><input name="slug" value="' + v("slug") + '"></div>' +
        '<div class="field"><label>Görsel URL</label><input name="image" value="' + v("image") + '" placeholder="assets/img/...svg"></div>' +
        '<div class="field full"><label>Kısa Açıklama</label><textarea name="short_desc" rows="2">' + v("short_desc") + '</textarea></div>' +
        '<div class="field"><label>Fiyat <span class="hint">(boşsa "Bilgi Al")</span></label><input name="price" value="' + v("price") + '" placeholder="10.000 TL"></div>' +
        '<div class="field"><label>Fiyat Notu</label><input name="price_note" value="' + v("price_note") + '" placeholder="(KDV dahil)"></div>' +
        egitimFields +
        '<div class="field full"><label>Özellikler <span class="hint">(her satıra bir madde)</span></label><textarea name="ozellikler" rows="4">' + arr("ozellikler") + '</textarea></div>' +
        '<div class="field full"><label>Açıklama Paragrafları <span class="hint">(her satıra bir paragraf)</span></label><textarea name="aciklama" rows="4">' + arr("aciklama") + '</textarea></div>' +
        '<div class="field full"><label>Müfredat ' + mufHint + '</label><textarea name="mufredat" rows="8" style="font-family:monospace;font-size:13px">' + esc(mufredatText) + '</textarea></div>' +
        '<div class="field"><label>Sıra No</label><input type="number" name="sort_order" value="' + (m ? m.sort_order : 0) + '"></div>' +
        '<div class="field check"><input type="checkbox" name="featured" id="feat" ' + (m && m.featured == 1 ? "checked" : "") + '><label for="feat">Öne çıkan / ana sayfada göster</label></div>' +
        '</div><div class="form-actions"><button type="button" class="btn-ghost" id="cancelBtn">İptal</button><button type="submit" class="btn-primary sm">Kaydet</button></div></form>');

      document.getElementById("cancelBtn").onclick = closeModal;
      document.getElementById("modForm").onsubmit = function (e) {
        e.preventDefault();
        var f = e.target, out = { id: id ? +id : 0, type: type, featured: f.featured.checked ? 1 : 0 };
        ["title", "slug", "image", "short_desc", "price", "price_note", "duration", "egitim_turu",
          "instructors", "etiket", "video", "video_poster", "katilim_not", "tarih_not",
          "ozellikler", "aciklama", "hediye", "hediyeGorsel", "tarihler", "mufredat", "sort_order"].forEach(function (k) {
          if (f[k]) out[k] = f[k].value;
        });
        post("module_save", out).then(function (r) {
          if (r.ok) { toast("Kaydedildi"); closeModal(); renderModules(type); }
          else toast(r.error || "Hata", "err");
        });
      };
    });
  }

  function mufredatToText(muf) {
    if (!muf || !muf.length) return "";
    var out = [];
    muf.forEach(function (g) {
      out.push("# " + (g.baslik || ""));
      (g.bolumler || []).forEach(function (b) {
        out.push("## " + (b.baslik || ""));
        (b.maddeler || []).forEach(function (mad) { out.push("- " + mad); });
      });
    });
    return out.join("\n");
  }

  // ---------- SSS ----------
  renderers.sss = function () {
    setTopAction("+ Yeni Soru", function () { faqForm(null); });
    get("faqs_list").then(function (d) {
      if (!d.ok) return;
      var rows = d.items.length ? d.items.map(function (f) {
        return "<tr><td><div class='row-title'>" + esc(f.question) + "</div><div class='small'>" + esc(f.answer.slice(0, 120)) + (f.answer.length > 120 ? "…" : "") + "</div></td>" +
          "<td>" + f.sort_order + "</td>" +
          "<td class='row-actions'><button class='btn tiny' data-edit='" + f.id + "'>Düzenle</button><button class='btn tiny danger' data-del='" + f.id + "'>Sil</button></td></tr>";
      }).join("") : "<tr><td colspan='3' class='empty'>Henüz soru yok.</td></tr>";
      el.wrap.innerHTML = '<div class="panel"><div class="table-wrap"><table><thead><tr><th>Soru</th><th>Sıra</th><th></th></tr></thead><tbody>' + rows + "</tbody></table></div></div>";
      el.wrap.querySelectorAll("[data-edit]").forEach(function (b) { b.onclick = function () { faqForm(b.getAttribute("data-edit"), d.items); }; });
      el.wrap.querySelectorAll("[data-del]").forEach(function (b) {
        b.onclick = function () {
          if (!confirm("Bu soruyu silmek istiyor musunuz?")) return;
          post("faq_delete", { id: +b.getAttribute("data-del") }).then(function (r) { if (r.ok) { toast("Silindi"); renderers.sss(); } });
        };
      });
    });
  };
  function faqForm(id, items) {
    var f = id && items ? items.filter(function (x) { return x.id == id; })[0] : null;
    openModal((id ? "Düzenle" : "Yeni") + " · Soru",
      '<form id="faqForm"><div class="form-grid">' +
      '<div class="field full"><label>Soru *</label><input name="question" value="' + (f ? esc(f.question) : "") + '" required></div>' +
      '<div class="field full"><label>Cevap *</label><textarea name="answer" rows="5" required>' + (f ? esc(f.answer) : "") + '</textarea></div>' +
      '<div class="field"><label>Sıra No</label><input type="number" name="sort_order" value="' + (f ? f.sort_order : 0) + '"></div>' +
      '</div><div class="form-actions"><button type="button" class="btn-ghost" id="cancelBtn">İptal</button><button type="submit" class="btn-primary sm">Kaydet</button></div></form>');
    document.getElementById("cancelBtn").onclick = closeModal;
    document.getElementById("faqForm").onsubmit = function (e) {
      e.preventDefault();
      var fm = e.target;
      post("faq_save", { id: id ? +id : 0, question: fm.question.value, answer: fm.answer.value, sort_order: +fm.sort_order.value })
        .then(function (r) { if (r.ok) { toast("Kaydedildi"); closeModal(); renderers.sss(); } else toast(r.error || "Hata", "err"); });
    };
  }

  // ---------- CONTACTS ----------
  renderers.iletisim = function () {
    get("contacts_list").then(function (d) {
      if (!d.ok) return;
      var rows = d.items.length ? d.items.map(function (c) {
        return "<tr>" +
          "<td>" + (c.is_read == 0 ? "<span class='dot-unread'></span>" : "") + "<span class='small'>" + fmtDate(c.created_at) + "</span></td>" +
          "<td><div class='row-title'>" + esc(c.name || "-") + "</div><div class='small'>" + esc(c.email || "") + " · " + esc(c.phone || "") + "</div></td>" +
          "<td>" + esc(c.subject || "-") + "</td>" +
          "<td class='row-actions'><button class='btn tiny' data-view='" + c.id + "'>Gör</button><button class='btn tiny danger' data-del='" + c.id + "'>Sil</button></td>" +
          "</tr>";
      }).join("") : "<tr><td colspan='4' class='empty'>Henüz mesaj yok.</td></tr>";
      el.wrap.innerHTML = '<div class="panel"><div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Kişi</th><th>Konu</th><th></th></tr></thead><tbody>' + rows + "</tbody></table></div></div>";

      el.wrap.querySelectorAll("[data-view]").forEach(function (b) {
        b.onclick = function () {
          var c = d.items.filter(function (x) { return x.id == b.getAttribute("data-view"); })[0];
          openModal("Mesaj Detayı",
            '<div class="detail-line"><strong>Tarih</strong><span>' + fmtDate(c.created_at) + "</span></div>" +
            '<div class="detail-line"><strong>Ad Soyad</strong><span>' + esc(c.name || "-") + "</span></div>" +
            '<div class="detail-line"><strong>E-posta</strong><span>' + esc(c.email || "-") + "</span></div>" +
            '<div class="detail-line"><strong>Telefon</strong><span>' + esc(c.phone || "-") + "</span></div>" +
            '<div class="detail-line"><strong>Konu</strong><span>' + esc(c.subject || "-") + "</span></div>" +
            '<div class="detail-line"><strong>Mesaj</strong><span style="white-space:pre-wrap">' + esc(c.message || "-") + "</span></div>" +
            '<div class="form-actions">' +
            (c.email ? '<a class="btn" href="mailto:' + esc(c.email) + '">E-posta Gönder</a>' : "") +
            (c.phone ? '<a class="btn gold" href="https://wa.me/' + esc(String(c.phone).replace(/\D/g, "")) + '" target="_blank">WhatsApp</a>' : "") +
            "</div>");
          if (c.is_read == 0) post("contact_read", { id: c.id }).then(function () { updateBadge(); });
        };
      });
      el.wrap.querySelectorAll("[data-del]").forEach(function (b) {
        b.onclick = function () {
          if (!confirm("Bu mesajı silmek istiyor musunuz?")) return;
          post("contact_delete", { id: +b.getAttribute("data-del") }).then(function (r) { if (r.ok) { toast("Silindi"); renderers.iletisim(); } });
        };
      });
    });
  };

  // ---------- STATS ----------
  renderers.istatistik = function () {
    get("stats").then(function (d) {
      if (!d.ok) return;
      var max = 1; d.series.forEach(function (s) { if (s.views > max) max = s.views; });
      var bars = d.series.map(function (s) {
        var h = Math.round((s.views / max) * 100);
        var lbl = s.date.slice(8) + "." + s.date.slice(5, 7);
        return '<div class="bar" style="height:' + h + '%"><span class="tip">' + lbl + ": " + s.views + " · " + s.unique + " tekil</span></div>";
      }).join("");
      var tableRows = d.series.slice().reverse().filter(function (s) { return s.views > 0; }).map(function (s) {
        return "<tr><td>" + s.date + "</td><td style='text-align:right'>" + s.views + "</td><td style='text-align:right'>" + s.unique + "</td></tr>";
      }).join("") || "<tr><td colspan='3' class='empty'>Henüz ziyaret kaydı yok.</td></tr>";
      el.wrap.innerHTML =
        '<div class="stat-grid">' +
        card("Bugün", d.cards.today, d.cards.uniqueToday + " tekil", true) +
        card("Son 7 Gün", d.cards.week, "görüntüleme") +
        card("Toplam", d.cards.total, "tüm zamanlar") +
        "</div>" +
        '<div class="panel"><div class="panel-head"><h3>Günlük Görüntüleme (30 gün)</h3></div><div class="panel-body"><div class="chart">' + bars + "</div></div></div>" +
        '<div class="panel"><div class="panel-head"><h3>Gün Gün Detay</h3></div><div class="table-wrap"><table><thead><tr><th>Tarih</th><th style="text-align:right">Görüntüleme</th><th style="text-align:right">Tekil</th></tr></thead><tbody>' + tableRows + "</tbody></table></div></div>";
    });
  };

  // ---------- PAYMENTS (iyzico işlem listesi) ----------
  function copyBtn(value) {
    if (!value) return "—";
    return (
      "<code>" + esc(value) + "</code>" +
      " <button class='btn tiny' data-copy='" + esc(value) + "' title='iyzico’da aramak için kopyala'>kopyala</button>"
    );
  }

  renderers.odemeler = function () {
    drawOdemeler(lastDays(7));
  };
  function drawOdemeler(filters) {
    get("payments_overview", filters).then(function (d) {
      if (!d.ok) { el.wrap.innerHTML = '<p class="empty">Yüklenemedi</p>'; return; }
      var s = d.summary || {};
      var sc = d.subCounts || {};
      var items = d.items || [];
      var dups = d.duplicates || [];
      filters = filters || {};
      filters.from = d.from || "";
      filters.to = d.to || "";

      var rows = items.length
        ? items.map(function (r) {
            var okMark = r.tahsilEdildi
              ? "<span class='pill ok'>tahsil edildi</span>"
              : "<span class='pill off'>tahsilat yok</span>";
            var payNo = r.iyzicoPaymentId || r.aramaRef || r.ref;
            return (
              "<tr>" +
              "<td class='small'>" + esc(fmtDate(r.paid_at || r.created_at)) + "</td>" +
              "<td>" + esc(r.tur) + "</td>" +
              "<td class='small'>" + copyBtn(payNo) + "</td>" +
              "<td>" + esc(r.student_name || r.student_email || "—") +
              "<div class='small'>" + esc(r.student_email || "") + "</div></td>" +
              "<td class='small'>" + esc(r.aciklama || "—") + "</td>" +
              "<td>" + formatTryKurus(r.amount_kurus) + "</td>" +
              "<td>" + payPill(r.status) + "<div class='small'>" + okMark + "</div>" +
              (r.error_message ? "<div class='small'>" + esc(r.error_message) + "</div>" : "") +
              "</td>" +
              "</tr>"
            );
          }).join("")
        : "<tr><td colspan='7' class='empty'>Bu filtrede ödeme yok.</td></tr>";

      var turOpts = [["", "Tümü"], ["kurs", "Kurs"], ["abonelik", "Abonelik"]].map(function (o) {
        return '<option value="' + o[0] + '"' + ((filters.tur || "") === o[0] ? " selected" : "") + ">" + o[1] + "</option>";
      }).join("");

      el.wrap.innerHTML =
        '<p class="page-hint">Kurs tahsilatları ve abonelik çekimleri. Saatler Türkiye (Istanbul). iyzico’da aramak için <b>ödeme numarasını</b> kopyalayıp ' +
        '<a href="' + esc(d.transactionsUrl || "#") + '" target="_blank" rel="noopener">İşlem Listesi</a> ' +
        'arama kutusuna yapıştırın (ör. 37475570).</p>' +
        '<div class="stat-grid">' +
        '<div class="stat-card gold"><div class="label">Tahsil edilen</div><div class="num">' + formatTryKurus(s.tahsilEdilenKurus || 0) + "</div></div>" +
        '<div class="stat-card"><div class="label">Başarılı ödeme</div><div class="num">' + (s.tahsilEdilen || 0) + "</div></div>" +
        '<div class="stat-card"><div class="label">İade</div><div class="num">' + (s.iadeEdilen || 0) + "</div></div>" +
        '<div class="stat-card' + ((s.inceleme || 0) > 0 ? " gold" : "") + '" id="odIncelemeCard"' +
        ((s.inceleme || 0) > 0 ? ' style="cursor:pointer"' : "") + ">" +
        '<div class="label">İnceleme gerektiren</div><div class="num">' + (s.inceleme || 0) + "</div></div>" +
        '<div class="stat-card"><div class="label">Aktif abone</div><div class="num">' + (sc.active || 0) + "</div></div>" +
        "</div>" +
        ((s.inceleme || 0) > 0
          ? '<p class="page-hint">⚠️ <b>' + (s.inceleme || 0) + ' ödeme inceleme bekliyor:</b> para çekilmiş olabilir ama erişim açılmamış. ' +
            'Karta tıklayıp listeyi görün; iyzico’da doğrulayıp Öğrenciler sekmesinden elle erişim verin.</p>'
          : "") +
        (dups.length
          ? '<p class="page-hint">⚠️ Aynı günde birden fazla abonelik çekimi: ' +
            dups.map(function (x) {
              return esc(x.name || x.email) + " ×" + x.count + " (" + esc(x.day) + ")";
            }).join(" · ") +
            ". iyzico’da ayrı abonelik kaydı vardır; sitede ikinci satır açılmaz ama kart iki kez çekilir.</p>"
          : "") +
        '<div class="panel"><div class="panel-body"><div class="filters">' +
        '<div class="field"><label>Başlangıç</label><input type="date" id="odFrom" value="' + esc(filters.from || "") + '"></div>' +
        '<div class="field"><label>Bitiş</label><input type="date" id="odTo" value="' + esc(filters.to || "") + '"></div>' +
        '<div class="field"><label>Tür</label><select id="odTur">' + turOpts + "</select></div>" +
        '<div class="field"><label>Ara (ödeme no / e-posta)</label><input type="text" id="odQ" value="' + esc(filters.q || "") + '" placeholder="37475570 veya e-posta"></div>' +
        '<div class="field"><label>Durum</label><select id="odSt">' +
        ['', 'paid', 'refunded', 'review', 'cancelled', 'failed', 'pending'].map(function (v) {
          var lbl = { '': 'Tümü', paid: 'Ödendi', refunded: 'İade', review: 'İnceleme', cancelled: 'İptal', failed: 'Başarısız', pending: 'Bekliyor' }[v];
          return '<option value="' + v + '"' + ((filters.status || "") === v ? " selected" : "") + ">" + lbl + "</option>";
        }).join("") +
        "</select></div>" +
        '<button type="button" class="btn-primary sm" id="odGo">Uygula</button>' +
        '<button type="button" class="btn-ghost sm" id="odWeek">Son 7 gün</button>' +
        '<button type="button" class="btn-ghost sm" id="odAll">Tüm zamanlar</button>' +
        "</div></div></div>" +
        '<div class="panel"><div class="panel-head"><h3>İşlem listesi</h3></div><div class="table-wrap"><table><thead><tr>' +
        "<th>Tarih</th><th>Tür</th><th>Ödeme No</th><th>Öğrenci</th><th>Açıklama</th><th>Tutar</th><th>Durum</th>" +
        "</tr></thead><tbody>" + rows + "</tbody></table></div></div>";

      function odFilters(extra) {
        extra = extra || {};
        return {
          q: extra.q !== undefined ? extra.q : document.getElementById("odQ").value.trim(),
          status: extra.status !== undefined ? extra.status : document.getElementById("odSt").value,
          tur: extra.tur !== undefined ? extra.tur : document.getElementById("odTur").value,
          from: extra.from !== undefined ? extra.from : document.getElementById("odFrom").value,
          to: extra.to !== undefined ? extra.to : document.getElementById("odTo").value,
        };
      }
      document.getElementById("odGo").onclick = function () { drawOdemeler(odFilters()); };
      document.getElementById("odWeek").onclick = function () {
        var w = lastDays(7);
        drawOdemeler(odFilters({ from: w.from, to: w.to }));
      };
      document.getElementById("odAll").onclick = function () {
        drawOdemeler(odFilters({ from: "", to: "" }));
      };
      document.getElementById("odQ").addEventListener("keydown", function (e) {
        if (e.key === "Enter") document.getElementById("odGo").click();
      });
      var incCard = document.getElementById("odIncelemeCard");
      if (incCard && (s.inceleme || 0) > 0) {
        incCard.onclick = function () { drawOdemeler(odFilters({ status: "review" })); };
      }
      el.wrap.querySelectorAll("[data-copy]").forEach(function (b) {
        b.onclick = function () {
          var v = b.getAttribute("data-copy");
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(v).then(function () { toast("Kopyalandı: " + v); });
          } else {
            toast(v);
          }
        };
      });
    });
  }

  // ---------- STUDENTS ----------
  renderers.ogrenciler = function () {
    drawOgrenciler({});
  };
  function drawOgrenciler(filters) {
    get("admin_students_list", filters).then(function (d) {
      if (!d.ok) { el.wrap.innerHTML = '<p class="empty">Yüklenemedi</p>'; return; }
      var items = d.items || [];
      var rows = items.length
        ? items.map(function (r) {
            var acc = r.account_status === "active" ? "Kayıtlı" : (r.account_status ? r.account_status : "Misafir");
            return (
              "<tr>" +
              "<td>" + esc(r.instructor_name || "Atanmamış") + "</td>" +
              "<td><strong>" + esc(r.student_name || "—") + "</strong><div class='small'>" +
              esc(r.student_email || "") + (r.student_phone ? " · " + esc(r.student_phone) : "") +
              "</div></td>" +
              "<td>" + esc(r.course_title || "—") + "</td>" +
              "<td>" + payPill(r.payment_status) + "</td>" +
              "<td class='small'>" + esc(acc) + "</td>" +
              "<td>%" + esc(String(parseInt(r.progress_pct, 10) || 0)) + "</td>" +
              "<td class='small'>" + esc(fmtDate(r.enrolled_at)) + "</td>" +
              "<td>" + (r.student_account_id
                ? "<button class='btn tiny' data-st='" + esc(String(r.student_account_id)) + "' data-to='" +
                  (r.account_status === "suspended" ? "active" : "suspended") + "'>" +
                  (r.account_status === "suspended" ? "Aktif et" : "Pasife al") + "</button>"
                : "—") +
              "</td>" +
              "</tr>"
            );
          }).join("")
        : "<tr><td colspan='8' class='empty'>Kayıt yok.</td></tr>";
      el.wrap.innerHTML =
        '<p class="page-hint">Hangi eğitmenin hangi öğrencisi var, ödeme ve hesap durumu. Yalnızca kurs kayıtları (abonelik ayrı sekmede).</p>' +
        '<div class="panel"><div class="panel-head"><h3>Elle kurs erişimi (havale)</h3></div><div class="panel-body">' +
        '<p class="hint">Para havale ile geldiğinde kursu buradan açın. Kart iadesi yoktur.</p>' +
        '<div class="filters">' +
        '<div class="field"><label>E-posta</label><input id="grEmail" placeholder="ogrenci@email.com"></div>' +
        '<div class="field"><label>Ad soyad</label><input id="grName" placeholder="isteğe bağlı"></div>' +
        '<div class="field"><label>Kurs</label><select id="grCourse">' + selectOpts(d.courses, "id", "title", "") + "</select></div>" +
        '<button type="button" class="btn-primary sm" id="grGo">Erişim aç</button>' +
        "</div></div></div>" +
        '<div class="panel"><div class="panel-body">' +
        '<div class="filters">' +
        '<div class="field"><label>Eğitmen</label><select id="ogIns">' + selectOpts(d.instructors, "id", "name", filters.instructor_id) + "</select></div>" +
        '<div class="field"><label>Kurs</label><select id="ogCourse">' + selectOpts(d.courses, "id", "title", filters.course_id) + "</select></div>" +
        '<div class="field"><label>Ara</label><input id="ogQ" placeholder="ad, e-posta, telefon" value="' + esc(filters.q || "") + '"></div>' +
        '<button type="button" class="btn-primary sm" id="ogGo">Uygula</button>' +
        "</div></div>" +
        '<div class="table-wrap"><table><thead><tr>' +
        "<th>Eğitmen</th><th>Öğrenci</th><th>Kurs</th><th>Ödeme</th><th>Hesap</th><th>İlerleme</th><th>Kayıt</th><th></th>" +
        "</tr></thead><tbody>" + rows + "</tbody></table></div></div>";
      document.getElementById("ogGo").onclick = function () {
        drawOgrenciler({
          instructor_id: document.getElementById("ogIns").value,
          course_id: document.getElementById("ogCourse").value,
          q: document.getElementById("ogQ").value,
        });
      };
      var grGo = document.getElementById("grGo");
      if (grGo) {
        grGo.onclick = function () {
          post("admin_grant_access", {
            email: document.getElementById("grEmail").value,
            name: document.getElementById("grName").value,
            course_id: Number(document.getElementById("grCourse").value || 0),
          }).then(function (r) {
            if (r.ok) {
              toast("Erişim açıldı");
              drawOgrenciler(filters);
            } else toast(r.error || "Hata", "err");
          });
        };
      }
      el.wrap.querySelectorAll("[data-st]").forEach(function (btn) {
        btn.onclick = function () {
          post("admin_student_status", {
            id: Number(btn.getAttribute("data-st")),
            status: btn.getAttribute("data-to"),
          }).then(function (r) {
            if (r.ok) drawOgrenciler(filters);
            else toast(r.error || "Hata", "err");
          });
        };
      });
    });
  }

  // ---------- SALES ----------
  renderers.satislar = function () {
    drawSatislar(lastDays(30));
  };
  function drawSatislar(filters) {
    get("admin_sales_report", filters).then(function (d) {
      if (!d.ok) { el.wrap.innerHTML = '<p class="empty">Yüklenemedi</p>'; return; }
      var items = d.items || [];
      var insRows = (d.by_instructor || []).map(function (r) {
        return "<tr><td>" + esc(r.name) + "</td><td>" + r.count + "</td><td>" + formatTryKurus(r.sales_kurus) + "</td><td>" + formatTryKurus(r.earn_kurus) + "</td></tr>";
      }).join("") || "<tr><td colspan='4' class='empty'>Bu aralıkta satış yok.</td></tr>";
      var courseRows = (d.by_course || []).map(function (r) {
        return "<tr><td>" + esc(r.title) + "</td><td class='small'>" + esc(r.instructor_name) + "</td><td>" + r.count + "</td><td>" + formatTryKurus(r.sales_kurus) + "</td></tr>";
      }).join("") || "<tr><td colspan='4' class='empty'>Bu aralıkta satış yok.</td></tr>";
      var saleRows = items.length
        ? items.map(function (r) {
            return (
              "<tr>" +
              "<td class='small'>" + esc(fmtDate(r.paid_at || r.created_at)) + "</td>" +
              "<td>" + esc(r.instructor_name || "Atanmamış") + "</td>" +
              "<td>" + esc(r.course_title || "—") + "</td>" +
              "<td>" + esc(r.student_name || r.student_email || "—") + "<div class='small'>" + esc(r.student_email || "") + "</div></td>" +
              "<td>" + formatTryKurus(r.amount_kurus) + "</td>" +
              "<td class='small'>" + formatTryKurus(r.earn_kurus) + "</td>" +
              "</tr>"
            );
          }).join("")
        : "<tr><td colspan='6' class='empty'>Bu filtrede satış yok.</td></tr>";
      el.wrap.innerHTML =
        '<p class="page-hint">Ödenmiş kurs satışları. Tarih, eğitmen ve kurs seçerek toplamı görün. Abonelikler bu rapora dahil değil.</p>' +
        '<div class="panel"><div class="panel-body"><div class="filters">' +
        '<div class="field"><label>Başlangıç</label><input type="date" id="saFrom" value="' + esc(filters.from || "") + '"></div>' +
        '<div class="field"><label>Bitiş</label><input type="date" id="saTo" value="' + esc(filters.to || "") + '"></div>' +
        '<div class="field"><label>Eğitmen</label><select id="saIns">' + selectOpts(d.instructors, "id", "name", filters.instructor_id) + "</select></div>" +
        '<div class="field"><label>Kurs</label><select id="saCourse">' + selectOpts(d.courses, "id", "title", filters.course_id) + "</select></div>" +
        '<button type="button" class="btn-primary sm" id="saGo">Uygula</button>' +
        '<button type="button" class="btn-ghost sm" id="saAll">Tüm zamanlar</button>' +
        "</div></div></div>" +
        '<div class="stat-grid">' +
        '<div class="stat-card"><div class="label">Satış adedi</div><div class="num">' + (d.count || 0) + "</div></div>" +
        '<div class="stat-card gold"><div class="label">Toplam tutar</div><div class="num">' + formatTryKurus(d.sales_kurus) + "</div></div>" +
        '<div class="stat-card"><div class="label">Eğitmen payı</div><div class="num">' + formatTryKurus(d.earn_kurus) + "</div></div>" +
        "</div>" +
        '<div class="panel"><div class="panel-head"><h3>Eğitmene göre</h3></div><div class="table-wrap"><table><thead><tr><th>Eğitmen</th><th>Adet</th><th>Satış</th><th>Pay</th></tr></thead><tbody>' +
        insRows + "</tbody></table></div></div>" +
        '<div class="panel"><div class="panel-head"><h3>Kursa göre</h3></div><div class="table-wrap"><table><thead><tr><th>Kurs</th><th>Eğitmen</th><th>Adet</th><th>Satış</th></tr></thead><tbody>' +
        courseRows + "</tbody></table></div></div>" +
        '<div class="panel"><div class="panel-head"><h3>Satış listesi</h3></div><div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Eğitmen</th><th>Kurs</th><th>Öğrenci</th><th>Tutar</th><th>Pay</th></tr></thead><tbody>' +
        saleRows + "</tbody></table></div></div>";
      document.getElementById("saGo").onclick = function () {
        drawSatislar({
          from: document.getElementById("saFrom").value,
          to: document.getElementById("saTo").value,
          instructor_id: document.getElementById("saIns").value,
          course_id: document.getElementById("saCourse").value,
        });
      };
      document.getElementById("saAll").onclick = function () {
        drawSatislar({
          instructor_id: document.getElementById("saIns").value,
          course_id: document.getElementById("saCourse").value,
        });
      };
    });
  }

  // ---------- SUBSCRIPTIONS (WhatsApp grubu) ----------
  var SUB_STATUSES = {
    active: "Aktif",
    past_due: "Ödeme gecikti",
    pending: "Ödeme bekliyor",
    cancelled: "İptal",
    expired: "Süresi doldu",
  };
  var subState = { items: [], q: "", status: "", todo: "" };

  function subWaAdded(r) { return !!Number(r.wa_added); }

  // Yapılacak iş: erişimi var ama grupta değil (ekle) / erişimi yok ama grupta (çıkar)
  function subTodo(r) {
    if (r.entitled && !subWaAdded(r)) return "add";
    if (!r.entitled && subWaAdded(r)) return "remove";
    return "";
  }

  renderers.abonelikler = function () {
    subState = { items: [], q: "", status: "", todo: "" };
    subLoad();
  };

  function subLoad() {
    get("subscriptions_list").then(function (d) {
      if (!d.ok) { el.wrap.innerHTML = '<p class="empty">Yüklenemedi</p>'; return; }
      subState.items = d.items || [];
      drawAbonelikler();
    });
  }

  function drawAbonelikler() {
    var all = subState.items;
    var counts = { active: 0, entitled: 0, add: 0, remove: 0 };
    all.forEach(function (r) {
      if (r.status === "active" || (r.status === "cancelled" && r.entitled)) counts.active++;
      if (r.entitled) counts.entitled++;
      var t = subTodo(r);
      if (t === "add") counts.add++;
      else if (t === "remove") counts.remove++;
    });

    var q = subState.q.trim().toLowerCase();
    var items = all.filter(function (r) {
      if (subState.status === "active") {
        if (!(r.status === "active" || (r.status === "cancelled" && r.entitled))) return false;
      } else if (subState.status && r.status !== subState.status) return false;
      if (subState.todo && subTodo(r) !== subState.todo) return false;
      if (q) {
        var hay = (
          (r.student_name || "") + " " + (r.student_email || "") + " " + (r.student_phone || "")
        ).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      return true;
    });

    items.sort(function (a, b) {
      function rank(r) {
        if (r.status === "active") return 0;
        if (r.status === "past_due") return 1;
        if (r.status === "pending") return 2;
        if (r.status === "cancelled" && r.entitled) return 3;
        if (r.status === "cancelled") return 4;
        return 5;
      }
      var c = rank(a) - rank(b);
      if (c !== 0) return c;
      return (Number(b.id) || 0) - (Number(a.id) || 0);
    });

    var rows = items.length
      ? items.map(function (r) {
          var digits = String(r.wa_digits || "").replace(/\D/g, "");
          if (digits.length === 10) digits = "90" + digits;
          if (digits.length === 11 && digits.charAt(0) === "0") digits = "90" + digits.slice(1);
          var waHref = digits ? "https://wa.me/" + digits : "";
          var pill = (r.status === "active" || (r.status === "cancelled" && r.entitled)) ? "ok"
            : (r.status === "past_due" ? "urun" : "off");
          var waBtn = subWaAdded(r)
            ? "<button class='btn tiny' data-wa='0' data-id='" + r.id + "'>WP çıkarıldı</button>"
            : "<button class='btn tiny primary' data-wa='1' data-id='" + r.id + "'>WP eklendi</button>";
          var todo = subTodo(r);
          var need = "";
          if (r.status === "cancelled" && r.entitled) need += " <span class='pill'>iptal</span>";
          if (todo === "add") need += " <span class='pill urun'>gruba ekle</span>";
          else if (todo === "remove") need += " <span class='pill'>gruptan çıkar</span>";
          return (
            "<tr>" +
            "<td>" + esc(r.student_name || "—") + "<div class='small'>" + esc(r.student_email || "") + "</div></td>" +
            "<td>" + (waHref ? "<a href='" + esc(waHref) + "' target='_blank' rel='noopener'>" + esc(r.student_phone || digits) + "</a>" : esc(r.student_phone || "—")) + "</td>" +
            "<td><span class='pill " + pill + "'>" + esc(r.status_label || r.status) + "</span>" + need + "</td>" +
            "<td class='small'>" + esc(fmtDate(r.current_period_end)) + "</td>" +
            "<td class='small'>" + esc(fmtDate(r.last_paid_at)) + "</td>" +
            "<td class='row-actions'>" + waBtn + "</td>" +
            "</tr>"
          );
        }).join("")
      : "<tr><td colspan='6' class='empty'>" +
        (all.length ? "Bu süzgeçte abone yok." : "Henüz abone yok.") + "</td></tr>";

    var statusOpts = [""].concat(Object.keys(SUB_STATUSES)).map(function (v) {
      var lbl = v === "" ? "Tümü" : SUB_STATUSES[v];
      return '<option value="' + v + '"' + (subState.status === v ? " selected" : "") + ">" + lbl + "</option>";
    }).join("");
    var todoOpts = [
      ["", "Tümü"],
      ["add", "Gruba eklenecek"],
      ["remove", "Gruptan çıkarılacak"],
    ].map(function (o) {
      return '<option value="' + o[0] + '"' + (subState.todo === o[0] ? " selected" : "") + ">" + o[1] + "</option>";
    }).join("");

    var keepFocus = document.activeElement && document.activeElement.id === "abQ";

    el.wrap.innerHTML =
      '<div class="stat-grid">' +
      '<div class="stat-card gold" id="abCardActive" style="cursor:pointer"><div class="label">Aktif abone</div><div class="num">' + counts.active + "</div></div>" +
      '<div class="stat-card"><div class="label">Erişimi olan</div><div class="num">' + counts.entitled + "</div></div>" +
      '<div class="stat-card' + (counts.add > 0 ? " gold" : "") + '" id="abCardAdd" style="cursor:pointer"><div class="label">Gruba eklenecek</div><div class="num">' + counts.add + "</div></div>" +
      '<div class="stat-card" id="abCardRemove" style="cursor:pointer"><div class="label">Gruptan çıkarılacak</div><div class="num">' + counts.remove + "</div></div>" +
      "</div>" +
      '<div class="panel"><div class="panel-body"><div class="filters">' +
      '<div class="field"><label>Ara (isim / e-posta / telefon)</label><input type="text" id="abQ" value="' + esc(subState.q) + '" placeholder="Ad veya e-posta"></div>' +
      '<div class="field"><label>Durum</label><select id="abSt">' + statusOpts + "</select></div>" +
      '<div class="field"><label>Yapılacak</label><select id="abTodo">' + todoOpts + "</select></div>" +
      '<button type="button" class="btn-ghost sm" id="abClear">Temizle</button>' +
      "</div></div></div>" +
      '<div class="panel"><div class="panel-head"><h3>WhatsApp grubu aboneleri</h3>' +
      '<span class="count">' + items.length + " / " + all.length + " kayıt</span></div>" +
      '<div class="panel-body"><p class="hint">Grup ekleme/çıkarma siteden yapılmaz. WhatsApp’tan elle ekleyip buradan işaretleyin. Kart çekimi iyzico aboneliğidir. Dönem saati çekim saati değildir: üyelik o gün Türkiye saatiyle 24:00’e kadar açık kalır. Gece iyzico’ya bakılır; çekildiyse aynı abone kalır (yeni satır açılmaz), çekilmediyse süresi dolar ve gruptan çıkarılır.</p></div>' +
      '<div class="table-wrap"><table><thead><tr>' +
      "<th>Öğrenci</th><th>Telefon</th><th>Durum</th><th>Dönem sonu</th><th>Son ödeme</th><th></th>" +
      "</tr></thead><tbody>" + rows + "</tbody></table></div></div>";

    var qInput = document.getElementById("abQ");
    qInput.oninput = function () { subState.q = qInput.value; drawAbonelikler(); };
    if (keepFocus) {
      qInput.focus();
      qInput.setSelectionRange(qInput.value.length, qInput.value.length);
    }
    document.getElementById("abSt").onchange = function () { subState.status = this.value; drawAbonelikler(); };
    document.getElementById("abTodo").onchange = function () { subState.todo = this.value; drawAbonelikler(); };
    document.getElementById("abClear").onclick = function () {
      subState.q = ""; subState.status = ""; subState.todo = ""; drawAbonelikler();
    };
    document.getElementById("abCardActive").onclick = function () {
      subState.status = "active"; subState.todo = ""; drawAbonelikler();
    };
    document.getElementById("abCardAdd").onclick = function () {
      subState.todo = "add"; subState.status = ""; drawAbonelikler();
    };
    document.getElementById("abCardRemove").onclick = function () {
      subState.todo = "remove"; subState.status = ""; drawAbonelikler();
    };

    el.wrap.querySelectorAll("[data-wa]").forEach(function (btn) {
      btn.onclick = function () {
        post("subscription_wa_set", { id: Number(btn.getAttribute("data-id")), wa_added: Number(btn.getAttribute("data-wa")) })
          .then(function (r) {
            if (r.ok) subLoad();
            else toast(r.error || "Hata", "err");
          });
      };
    });
  }

  // ---------- SETTINGS ----------
  renderers.ayarlar = function () {
    get("settings_get").then(function (d) {
      var s = d.settings || {};
      function fld(name, label, val) { return '<div class="field"><label>' + label + '</label><input name="' + name + '" value="' + esc(s[name] || "") + '"></div>'; }
      el.wrap.innerHTML =
        '<div class="panel"><div class="panel-head"><h3>İletişim Bilgileri</h3></div><div class="panel-body">' +
        '<form id="setForm"><div class="form-grid">' +
        fld("marka", "Marka adı") + fld("sehir", "Şehir") +
        fld("telefon", "Telefon") + fld("whatsapp", "WhatsApp (90...)") +
        fld("instagram", "Instagram URL") + fld("twitter", "X / Twitter URL") +
        fld("banka", "Banka") + fld("hesap_adi", "Hesap Adı") +
        '<div class="field full"><label>IBAN</label><input name="iban" value="' + esc(s.iban || "") + '"></div>' +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">Satıcı kimliği (yasal metinler)</h3>' +
        '<p class="hint" style="margin:0 0 12px">Mesafeli satış ve ön bilgilendirme sayfalarında görünür. MERSİS / vergi boş bırakılabilir.</p>' +
        '<div class="form-grid">' +
        fld("satici_unvan", "Ticari unvan") +
        '<div class="field full"><label>Adres</label><input name="satici_adres" value="' + esc(s.satici_adres || "") + '"></div>' +
        fld("satici_vergi", "Vergi no") + fld("satici_mersis", "MERSİS") +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">Üst menü (başlık)</h3>' +
        '<p class="hint" style="margin:0 0 12px">Kapalı olanlar sitede görünmez. Sayfalar durur; yalnız menüden kalkar. Abonelik başlıkta yoktur.</p>' +
        '<div class="form-grid">' +
        '<div class="field"><label>Hakkımızda</label><select name="nav_hakkimizda">' +
          '<option value="0"' + ((s.nav_hakkimizda || "0") !== "1" ? " selected" : "") + ">Kapalı</option>" +
          '<option value="1"' + ((s.nav_hakkimizda || "0") === "1" ? " selected" : "") + ">Açık</option>" +
        "</select></div>" +
        '<div class="field"><label>S.S.S.</label><select name="nav_sss">' +
          '<option value="0"' + ((s.nav_sss || "0") !== "1" ? " selected" : "") + ">Kapalı</option>" +
          '<option value="1"' + ((s.nav_sss || "0") === "1" ? " selected" : "") + ">Açık</option>" +
        "</select></div>" +
        '<div class="field"><label>İletişim</label><select name="nav_iletisim">' +
          '<option value="0"' + ((s.nav_iletisim || "0") !== "1" ? " selected" : "") + ">Kapalı</option>" +
          '<option value="1"' + ((s.nav_iletisim || "0") === "1" ? " selected" : "") + ">Açık</option>" +
        "</select></div>" +
        '<div class="field"><label>Araçlar</label><select name="nav_araclar">' +
          '<option value="0"' + ((s.nav_araclar || "0") !== "1" ? " selected" : "") + ">Kapalı</option>" +
          '<option value="1"' + ((s.nav_araclar || "0") === "1" ? " selected" : "") + ">Açık</option>" +
        "</select></div>" +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">WhatsApp grubu aboneliği</h3>' +
        '<p class="hint" style="margin:0 0 12px">iyzico Subscription ile karttan periyodik çekim. Sandbox’ta günlük, canlıda aylık. Grup linki sitede yayınlanmaz; üyeleri Abonelikler sekmesinden görürsünüz.</p>' +
        '<div class="form-grid">' +
        '<div class="field"><label>Satış açık</label><select name="sub_enabled">' +
          '<option value="1"' + ((s.sub_enabled || "1") !== "0" ? " selected" : "") + ">Açık</option>" +
          '<option value="0"' + ((s.sub_enabled || "1") === "0" ? " selected" : "") + ">Kapalı</option>" +
        "</select></div>" +
        fld("sub_title", "Başlık") +
        '<div class="field"><label>Fiyat (TL)</label><input name="sub_price" value="' + esc(s.sub_price || "199") + '"></div>' +
        '<div class="field"><label>Dönem</label><input value="' + esc(s.sub_interval_label || "") + '" disabled></div>' +
        '<div class="field full"><label>Kısa açıklama</label><input name="sub_blurb" value="' + esc(s.sub_blurb || "") + '"></div>' +
        '<div class="field full"><label>Bağlı eğitmen</label><select name="sub_instructor_id">' +
        selectOpts(d.instructors || [], "id", "name", s.sub_instructor_id) +
        "</select></div>" +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">Eğitmen kazancı</h3>' +
        '<p class="hint" style="margin:0 0 12px">Yeni eğitmenlerde varsayılan pay. Kişiye özel yüzdeyi Eğitmenler → Düzenle’den verirsiniz (ör. biri %60, diğeri %70). Para iyzico’da kalır; burası yalnızca gösterimdir.</p>' +
        '<div class="form-grid">' +
        '<div class="field"><label>Eğitmen payı (%)</label><input type="number" name="instructor_share_pct" min="0" max="100" step="1" value="' +
        esc(s.instructor_share_pct || "60") +
        '"></div>' +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">Kart ödemesi (iyzico sandbox)</h3>' +
        '<p class="hint" style="margin:0 0 12px">Gerçek tahsilat kapalı (sandbox). Test ödemeleri yalnızca <b>sandbox-merchant.iyzipay.com</b> → İşlemler’de görünür; canlı merchant panelinde görünmez. Sitedeki tahsilatları <b>Ödemeler</b> sekmesinden görürsünüz.</p>' +
        '<p class="hint" style="margin:0 0 12px">Durum: ' +
        ((s.iyzico_ready === "1") ? "sandbox açık" : "sandbox kapalı") +
        " · kaynak: " + esc(s.iyzico_key_source || "yok") +
        ". Test kartı: 5528 7900 0000 0008 · 12/30 · CVC 123 · SMS 123456</p>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">Öğrenci e-postaları (SMTP)</h3>' +
        '<p class="hint" style="margin:0 0 12px">Kayıt doğrulama ve şifre sıfırlama Gmail SMTP ile gider. Boş şifre alanı kayıtlı şifreyi değiştirmez.</p>' +
        '<div class="form-grid">' +
        fld("smtp_host", "SMTP sunucu") + fld("smtp_port", "Port (587 / 465)") +
        '<div class="field"><label>Şifreleme</label><select name="smtp_secure">' +
          '<option value="tls"' + ((s.smtp_secure || "tls") === "tls" ? " selected" : "") + ">STARTTLS (587)</option>" +
          '<option value="ssl"' + (s.smtp_secure === "ssl" ? " selected" : "") + ">SSL (465)</option>" +
          '<option value="none"' + (s.smtp_secure === "none" ? " selected" : "") + ">Yok</option>" +
        "</select></div>" +
        fld("smtp_user", "Kullanıcı (e-posta)") +
        '<div class="field"><label>Şifre</label><input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="' + (s.smtp_pass_set ? "Kayıtlı (değiştirmek için yazın)" : "") + '"></div>' +
        fld("smtp_from", "Gönderen e-posta") + fld("smtp_from_name", "Gönderen adı") +
        "</div>" +
        '<h3 style="margin:22px 0 4px;font-size:15px">İletişim formu (EmailJS)</h3>' +
        '<div class="form-grid">' +
        fld("emailjs_public", "Public Key") + fld("emailjs_service", "Service ID") +
        fld("emailjs_template", "Template ID") + fld("emailjs_to", "Alıcı E-posta") +
        "</div>" +
        '<div class="form-actions"><button type="submit" class="btn-primary sm">Ayarları Kaydet</button></div></form>' +
        "</div></div>" +
        '<div class="panel"><div class="panel-head"><h3>Şifre Değiştir</h3></div><div class="panel-body">' +
        '<form id="pwForm"><div class="form-grid">' +
        '<div class="field"><label>Mevcut Şifre</label><input type="password" name="current" required></div>' +
        '<div class="field"><label>Yeni Şifre (min 6)</label><input type="password" name="new" required></div>' +
        '</div><div class="form-actions"><button type="submit" class="btn-ghost">Şifreyi Güncelle</button></div></form>' +
        "</div></div>";

      document.getElementById("setForm").onsubmit = function (e) {
        e.preventDefault();
        var f = e.target, out = {};
        ["marka", "sehir", "telefon", "whatsapp", "instagram", "twitter", "banka", "hesap_adi", "iban", "instructor_share_pct", "emailjs_public", "emailjs_service", "emailjs_template", "emailjs_to", "smtp_host", "smtp_port", "smtp_secure", "smtp_user", "smtp_pass", "smtp_from", "smtp_from_name", "sub_enabled", "sub_title", "sub_price", "sub_blurb", "sub_instructor_id", "satici_unvan", "satici_adres", "satici_vergi", "satici_mersis", "nav_hakkimizda", "nav_sss", "nav_iletisim", "nav_araclar"].forEach(function (k) { if (f[k]) out[k] = f[k].value; });
        post("settings_save", out).then(function (r) { toast(r.ok ? "Ayarlar kaydedildi" : (r.error || "Hata"), r.ok ? "ok" : "err"); });
      };
      document.getElementById("pwForm").onsubmit = function (e) {
        e.preventDefault();
        var f = e.target;
        post("change_password", { current: f.current.value, new: f.new.value }).then(function (r) {
          if (r.ok) { toast("Şifre güncellendi"); f.reset(); } else toast(r.error || "Hata", "err");
        });
      };
    });
  };

  function updateBadge() {
    get("stats").then(function (d) { if (d.ok) updateUnread(d.cards.unread); });
  }

  // start
  setView("dashboard");
})();
