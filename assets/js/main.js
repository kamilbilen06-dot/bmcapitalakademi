/*
 * BM Capital - Ortak site davranışları
 * Header/footer enjeksiyonu, mobil menü, accordion, form, kopyala, WhatsApp, animasyonlar.
 */
(function () {
  var S = (window.BM_DATA && window.BM_DATA.site) || {};
  var waLink = "https://wa.me/" + (S.whatsapp || "");

  // Aktif sayfa dosya adı (nav vurgusu için)
  var path = location.pathname.split("/").pop() || "index.html";
  // Kök dizine göre önek: dizin derinliği kadar "../"
  // /araclar/temettu.html → "../", /borsa-egitimi/manisa → "../", /rehber/ → "../"
  var base = (function () {
    var segments = location.pathname.split("/").filter(Boolean);
    var depth = /\/$/.test(location.pathname) ? segments.length : segments.length - 1;
    return depth > 0 ? new Array(depth + 1).join("../") : "";
  })();
  // API kök dizini (PHP endpoint'leri)
  var apiBase = base + "api/";
  var inAraclar = /araclar/.test(location.pathname);
  var inRehber = /^\/rehber(\/|$)/.test(location.pathname);
  var inCity = /^\/borsa-egitimi(\/|$)/.test(location.pathname);

  function navOn(flag) {
    return flag === true || flag === 1 || flag === "1";
  }

  function navLink(href, label) {
    var isActive =
      (href === path && !inRehber && !inCity) ||
      (href === "araclar.html" && inAraclar) ||
      (href === "rehber/" && inRehber) ||
      (href === "egitimler.html" && inCity);
    return (
      '<li><a href="' + base + href + '"' + (isActive ? ' class="active"' : "") + ">" + label + "</a></li>"
    );
  }

  function brandHtml() {
    var mark = S.brandMark || "BM";
    var word = S.brandWord || "Capital";
    var tag = S.brandTagline || "Akademi";
    var label = S.marka || "Ana sayfa";
    return (
      '<a href="' + base + 'index.html" class="brand" aria-label="' + label + '">' +
      '<span class="brand-mark"><span class="bm">' + mark + "</span></span>" +
      '<span class="brand-word">' + word + "<small>" + tag + "</small></span></a>"
    );
  }

  function esc(v) {
    return String(v == null ? "" : v).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // Öğrenci oturumu yoksa gösterilen hâl; giriş varsa initStudentNav değiştirir.
  function studentNavGuest() {
    return (
      '<li class="nav-auth"><a href="' + base + 'ogrenci/giris.php">Giriş</a></li>' +
      '<li class="nav-cta nav-auth"><a href="' + base + 'ogrenci/kayit.php">Ücretsiz Kayıt</a></li>'
    );
  }

  function studentNavUser(name) {
    var label = name ? String(name).trim().split(/\s+/)[0] : "Hesabım";
    return (
      '<li class="nav-auth"><a href="' + base + 'ogrenci/index.php">Kurslarım</a></li>' +
      '<li class="nav-cta nav-auth"><a href="' + base + 'ogrenci/profil.php">' + esc(label) + "</a></li>"
    );
  }

  // Misafir kullanıcıyı satın alma öncesi girişe yönlendir (next ile geri döner)
  function gateBuyButtons() {
    document.querySelectorAll("a[data-buy]").forEach(function (a) {
      var target = a.getAttribute("href") || "odeme.php";
      a.setAttribute("href", base + "ogrenci/giris.php?next=" + encodeURIComponent("../" + target));
    });
  }

  function initStudentNav() {
    if (!window.fetch) return;
    fetch(apiBase + "student_auth.php?action=me", { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        if (!d.loggedIn) {
          gateBuyButtons();
          return;
        }
        var links = document.querySelector(".nav-links");
        if (!links) return;
        links.querySelectorAll(".nav-auth").forEach(function (el) { el.remove(); });
        links.insertAdjacentHTML("beforeend", studentNavUser(d.student && d.student.name));
        if (window.BM_CART && d.ownedCourseIds) {
          window.BM_CART.pruneOwned(d.ownedCourseIds);
        }
      })
      .catch(function () {});
  }

  function siteOrigin() {
    if (S.publicUrl) return String(S.publicUrl).replace(/\/$/, "");
    if (location.origin && location.origin.indexOf("http") === 0) return location.origin;
    return "https://www.bmcapitalakademi.com";
  }

  function buildHeader() {
    return (
      '<header class="site-header"><div class="container nav">' +
      brandHtml() +
      '<button class="nav-toggle" aria-label="Menüyü aç/kapat" aria-expanded="false">' +
      '<i class="fa-solid fa-bars"></i></button>' +
      '<ul class="nav-links">' +
      navLink("index.html", "Ana Sayfa") +
      navLink("egitimler.html", "Eğitimler") +
      navLink("urunler.html", "Ürünler") +
      navLink("rehber/", "Rehber") +
      (navOn(S.navAraclar) ? navLink("araclar.html", "Araçlar") : "") +
      (navOn(S.navHakkimizda) ? navLink("hakkimizda.html", "Hakkımızda") : "") +
      (navOn(S.navSss) ? navLink("sss.html", "S.S.S.") : "") +
      (navOn(S.navIletisim) ? navLink("iletisim.html", "İletişim") : "") +
      studentNavGuest() +
      "</ul></div></header>"
    );
  }

  // Şehir ve rehber sayfalarına iç link satırı (tarama derinliğini azaltır)
  var FOOTER_CITY_SLUGS = [
    ["istanbul", "İstanbul"], ["ankara", "Ankara"], ["izmir", "İzmir"],
    ["bursa", "Bursa"], ["antalya", "Antalya"], ["adana", "Adana"],
    ["konya", "Konya"], ["kocaeli", "Kocaeli"], ["manisa", "Manisa"],
    ["gaziantep", "Gaziantep"], ["kayseri", "Kayseri"], ["samsun", "Samsun"],
    ["eskisehir", "Eskişehir"], ["denizli", "Denizli"], ["trabzon", "Trabzon"],
  ];

  var FOOTER_GUIDES = [
    ["akd-analizi-nedir.html", "AKD analizi nedir?"],
    ["bist-takas-analizi.html", "BIST takas analizi"],
    ["hisse-senedi-analizi-nasil-yapilir.html", "Hisse senedi analizi"],
    ["bist-100-nedir.html", "BIST 100 nedir?"],
    ["borsa-yatirim-stratejileri.html", "Borsa yatırım stratejileri"],
    ["borsa-egitimi-nasil-secilir.html", "Borsa eğitimi nasıl seçilir?"],
  ];

  function buildFooterSeoRow() {
    var cities = FOOTER_CITY_SLUGS.map(function (c) {
      return '<a href="' + base + "borsa-egitimi/" + c[0] + '">' + c[1] + " Borsa Eğitimi</a>";
    }).join("");
    var guides = FOOTER_GUIDES.map(function (g) {
      return '<a href="' + base + "rehber/" + g[0] + '">' + g[1] + "</a>";
    }).join("");
    return (
      '<div class="footer-seo">' +
      "<h5>Şehirlere göre borsa eğitimi</h5>" +
      '<div class="footer-seo-links">' + cities +
      '<a href="' + base + 'borsa-egitimi/">Tüm iller →</a></div>' +
      "<h5>Rehberler</h5>" +
      '<div class="footer-seo-links">' + guides + "</div>" +
      "</div>"
    );
  }

  function buildFooter() {
    var socialHtml = "";
    if (S.twitter) {
      socialHtml +=
        '<a href="' + S.twitter + '" target="_blank" rel="noopener" title="Twitter/X"><i class="fa-brands fa-x-twitter"></i></a>';
    }
    if (S.instagram) {
      socialHtml +=
        '<a href="' + S.instagram + '" target="_blank" rel="noopener" title="Instagram"><i class="fa-brands fa-instagram"></i></a>';
    }
    socialHtml +=
      '<a href="' + waLink + '" target="_blank" rel="noopener" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>';

    var kurumsal = "";
    if (navOn(S.navHakkimizda)) {
      kurumsal +=
        '<li><a href="' + base + 'hakkimizda.html">Hakkımızda</a></li>' +
        '<li><a href="' + base + 'hakkimizda.html">Eğitmenler</a></li>';
    }
    if (navOn(S.navSss)) {
      kurumsal += '<li><a href="' + base + 'sss.html">S.S.S.</a></li>';
    }
    if (navOn(S.navIletisim)) {
      kurumsal += '<li><a href="' + base + 'iletisim.html">İletişim</a></li>';
    }

    return (
      '<footer class="site-footer"><div class="container">' +
      '<div class="footer-grid">' +
      "<div>" +
      brandHtml() +
      "<p>Bilimsel yöntem, veri analizi ve disiplinli sistemlerle sürdürülebilir getiri hedefleyen yatırımcılar için eğitim ve algoritmik çözümler.</p>" +
      '<div class="footer-social">' +
      socialHtml +
      "</div></div>" +
      (kurumsal ? "<div><h4>Kurumsal</h4><ul class=\"footer-links\">" + kurumsal + "</ul></div>" : "") +
      "<div><h4>Eğitim & Ürünler</h4><ul class=\"footer-links\">" +
      '<li><a href="' + base + 'egitimler.html">Eğitimler</a></li>' +
      '<li><a href="' + base + 'urunler.html">Ürünler</a></li>' +
      (navOn(S.navAraclar) ? '<li><a href="' + base + 'araclar.html">Borsa Araçları</a></li>' : "") +
      '<li><a href="' + base + 'rehber/">Rehber</a></li>' +
      '<li><a href="' + base + 'borsa-egitimi/">Şehirlere Göre Eğitim</a></li>' +
      '<li><a href="' + base + 'odeme.php">Kayıt / Ödeme</a></li>' +
      "</ul></div>" +
      "<div><h4>Yasal</h4><ul class=\"footer-links\">" +
      '<li><a href="' + base + 'yasal/kvkk.html">KVKK Aydınlatma Metni</a></li>' +
      '<li><a href="' + base + 'yasal/gizlilik.html">Gizlilik Politikası</a></li>' +
      '<li><a href="' + base + 'yasal/on-bilgilendirme.html">Ön Bilgilendirme Formu</a></li>' +
      '<li><a href="' + base + 'yasal/mesafeli-satis.html">Mesafeli Satış Sözleşmesi</a></li>' +
      '<li><a href="' + base + 'yasal/cerez.html">Çerez Politikası</a></li>' +
      "</ul></div>" +
      "</div>" +
      buildFooterSeoRow() +
      '<div class="footer-bottom">© 2026 ' + (S.marka || "BM Capital") +
      ". Tüm hakları saklıdır. &nbsp;|&nbsp; İletişim: " +
      '<a href="' + (S.telefonHref || "#") + '" style="color:#f39c12;text-decoration:none;">' +
      (S.telefon || "") + "</a></div>" +
      "</div></footer>"
    );
  }

  function buildFloaters() {
    var rail = "";
    if (S.twitter) {
      rail +=
        '<a href="' + S.twitter + '" target="_blank" rel="noopener" class="tw" title="Twitter/X"><i class="fa-brands fa-x-twitter"></i></a>';
    }
    if (S.instagram) {
      rail +=
        '<a href="' + S.instagram + '" target="_blank" rel="noopener" class="ig" title="Instagram"><i class="fa-brands fa-instagram"></i></a>';
    }
    return (
      (rail ? '<div class="social-rail">' + rail + "</div>" : "") +
      '<a href="' + waLink + '" target="_blank" rel="noopener" class="wa-float" title="WhatsApp ile yazın"><i class="fa-brands fa-whatsapp"></i></a>'
    );
  }

  function inject(id, html, mode) {
    var el = document.getElementById(id);
    if (!el) return;
    el.outerHTML = html; // placeholder'ı gerçek içerikle değiştir
  }

  // --- Mobil menü ---
  function initMenu() {
    var toggle = document.querySelector(".nav-toggle");
    var links = document.querySelector(".nav-links");
    if (!toggle || !links) return;
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    // Delegasyon: oturum durumuna göre sonradan eklenen linkler de menüyü kapatır
    links.addEventListener("click", function (e) {
      if (!e.target.closest("a")) return;
      links.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    });
  }

  // --- Accordion (aria destekli) ---
  function initAccordions() {
    document.addEventListener("click", function (e) {
      var header = e.target.closest(".acc-header");
      if (!header) return;
      var panel = header.nextElementSibling;
      var expanded = header.getAttribute("aria-expanded") === "true";
      header.setAttribute("aria-expanded", expanded ? "false" : "true");
      if (panel) panel.classList.toggle("open", !expanded);
    });
  }

  // --- Kopyala butonları ---
  function initCopy() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-copy]");
      if (!btn) return;
      var text = btn.getAttribute("data-copy");
      var done = function () {
        var old = btn.textContent;
        btn.textContent = "Kopyalandı!";
        setTimeout(function () { btn.textContent = old; }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(done);
      } else {
        var ta = document.createElement("textarea");
        ta.value = text; document.body.appendChild(ta); ta.select();
        try { document.execCommand("copy"); } catch (err) {}
        document.body.removeChild(ta); done();
      }
    });
  }

  // --- Form gönderimi (EmailJS ile e-posta) ---
  function emailCfg() {
    return (window.BM_DATA && window.BM_DATA.site && window.BM_DATA.site.emailjs) || null;
  }

  function initEmail() {
    var cfg = emailCfg();
    if (cfg && typeof window.emailjs !== "undefined" && cfg.publicKey) {
      try { window.emailjs.init(cfg.publicKey); } catch (e) {}
    }
  }

  function showMsg(msg, ok, text) {
    if (!msg) return;
    msg.classList.remove("ok", "err");
    msg.classList.add(ok ? "ok" : "err");
    if (text) msg.textContent = text;
    msg.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  // İletişim mesajını veritabanına kaydet (panelde görüntülenir)
  function logContact(form) {
    if (!window.fetch) return;
    var fd = new FormData(form);
    var ad = ((fd.get("ad") || "") + " " + (fd.get("soyad") || "")).trim();
    var payload = {
      name: ad,
      email: fd.get("email") || "",
      phone: fd.get("tel") || "",
      subject: fd.get("ilgi") || "Genel",
      message: fd.get("mesaj") || "",
    };
    if (!payload.name && !payload.email && !payload.phone) return;
    try {
      fetch(apiBase + "contact.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        credentials: "same-origin",
      }).catch(function () {});
    } catch (e) {}
  }

  function initForms() {
    document.querySelectorAll("form[data-form]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var msg = form.querySelector(".form-msg");
        var cfg = emailCfg();
        var isEmail = form.getAttribute("data-form") === "email";
        logContact(form);

        if (isEmail && cfg && typeof window.emailjs !== "undefined") {
          var btn = form.querySelector('button[type="submit"]');
          var orig = btn ? btn.textContent : "";
          if (btn) { btn.disabled = true; btn.textContent = "Gönderiliyor..."; }
          var fd = new FormData(form);
          var adSoyad = ((fd.get("ad") || "") + " " + (fd.get("soyad") || "")).trim();
          var params = {
            to_email: cfg.toEmail,
            from_name: adSoyad || "Web Ziyaretçisi",
            ad_soyad: adSoyad,
            eposta: fd.get("email") || "",
            telefon: fd.get("tel") || "",
            konu: fd.get("ilgi") || "Genel",
            message:
              "Ad Soyad: " + adSoyad +
              "\nE-posta: " + (fd.get("email") || "") +
              "\nTelefon: " + (fd.get("tel") || "") +
              "\nİlgilenilen: " + (fd.get("ilgi") || "") +
              "\nMesaj: " + (fd.get("mesaj") || ""),
          };
          window.emailjs.send(cfg.serviceId, cfg.templateId, params).then(
            function () {
              showMsg(msg, true, "Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.");
              form.reset();
              if (btn) { btn.disabled = false; btn.textContent = orig; }
            },
            function (err) {
              showMsg(msg, false, "Gönderim sırasında bir sorun oluştu. Lütfen WhatsApp veya telefon ile ulaşın.");
              if (btn) { btn.disabled = false; btn.textContent = orig; }
              if (window.console) console.error("EmailJS:", err);
            }
          );
          return;
        }

        // EmailJS yoksa: bilgilendirme
        showMsg(msg, true, "Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.");
        form.reset();
      });
    });
  }

  // --- Scroll reveal ---
  function initReveal() {
    var els = document.querySelectorAll(".reveal");
    if (!("IntersectionObserver" in window)) {
      els.forEach(function (el) { el.classList.add("in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
  }

  // --- İçeriği API'den yükle (yönetim paneli verileri). Başarısız olursa data.js kullanılır. ---
  function loadContent(cb) {
    if (!window.fetch) { cb(); return; }
    var done = false;
    var finish = function () { if (!done) { done = true; cb(); } };
    fetch(apiBase + "public.php", { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && Array.isArray(d.egitimler)) {
          window.BM_DATA.egitimler = d.egitimler;
          window.BM_DATA.urunler = d.urunler || [];
          if (Array.isArray(d.sss)) window.BM_DATA.sss = d.sss;
          if (Array.isArray(d.egitmenProfilleri)) {
            window.BM_DATA.egitmenProfilleri = d.egitmenProfilleri;
          }
          if (d.site) {
            var site = window.BM_DATA.site || {};
            for (var k in d.site) { if (d.site[k] !== "" && d.site[k] != null) site[k] = d.site[k]; }
            if (site.telefon) site.telefonHref = "tel:" + String(site.telefon).replace(/\s/g, "");
            if (!site.marka) site.marka = "BM Capital Akademi";
            window.BM_DATA.site = site;
          }
        }
        finish();
      })
      .catch(finish);
    // API yavaş/erişilemez ise 4 sn sonra yine de devam et
    setTimeout(finish, 4000);
  }

  // --- Ziyaretçi takibi (yönetim paneli istatistikleri) ---
  function readVisitorId() {
    var match = document.cookie.match(/(?:^|;\s*)bm_vid=([A-Fa-f0-9]{8,16})/);
    return match ? match[1] : "";
  }

  function trackVisit() {
    if (!window.fetch) return;
    try {
      fetch(apiBase + "track.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          path: location.pathname,
          title: document.title,
          referrer: document.referrer,
          visitor_id: readVisitorId(),
        }),
        credentials: "same-origin",
        keepalive: true,
      }).catch(function () {});
    } catch (e) {}
  }

  function paintChrome() {
    S = (window.BM_DATA && window.BM_DATA.site) || {};
    waLink = "https://wa.me/" + (S.whatsapp || "");
    if (window.BM_HELPERS) window.BM_HELPERS.waLink = waLink;
    inject("site-header", buildHeader());
    inject("site-footer", buildFooter());
    var floaters = document.getElementById("site-floaters");
    if (floaters) floaters.outerHTML = buildFloaters();
  }

  function bindUi() {
    initMenu();
    initAccordions();
    initCopy();
    initEmail();
    initForms();
    initReveal();
    fillLegalSeller();
    initCookieBanner();
  }

  function start() {
    loadGaConfig(function () {
      initGoogleAnalytics();
      paintChrome();
      if (window.BM_CATALOG) window.BM_CATALOG.init();
      bindUi();
      injectOrgSchema();
      injectBreadcrumb();

      if (window.BM_CART) {
        initStudentNav();
      } else {
        var cs = document.createElement("script");
        cs.src = base + "assets/js/cart.js";
        cs.onload = function () { initStudentNav(); };
        cs.onerror = function () { initStudentNav(); };
        document.head.appendChild(cs);
      }

      loadContent(function () {
        paintChrome();
        initMenu();
        initStudentNav();
        if (resolveGaMeasurementId()) initGoogleAnalytics();
        if (window.BM_CATALOG) window.BM_CATALOG.init();
      });
      trackVisit();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }

  // --- SEO/GEO: Kuruluş yapısal verisi (her sayfada) ---
  // Aynı tipte JSON-LD tekrar basılırsa eskisini değiştirir (init iki kez çağrılıyor)
  function addJsonLd(obj) {
    try {
      var key = obj && obj["@type"] ? String(obj["@type"]) : "Thing";
      var old = document.head.querySelector('script[data-bm-ld="' + key + '"]');
      if (old) old.remove();
      var s = document.createElement("script");
      s.type = "application/ld+json";
      s.setAttribute("data-bm-ld", key);
      s.textContent = JSON.stringify(obj);
      document.head.appendChild(s);
    } catch (e) {}
  }

  // Hizmet alanı: canlı online olduğu için Türkiye geneli + öne çıkan büyükşehirler
  var SERVED_CITIES = [
    "İzmir", "İstanbul", "Ankara", "Bursa", "Antalya", "Adana", "Konya",
    "Gaziantep", "Kocaeli", "Mersin", "Kayseri", "Eskişehir", "Samsun",
    "Denizli", "Manisa", "Aydın", "Balıkesir", "Muğla", "Trabzon", "Diyarbakır",
  ];

  var KNOWS_ABOUT = [
    "Borsa eğitimi",
    "Borsa İstanbul",
    "BIST 100",
    "Hisse senedi analizi",
    "Teknik analiz",
    "Temel analiz",
    "AKD analizi",
    "Aracı kurum dağılımı analizi",
    "BIST takas analizi",
    "Kurumsal yatırımcı analizi",
    "Algoritmik trade",
    "Borsa yatırım stratejileri",
    "Risk yönetimi",
    "Portföy yönetimi",
  ];

  function instructorPerson(origin) {
    return {
      "@type": "Person",
      "@id": origin + "/egitmen-profil.html?id=kamil-bilen",
      name: "Dr. Kamil Bilen",
      jobTitle: "Borsa Eğitmeni",
      url: origin + "/egitmen-profil.html?id=kamil-bilen",
      knowsAbout: KNOWS_ABOUT.slice(0, 10),
    };
  }

  function injectOrgSchema() {
    var origin = siteOrigin();
    var name = S.marka || "BM Capital Akademi";
    var alt = S.brandShort || "BM Capital";
    var city = S.sehir || "İzmir";
    var served = SERVED_CITIES.map(function (c) {
      return { "@type": "City", name: c };
    });
    served.push({ "@type": "Country", name: "Türkiye" });

    addJsonLd({
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "@id": origin + "/#organization",
      name: name,
      alternateName: alt,
      description:
        "BM Capital Akademi, Türkiye'nin tüm illerinden canlı online katılıma açık profesyonel borsa eğitimi veren bir eğitim kuruluşudur. Müfredat; BIST 100 ve hisse senedi analizi, teknik analiz, temel analiz, AKD (aracı kurum dağılımı) ve takas analizi ile algoritmik trade konularını kapsar. " +
        city + " merkezli olarak yüz yüze eğitim de verilir.",
      url: origin + "/",
      logo: origin + "/assets/img/og-cover.jpg",
      image: origin + "/assets/img/og-cover.jpg",
      telephone: S.telefonHref || S.telefon || "",
      email: (S.emailjs && S.emailjs.toEmail) || "",
      areaServed: served,
      knowsAbout: KNOWS_ABOUT,
      founder: instructorPerson(origin),
      employee: [instructorPerson(origin)],
      address: {
        "@type": "PostalAddress",
        addressLocality: city,
        addressRegion: city,
        addressCountry: "TR",
      },
      hasOfferCatalog: {
        "@type": "OfferCatalog",
        name: "Borsa Eğitimleri",
        itemListElement: [
          {
            "@type": "Course",
            name: "Teknik, Temel ve Algoritmik Analiz Eğitimi",
            url: origin + "/egitim-detay.html?id=teknik-temel-algoritmik",
          },
          {
            "@type": "Course",
            name: "Takas ve Aracı Kurum Dağılımı (AKD) Analizi Eğitimi",
            url: origin + "/egitim-detay.html?id=takas-akd-analizi",
          },
        ],
      },
      sameAs: [S.instagram, S.twitter].filter(Boolean),
    });
  }

  // --- SEO: Sayfa yolu (breadcrumb) yapısal verisi ---
  function injectBreadcrumb() {
    var origin = siteOrigin();
    var labels = {
      "index.html": "Ana Sayfa",
      "egitimler.html": "Eğitimler",
      "urunler.html": "Ürünler",
      "araclar.html": "Borsa Araçları",
      "hakkimizda.html": "Hakkımızda",
      "sss.html": "Sıkça Sorulan Sorular",
      "iletisim.html": "İletişim",
      "odeme.php": "Ödeme",
      "abonelik.html": "Abonelik",
      "egitim-detay.html": "Eğitim Detayı",
      "egitmen-profil.html": "Eğitmen",
      "urun-detay.html": "Ürün Detayı",
      "temettu.html": "Temettü Hesaplama",
      "kar-zarar.html": "Hisse Kâr / Zarar",
      "maliyet.html": "Maliyet Düşürme",
      "bedelli.html": "Bedelli Sermaye Artırımı",
      "bedelsiz.html": "Bedelsiz Sermaye Artırımı",
      "bilesik-faiz.html": "Bileşik Faiz",
      "tavan-serisi.html": "Tavan Serisi",
      "konut-kredisi.html": "Konut Kredisi Hesaplama",
      "tasarruf-finansmani.html": "Tasarruf Finansmanı",
      "kk-vs-tk.html": "Konut Kredisi vs Tasarruf Finansmanı",
    };
    var items = [{ "@type": "ListItem", position: 1, name: "Ana Sayfa", item: origin + "/" }];

    // Sayfa kendi izini bildirdiyse (şehir/rehber sayfaları) onu kullan
    if (Array.isArray(window.BM_PAGE_BREADCRUMB) && window.BM_PAGE_BREADCRUMB.length) {
      window.BM_PAGE_BREADCRUMB.forEach(function (entry, i) {
        if (!entry || !entry.name) return;
        items.push({
          "@type": "ListItem",
          position: i + 2,
          name: entry.name,
          item: origin + (entry.path || "/"),
        });
      });
      addJsonLd({ "@context": "https://schema.org", "@type": "BreadcrumbList", itemListElement: items });
      return;
    }

    if (inAraclar && path !== "araclar.html") {
      items.push({ "@type": "ListItem", position: 2, name: "Borsa Araçları", item: origin + "/araclar.html" });
      if (labels[path]) {
        items.push({ "@type": "ListItem", position: 3, name: labels[path], item: origin + "/araclar/" + path });
      }
    } else if (path && path !== "index.html" && labels[path]) {
      items.push({ "@type": "ListItem", position: 2, name: labels[path], item: origin + "/" + path });
    }
    addJsonLd({ "@context": "https://schema.org", "@type": "BreadcrumbList", itemListElement: items });
  }

  function fillLegalSeller() {
    var map = {
      unvan: S.saticiUnvan,
      adres: S.saticiAdres,
      vergi: S.saticiVergi,
      mersis: S.saticiMersis,
    };
    document.querySelectorAll("[data-legal]").forEach(function (el) {
      var k = el.getAttribute("data-legal");
      if (k && map[k]) el.textContent = map[k];
    });
  }

  function initCookieBanner() {
    var hasGa = !!resolveGaMeasurementId();
    try {
      if (localStorage.getItem("bmcap_cookie_ok") === "1") {
        grantAnalyticsConsent();
        return;
      }
    } catch (e) {}
    if (document.getElementById("cookieBanner")) return;
    var bar = document.createElement("div");
    bar.id = "cookieBanner";
    bar.className = "cookie-banner";
    bar.innerHTML =
      "<p>Sitemiz oturum, güvenlik ve sepet için zorunlu çerez kullanır." +
      (hasGa
        ? " Analitik çerezler (Google Analytics) yalnızca onayınızla yüklenir."
        : "") +
      " Ayrıntı: <a href=\"" +
      base +
      'yasal/cerez.html">Çerez Politikası</a>.</p>' +
      '<button type="button" class="btn btn-primary btn-sm" id="cookieOk">Kabul et</button>';
    document.body.appendChild(bar);
    var btn = document.getElementById("cookieOk");
    if (btn) {
      btn.onclick = function () {
        try {
          localStorage.setItem("bmcap_cookie_ok", "1");
        } catch (e) {}
        grantAnalyticsConsent();
        bar.remove();
      };
    }
  }

  var gaBooted = false;

  function resolveGaMeasurementId() {
    var id = window.BM_GA_MEASUREMENT_ID || "";
    if (!id && window.BM_DATA && window.BM_DATA.site) {
      id = window.BM_DATA.site.gaMeasurementId || "";
    }
    id = String(id || "").trim().toUpperCase();
    return /^G-[A-Z0-9]{6,12}$/.test(id) ? id : "";
  }

  function ensureGtag(fn) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    if (typeof fn === "function") fn();
  }

  function grantAnalyticsConsent() {
    var id = resolveGaMeasurementId();
    if (!id) return;
    ensureGtag(function () {
      window.gtag("consent", "update", {
        analytics_storage: "granted",
        ad_storage: "granted",
        ad_user_data: "granted",
        ad_personalization: "granted",
      });
      if (!gaBooted) {
        window.gtag("js", new Date());
        window.gtag("config", id, { anonymize_ip: true, send_page_view: true });
        gaBooted = true;
      } else {
        window.gtag("event", "page_view", { page_path: location.pathname, page_title: document.title });
      }
    });
  }

  function initGoogleAnalytics() {
    var id = resolveGaMeasurementId();
    if (!id) return;
    if (document.getElementById("bmGaTag")) return;

    ensureGtag(function () {
      window.gtag("consent", "default", {
        analytics_storage: "denied",
        ad_storage: "denied",
        ad_user_data: "denied",
        ad_personalization: "denied",
        wait_for_update: 500,
      });
    });

    var s = document.createElement("script");
    s.id = "bmGaTag";
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(id);
    s.onload = function () {
      try {
        if (localStorage.getItem("bmcap_cookie_ok") === "1") {
          grantAnalyticsConsent();
        }
      } catch (e) {}
    };
    document.head.appendChild(s);
  }

  function loadGaConfig(cb) {
    cb = cb || function () {};
    if (window.BM_GA_MEASUREMENT_ID !== undefined) {
      cb();
      return;
    }
    var s = document.createElement("script");
    s.src = apiBase + "ga_config.php";
    s.onload = cb;
    s.onerror = cb;
    document.head.appendChild(s);
  }

  window.BM_GA = { grant: grantAnalyticsConsent };

  window.BM_SEO = { addJsonLd: addJsonLd };

  window.BM_HELPERS = { waLink: waLink };
})();
