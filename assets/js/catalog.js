/*
 * BM Capital - Katalog render motoru
 * data.js verilerinden kart ve detay içeriği üretir.
 * Kullanım (HTML tarafında):
 *   <div data-catalog="egitim"></div>   -> eğitim kartları
 *   <div data-catalog="urun"></div>     -> ürün kartları
 *   <div data-catalog="one-cikan"></div>-> öne çıkan eğitim + ürünler
 *   <div data-detail="egitim"></div>    -> ?id=... ile eğitim detayı
 *   <div data-detail="urun"></div>      -> ?id=... ile ürün detayı
 */
(function () {
  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }
  function param(name) {
    return new URLSearchParams(location.search).get(name);
  }

  function siteOrigin() {
    var S = (window.BM_DATA && window.BM_DATA.site) || {};
    if (S.publicUrl) return String(S.publicUrl).replace(/\/$/, "");
    if (location.origin && location.origin.indexOf("http") === 0) return location.origin;
    return "https://www.bmcapitalakademi.com";
  }

  function brandName() {
    var S = (window.BM_DATA && window.BM_DATA.site) || {};
    return S.marka || "BM Capital Akademi";
  }

  function setName(name, content) {
    var m = document.querySelector('meta[name="' + name + '"]');
    if (!m) { m = document.createElement("meta"); m.setAttribute("name", name); document.head.appendChild(m); }
    m.setAttribute("content", content);
  }
  function setProp(prop, content) {
    var m = document.querySelector('meta[property="' + prop + '"]');
    if (!m) { m = document.createElement("meta"); m.setAttribute("property", prop); document.head.appendChild(m); }
    m.setAttribute("content", content);
  }
  function setCanonical() {
    var l = document.getElementById("canonical") || document.querySelector('link[rel="canonical"]');
    if (l) l.setAttribute("href", location.href.split("#")[0]);
  }
  function addLd(obj) {
    if (window.BM_SEO && window.BM_SEO.addJsonLd) window.BM_SEO.addJsonLd(obj);
  }
  function priceNum(f) {
    if (!f) return null;
    var n = String(f).replace(/[^\d]/g, "");
    return n ? parseInt(n, 10) : null;
  }
  function applyDetailMeta(item) {
    if (!item) return;
    var t = item.baslik + " - BM Capital Akademi";
    document.title = t;
    var desc = item.kisaAciklama || "";
    setName("description", desc);
    setProp("og:title", t);
    setProp("og:description", desc);
    setCanonical();
  }

  function card(item) {
    var isEgitim = item.tip === "egitim";
    var detailUrl = (isEgitim ? "egitim-detay.html" : "urun-detay.html") + "?id=" + encodeURIComponent(item.id);
    var tag = isEgitim ? (item.egitimTuru || "Eğitim") : (item.etiket || "Ürün");
    var media = item.gorsel
      ? '<img src="' + esc(item.gorsel) + '" alt="' + esc(item.baslik) + '" loading="lazy">'
      : '<i class="fa-solid fa-chart-line" style="font-size:52px;color:#f39c12;"></i>';

    var meta = "";
    if (isEgitim) {
      if (item.sure) meta += '<span><i class="fa-regular fa-clock"></i>' + esc(item.sure) + "</span>";
      meta += '<span><i class="fa-solid fa-video"></i>Online / İzmir yüz yüze</span>';
    } else if (item.ozellikler && item.ozellikler.length) {
      meta += '<span><i class="fa-solid fa-gears"></i>' + item.ozellikler.length + " özellik</span>";
    }

    var fiyat = !item.fiyat
      ? '<span class="cat-price" style="font-size:16px;color:#8a93a0;">Bilgi Al</span>'
      : '<span class="cat-price">' + esc(item.fiyat) + (item.fiyatNot ? " <small>" + esc(item.fiyatNot) + "</small>" : "") + "</span>";

    return (
      '<article class="cat-card reveal">' +
      '<a href="' + detailUrl + '" class="cat-media">' +
      '<span class="cat-tag">' + esc(tag) + "</span>" + media + "</a>" +
      '<div class="cat-body">' +
      "<h3>" + esc(item.baslik) + "</h3>" +
      "<p>" + esc(item.kisaAciklama) + "</p>" +
      (meta ? '<div class="cat-meta">' + meta + "</div>" : "") +
      '<div class="cat-foot">' + fiyat +
      '<a href="' + detailUrl + '" class="btn btn-primary">İncele</a>' +
      "</div></div></article>"
    );
  }

  function renderCards(el, list) {
    el.classList.add("grid-catalog");
    el.innerHTML = list.map(card).join("");
  }

  var TR_MONTHS = {
    ocak: "01", subat: "02", şubat: "02", mart: "03", nisan: "04",
    mayis: "05", mayıs: "05", haziran: "06", temmuz: "07", agustos: "08",
    ağustos: "08", eylul: "09", eylül: "09", ekim: "10", kasim: "11",
    kasım: "11", aralik: "12", aralık: "12",
  };

  /** "13 Nisan 2026 Pazartesi / 20:00-24:00" → "2026-04-13" */
  function parseTrDate(text) {
    if (!text) return null;
    var m = String(text).match(/(\d{1,2})\s+([A-Za-zÇĞİÖŞÜçğıöşü]+)\s+(\d{4})/);
    if (!m) return null;
    var day = ("0" + m[1]).slice(-2);
    var mon = TR_MONTHS[m[2].toLowerCase()];
    if (!mon) return null;
    return m[3] + "-" + mon + "-" + day;
  }

  function courseDateRange(item) {
    var list = item.tarihler || [];
    var dates = [];
    for (var i = 0; i < list.length; i++) {
      var d = parseTrDate(list[i]);
      if (d) dates.push(d);
    }
    if (!dates.length) return null;
    dates.sort();
    return { start: dates[0], end: dates[dates.length - 1] };
  }

  function addCourseSchema(item) {
    var origin = siteOrigin();
    var pageUrl = location.href.split("#")[0];
    var range = courseDateRange(item);
    var city = ((window.BM_DATA && window.BM_DATA.site) || {}).sehir || "İzmir";
    var onlineInstance = {
      "@type": "CourseInstance",
      courseMode: "online",
      courseWorkload: item.sure || "PT20H",
      location: { "@type": "VirtualLocation", url: pageUrl },
    };
    var onsiteInstance = {
      "@type": "CourseInstance",
      courseMode: "onsite",
      courseWorkload: item.sure || "PT20H",
      location: {
        "@type": "Place",
        name: brandName() + " — " + city,
        address: {
          "@type": "PostalAddress",
          addressLocality: city,
          addressRegion: city,
          addressCountry: "TR",
        },
      },
    };
    if (range) {
      onlineInstance.startDate = range.start;
      onlineInstance.endDate = range.end;
      onsiteInstance.startDate = range.start;
      onsiteInstance.endDate = range.end;
    }
    var data = {
      "@context": "https://schema.org",
      "@type": "Course",
      name: item.baslik,
      description: item.kisaAciklama,
      provider: {
        "@type": "Organization",
        name: "BM Capital Akademi",
        sameAs: origin + "/",
      },
      educationalCredentialAwarded: "Katılım Sertifikası",
      hasCourseInstance: [onlineInstance, onsiteInstance],
    };
    var p = priceNum(item.fiyat);
    if (p) {
      data.offers = {
        "@type": "Offer",
        price: p,
        priceCurrency: "TRY",
        category: "Paid",
        availability: "https://schema.org/InStock",
        url: pageUrl,
      };
    }
    addLd(data);
  }

  function addProductSchema(item) {
    var origin = siteOrigin();
    var data = {
      "@context": "https://schema.org",
      "@type": "Product",
      name: item.baslik,
      description: item.kisaAciklama,
      brand: { "@type": "Brand", name: brandName() },
    };
    if (item.gorsel) data.image = origin + "/" + item.gorsel;
    var p = priceNum(item.fiyat);
    if (p) {
      data.offers = { "@type": "Offer", price: p, priceCurrency: "TRY", availability: "https://schema.org/InStock", url: location.href.split("#")[0] };
    }
    addLd(data);
  }

  function addFaqSchema(list) {
    if (!list || !list.length) return;
    addLd({
      "@context": "https://schema.org",
      "@type": "FAQPage",
      mainEntity: list.map(function (f) {
        return {
          "@type": "Question",
          name: f.soru,
          acceptedAnswer: { "@type": "Answer", text: f.cevap },
        };
      }),
    });
  }

  var FALLBACK_INSTRUCTORS = [
    {
      id: "kamil-bilen",
      name: "Dr. Kamil BİLEN",
      title: "Analiz & Yatırım Eğitmeni · SPL Düzey 3 & Türev",
      photo: "",
      bio: "Teknik Analiz, Takas Analizi, AKD Analizi ve Algoritmik Trade konularında uygulamalı eğitimler verir. SPL Düzey 3 ve Türev Piyasaları Lisansı sahibidir; sermaye piyasalarında uzun yıllara dayanan kurumsal deneyime sahiptir.",
      socials: [],
    },
  ];

  var SOCIAL_ICONS = {
    youtube: { icon: "fa-brands fa-youtube", label: "YouTube" },
    instagram: { icon: "fa-brands fa-instagram", label: "Instagram" },
    x: { icon: "fa-brands fa-x-twitter", label: "X" },
    twitter: { icon: "fa-brands fa-x-twitter", label: "X" },
    linkedin: { icon: "fa-brands fa-linkedin-in", label: "LinkedIn" },
    facebook: { icon: "fa-brands fa-facebook-f", label: "Facebook" },
    web: { icon: "fa-solid fa-globe", label: "Web" },
    link: { icon: "fa-solid fa-link", label: "Link" },
  };

  function instructorInitials(name) {
    return (
      String(name || "")
        .replace(/[^A-Za-zÇĞİÖŞÜçğıöşü]/g, " ")
        .trim()
        .split(/\s+/)
        .map(function (w) {
          return w.charAt(0);
        })
        .slice(0, 2)
        .join("") || "?"
    ).toUpperCase();
  }

  function normalizeInstructor(raw) {
    if (!raw) return null;
    var name = raw.name || "";
    var socials = Array.isArray(raw.socials) ? raw.socials : [];
    var links = socials
      .filter(function (s) {
        return s && String(s.url || "").trim();
      })
      .map(function (s) {
        var p = String(s.platform || "link").toLowerCase();
        var meta = SOCIAL_ICONS[p] || SOCIAL_ICONS.link;
        return {
          href: s.url,
          icon: meta.icon,
          label: meta.label,
          platform: p,
        };
      });
    return {
      id: raw.id || raw.slug || "",
      name: name,
      title: raw.title || "Eğitmen",
      photo: raw.photo || raw.photo_path || "",
      avatar: instructorInitials(name),
      bio: raw.bio || "",
      socials: socials,
      links: links,
    };
  }

  function getInstructorProfiles() {
    var fromApi = (window.BM_DATA && window.BM_DATA.egitmenProfilleri) || [];
    if (fromApi.length) {
      return fromApi.map(normalizeInstructor).filter(Boolean);
    }
    return FALLBACK_INSTRUCTORS.map(normalizeInstructor);
  }

  function normalizeInstructorKey(name) {
    return String(name || "")
      .toLocaleLowerCase("tr-TR")
      .replace(/\s+/g, " ")
      .trim();
  }

  function resolveInstructors(raw) {
    var profiles = getInstructorProfiles();
    var text = String(raw || "").trim();
    if (!text) text = profiles[0] ? profiles[0].name : "Dr. Kamil BİLEN";
    var parts = text.split(/\s*,\s*|\s+ve\s+/i).filter(Boolean);
    var list = [];
    parts.forEach(function (p) {
      var key = normalizeInstructorKey(p);
      var found = null;
      profiles.forEach(function (ins) {
        if (found) return;
        var nk = normalizeInstructorKey(ins.name);
        if (key.indexOf(nk) !== -1 || nk.indexOf(key) !== -1) found = ins;
      });
      list.push(
        found ||
          normalizeInstructor({
            id: "custom-" + list.length,
            name: p,
            title: "Eğitmen",
            bio: "BM Capital Akademi eğitmeni.",
            socials: [],
          })
      );
    });
    return list;
  }

  function findInstructorById(id) {
    var want = String(id || "").trim();
    if (!want) return null;
    var found = null;
    getInstructorProfiles().forEach(function (ins) {
      if (!found && ins.id === want) found = ins;
    });
    return found;
  }

  function coursesForInstructor(instructorId) {
    var D = window.BM_DATA || {};
    var list = D.egitimler || [];
    return list.filter(function (c) {
      return resolveInstructors(c.egitmenler).some(function (ins) {
        return ins.id === instructorId;
      });
    });
  }

  function countCurriculum(item) {
    var secs = item.mufredat || [];
    var lessons = 0;
    secs.forEach(function (s) {
      (s.bolumler || []).forEach(function (b) {
        lessons += (b.maddeler || []).length;
      });
    });
    return { sections: secs.length, lessons: lessons };
  }

  function formatDurationParts(seconds) {
    var sn = parseInt(seconds, 10) || 0;
    if (sn <= 0) return "";
    var h = Math.floor(sn / 3600);
    var m = Math.floor((sn % 3600) / 60);
    if (h && m) return h + " saat " + m + " dakika";
    if (h) return h + " saat";
    return m + " dakika";
  }

  function formatSureLabel(item) {
    var fromSec = formatDurationParts(item.sureSn);
    if (fromSec) return fromSec;
    var s = String(item.sure || "").trim();
    if (!s) return "";
    // "20 saat", "5 sa 10 dak" vb. → "saat / dakika"
    return s
      .replace(/\bsa\b/gi, "saat")
      .replace(/\bdak\b/gi, "dakika")
      .replace(/\s+/g, " ")
      .trim();
  }

  function renderDetailEgitim(el, item) {
    if (!item) { el.innerHTML = notFound(); return; }
    applyDetailMeta(item);
    addCourseSchema(item);

    var counts = countCurriculum(item);
    var sureLabel = formatSureLabel(item);
    var poster = item.videoPoster || item.gorsel || "";
    var hasVideo = !!item.video;
    var instructors = resolveInstructors(item.egitmenler);

    var learnItems = (item.ozellikler || []).map(function (o) {
      return (
        '<li><i class="fa-solid fa-check"></i><span>' + esc(o) + "</span></li>"
      );
    }).join("");

    var aciklama = (item.aciklama || []).map(function (p) {
      return "<p>" + esc(p) + "</p>";
    }).join("");
    if (!aciklama && item.kisaAciklama) {
      aciklama = "<p>" + esc(item.kisaAciklama) + "</p>";
    }

    var tags = [];
    if (item.etiket) tags.push(item.etiket);
    if (item.egitimTuru) tags.push(item.egitimTuru);
    var tagsHtml = tags
      .map(function (t) {
        return '<span class="course-tag">' + esc(t) + "</span>";
      })
      .join("");

    var instructorLinks = instructors
      .map(function (ins) {
        return (
          '<a class="instructor-link" href="egitmen-profil.html?id=' +
          encodeURIComponent(ins.id) +
          '">' +
          esc(ins.name) +
          "</a>"
        );
      })
      .join('<span class="instructor-sep">, </span>');

    var mufredat = (item.mufredat || [])
      .map(function (gun) {
        var rows = [];
        (gun.bolumler || []).forEach(function (b) {
          (b.maddeler || []).forEach(function (m) {
            var title = typeof m === "string" ? m : m.baslik || "";
            var preview = typeof m === "object" && !!m.preview;
            var lecId = typeof m === "object" ? parseInt(m.lectureId, 10) || 0 : 0;
            var previewLink =
              preview && lecId
                ? '<a class="cur-preview" href="ogrenci/kurs.php?id=' +
                  encodeURIComponent(item.id) +
                  "&ders=" +
                  lecId +
                  '">Ücretsiz izle</a>'
                : "";
            rows.push(
              '<div class="cur-lesson">' +
                '<span class="cur-lesson-title"><i class="fa-regular fa-circle-play"></i> ' +
                esc(title) +
                "</span>" +
                previewLink +
                "</div>"
            );
          });
        });
        var lessonCount = rows.length;
        return (
          '<div class="cur-section">' +
          '<button type="button" class="cur-section-head acc-header" aria-expanded="false">' +
          '<span class="cur-left"><i class="fa-solid fa-chevron-down cur-chevron"></i><strong>' +
          esc(gun.baslik) +
          "</strong></span>" +
          '<span class="cur-right">' +
          lessonCount +
          " ders</span></button>" +
          '<div class="acc-panel cur-section-body">' +
          rows.join("") +
          "</div></div>"
        );
      })
      .join("");

    var curStats =
      counts.sections +
      " bölüm • " +
      counts.lessons +
      " ders" +
      (sureLabel ? " • " + sureLabel + " toplam süre" : "");

    var tarihler = (item.tarihler || [])
      .map(function (t) {
        return "<li>" + esc(t) + "</li>";
      })
      .join("");
    var tarihBlok = tarihler
      ? '<div class="course-dates"><h3>Sabit Eğitim Tarihleri</h3>' +
        (item.tarihNot
          ? '<p class="course-muted">' + esc(item.tarihNot) + "</p>"
          : "") +
        '<ul class="side-list">' +
        tarihler +
        "</ul></div>"
      : "";

    var gift = item.hediye && item.hediye.length
      ? '<div class="gift-box"><h4>Hediye Paketi</h4>' +
        (item.hediyeGorsel
          ? '<img src="' + esc(item.hediyeGorsel) + '" alt="Hediye">'
          : "") +
        item.hediye
          .map(function (h) {
            return "<p>✓ " + esc(h) + "</p>";
          })
          .join("") +
        "</div>"
      : "";

    var priceHtml = !item.fiyat
      ? '<div class="preview-price">Bilgi Al</div>'
      : '<div class="preview-price">' +
        esc(item.fiyat) +
        (item.fiyatNot
          ? '<small>' + esc(item.fiyatNot) + "</small>"
          : "") +
        "</div>";

    var waHref =
      window.BM_HELPERS && window.BM_HELPERS.waLink
        ? window.BM_HELPERS.waLink
        : "iletisim.html";
    var buyUrl = "odeme.php?course=" + encodeURIComponent(item.id);
    var inCart = window.BM_CART && window.BM_CART.has(item.id);
    var buyHtml = !item.fiyat
      ? '<div class="buy-stack">' +
        '<a href="' +
        waHref +
        '" target="_blank" rel="noopener" class="btn-buy btn-buy-cart">Bilgi Al</a>' +
        '<a href="iletisim.html" class="btn-buy btn-buy-now">İletişim</a>' +
        "</div>"
      : '<div class="buy-stack">' +
        '<button type="button" class="btn-buy btn-buy-cart" data-add-cart>' +
        (inCart ? "Sepette" : "Sepete ekle") +
        "</button>" +
        '<a href="' +
        buyUrl +
        '" data-buy="' +
        encodeURIComponent(item.id) +
        '" class="btn-buy btn-buy-now">Hemen satın alın</a>' +
        "</div>" +
        '<a href="' +
        waHref +
        '" target="_blank" rel="noopener" class="preview-wa-link"><i class="fa-brands fa-whatsapp"></i> WhatsApp\'tan sor</a>';

    var previewThumb =
      '<button type="button" class="preview-thumb' +
      (hasVideo ? " has-video" : "") +
      '" id="btnOpenPreview"' +
      (hasVideo ? "" : " disabled") +
      ' aria-label="Tanıtım videosunu izle">' +
      (poster
        ? '<img src="' + esc(poster) + '" alt="' + esc(item.baslik) + '">'
        : '<div class="preview-thumb-fallback"><i class="fa-solid fa-graduation-cap"></i></div>') +
      (hasVideo
        ? '<span class="preview-play"><i class="fa-solid fa-play"></i></span><span class="preview-caption">Bu kursu önizle</span>'
        : '<span class="preview-caption muted">Tanıtım videosu yok</span>') +
      "</button>";

    el.innerHTML =
      '<section class="course-lp">' +
      '<div class="course-lp-banner">' +
      '<div class="container">' +
      '<nav class="course-crumb"><a href="index.html">Ana Sayfa</a><span>/</span><a href="egitimler.html">Eğitimler</a><span>/</span><span>' +
      esc(item.baslik) +
      "</span></nav>" +
      "<h1>" +
      esc(item.baslik) +
      "</h1>" +
      (item.kisaAciklama
        ? '<p class="course-sub">' + esc(item.kisaAciklama) + "</p>"
        : "") +
      '<div class="course-banner-meta">' +
      '<span class="instructor-line">Eğitmen ' +
      instructorLinks +
      "</span>" +
      (sureLabel
        ? '<span><i class="fa-regular fa-clock"></i> ' + esc(sureLabel) + "</span>"
        : "") +
      (tagsHtml ? '<span class="course-tags">' + tagsHtml + "</span>" : "") +
      "</div></div></div>" +
      '<div class="course-lp-body"><div class="container course-lp-grid">' +
      '<div class="course-lp-main">' +
      (learnItems
        ? '<div class="learn-box"><h2>Öğrenecekleriniz</h2><ul class="learn-grid">' +
          learnItems +
          "</ul></div>"
        : "") +
      (tagsHtml
        ? '<div class="course-topics"><h3>Konu başlıkları</h3><div class="course-tags-row">' +
          tagsHtml +
          "</div></div>"
        : "") +
      '<div class="course-includes">' +
      "<h3>Bu kursun içeriği</h3>" +
      '<ul class="includes-grid">' +
      (counts.sections
        ? '<li><i class="fa-solid fa-layer-group"></i> ' +
          counts.sections +
          " bölüm</li>"
        : "") +
      (counts.lessons
        ? '<li><i class="fa-solid fa-clapperboard"></i> ' +
          counts.lessons +
          " ders</li>"
        : "") +
      (sureLabel
        ? '<li><i class="fa-regular fa-clock"></i> ' +
          esc(sureLabel) +
          " toplam süre</li>"
        : "") +
      (item.video
        ? '<li><i class="fa-solid fa-circle-play"></i> Tanıtım videosu</li>'
        : "") +
      '<li><i class="fa-solid fa-mobile-screen"></i> Her cihazdan erişim</li>' +
      '<li><i class="fa-solid fa-certificate"></i> Katılım sertifikası</li>' +
      "</ul></div>" +
      (aciklama
        ? '<div class="course-desc" id="courseDesc">' +
          "<h2>Açıklama</h2>" +
          '<div class="desc-clip is-collapsed" id="descClip">' +
          '<div class="desc-body">' +
          aciklama +
          (item.katilimNot
            ? '<p class="katilim-note"><i class="fa-solid fa-circle-info"></i> ' +
              esc(item.katilimNot) +
              "</p>"
            : "") +
          '</div><div class="desc-fade"></div></div>' +
          '<button type="button" class="btn-more" id="btnDescMore"><i class="fa-solid fa-chevron-down"></i> Daha Fazla</button>' +
          "</div>"
        : "") +
      (mufredat
        ? '<div class="course-curriculum">' +
          "<h2>Kurs içeriği</h2>" +
          '<div class="cur-meta-row">' +
          '<span class="cur-stats">' +
          esc(curStats) +
          "</span>" +
          '<button type="button" class="cur-expand-all" id="btnExpandAll">Tüm bölümleri genişlet</button>' +
          "</div>" +
          '<div class="cur-list accordion" id="curList">' +
          mufredat +
          "</div>" +
          (sureLabel
            ? '<p class="cur-total-duration"><i class="fa-regular fa-clock"></i> Toplam eğitim süresi: <strong>' +
              esc(sureLabel) +
              "</strong></p>"
            : "") +
          "</div>"
        : "") +
      "</div>" +
      '<aside class="course-lp-aside">' +
      '<div class="preview-card">' +
      previewThumb +
      '<div class="preview-card-body">' +
      priceHtml +
      buyHtml +
      '<ul class="preview-highlights">' +
      (item.egitimTuru
        ? "<li><i class=\"fa-solid fa-video\"></i> " + esc(item.egitimTuru) + "</li>"
        : "") +
      (sureLabel
        ? "<li><i class=\"fa-regular fa-clock\"></i> " + esc(sureLabel) + " toplam süre</li>"
        : "") +
      "<li><i class=\"fa-solid fa-infinity\"></i> Kayıt sonrası erişim</li>" +
      "<li><i class=\"fa-solid fa-laptop\"></i> Her cihazdan erişim</li>" +
      "</ul>" +
      tarihBlok +
      gift +
      "</div></div></aside></div></div>" +
      (hasVideo
        ? '<div class="course-modal" id="coursePreviewModal" hidden>' +
          '<div class="course-modal-backdrop" data-close-preview></div>' +
          '<div class="course-modal-dialog" role="dialog" aria-modal="true" aria-label="Kurs önizlemesi">' +
          '<div class="course-modal-top">' +
          "<div><span class=\"course-modal-label\">Kurs Önizlemesi</span>" +
          "<h2>" +
          esc(item.baslik) +
          "</h2></div>" +
          '<button type="button" class="course-modal-close" data-close-preview aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>' +
          "</div>" +
          '<div class="course-modal-player">' +
          '<video id="coursePreviewVideo" controls playsinline preload="metadata"' +
          (poster ? ' poster="' + esc(poster) + '"' : "") +
          ">" +
          '<source src="' +
          esc(item.video) +
          '" type="video/mp4">' +
          "</video></div>" +
          '<div class="course-modal-foot">' +
          '<div class="course-modal-item">' +
          (poster
            ? '<img src="' + esc(poster) + '" alt="">'
            : '<span class="thumb-dot"></span>') +
          "<div><strong>Tanıtım videosu</strong><span>Ücretsiz önizleme</span></div>" +
          "</div></div></div></div>"
        : "") +
      "</section>";

    wireCoursePreview(el);
    wireCourseDesc(el);
    wireCurriculumExpand(el);
    wireCourseCart(el, item);
  }

  function wireCourseCart(el, item) {
    function showOwned() {
      if (!window.BM_CART || !BM_CART.owns(item.id)) return false;
      var stack = el.querySelector(".buy-stack");
      if (stack) {
        stack.innerHTML =
          '<a href="ogrenci/kurs.php?id=' +
          encodeURIComponent(item.id) +
          '" class="btn-buy btn-buy-now">Eğitime git</a>';
      }
      return true;
    }
    if (showOwned()) return;
    document.addEventListener("bm:owned", function () {
      showOwned();
    });
    var btn = el.querySelector("[data-add-cart]");
    if (!btn || !item || !item.fiyat) return;
    btn.addEventListener("click", function () {
      var cart = window.BM_CART;
      if (!cart) return;
      if (cart.owns(item.id)) {
        showOwned();
        cart.toast("Bu eğitime zaten erişiminiz var", "ogrenci/index.php", "Kurslarım");
        return;
      }
      cart.add({
        id: item.id,
        title: item.baslik,
        price: item.fiyat,
        image: item.gorsel || "",
        href: "egitim-detay.html?id=" + encodeURIComponent(item.id),
      });
      btn.textContent = "Sepette";
      cart.toast("Eğitim sepete eklendi", cart.panelUrl(), "Sepete git");
    });
  }

  function renderInstructorProfile(el, instructorId) {
    var ins = findInstructorById(instructorId);
    if (!ins) {
      el.innerHTML =
        '<div class="ip-empty">' +
        "<h2>Eğitmen bulunamadı</h2>" +
        "<p>Aradığınız eğitmen kaydı yok.</p>" +
        '<a href="egitimler.html" class="btn btn-primary">Eğitimlere Dön</a></div>';
      return;
    }

    document.title = ins.name + " - BM Capital Akademi";
    setName("description", ins.bio || (ins.name + " eğitmen profili"));
    setCanonical();

    var photoHtml = ins.photo
      ? '<button type="button" class="ip-photo-btn" id="btnIpPhoto" aria-label="Fotoğrafı büyüt">' +
        '<img class="ip-photo" src="' +
        esc(ins.photo) +
        '" alt="' +
        esc(ins.name) +
        '"></button>'
      : '<span class="ip-photo ip-photo-fallback">' + esc(ins.avatar) + "</span>";

    var socialHtml = (ins.links || [])
      .map(function (l) {
        return (
          '<a href="' +
          esc(l.href) +
          '" target="_blank" rel="noopener" class="ip-social" title="' +
          esc(l.label) +
          '" aria-label="' +
          esc(l.label) +
          '"><i class="' +
          esc(l.icon) +
          '"></i></a>'
        );
      })
      .join("");

    var courses = coursesForInstructor(ins.id);
    var courseHtml = courses.length
      ? courses
          .map(function (c) {
            var thumb = c.gorsel || c.videoPoster || "";
            var href = "egitim-detay.html?id=" + encodeURIComponent(c.id);
            var desc = c.kisaAciklama || (c.aciklama && c.aciklama[0]) || "";
            return (
              '<a class="ip-course" href="' +
              href +
              '">' +
              (thumb
                ? '<img class="ip-course-thumb" src="' + esc(thumb) + '" alt="">'
                : '<span class="ip-course-thumb ip-course-thumb-empty"><i class="fa-solid fa-graduation-cap"></i></span>') +
              '<div class="ip-course-body"><strong>' +
              esc(c.baslik || c.title || "Eğitim") +
              "</strong>" +
              (desc ? "<p>" + esc(desc) + "</p>" : "") +
              "</div></a>"
            );
          })
          .join("")
      : '<p class="ip-muted">Bu eğitmene ait yayınlanmış eğitim bulunamadı.</p>';

    var bio = String(ins.bio || "").trim();
    var bioHtml = bio
      ? bio
          .split(/\n+/)
          .filter(Boolean)
          .map(function (p) {
            return "<p>" + esc(p) + "</p>";
          })
          .join("")
      : "<p>Henüz açıklama eklenmemiş.</p>";

    el.innerHTML =
      '<div class="ip-layout">' +
      '<div class="ip-main">' +
      '<nav class="ip-breadcrumb" aria-label="breadcrumb">' +
      '<a href="index.html">Anasayfa</a><span>/</span><span>' +
      esc(ins.name) +
      "</span></nav>" +
      '<h1 class="ip-name">' +
      esc(ins.name) +
      "</h1>" +
      (ins.title ? '<p class="ip-title">' + esc(ins.title) + "</p>" : "") +
      '<section class="ip-section">' +
      '<h2>Açıklama</h2>' +
      '<div class="ip-desc is-collapsed" id="ipDescClip"><div class="desc-body">' +
      bioHtml +
      "</div></div>" +
      '<button type="button" class="ip-more-btn" id="btnIpDescMore">' +
      '<i class="fa-solid fa-chevron-down"></i> Daha Fazla</button>' +
      "</section>" +
      '<section class="ip-section">' +
      "<h2>Eğitimler</h2>" +
      '<div class="ip-courses">' +
      courseHtml +
      "</div></section>" +
      "</div>" +
      '<aside class="ip-aside">' +
      photoHtml +
      (socialHtml ? '<div class="ip-socials">' + socialHtml + "</div>" : "") +
      '<button type="button" class="ip-share-btn" id="btnIpShare">' +
      '<i class="fa-solid fa-share-nodes"></i> Paylaş</button>' +
      "</aside></div>";

    wireInstructorDesc(el);
    wireInstructorShare(el, ins);
    wireInstructorPhoto(el, ins);
  }

  function wireInstructorPhoto(root, ins) {
    var btn = root.querySelector("#btnIpPhoto");
    if (!btn || !ins.photo) return;
    btn.addEventListener("click", function () {
      var old = document.getElementById("ipPhotoLightbox");
      if (old) old.remove();
      var box = document.createElement("div");
      box.id = "ipPhotoLightbox";
      box.className = "ip-lightbox";
      box.innerHTML =
        '<div class="ip-lightbox-card" role="dialog" aria-modal="true" aria-label="Profil fotoğrafı">' +
        '<button type="button" class="ip-lightbox-close" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>' +
        '<img src="' +
        esc(ins.photo) +
        '" alt="' +
        esc(ins.name) +
        '">' +
        "</div>";
      document.body.appendChild(box);
      document.body.classList.add("modal-open");
      function close() {
        box.remove();
        document.body.classList.remove("modal-open");
        document.removeEventListener("keydown", onKey);
      }
      function onKey(e) {
        if (e.key === "Escape") close();
      }
      box.addEventListener("click", function (e) {
        if (e.target === box || e.target.closest(".ip-lightbox-close")) close();
      });
      document.addEventListener("keydown", onKey);
    });
  }

  function wireInstructorDesc(root) {
    var clip = root.querySelector("#ipDescClip");
    var btn = root.querySelector("#btnIpDescMore");
    if (!clip || !btn) return;
    var body = clip.querySelector(".desc-body");
    if (body && body.scrollHeight <= 180) {
      clip.classList.remove("is-collapsed");
      btn.hidden = true;
      return;
    }
    btn.addEventListener("click", function () {
      clip.classList.toggle("is-collapsed");
      var collapsed = clip.classList.contains("is-collapsed");
      btn.innerHTML = collapsed
        ? '<i class="fa-solid fa-chevron-down"></i> Daha Fazla'
        : '<i class="fa-solid fa-chevron-up"></i> Daha Az';
    });
  }

  function wireInstructorShare(root, ins) {
    var btn = root.querySelector("#btnIpShare");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var url = location.href;
      var title = ins.name + " - BM Capital Akademi";
      if (navigator.share) {
        navigator.share({ title: title, url: url }).catch(function () {});
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(
          function () {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Kopyalandı';
            setTimeout(function () {
              btn.innerHTML = '<i class="fa-solid fa-share-nodes"></i> Paylaş';
            }, 1800);
          },
          function () {
            window.prompt("Bağlantıyı kopyalayın:", url);
          }
        );
      } else {
        window.prompt("Bağlantıyı kopyalayın:", url);
      }
    });
  }

  function wireCourseDesc(root) {
    var clip = root.querySelector("#descClip");
    var btn = root.querySelector("#btnDescMore");
    if (!clip || !btn) return;
    var body = clip.querySelector(".desc-body");
    if (body && body.scrollHeight <= 220) {
      clip.classList.remove("is-collapsed");
      btn.hidden = true;
      return;
    }
    btn.addEventListener("click", function () {
      clip.classList.toggle("is-collapsed");
      var collapsed = clip.classList.contains("is-collapsed");
      btn.innerHTML = collapsed
        ? '<i class="fa-solid fa-chevron-down"></i> Daha Fazla'
        : '<i class="fa-solid fa-chevron-up"></i> Daha Az';
    });
  }

  function wireCurriculumExpand(root) {
    var btn = root.querySelector("#btnExpandAll");
    var list = root.querySelector("#curList");
    if (!btn || !list) return;
    var expanded = false;
    btn.addEventListener("click", function () {
      expanded = !expanded;
      list.querySelectorAll(".acc-header").forEach(function (h) {
        h.setAttribute("aria-expanded", expanded ? "true" : "false");
        var panel = h.nextElementSibling;
        if (panel) panel.classList.toggle("open", expanded);
      });
      btn.textContent = expanded
        ? "Tüm bölümleri daralt"
        : "Tüm bölümleri genişlet";
    });
  }

  function wireCoursePreview(root) {
    var modal = root.querySelector("#coursePreviewModal");
    var openBtn = root.querySelector("#btnOpenPreview");
    var video = root.querySelector("#coursePreviewVideo");
    if (!modal || !openBtn || !video) return;

    function openModal() {
      modal.hidden = false;
      document.body.classList.add("modal-open");
      try {
        video.currentTime = 0;
        var p = video.play();
        if (p && p.catch) p.catch(function () {});
      } catch (e) {}
    }
    function closeModal() {
      modal.hidden = true;
      document.body.classList.remove("modal-open");
      try {
        video.pause();
      } catch (e) {}
    }

    openBtn.addEventListener("click", openModal);
    modal.querySelectorAll("[data-close-preview]").forEach(function (btn) {
      btn.addEventListener("click", closeModal);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hidden) closeModal();
    });
  }

  function renderDetailUrun(el, item) {
    if (!item) { el.innerHTML = notFound(); return; }
    applyDetailMeta(item);
    addProductSchema(item);

    var ozellikler = (item.ozellikler || []).map(function (o) {
      return '<li><i class="fa-solid fa-circle-check"></i>' + esc(o) + "</li>";
    }).join("");
    var aciklama = (item.aciklama || []).map(function (p) { return "<p>" + esc(p) + "</p>"; }).join("");

    var priceBox = !item.fiyat
      ? ""
      : '<div class="price-box"><div class="p">' + esc(item.fiyat) + "</div>" +
        (item.fiyatNot ? "<small>" + esc(item.fiyatNot) + "</small>" : "") + "</div>";

    var video = item.video
      ? '<div class="video-wrap"><video controls preload="metadata" playsinline' +
        (item.videoPoster ? ' poster="' + esc(item.videoPoster) + '"' : "") +
        '><source src="' + esc(item.video) + '" type="video/mp4">Tarayıcınız videoyu desteklemiyor.</video></div>'
      : "";

    el.innerHTML =
      '<div class="detail-grid">' +
      '<div class="detail-main">' +
      video +
      "<h2>Ürün Hakkında</h2>" + aciklama +
      (ozellikler ? "<h3>Öne Çıkan Özellikler</h3><ul class=\"check-list\">" + ozellikler + "</ul>" : "") +
      "</div>" +
      '<aside><div class="side-card">' +
      '<div class="meta-box">' +
      (item.etiket ? '<div class="meta-item"><strong>KATEGORİ:</strong> ' + esc(item.etiket) + "</div>" : "") +
      "</div>" + priceBox +
      '<a href="' + window.BM_HELPERS.waLink + '" target="_blank" rel="noopener" class="btn btn-primary btn-block btn-lg"><i class="fa-brands fa-whatsapp"></i> Bilgi Al</a>' +
      '<a href="iletisim.html" class="btn btn-outline btn-block" style="margin-top:12px;">İletişim Formu</a>' +
      "</div></aside></div>";
  }

  function notFound() {
    return '<div style="text-align:center;padding:60px 0;"><h2 style="color:#2c3e50;">İçerik bulunamadı</h2>' +
      '<p style="color:#5b6572;margin:12px 0 24px;">Aradığınız kayıt mevcut değil.</p>' +
      '<a href="egitimler.html" class="btn btn-primary">Eğitimlere Dön</a></div>';
  }

  function findById(id) {
    return window.BM_DATA.tumModuller().filter(function (x) { return x.id === id; })[0];
  }

  function renderFaqs(el, list) {
    if (!list || !list.length) {
      el.innerHTML = '<p style="color:#5b6572;">Henüz soru eklenmemiş.</p>';
      return;
    }
    el.classList.add("accordion");
    el.innerHTML = list.map(function (f) {
      return (
        '<div class="acc-item"><button class="acc-header" aria-expanded="false">' +
        '<span class="acc-icon">+</span><span class="acc-title">' + esc(f.soru) + "</span></button>" +
        '<div class="acc-panel"><p>' + esc(f.cevap) + "</p></div></div>"
      );
    }).join("");
    addFaqSchema(list);
  }

  window.BM_CATALOG = {
    init: function () {
      var D = window.BM_DATA;
      if (!D) return;

      document.querySelectorAll("[data-catalog]").forEach(function (el) {
        var kind = el.getAttribute("data-catalog");
        if (kind === "egitim") renderCards(el, D.egitimler);
        else if (kind === "urun") renderCards(el, D.urunler);
        else if (kind === "one-cikan") {
          var list = D.tumModuller().filter(function (x) { return x.oneCikan; });
          renderCards(el, list);
        }
      });

      var de = document.querySelector('[data-detail="egitim"]');
      if (de) renderDetailEgitim(de, findById(param("id")));
      var du = document.querySelector('[data-detail="urun"]');
      if (du) renderDetailUrun(du, findById(param("id")));

      var ip = document.querySelector("[data-instructor-profile]");
      if (ip) renderInstructorProfile(ip, param("id") || "kamil-bilen");

      var faq = document.querySelector("[data-faqs]");
      if (faq) renderFaqs(faq, D.sss || []);
    },
  };
})();
