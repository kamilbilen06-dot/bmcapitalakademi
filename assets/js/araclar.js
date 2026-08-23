/**
 * BM Capital Akademi — Borsa Araçları hesap motoru
 * Sayfada: <div data-tool="temettu"> ... </div>
 */
(function () {
  "use strict";

  function num(el) {
    if (!el) return 0;
    var v = parseFloat(String(el.value).replace(",", "."));
    return isFinite(v) ? v : 0;
  }

  function fmtMoney(n) {
    if (!isFinite(n)) return "—";
    return (
      n.toLocaleString("tr-TR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }) + " TL"
    );
  }

  function fmtPct(n) {
    if (!isFinite(n)) return "—";
    return (
      "%" +
      n.toLocaleString("tr-TR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function fmtNum(n, d) {
    if (!isFinite(n)) return "—";
    return n.toLocaleString("tr-TR", {
      minimumFractionDigits: d == null ? 0 : d,
      maximumFractionDigits: d == null ? 2 : d,
    });
  }

  function set(id, text, colorClass) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.className = "calc-stat-value" + (colorClass ? " " + colorClass : "");
    var card = el.closest(".calc-stat");
    if (card) {
      card.classList.remove("is-pulse");
      void card.offsetWidth;
      card.classList.add("is-pulse");
    }
  }

  function bindRange(inputId, rangeId) {
    var inp = document.getElementById(inputId);
    var rng = document.getElementById(rangeId);
    if (!inp || !rng) return;
    var sync = function (from, to) {
      to.value = from.value;
    };
    inp.addEventListener("input", function () {
      sync(inp, rng);
    });
    rng.addEventListener("input", function () {
      sync(rng, inp);
    });
  }

  function onChange(root, fn) {
    root.addEventListener("input", fn);
    root.addEventListener("change", fn);
    fn();
  }

  function updateBrutLabel() {
    var tip = document.getElementById("dagitim");
    var label = document.getElementById("brut_label");
    var unit = document.getElementById("brut_unit");
    if (!tip || !label) return;
    if (tip.value === "oran") {
      label.textContent = "Nominal Oran";
      if (unit) unit.textContent = "%";
    } else {
      label.textContent = "Hisse Başına Brüt Temettü";
      if (unit) unit.textContent = "TL";
    }
  }

  /* ---- Tools ---- */
  function temettu(root) {
    bindRange("lot", "lot_r");
    bindRange("brut", "brut_r");
    bindRange("fiyat", "fiyat_r");
    updateBrutLabel();
    onChange(root, function () {
      updateBrutLabel();
      var lot = num(document.getElementById("lot"));
      var tip = document.getElementById("dagitim");
      var brutRaw = num(document.getElementById("brut"));
      var brutPs = tip && tip.value === "oran" ? brutRaw / 100 : brutRaw;
      var fiyat = num(document.getElementById("fiyat"));
      var stopajOran = 0.1;
      var brut = lot * brutPs;
      var stopaj = brut * stopajOran;
      var net = brut - stopaj;
      var verim = fiyat > 0 ? (brutPs / fiyat) * 100 : NaN;
      set("out_brut", fmtMoney(brut), "gold");
      set("out_stopaj", "−" + fmtMoney(stopaj).replace(" TL", "") + " TL", "red");
      set("out_net", fmtMoney(net), "green");
      set("out_verim", fmtPct(verim), "green");
    });
  }

  function karZarar(root) {
    bindRange("lot", "lot_r");
    bindRange("alis", "alis_r");
    bindRange("satis", "satis_r");
    onChange(root, function () {
      var lot = num(document.getElementById("lot"));
      var alis = num(document.getElementById("alis"));
      var satis = num(document.getElementById("satis"));
      var kAlis = num(document.getElementById("kom_alis"));
      var kSatis = num(document.getElementById("kom_satis"));
      var maliyet = alis * lot + kAlis;
      var gelir = satis * lot - kSatis;
      var net = gelir - maliyet;
      var getiri = maliyet > 0 ? (net / maliyet) * 100 : NaN;
      set("out_maliyet", fmtMoney(maliyet), "navy");
      set("out_gelir", fmtMoney(gelir), "gold");
      set("out_net", fmtMoney(net), net >= 0 ? "green" : "red");
      set("out_getiri", fmtPct(getiri), net >= 0 ? "green" : "red");
    });
  }

  function maliyet(root) {
    bindRange("lot1", "lot1_r");
    bindRange("fiyat1", "fiyat1_r");
    bindRange("lot2", "lot2_r");
    bindRange("fiyat2", "fiyat2_r");
    onChange(root, function () {
      var l1 = num(document.getElementById("lot1"));
      var f1 = num(document.getElementById("fiyat1"));
      var l2 = num(document.getElementById("lot2"));
      var f2 = num(document.getElementById("fiyat2"));
      var totL = l1 + l2;
      var totC = l1 * f1 + l2 * f2;
      var avg = totL > 0 ? totC / totL : NaN;
      set("out_lot", fmtNum(totL, 0), "navy");
      set("out_maliyet", fmtMoney(totC), "gold");
      set("out_ortalama", fmtMoney(avg), "green");
      var dusus = f1 > 0 && isFinite(avg) ? ((f1 - avg) / f1) * 100 : NaN;
      set("out_dusus", fmtPct(dusus), dusus >= 0 ? "green" : "red");
    });
  }

  function bedelli(root) {
    bindRange("lot", "lot_r");
    bindRange("fiyat", "fiyat_r");
    bindRange("oran", "oran_r");
    bindRange("bedel", "bedel_r");
    onChange(root, function () {
      var lot = num(document.getElementById("lot"));
      var fiyat = num(document.getElementById("fiyat"));
      var oranPct = num(document.getElementById("oran"));
      var bedel = num(document.getElementById("bedel"));
      var oran = oranPct / 100;
      var yeniHisse = lot * oran;
      var ruchan = yeniHisse * bedel;
      var yeniLot = lot + yeniHisse;
      var teorik = 1 + oran > 0 ? (fiyat + oran * bedel) / (1 + oran) : NaN;
      set("out_ruchan", fmtMoney(ruchan), "gold");
      set("out_yeni_hisse", fmtNum(yeniHisse, 2), "navy");
      set("out_yeni_lot", fmtNum(yeniLot, 2), "green");
      set("out_teorik", fmtMoney(teorik), "green");
    });
  }

  function bedelsiz(root) {
    bindRange("lot", "lot_r");
    bindRange("fiyat", "fiyat_r");
    bindRange("oran", "oran_r");
    onChange(root, function () {
      var lot = num(document.getElementById("lot"));
      var fiyat = num(document.getElementById("fiyat"));
      var oran = num(document.getElementById("oran")) / 100;
      var yeniLot = lot * (1 + oran);
      var bedelsizAdet = lot * oran;
      var teorik = 1 + oran > 0 ? fiyat / (1 + oran) : NaN;
      set("out_bedelsiz", fmtNum(bedelsizAdet, 2), "gold");
      set("out_yeni_lot", fmtNum(yeniLot, 2), "green");
      set("out_teorik", fmtMoney(teorik), "green");
      set("out_carpan", fmtNum(1 + oran, 2) + "×", "navy");
    });
  }

  function bilesik(root) {
    bindRange("anapara", "anapara_r");
    bindRange("oran", "oran_r");
    bindRange("yil", "yil_r");
    onChange(root, function () {
      var anapara = num(document.getElementById("anapara"));
      var oran = num(document.getElementById("oran")) / 100;
      var yil = Math.min(50, Math.max(1, Math.floor(num(document.getElementById("yil")) || 1)));
      var rows = "";
      var bal = anapara;
      for (var i = 1; i <= yil; i++) {
        bal = bal * (1 + oran);
        rows +=
          "<tr><td>" +
          i +
          ". yıl</td><td>" +
          fmtMoney(bal) +
          "</td><td>" +
          fmtMoney(bal - anapara) +
          "</td></tr>";
      }
      set("out_son", fmtMoney(bal), "green");
      set("out_kazanc", fmtMoney(bal - anapara), bal >= anapara ? "green" : "red");
      set("out_kat", fmtNum(anapara > 0 ? bal / anapara : NaN, 2) + "×", "gold");
      set("out_yil", String(yil) + " yıl", "navy");
      var tb = document.getElementById("out_tablo");
      if (tb) tb.innerHTML = rows;
    });
  }

  function tavan(root) {
    bindRange("fiyat", "fiyat_r");
    bindRange("lot", "lot_r");
    bindRange("gun", "gun_r");
    onChange(root, function () {
      var fiyat = num(document.getElementById("fiyat"));
      var lot = num(document.getElementById("lot"));
      var gun = Math.min(30, Math.max(1, Math.floor(num(document.getElementById("gun")) || 1)));
      var rows = "";
      var p = fiyat;
      var baslangic = fiyat * lot;
      for (var i = 1; i <= gun; i++) {
        p = p * 1.1;
        var val = p * lot;
        rows +=
          "<tr><td>" +
          i +
          ". gün</td><td>" +
          fmtMoney(p) +
          "</td><td>" +
          fmtMoney(val) +
          "</td></tr>";
      }
      set("out_son_fiyat", fmtMoney(p), "gold");
      set("out_son_deger", fmtMoney(p * lot), "green");
      set("out_kat", fmtNum(baslangic > 0 ? (p * lot) / baslangic : NaN, 2) + "×", "green");
      set("out_gun", String(gun) + " gün", "navy");
      var tb = document.getElementById("out_tablo");
      if (tb) tb.innerHTML = rows;
    });
  }

  function monthRate(annual) {
    return Math.pow(1 + annual, 1 / 12) - 1;
  }

  function parseTr(raw) {
    if (raw == null) return 0;
    var s = String(raw).trim().replace(/\s/g, "");
    if (!s) return 0;
    if (s.indexOf(",") >= 0) {
      s = s.replace(/\./g, "").replace(",", ".");
    } else if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
      s = s.replace(/\./g, "");
    } else {
      s = s.replace(/\./g, "").replace(",", ".");
    }
    var v = parseFloat(s);
    return isFinite(v) ? v : 0;
  }

  function moneyVal(id) {
    var el = document.getElementById(id);
    return el ? parseTr(el.value) : 0;
  }

  function formatTrInt(n) {
    if (!isFinite(n)) return "";
    return Math.round(n).toLocaleString("tr-TR");
  }

  function formatTrDec(n, d) {
    if (!isFinite(n)) return "";
    return n.toLocaleString("tr-TR", {
      minimumFractionDigits: d == null ? 2 : d,
      maximumFractionDigits: d == null ? 2 : d,
    });
  }

  function bindMoneyInputs(root) {
    root.querySelectorAll("[data-money]").forEach(function (el) {
      el.addEventListener("blur", function () {
        var v = parseTr(el.value);
        el.value = v ? formatTrInt(v) : "";
      });
      el.addEventListener("focus", function () {
        var v = parseTr(el.value);
        if (v) el.value = String(Math.round(v));
      });
    });
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
  }

  function onCalculate(root, fn) {
    var btn = root.querySelector("[data-calc-btn]");
    var panel = root.querySelector("[data-calc-results]");
    var empty = root.querySelector("[data-calc-empty]");
    if (panel) {
      panel.classList.remove("is-open");
      panel.setAttribute("hidden", "");
    }
    if (empty) empty.hidden = false;
    if (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        fn();
        if (empty) empty.hidden = true;
        if (panel) {
          panel.removeAttribute("hidden");
          panel.classList.add("is-open");
          panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
      });
    }
  }

  function annuityPaymentMonthly(principal, monthlyRate, months) {
    if (principal <= 0 || months <= 0) return NaN;
    if (monthlyRate <= 0) return principal / months;
    var r = monthlyRate;
    var f = Math.pow(1 + r, months);
    return (principal * r * f) / (f - 1);
  }

  function sumPvAnnuity(payment, rMonth, from, to) {
    var s = 0;
    for (var t = from; t <= to; t++) {
      s += payment / Math.pow(1 + rMonth, t);
    }
    return s;
  }

  function sumPvGrowingRent(emsal, enfAnnual, rMonth, from, to) {
    var s = 0;
    for (var t = from; t <= to; t++) {
      var kira = emsal * Math.pow(1 + enfAnnual, t / 12);
      s += kira / Math.pow(1 + rMonth, t);
    }
    return s;
  }

  function bindMoneyRange(inputId, rangeId) {
    var inp = document.getElementById(inputId);
    var rng = document.getElementById(rangeId);
    if (!inp || !rng) return;
    var fromRange = function () {
      inp.value = formatTrInt(parseTr(rng.value));
    };
    var fromInput = function () {
      var v = parseTr(inp.value);
      if (v < parseTr(rng.min)) v = parseTr(rng.min);
      if (v > parseTr(rng.max)) v = parseTr(rng.max);
      rng.value = String(Math.round(v));
      inp.value = formatTrInt(v);
    };
    rng.addEventListener("input", fromRange);
    inp.addEventListener("change", fromInput);
    inp.addEventListener("blur", fromInput);
  }

  function annuityPaymentAnnual(principal, annualRate, months) {
    if (principal <= 0 || months <= 0) return NaN;
    if (annualRate <= 0) return principal / months;
    var r = annualRate / 12;
    var f = Math.pow(1 + r, months);
    return (principal * r * f) / (f - 1);
  }

  /* ---- KK vs TF hesap motoru (gömülü; ayrı dosya şart değil) ---- */
  var KkVsTfCalc = (function () {
    var moneyFmt = new Intl.NumberFormat("tr-TR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    var intFmt = new Intl.NumberFormat("tr-TR", { maximumFractionDigits: 0 });

    function formatMoney(n) {
      if (!isFinite(n)) return "—";
      return moneyFmt.format(n) + " TL";
    }
    function formatInt(n) {
      if (!isFinite(n)) return "—";
      return intFmt.format(Math.round(n)) + " TL";
    }

    function calculateTotalRent(startRent, annualIncreaseRate, months) {
      var m = Math.max(0, Math.floor(months || 0));
      var rent = Math.max(0, startRent || 0);
      var g = Math.max(0, annualIncreaseRate || 0);
      if (m <= 0 || rent <= 0) return 0;
      var total = 0;
      var current = rent;
      for (var t = 1; t <= m; t++) {
        total += current;
        if (t % 12 === 0 && t < m) current = current * (1 + g);
      }
      return total;
    }

    function calculateMonthlyAnnuity(principal, monthlyRate, months) {
      var P = Math.max(0, principal || 0);
      var n = Math.max(1, Math.floor(months || 1));
      var r = monthlyRate || 0;
      if (P <= 0) return 0;
      if (r <= 0) return P / n;
      var f = Math.pow(1 + r, n);
      return (P * r * f) / (f - 1);
    }

    function calculateTasarrufFinansmani(input) {
      var konutBugun = Math.max(0, input.konutBugun || 0);
      var pesinat = Math.max(0, input.pesinat || 0);
      var vade = Math.max(1, Math.floor(input.vade || 1));
      var teslimAy = Math.max(0, Math.floor(input.teslimAy || 0));
      var hizmetOrani = Math.max(0, input.hizmetOrani || 0);
      var konutArtis = Math.max(0, input.konutArtis || 0);
      var baslangicKira = Math.max(0, input.baslangicKira || 0);
      var kiraArtis = Math.max(0, input.kiraArtis || 0);

      var konutTeslim = konutBugun * Math.pow(1 + konutArtis, teslimAy / 12);
      var hizmetBedeli = Math.max(0, (konutTeslim - pesinat) * hizmetOrani);
      var gercekPesinat = pesinat - hizmetBedeli;
      var finansman = konutTeslim - gercekPesinat;
      if (finansman < 0) finansman = 0;
      var aylikTF = finansman / vade;
      var toplamKira = calculateTotalRent(baslangicKira, kiraArtis, teslimAy);
      var toplamNominal = pesinat + finansman + toplamKira;

      return {
        konutBugun: konutBugun,
        konutTeslim: konutTeslim,
        pesinat: pesinat,
        hizmetBedeli: hizmetBedeli,
        gercekPesinat: gercekPesinat,
        finansman: finansman,
        aylikOdeme: aylikTF,
        toplamGeriOdeme: finansman,
        toplamFaizVeyaHizmet: hizmetBedeli,
        toplamKira: toplamKira,
        toplamNominal: toplamNominal,
        vade: vade,
        teslimAy: teslimAy,
      };
    }

    function calculateKonutKredisi(input) {
      var konutBugun = Math.max(0, input.konutBugun || 0);
      var pesinat = Math.max(0, input.pesinat || 0);
      var vade = Math.max(1, Math.floor(input.vade || 1));
      var faizAylik = Math.max(0, input.faizAylik || 0);
      var krediTutari = Math.max(0, konutBugun - pesinat);
      var aylikTaksit = calculateMonthlyAnnuity(krediTutari, faizAylik, vade);
      var toplamKK = aylikTaksit * vade;
      var toplamFaiz = Math.max(0, toplamKK - krediTutari);
      return {
        konutBugun: konutBugun,
        konutTeslim: konutBugun,
        pesinat: pesinat,
        hizmetBedeli: 0,
        gercekPesinat: pesinat,
        finansman: krediTutari,
        aylikOdeme: aylikTaksit,
        toplamGeriOdeme: toplamKK,
        toplamFaizVeyaHizmet: toplamFaiz,
        toplamKira: 0,
        toplamNominal: toplamKK + pesinat,
        vade: vade,
        teslimAy: 0,
      };
    }

    function compare(input) {
      var tf = calculateTasarrufFinansmani(input);
      var kk = calculateKonutKredisi(input);
      var fark = tf.toplamNominal - kk.toplamNominal;
      var kazanan = Math.abs(fark) < 1 ? "eşit" : fark > 0 ? "kredi" : "tasarruf";
      return { tf: tf, kk: kk, fark: fark, kazanan: kazanan };
    }

    return {
      formatMoney: formatMoney,
      formatInt: formatInt,
      calculateTotalRent: calculateTotalRent,
      calculateTasarrufFinansmani: calculateTasarrufFinansmani,
      calculateKonutKredisi: calculateKonutKredisi,
      compare: compare,
    };
  })();

  /* ---- Konut Kredisi vs Tasarruf Finansmanı (nominal maliyet) ---- */
  function kkVsTk(root) {
    var Calc = window.KkVsTfCalc || KkVsTfCalc;

    bindMoneyInputs(root);
    [
      ["konut_bugun", "konut_bugun_r"],
      ["pesinat", "pesinat_r"],
      ["kira", "kira_r"],
    ].forEach(function (p) {
      bindMoneyRange(p[0], p[1]);
    });
    ["vade", "teslim", "hizmet_pct", "konut_artis", "kira_artis", "faiz_aylik"].forEach(function (id) {
      bindRange(id, id + "_r");
    });

    function readInput() {
      return {
        konutBugun: Math.max(0, moneyVal("konut_bugun")),
        pesinat: Math.max(0, moneyVal("pesinat")),
        vade: Math.max(1, Math.floor(num(document.getElementById("vade")) || 1)),
        teslimAy: Math.max(0, Math.floor(num(document.getElementById("teslim")) || 0)),
        hizmetOrani: Math.max(0, num(document.getElementById("hizmet_pct")) / 100),
        konutArtis: Math.max(0, num(document.getElementById("konut_artis")) / 100),
        baslangicKira: Math.max(0, moneyVal("kira")),
        kiraArtis: Math.max(0, num(document.getElementById("kira_artis")) / 100),
        faizAylik: Math.max(0, num(document.getElementById("faiz_aylik")) / 100),
      };
    }

    function render() {
      var result = Calc.compare(readInput());
      var tf = result.tf;
      var kk = result.kk;
      var M = Calc.formatMoney;

      setText("out_nom_tf", M(tf.toplamNominal));
      setText("out_nom_kk", M(kk.toplamNominal));
      setText("out_fark", M(Math.abs(result.fark)));
      setText(
        "out_kazanan",
        result.kazanan === "eşit"
          ? "Neredeyse eşit"
          : result.kazanan === "kredi"
            ? "Konut Kredisi"
            : "Tasarruf Finansmanı"
      );

      var badgeTf = document.getElementById("badge_tf");
      var badgeKk = document.getElementById("badge_kk");
      if (badgeTf) {
        badgeTf.hidden = result.kazanan !== "tasarruf";
        badgeTf.classList.toggle("is-on", result.kazanan === "tasarruf");
      }
      if (badgeKk) {
        badgeKk.hidden = result.kazanan !== "kredi";
        badgeKk.classList.toggle("is-on", result.kazanan === "kredi");
      }
      var nTf = document.getElementById("out_nom_tf");
      var nKk = document.getElementById("out_nom_kk");
      if (nTf) nTf.classList.toggle("is-win", result.kazanan === "tasarruf");
      if (nKk) nKk.classList.toggle("is-win", result.kazanan === "kredi");

      setText("c_bugun_tf", M(tf.konutBugun));
      setText("c_bugun_kk", M(kk.konutBugun));
      setText("c_teslim_tf", M(tf.konutTeslim));
      setText("c_teslim_kk", M(kk.konutTeslim));
      setText("c_nakit_tf", M(tf.pesinat));
      setText("c_nakit_kk", M(kk.pesinat));
      setText("c_hizmet_tf", M(tf.hizmetBedeli));
      setText("c_hizmet_kk", "—");
      setText("c_gercek_tf", M(tf.gercekPesinat));
      setText("c_gercek_kk", M(kk.gercekPesinat));
      setText("c_fin_tf", M(tf.finansman));
      setText("c_fin_kk", M(kk.finansman));
      setText("c_aylik_tf", M(tf.aylikOdeme));
      setText("c_aylik_kk", M(kk.aylikOdeme));
      setText("c_geri_tf", M(tf.toplamGeriOdeme));
      setText("c_geri_kk", M(kk.toplamGeriOdeme));
      setText("c_faiz_tf", "—");
      setText("c_faiz_kk", M(kk.toplamFaizVeyaHizmet));
      setText("c_kira_tf", M(tf.toplamKira));
      setText("c_kira_kk", "0,00 TL");
      setText("c_nom_tf", M(tf.toplamNominal));
      setText("c_nom_kk", M(kk.toplamNominal));

      var empty = root.querySelector("[data-calc-empty]");
      var panel = root.querySelector("[data-calc-results]");
      var table = root.querySelector("[data-calc-table]");
      if (empty) empty.hidden = true;
      if (panel) {
        panel.removeAttribute("hidden");
        panel.classList.add("is-open");
      }
      if (table) {
        table.removeAttribute("hidden");
        table.classList.add("is-open");
      }
    }

    root.addEventListener("input", render);
    root.addEventListener("change", render);

    var btn = root.querySelector("[data-calc-btn]");
    if (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        render();
        var table = root.querySelector("[data-calc-table]");
        if (table) table.scrollIntoView({ behavior: "smooth", block: "nearest" });
      });
    }

    render();
  }

  /* ---- Konut Kredisi ---- */
  function konutKredisi(root) {
    bindMoneyInputs(root);
    bindMoneyRange("kredi", "kredi_r");
    bindRange("faiz", "faiz_r");
    bindRange("vade", "vade_r");
    bindMoneyRange("masraf", "masraf_r");
    onCalculate(root, function () {
      var kredi = Math.max(0, moneyVal("kredi"));
      var faizAylik = num(document.getElementById("faiz")) / 100;
      var vade = Math.max(1, Math.floor(num(document.getElementById("vade")) || 1));
      var masraf = Math.max(0, moneyVal("masraf"));
      var taksit = annuityPaymentMonthly(kredi, faizAylik, vade);
      var toplam = taksit * vade;
      var faizTop = Math.max(0, toplam - kredi);
      var ymo = (Math.pow(1 + faizAylik, 12) - 1) * 100;
      setText("out_taksit", fmtMoney(taksit));
      setText("out_kredi", fmtMoney(kredi));
      setText(
        "out_aylik_faiz",
        "%" + (faizAylik * 100).toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 4 })
      );
      setText("out_ymo", "%" + ymo.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 4 }));
      setText("out_faiz", fmtMoney(faizTop));
      setText("out_toplam", fmtMoney(toplam + masraf));
    });
  }

  /** TF (tekil araç): peşinat + taksitler ile finansmanın %45’i dolmadan teslim yok */
  function deliveryFrom45(finansman, taksit, vade, pesinat) {
    var v = Math.max(1, Math.floor(vade || 1));
    var down = Math.max(0, pesinat || 0);
    if (!(finansman > 0)) return v;
    var need = finansman * 0.45;
    var kalan = need - down;
    if (kalan <= 0) return 1;
    if (!(taksit > 0)) return v;
    var teslim = Math.ceil(kalan / taksit);
    if (teslim < 1) teslim = 1;
    if (teslim > v) teslim = v;
    return teslim;
  }

  /* ---- Tasarruf Finansmanı ---- */
  function tasarrufFinansman(root) {
    bindMoneyInputs(root);
    bindMoneyRange("finansman", "finansman_r");
    bindMoneyRange("pesinat", "pesinat_r");
    bindMoneyRange("taksit", "taksit_r");
    bindRange("vade", "vade_r");
    bindRange("hizmet_pct", "hizmet_pct_r");

    var syncLive = function () {
      var f = moneyVal("finansman");
      var p = moneyVal("pesinat");
      var pct = num(document.getElementById("hizmet_pct"));
      var tlEl = document.getElementById("hizmet_tl");
      if (tlEl) tlEl.textContent = "≈ " + formatTrInt(f * (pct / 100)) + " TL";
      var taksit = moneyVal("taksit");
      var vade = Math.max(1, Math.floor(num(document.getElementById("vade")) || 1));
      var teslim = deliveryFrom45(f, taksit, vade, p);
      setText("live_teslim", teslim + ". ay");
    };
    root.addEventListener("input", syncLive);
    root.addEventListener("change", syncLive);
    syncLive();

    onCalculate(root, function () {
      var finansman = Math.max(0, moneyVal("finansman"));
      var pesinat = Math.max(0, moneyVal("pesinat"));
      var taksit = Math.max(0, moneyVal("taksit"));
      var vade = Math.max(1, Math.floor(num(document.getElementById("vade")) || 1));
      var hizmetPct = num(document.getElementById("hizmet_pct")) / 100;
      var org = finansman * hizmetPct;
      var teslim = deliveryFrom45(finansman, taksit, vade, pesinat);
      var esik = finansman * 0.45;
      var toplam = pesinat + taksit * vade + org;
      setText("out_toplam", fmtMoney(toplam));
      setText("out_hizmet", fmtMoney(org));
      setText("out_teslim", teslim + ". ay");
      setText("out_esik", fmtMoney(esik));
      setText("out_kalan", fmtMoney(taksit * Math.max(0, vade - teslim)));
      setText("live_teslim", teslim + ". ay");
    });
  }

  var runners = {
    temettu: temettu,
    "kar-zarar": karZarar,
    maliyet: maliyet,
    bedelli: bedelli,
    bedelsiz: bedelsiz,
    bilesik: bilesik,
    tavan: tavan,
    "konut-kredisi": konutKredisi,
    "tasarruf-finansmani": tasarrufFinansman,
    "kk-vs-tk": kkVsTk,
  };

  document.addEventListener("DOMContentLoaded", function () {
    var TOOLS = [
      ["temettu.html", "Temettü"],
      ["kar-zarar.html", "Hisse Kâr / Zarar"],
      ["maliyet.html", "Maliyet Düşürme"],
      ["bedelli.html", "Bedelli Sermaye Artırımı"],
      ["bedelsiz.html", "Bedelsiz Sermaye Artırımı"],
      ["bilesik-faiz.html", "Bileşik Faiz"],
      ["tavan-serisi.html", "Tavan Serisi"],
      ["konut-kredisi.html", "Konut Kredisi"],
      ["tasarruf-finansmani.html", "Tasarruf Finansmanı"],
      ["kk-vs-tk.html", "Konut Kredisi vs Tasarruf Finansmanı"],
    ];
    document.querySelectorAll("[data-tools-aside]").forEach(function (box) {
      var cur = box.getAttribute("data-tools-aside");
      box.innerHTML = TOOLS.map(function (t) {
        var cls = t[0] === cur ? ' class="is-current"' : "";
        return '<a href="' + t[0] + '"' + cls + ">" + t[1] + "</a>";
      }).join("");
    });

    var root = document.querySelector("[data-tool]");
    if (!root) return;
    var id = root.getAttribute("data-tool");
    if (runners[id]) runners[id](root);
  });
})();
