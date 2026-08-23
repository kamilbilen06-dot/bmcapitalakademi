/*
 * BM Capital - Merkezi veri kaynağı
 * Yeni bir eğitim veya ürün eklemek için ilgili diziye yeni bir obje ekleyin.
 * Detay sayfaları bu verileri `id` üzerinden okur (egitim-detay.html?id=... / urun-detay.html?id=...).
 */
window.BM_DATA = {
  // Genel site bilgileri (header/footer ve iletişim alanlarında kullanılır)
  site: {
    marka: "BM Capital Akademi",
    brandMark: "BM",
    brandWord: "Capital",
    brandTagline: "Akademi",
    brandShort: "BM Capital",
    publicUrl: "",
    telefon: "0533 449 09 66",
    telefonHref: "tel:05334490966",
    whatsapp: "905334490966",
    instagram: "https://www.instagram.com/meteakyol1975/",
    twitter: "https://x.com/DrMeteAkyol",
    iban: "TR61 0006 7010 0000 0076 3994 01",
    banka: "Yapı Kredi",
    hesapAdi: "Marmara Revizyon A.Ş.",
    sehir: "İzmir",
    navHakkimizda: false,
    navSss: false,
    navIletisim: false,
    navAraclar: false,
    // İletişim formu e-posta gönderimi (EmailJS - public key gizli değildir)
    emailjs: {
      publicKey: "GCgkZATeZisTHfirF",
      serviceId: "service_z1424c5",
      templateId: "template_ktpxiei",
      toEmail: "kamilbilen06@gmail.com",
    },
  },

  // Tüm eğitimlerde ortak katılım notu
  katilimNot: "Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.",

  // EĞİTİM MODÜLLERİ
  egitimler: [
    {
      id: "teknik-temel-algoritmik",
      tip: "egitim",
      oneCikan: true,
      baslik: "Teknik - Temel Analiz & Algoritmik Trade Eğitimi",
      kisaAciklama:
        "Temel analizden algoritmik trade sistemlerine kadar A'dan Z'ye yatırımcı eğitimi. Canlı online veya İzmir yüz yüze, uygulamalı.",
      gorsel: "assets/img/egitim-analiz.svg",
      fiyat: "20.000 TL",
      fiyatNot: "(KDV dahil)",
      sure: "20 saat",
      egitimTuru: "Canlı Online veya İzmir Yüz Yüze Eğitim",
      egitmenler: "Dr. Mete AKYOL, Dr. Kamil BİLEN",
      katilimNot: "Sabit tarihli grup eğitimi (online veya İzmir yüz yüze) veya size uygun zamanda birebir özel ders olarak alınabilir.",
      tarihNot: "Eğitim 5 gün sürecektir.",
      tarihler: [
        "13 Nisan 2026 Pazartesi / 20:00-24:00",
        "14 Nisan 2026 Salı / 20:00-24:00",
        "15 Nisan 2026 Çarşamba / 20:00-24:00",
        "16 Nisan 2026 Perşembe / 20:00-24:00",
        "17 Nisan 2026 Cuma / 20:00-24:00+",
      ],
      hediye: [
        'Dr. Mete Akyol\'un "Metematiksel Analiz" Kitabı',
        "1 Aylık Ücretsiz WhatsApp Analiz Grubu Üyeliği",
      ],
      hediyeGorsel:
        "https://img.kitapyurdu.com/v1/getImage/fn:12073531/wi:220/wh:cb88d3c67",
      ozellikler: [
        "Temel analiz ile şirket değerini doğru okuma",
        "Teknik analiz ile doğru zamanlama yapabilme",
        "AKD ve takas analizi ile kurumsal para hareketlerini yorumlama",
        "Algoritmik düşünce yapısı kazanma",
        "Kendi sisteminizi oluşturma ve test etme becerisi",
      ],
      aciklama: [
        "Finansal piyasalarda başarılı olmak tesadüf değildir.",
        "Bilimsel yöntem, veri analizi ve disiplinli sistem gerektirir.",
        "Bu eğitim, yatırım kararlarınızı duygusal değil analitik temelde alabilmeniz için tasarlanmıştır. Riskini yöneten ve sürdürülebilir getiri hedefleyen yatırımcılar için idealdir.",
      ],
      mufredat: [
        {
          baslik: "1. GÜN: A'dan Z'ye Temel Analiz",
          bolumler: [
            {
              baslik: "1. TEMEL ANALİZE GİRİŞ",
              maddeler: [
                "Temel analiz nedir",
                "Temel analiz vs teknik analiz",
                "Değer yatırımcılığı yaklaşımı",
                "Piyasa fiyatı vs gerçek değer",
                "Finansal analiz neden yapılır",
              ],
            },
            {
              baslik: "2. MAKRO EKONOMİ ANALİZİ",
              maddeler: [
                "Ekonomik büyüme (GSYH)",
                "Enflasyon",
                "Faiz oranları",
                "Para politikası",
                "Döviz kuru",
                "Yatırımcı için kritik göstergeler",
              ],
            },
            {
              baslik: "3. SEKTÖR ANALİZİ",
              maddeler: [
                "Sektör büyüklüğü",
                "Rekabet yapısı",
                "Giriş engelleri",
                "Teknolojik değişim",
                "Regülasyonlar",
              ],
            },
            { baslik: "4. ŞİRKET ANALİZİ", maddeler: [] },
          ],
        },
        {
          baslik: "2. GÜN: A'dan Z'ye Temel Analiz – Bölüm 2",
          bolumler: [
            {
              baslik: "5. FİNANSAL TABLO ANALİZİ",
              maddeler: ["Bilanço analizi", "Gelir tablosu analizi", "Nakit akış analizi"],
            },
            {
              baslik: "6. ORAN ANALİZİ",
              maddeler: [
                "Likidite oranları",
                "Faaliyet oranları",
                "Karlılık oranları",
                "Borçluluk oranları",
              ],
            },
            { baslik: "7. NAKİT DÖNGÜSÜ VE İŞLETME SERMAYESİ", maddeler: [] },
            { baslik: "8. BÜYÜME ANALİZİ", maddeler: [] },
            { baslik: "9. DEĞERLEME YÖNTEMLERİ", maddeler: [] },
            { baslik: "10. SWOT ANALİZİ", maddeler: [] },
            { baslik: "11. YATIRIM KARAR MODELİ", maddeler: [] },
          ],
        },
        {
          baslik: "3. GÜN: Teknik Analiz",
          bolumler: [
            { baslik: "1. TEKNİK ANALİZ NEDİR?", maddeler: [] },
            { baslik: "2. GRAFİK TÜRLERİ", maddeler: [] },
            { baslik: "3. ZAMAN DİLİMLERİ VE ÖNEMİ", maddeler: [] },
            { baslik: "4. DESTEK VE DİRENÇ", maddeler: [] },
            { baslik: "5. TREND ANALİZİ", maddeler: [] },
            { baslik: "6. FORMASYON ANALİZİ", maddeler: [] },
            { baslik: "7. TUZAKLAR VE TESPİTİ", maddeler: [] },
            { baslik: "8. HACİM VE FİYAT İLİŞKİSİ", maddeler: [] },
            { baslik: "9. KISA VADELİ TRADİNG STRATEJİLERİ", maddeler: [] },
          ],
        },
        {
          baslik: "4. GÜN: AKD ve Takas Analizi",
          bolumler: [
            { baslik: "1. ARACI KURUM DAĞILIMI ANALİZİNE GİRİŞ", maddeler: [] },
            { baslik: "2. KURUMLAR ANALİZİ", maddeler: [] },
            { baslik: "3. KURUM STRATEJİLERİ", maddeler: [] },
            { baslik: "4. PARA GİRİŞ/ÇIKIŞ ANALİZİ", maddeler: [] },
            { baslik: "5. MALİYET ANALİZİ", maddeler: [] },
            { baslik: "6. ARACI KURUM DAĞILIMI ANALİZİ UYGULAMASI", maddeler: [] },
            { baslik: "7. TAKAS ANALİZİ UYGULAMASI", maddeler: [] },
            { baslik: "8. AKD VE TAKAS ANALİZİNİN BİRLİKTE KULLANILMASI", maddeler: [] },
          ],
        },
        {
          baslik: "5. GÜN: Algoritmik Trade & Soru Cevap",
          bolumler: [
            { baslik: "1. İNDİKATÖRLER", maddeler: [] },
            { baslik: "2. TARAMALAR", maddeler: [] },
            { baslik: "3. TRADİNGVİEW TARAMA NASIL YAPILIR?", maddeler: [] },
            { baslik: "4. MATRİKS TARAMA NASIL YAPILIR?", maddeler: [] },
            { baslik: "5. İNDİKATÖR OPTİMİZASYONU", maddeler: [] },
            { baslik: "6. ALIM-SATIM SİSTEMİ OLUŞTURMA", maddeler: [] },
            { baslik: "7. BACKTEST VE OPTİMİZASYON", maddeler: [] },
            {
              baslik: "8. SORU CEVAP OTURUMU",
              maddeler: [
                "Eğitim sırasında sorularınız cevaplandırılır",
                "Canlı uygulamalı örnek çözümleri",
                "Kişisel danışmanlık ve rehberlik",
                "İleri düzey konulardaki açıklamalar",
              ],
            },
          ],
        },
      ],
    },

    {
      id: "takas-akd-analizi",
      tip: "egitim",
      oneCikan: true,
      baslik: "Takas & Aracı Kurum Dağılımı (AKD) Analizi Eğitimi",
      kisaAciklama:
        "Kurumsal para hareketlerini takip edin. Takas verisi ve aracı kurum dağılımı ile büyük oyuncuların adımlarını okuyun.",
      gorsel: "assets/img/egitim-takas.svg",
      fiyat: "10.000 TL",
      fiyatNot: "(KDV dahil)",
      sure: "Modüler",
      egitimTuru: "Canlı Online veya İzmir Yüz Yüze Eğitim",
      egitmenler: "Dr. Mete AKYOL, Dr. Kamil BİLEN",
      katilimNot: "Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.",
      hediye: [],
      hediyeGorsel: "",
      ozellikler: [
        "Aracı kurum dağılımı (AKD) verisini okuma",
        "Takas analizi ile kurumsal alım-satım tespiti",
        "Para giriş/çıkış ve maliyet analizi",
        "AKD ve takas analizini birlikte kullanma",
      ],
      aciklama: [
        "Piyasada büyük hacimli kurumsal oyuncuların hareketlerini takip etmek, doğru zamanlama için kritik bir avantajdır.",
        "Bu eğitimde takas verisini ve aracı kurum dağılımını okuyarak paranın nereye aktığını yorumlamayı öğrenirsiniz.",
      ],
      mufredat: [
        {
          baslik: "Takas & AKD Analizi",
          bolumler: [
            { baslik: "1. ARACI KURUM DAĞILIMI ANALİZİNE GİRİŞ", maddeler: [] },
            { baslik: "2. KURUMLAR ANALİZİ VE STRATEJİLERİ", maddeler: [] },
            { baslik: "3. PARA GİRİŞ/ÇIKIŞ ANALİZİ", maddeler: [] },
            { baslik: "4. MALİYET ANALİZİ", maddeler: [] },
            { baslik: "5. AKD & TAKAS UYGULAMALARI", maddeler: [] },
            { baslik: "6. AKD VE TAKAS ANALİZİNİN BİRLİKTE KULLANILMASI", maddeler: [] },
          ],
        },
      ],
    },

    {
      id: "algoritmik-trade",
      tip: "egitim",
      oneCikan: true,
      baslik: "Algoritmik Trade Eğitimi",
      kisaAciklama:
        "Stratejinizi kurallara dökün. İndikatör, tarama, backtest ve otomatik alım-satım sistemi kurmayı öğrenin.",
      gorsel: "assets/img/egitim-algo.svg",
      fiyat: "10.000 TL",
      fiyatNot: "(KDV dahil)",
      sure: "Modüler",
      egitimTuru: "Canlı Online veya İzmir Yüz Yüze Eğitim",
      egitmenler: "Dr. Mete AKYOL, Dr. Kamil BİLEN",
      katilimNot: "Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.",
      hediye: [],
      hediyeGorsel: "",
      ozellikler: [
        "İndikatör kullanımı ve optimizasyonu",
        "TradingView & Matriks tarama teknikleri",
        "Alım-satım sistemi oluşturma",
        "Backtest ve optimizasyon ile sistem doğrulama",
      ],
      aciklama: [
        "Algoritmik trade, yatırım kararlarını kurallara dökerek duygudan arındıran ve disiplinli hale getiren bir yaklaşımdır.",
        "Bu eğitimde kendi alım-satım sisteminizi kurmayı, test etmeyi ve optimize etmeyi öğrenirsiniz.",
      ],
      mufredat: [
        {
          baslik: "Algoritmik Trade",
          bolumler: [
            { baslik: "1. İNDİKATÖRLER", maddeler: [] },
            { baslik: "2. TARAMALAR", maddeler: [] },
            { baslik: "3. TRADİNGVİEW TARAMA NASIL YAPILIR?", maddeler: [] },
            { baslik: "4. MATRİKS TARAMA NASIL YAPILIR?", maddeler: [] },
            { baslik: "5. İNDİKATÖR OPTİMİZASYONU", maddeler: [] },
            { baslik: "6. ALIM-SATIM SİSTEMİ OLUŞTURMA", maddeler: [] },
            { baslik: "7. BACKTEST VE OPTİMİZASYON", maddeler: [] },
          ],
        },
      ],
    },
  ],

  // ÜRÜNLER / HİZMETLER
  urunler: [
    {
      id: "bist-robotu",
      tip: "urun",
      oneCikan: true,
      baslik: "BIST Robotu",
      kisaAciklama:
        "BIST hisselerinde stratejinize göre otomatik alım-satım sinyalleri üreten algoritmik trade robotu.",
      gorsel: "assets/img/urun-robot.svg",
      video: "assets/video/algo-robot.mp4",
      videoPoster: "assets/img/algo-robot-poster.jpg",
      fiyat: null, // fiyat gösterilmez
      fiyatNot: "",
      etiket: "Algoritmik Trade",
      ozellikler: [
        "Strateji tabanlı otomatik alım-satım sinyalleri",
        "TradingView / Matriks uyumlu tarama mantığı",
        "İndikatör optimizasyonu ve backtest desteği",
        "Disiplinli, duygusuz işlem yönetimi",
      ],
      aciklama: [
        "BIST Robotu, kendi stratejinizi kurallara dökerek piyasayı tarayan ve size sinyal üreten bir algoritmik trade çözümüdür.",
        "Ürünümüz hazırdır. Detaylı bilgi ve kurulum için hemen bizimle iletişime geçebilirsiniz.",
      ],
    },

    {
      id: "strateji-yazdirma",
      tip: "urun",
      oneCikan: true,
      baslik: "Strateji Yazdırma",
      kisaAciklama:
        "Hayalinizdeki alım-satım stratejisini bize anlatın, sizin için kodlayalım. Siz hayal edin, biz yazalım.",
      gorsel: "assets/img/urun-strateji.svg",
      fiyat: null,
      fiyatNot: "",
      etiket: "Özel Yazılım",
      ozellikler: [
        "Size özel, isteğe göre kodlanan strateji",
        "İndikatör ve kural bazlı sistemler",
        "Backtest ile doğrulama ve optimizasyon",
        "TradingView / Matriks uyumlu kurulum",
      ],
      aciklama: [
        "Strateji Yazdırma hizmetimizle, aklınızdaki alım-satım fikrini kurallara döküp sizin için yazıyoruz.",
        "Dilediğiniz stratejiyi yazdırabilirsiniz: siz hayal edin, biz yazalım. Fikrinizi bize iletin; kodlayıp test edelim.",
      ],
    },
  ],

  // SIKÇA SORULAN SORULAR (API yoksa yedek olarak kullanılır)
  sss: [
    { soru: "Eğitimler nasıl yapılıyor?", cevap: "Eğitimlerimiz canlı online veya İzmir'de yüz yüze yapılabilir. Belirtilen tarih ve saatlerde interaktif olarak katılır, sorularınızı canlı sorabilirsiniz. Grup dersi veya birebir özel ders seçenekleri mevcuttur." },
    { soru: "İzmir'de borsa eğitimi veriyor musunuz?", cevap: "Evet. BM Capital Akademi, İzmir'de yüz yüze borsa eğitimi ve tüm Türkiye'ye canlı online eğitim sunar. Teknik & temel analiz, takas/AKD analizi ve algoritmik trade eğitimlerine online veya İzmir yüz yüze katılabilirsiniz." },
    { soru: "Eğitimler yüz yüze mi online mı?", cevap: "İkisi de. Canlı online (uzaktan) veya İzmir'de yüz yüze eğitim alabilirsiniz. Kayıt sırasında tercihinizi belirtmeniz yeterlidir; birebir özel ders de mümkündür." },
    { soru: "2026 Nisan döneminde teknik-temel eğitim ne zaman?", cevap: "Teknik - Temel Analiz & Algoritmik Trade Eğitimi'nin sabit grup tarihleri 13–17 Nisan 2026'tır (her gün 20:00–24:00). Bu tarihlerde canlı online veya İzmir yüz yüze katılım mümkündür. Tarihler uymuyorsa birebir özel ders seçeneği de mevcuttur." },
    { soru: "Ön bilgi gerekiyor mu?", cevap: "Hayır. Eğitim A'dan Z'ye kurgulanmıştır; sıfırdan başlayanlar da rahatlıkla takip edebilir." },
    { soru: "Ödeme nasıl yapılıyor?", cevap: "Kart ile ödeme iyzico üzerinden yapılır. Havale/EFT seçerseniz dekontu iletmeniz gerekir; yönetici onayından sonra erişim açılır." },
    { soru: "Sertifika veriliyor mu?", cevap: "Evet, eğitimi tamamlayan katılımcılara katılım sertifikası verilir." },
    { soru: "Hediye paketinde neler var?", cevap: "Dr. Mete Akyol'un \"Metematiksel Analiz\" kitabı ve 1 aylık ücretsiz WhatsApp analiz grubu üyeliği hediye edilir." },
    { soru: "BIST Robotu satışta mı?", cevap: "Evet, BIST Robotu ürünümüz hazırdır. Detaylı bilgi ve kurulum için doğrudan WhatsApp veya telefon ile bizimle iletişime geçebilirsiniz." },
  ],
};

