/**
 * Konut Kredisi vs Tasarruf Finansmanı — nominal maliyet motoru
 * (NBD / iskonto yok. Soru: cebimden toplam ne kadar çıkar?)
 *
 * İleride şirket kuralları eklenebilir; UI yalnızca bu API’yi çağırır.
 */
(function (global) {
  "use strict";

  var moneyFmt = new Intl.NumberFormat("tr-TR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  var intFmt = new Intl.NumberFormat("tr-TR", {
    maximumFractionDigits: 0,
  });

  function formatMoney(n) {
    if (!isFinite(n)) return "—";
    return moneyFmt.format(n) + " TL";
  }

  function formatInt(n) {
    if (!isFinite(n)) return "—";
    return intFmt.format(Math.round(n)) + " TL";
  }

  /**
   * Kira her 12 ayda bir artar (aylık bileşik değil).
   * Örn. 30.000, %25 → aylar 1–12: 30k, 13–24: 37.5k, …
   */
  function calculateTotalRent(startRent, annualIncreaseRate, months) {
    var m = Math.max(0, Math.floor(months || 0));
    var rent = Math.max(0, startRent || 0);
    var g = Math.max(0, annualIncreaseRate || 0);
    if (m <= 0 || rent <= 0) return 0;

    var total = 0;
    var current = rent;
    for (var t = 1; t <= m; t++) {
      total += current;
      if (t % 12 === 0 && t < m) {
        current = current * (1 + g);
      }
    }
    return total;
  }

  /** Annüite: aylık faiz oranı ile taksit (banka formülü). */
  function calculateMonthlyAnnuity(principal, monthlyRate, months) {
    var P = Math.max(0, principal || 0);
    var n = Math.max(1, Math.floor(months || 1));
    var r = monthlyRate || 0;
    if (P <= 0) return 0;
    if (r <= 0) return P / n;
    var f = Math.pow(1 + r, n);
    return (P * r * f) / (f - 1);
  }

  /**
   * Tasarruf finansmanı (konut artışı → teslim değeri → hizmet → gerçek peşinat → finansman).
   */
  function calculateTasarrufFinansmani(input) {
    var konutBugun = Math.max(0, input.konutBugun || 0);
    var pesinat = Math.max(0, input.pesinat || 0); // başlangıç nakit peşinat
    var vade = Math.max(1, Math.floor(input.vade || 1));
    var teslimAy = Math.max(0, Math.floor(input.teslimAy || 0));
    var hizmetOrani = Math.max(0, input.hizmetOrani || 0);
    var konutArtis = Math.max(0, input.konutArtis || 0);
    var baslangicKira = Math.max(0, input.baslangicKira || 0);
    var kiraArtis = Math.max(0, input.kiraArtis || 0);

    // Teslimdeki tahmini konut değeri (konut artışı değişince burası değişir)
    var konutTeslim =
      konutBugun * Math.pow(1 + konutArtis, teslimAy / 12);

    // Hizmet peşin: (teslim değeri − başlangıç peşinatı) × oran
    var hizmetBedeli = Math.max(0, (konutTeslim - pesinat) * hizmetOrani);

    // Eve kalan gerçek peşinat (nakitten hizmet düşülünce — otomatik)
    var gercekPesinat = pesinat - hizmetBedeli;

    // Şirketin sağlaması gereken finansman (otomatik)
    var finansman = konutTeslim - gercekPesinat;
    if (finansman < 0) finansman = 0;

    var aylikTF = finansman / vade;
    var toplamKira = calculateTotalRent(baslangicKira, kiraArtis, teslimAy);

    // Cebinden çıkan: başlangıç peşinatı + tüm taksitler + bekleme kirası
    // (= konutTeslim + hizmetBedeli + toplamKira)
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

  /**
   * Konut kredisi: bugün al, kredi = konutBugun − peşinat, taksit aylık faizle.
   */
  function calculateKonutKredisi(input) {
    var konutBugun = Math.max(0, input.konutBugun || 0);
    var pesinat = Math.max(0, input.pesinat || 0);
    var vade = Math.max(1, Math.floor(input.vade || 1));
    var faizAylik = Math.max(0, input.faizAylik || 0);

    var krediTutari = Math.max(0, konutBugun - pesinat);
    var aylikTaksit = calculateMonthlyAnnuity(krediTutari, faizAylik, vade);
    var toplamKK = aylikTaksit * vade;
    var toplamFaiz = Math.max(0, toplamKK - krediTutari);
    var toplamNominal = toplamKK + pesinat;

    return {
      konutBugun: konutBugun,
      konutTeslim: konutBugun, // hemen teslim
      pesinat: pesinat,
      hizmetBedeli: 0,
      gercekPesinat: pesinat,
      finansman: krediTutari,
      aylikOdeme: aylikTaksit,
      toplamGeriOdeme: toplamKK,
      toplamFaizVeyaHizmet: toplamFaiz,
      toplamKira: 0,
      toplamNominal: toplamNominal,
      vade: vade,
      teslimAy: 0,
      krediTutari: krediTutari,
      toplamFaiz: toplamFaiz,
    };
  }

  /** Tam karşılaştırma. */
  function compare(input) {
    var tf = calculateTasarrufFinansmani(input);
    var kk = calculateKonutKredisi(input);
    var fark = tf.toplamNominal - kk.toplamNominal;
    var kazanan =
      Math.abs(fark) < 1 ? "eşit" : fark > 0 ? "kredi" : "tasarruf";
    return { tf: tf, kk: kk, fark: fark, kazanan: kazanan };
  }

  global.KkVsTfCalc = {
    formatMoney: formatMoney,
    formatInt: formatInt,
    calculateTotalRent: calculateTotalRent,
    calculateMonthlyAnnuity: calculateMonthlyAnnuity,
    calculateTasarrufFinansmani: calculateTasarrufFinansmani,
    calculateKonutKredisi: calculateKonutKredisi,
    compare: compare,
  };
})(typeof window !== "undefined" ? window : this);