// Yardımcı: tüm modülleri tek listede döndür
window.BM_DATA.tumModuller = function () {
  return [].concat(window.BM_DATA.egitimler, window.BM_DATA.urunler);
};

/** Mete pasifken yedek veriyi temizle (aktif için feature-flags.js + api/feature_flags.php). */
(function applyMeteDataFilter() {
  var flags = window.BM_FLAGS || {};
  if (flags.meteAkyolActive) return;
  var D = window.BM_DATA;
  if (!D) return;

  // Arşiv — aktif edilince bu değerler tekrar kullanılır (data.js kaynak + flag true)
  D._meteArchive = {
    instagram: D.site && D.site.instagram,
    twitter: D.site && D.site.twitter,
  };

  if (D.site) {
    var ig = D.site.instagram || "";
    var tw = D.site.twitter || "";
    if (/meteakyol|DrMeteAkyol/i.test(ig)) D.site.instagram = "";
    if (/DrMeteAkyol|meteakyol/i.test(tw)) D.site.twitter = "";
  }

  function stripMeteInstructors(s) {
    return String(s || "")
      .split(/\s*,\s*/)
      .map(function (x) { return x.trim(); })
      .filter(function (x) {
        return x && !/mete|akyol/i.test(x);
      })
      .join(", ");
  }

  (D.egitimler || []).forEach(function (e) {
    if (e.egitmenler) e.egitmenler = stripMeteInstructors(e.egitmenler);
    e.hediye = [];
    e.hediyeGorsel = "";
  });

  if (Array.isArray(D.sss)) {
    D.sss = D.sss.filter(function (f) {
      var q = String((f && f.soru) || "").toLowerCase();
      var a = String((f && f.cevap) || "").toLowerCase();
      if (q.indexOf("hediye") !== -1) return false;
      if (a.indexOf("mete") !== -1 || a.indexOf("metematiksel") !== -1) return false;
      return true;
    });
  }
})();
