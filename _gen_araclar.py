# -*- coding: utf-8 -*-
from pathlib import Path

root = Path(r"c:\bmcapital")

FONT = """  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css?v=araclar3">"""

TOOLS = [
    ("temettu.html", "Temettü"),
    ("kar-zarar.html", "Hisse Kâr / Zarar"),
    ("maliyet.html", "Maliyet Düşürme"),
    ("bedelli.html", "Bedelli Sermaye Artırımı"),
    ("bedelsiz.html", "Bedelsiz Sermaye Artırımı"),
    ("bilesik-faiz.html", "Bileşik Faiz"),
    ("bono.html", "Bono Hesaplama"),
    ("tavan-serisi.html", "Tavan Serisi"),
]


def aside(current):
    lines = [
        '      <aside class="tools-aside">',
        "        <h2>Diğer Araçlar</h2>",
        '        <div class="tools-aside-list">',
    ]
    for href, label in TOOLS:
        cls = ' class="is-current"' if href == current else ""
        lines.append(f'          <a href="{href}"{cls}>{label}</a>')
    lines += ["        </div>", "      </aside>"]
    return "\n".join(lines)


def page(meta_title, meta_desc, canonical, crumb, h1, lead, tool_id, shell, explain, current, og=False):
    og_block = ""
    if og:
        og_block = f"""  <meta property="og:title" content="{meta_title}">
  <meta property="og:image" content="https://www.bmcapitalakademi.com/assets/img/og-cover.png">
"""
    return f"""<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{meta_title}</title>
  <meta name="description" content="{meta_desc}">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="canonical" href="{canonical}">
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
{og_block}{FONT}
</head>
<body class="page-tools">
  <div id="site-header"></div>
  <section class="page-hero">
    <div class="container">
      <div class="tools-kicker">Ücretsiz Borsa Aracı</div>
      <h1>{h1}</h1>
      <p class="tools-lead">{lead}</p>
      <p class="tools-hint">Hızlıca kaydırın veya kesin rakamlar için kutucuklara yazın.</p>
      <div class="breadcrumb"><a href="../index.html">Ana Sayfa</a> / <a href="../araclar.html">Araçlar</a> / {crumb}</div>
    </div>
  </section>
  <section class="section-tight">
    <div class="container" data-tool="{tool_id}">
{shell}
      <div class="calc-footer-grid">
        <div class="calc-explain">
{explain}
        </div>
{aside(current)}
      </div>
    </div>
  </section>
  <div id="site-footer"></div>
  <div id="site-floaters"></div>
  <script src="../assets/js/data.js"></script>
  <script src="../assets/js/catalog.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/araclar.js?v=3"></script>
</body>
</html>
"""


def field(fid, label, unit, val, rmin, rmax, step, rid=None):
    rid = rid or fid + "_r"
    return f"""          <div class="calc-field">
            <label for="{fid}" id="{fid}_label">{label}</label>
            <div class="calc-control">
              <input type="number" id="{fid}" inputmode="decimal" min="{rmin}" max="{rmax}" step="{step}" value="{val}">
              <span class="calc-unit" id="{fid}_unit">{unit}</span>
            </div>
            <input type="range" id="{rid}" min="{rmin}" max="{rmax}" step="{step}" value="{val}">
          </div>"""


def field_norange(fid, label, unit, val, step="0.01", mn="0"):
    return f"""          <div class="calc-field">
            <label for="{fid}">{label}</label>
            <div class="calc-control">
              <input type="number" id="{fid}" inputmode="decimal" min="{mn}" step="{step}" value="{val}">
              <span class="calc-unit">{unit}</span>
            </div>
          </div>"""


def stat(oid, label, color="navy"):
    return f"""          <div class="calc-stat">
            <div class="calc-stat-label">{label}</div>
            <div class="calc-stat-value {color}" id="{oid}">—</div>
          </div>"""


def shell(inputs, stats, table=None):
    t = ""
    if table:
        t = f"""
        <div class="calc-table-wrap">
{table}
        </div>"""
    return f"""      <div class="calc-shell">
        <div class="calc-shell-grid">
          <div class="calc-inputs">
{inputs}
          </div>
          <div class="calc-stats">
{stats}
          </div>
        </div>{t}
      </div>"""


# TEMETTU
inputs = (
    field("lot", "Lot (Adet)", "Lot", 1000, 1, 100000, 1)
    + """
          <div class="calc-field">
            <label for="dagitim">Dağıtım Türü</label>
            <div class="calc-control">
              <select id="dagitim">
                <option value="brut" selected>Hisse Başına Brüt TL</option>
                <option value="oran">Nominal Orana Göre (%)</option>
              </select>
            </div>
          </div>
"""
    + field("brut", "Hisse Başına Brüt Temettü", "TL", 5, 0, 100, 0.01)
    + field("fiyat", "Güncel Hisse Fiyatı", "TL", 50, 0.01, 1000, 0.01)
)
stats = (
    stat("out_brut", "Toplam Brüt Temettü", "gold")
    + stat("out_stopaj", "Stopaj (%10)", "red")
    + stat("out_net", "Hesabına Yatacak Net", "green")
    + stat("out_verim", "Temettü Verimi", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <p>Şirketler kâr payını hisse başına brüt TL olarak ya da nominal değere (1 TL) göre yüzde olarak duyurur. Araç iki biçimi de destekler: yüzde seçtiğinizde hisse başına brüt tutar, oranın 100’e bölünmesiyle bulunur.</p>
          <p>Toplam brüt temettü = lot × hisse başı brüt tutar. Türkiye’de temettü ödemelerinden %10 stopaj yapılır; hesabınıza yatan net tutar brüt tutarın %90’ıdır.</p>
          <p>Temettü verimi, hisse başı brüt temettünün güncel hisse fiyatına oranıdır.</p>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.</div>"""
(root / "araclar" / "temettu.html").write_text(
    page(
        "Temettü Hesaplama - BM Capital Akademi",
        "Brüt temettüden stopajı düşüp net tutar ve temettü verimini hesaplayın. Ücretsiz temettü hesaplama aracı.",
        "https://www.bmcapitalakademi.com/araclar/temettu.html",
        "Temettü",
        "Temettü Hesaplama",
        "Hisse başı brüt temettü miktarını girerek hesabınıza yatacak net tutarı ve temettü veriminizi öğrenin.",
        "temettu",
        shell(inputs, stats),
        explain,
        "temettu.html",
        True,
    ),
    encoding="utf-8",
)

# KAR ZARAR
inputs = (
    field("lot", "Lot (Adet)", "Lot", 1000, 1, 100000, 1)
    + field("alis", "Alış Fiyatı", "TL", 45, 0.01, 1000, 0.01)
    + field("satis", "Satış Fiyatı", "TL", 52, 0.01, 1000, 0.01)
    + field_norange("kom_alis", "Alış Komisyonu", "TL", 0)
    + field_norange("kom_satis", "Satış Komisyonu", "TL", 0)
)
stats = (
    stat("out_maliyet", "Toplam Maliyet", "navy")
    + stat("out_gelir", "Satış Geliri", "gold")
    + stat("out_net", "Net Kâr / Zarar", "green")
    + stat("out_getiri", "Getiri", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <ul>
            <li>Maliyet = Alış × Lot + Alış komisyonu</li>
            <li>Gelir = Satış × Lot − Satış komisyonu</li>
            <li>Net = Gelir − Maliyet</li>
            <li>Getiri % = Net / Maliyet × 100</li>
          </ul>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.</div>"""
(root / "araclar" / "kar-zarar.html").write_text(
    page(
        "Hisse Kâr / Zarar Hesaplama - BM Capital Akademi",
        "Komisyon dahil net kâr/zarar ve yüzdesel getiriyi hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/kar-zarar.html",
        "Kâr / Zarar",
        "Hisse Kâr / Zarar",
        "Komisyon dahil net kâr/zararı ve yüzdesel getiriyi anında görün.",
        "kar-zarar",
        shell(inputs, stats),
        explain,
        "kar-zarar.html",
    ),
    encoding="utf-8",
)

# MALIYET
inputs = (
    field("lot1", "Mevcut Lot", "Lot", 1000, 0, 100000, 1)
    + field("fiyat1", "Mevcut Ortalama Maliyet", "TL", 40, 0.01, 1000, 0.01)
    + field("lot2", "Yeni Alım Lot", "Lot", 500, 0, 100000, 1)
    + field("fiyat2", "Yeni Alış Fiyatı", "TL", 30, 0.01, 1000, 0.01)
)
stats = (
    stat("out_lot", "Toplam Lot", "navy")
    + stat("out_maliyet", "Toplam Maliyet", "gold")
    + stat("out_ortalama", "Yeni Ortalama Maliyet", "green")
    + stat("out_dusus", "Maliyet Değişimi", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <p>Yeni ortalama = (Lot1 × Fiyat1 + Lot2 × Fiyat2) / (Lot1 + Lot2). Maliyet değişimi, eski ortalamaya göre yüzdesel farktır.</p>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.</div>"""
(root / "araclar" / "maliyet.html").write_text(
    page(
        "Maliyet Düşürme (Ortalama) - BM Capital Akademi",
        "Ek alım sonrası ağırlıklı ortalama hisse maliyetini hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/maliyet.html",
        "Maliyet",
        "Maliyet Düşürme (Ortalama)",
        "Yeni alım sonrası ağırlıklı ortalama maliyetinizi hesaplayın.",
        "maliyet",
        shell(inputs, stats),
        explain,
        "maliyet.html",
    ),
    encoding="utf-8",
)

# BEDELLI
inputs = (
    field("lot", "Mevcut Lot", "Lot", 1000, 1, 100000, 1)
    + field("fiyat", "Güncel Fiyat", "TL", 50, 0.01, 1000, 0.01)
    + field("oran", "Bedelli Oranı", "%", 50, 0, 500, 0.1)
    + field("bedel", "Bedel / Rüçhan Fiyatı", "TL", 10, 0, 500, 0.01)
)
stats = (
    stat("out_ruchan", "Rüçhan Maliyeti", "gold")
    + stat("out_yeni_hisse", "Alınacak Yeni Hisse", "navy")
    + stat("out_yeni_lot", "Yeni Toplam Lot", "green")
    + stat("out_teorik", "Teorik Fiyat", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <ul>
            <li>Yeni hisse = Lot × (Oran / 100)</li>
            <li>Rüçhan maliyeti = Yeni hisse × Bedel</li>
            <li>Yeni lot = Mevcut + Yeni hisse</li>
            <li>Teorik fiyat = (Fiyat + Oran × Bedel) / (1 + Oran)</li>
          </ul>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir. Şirket duyurusundaki oran ve bedeli kullanın.</div>"""
(root / "araclar" / "bedelli.html").write_text(
    page(
        "Bedelli Sermaye Artırımı Hesaplama - BM Capital Akademi",
        "Rüçhan maliyeti, yeni lot sayısı ve teorik fiyatı hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/bedelli.html",
        "Bedelli",
        "Bedelli Sermaye Artırımı",
        "Rüçhan maliyetini, yeni lot sayısını ve teorik fiyatı hesaplayın.",
        "bedelli",
        shell(inputs, stats),
        explain,
        "bedelli.html",
    ),
    encoding="utf-8",
)

# BEDELSIZ
inputs = (
    field("lot", "Mevcut Lot", "Lot", 1000, 1, 100000, 1)
    + field("fiyat", "Güncel Fiyat", "TL", 50, 0.01, 1000, 0.01)
    + field("oran", "Bedelsiz Oranı", "%", 100, 0, 500, 0.1)
)
stats = (
    stat("out_bedelsiz", "Bedelsiz Gelecek Hisse", "gold")
    + stat("out_carpan", "Lot Çarpanı", "navy")
    + stat("out_yeni_lot", "Yeni Toplam Lot", "green")
    + stat("out_teorik", "Teorik Fiyat", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <ul>
            <li>Bedelsiz hisse = Lot × (Oran / 100)</li>
            <li>Yeni lot = Lot × (1 + Oran)</li>
            <li>Teorik fiyat = Fiyat / (1 + Oran)</li>
          </ul>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.</div>"""
(root / "araclar" / "bedelsiz.html").write_text(
    page(
        "Bedelsiz Sermaye Artırımı Hesaplama - BM Capital Akademi",
        "Bedelsiz sonrası lot sayısı ve teorik fiyatı hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/bedelsiz.html",
        "Bedelsiz",
        "Bedelsiz Sermaye Artırımı",
        "Bedelsiz sonrası lot sayısını ve teorik hisse fiyatını hesaplayın.",
        "bedelsiz",
        shell(inputs, stats),
        explain,
        "bedelsiz.html",
    ),
    encoding="utf-8",
)

# BILESIK
inputs = (
    field("anapara", "Anapara", "TL", 100000, 1000, 10000000, 1000)
    + field("oran", "Yıllık Faiz", "%", 20, 0, 100, 0.1)
    + field("yil", "Süre", "Yıl", 10, 1, 50, 1)
)
stats = (
    stat("out_son", "Dönem Sonu Tutar", "green")
    + stat("out_kazanc", "Toplam Kazanç", "green")
    + stat("out_kat", "Katlanma", "gold")
    + stat("out_yil", "Süre", "navy")
)
table = """          <table class="calc-table">
            <thead><tr><th>Yıl</th><th>Bakiye</th><th>Kazanç</th></tr></thead>
            <tbody id="out_tablo"></tbody>
          </table>"""
explain = """          <h2>Nasıl hesaplanır?</h2>
          <p>Her yıl bakiye = bakiye × (1 + yıllık oran). Tablo yıllık bakiyeyi ve birikimli kazancı gösterir.</p>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir. Enflasyon ve vergi etkileri dahil değildir.</div>"""
(root / "araclar" / "bilesik-faiz.html").write_text(
    page(
        "Bileşik Faiz Hesaplama - BM Capital Akademi",
        "Bileşik getiriyi yıl yıl tablo ile görün.",
        "https://www.bmcapitalakademi.com/araclar/bilesik-faiz.html",
        "Bileşik Faiz",
        "Bileşik Faiz",
        "Bileşik getiriyi yıl yıl tablo ile görün.",
        "bilesik",
        shell(inputs, stats, table),
        explain,
        "bilesik-faiz.html",
    ),
    encoding="utf-8",
)

# BONO
inputs = (
    field("anapara", "Anapara", "TL", 100000, 1000, 10000000, 1000)
    + field("oran", "Yıllık Faiz", "%", 40, 0, 100, 0.1)
    + field("gun", "Vade", "Gün", 365, 1, 3650, 1)
    + field_norange("stopaj", "Stopaj Oranı", "%", 10, "0.1", "0")
)
stats = (
    stat("out_faiz", "Brüt Faiz", "gold")
    + stat("out_stopaj", "Stopaj", "red")
    + stat("out_net", "Net Faiz", "green")
    + stat("out_toplam", "Vade Sonu Toplam", "green")
)
explain = """          <h2>Nasıl hesaplanır?</h2>
          <ul>
            <li>Brüt faiz = Anapara × Oran × (Gün / 365)</li>
            <li>Stopaj = Brüt faiz × stopaj oranı</li>
            <li>Net faiz = Brüt − Stopaj</li>
            <li>Toplam = Anapara + Net faiz</li>
          </ul>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir. Güncel stopaj oranını kendi durumunuza göre doğrulayın.</div>"""
(root / "araclar" / "bono.html").write_text(
    page(
        "Bono Hesaplama - BM Capital Akademi",
        "Basit faizli bono/mevduat getirisini stopaj dahil hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/bono.html",
        "Bono",
        "Bono Hesaplama",
        "Basit faizli bono/mevduat getirisini stopaj dahil hesaplayın.",
        "bono",
        shell(inputs, stats),
        explain,
        "bono.html",
    ),
    encoding="utf-8",
)

# TAVAN
inputs = (
    field("fiyat", "Başlangıç Fiyatı", "TL", 20, 0.01, 1000, 0.01)
    + field("lot", "Lot", "Lot", 1000, 1, 100000, 1)
    + field("gun", "Ardışık Tavan Günü", "Gün", 5, 1, 30, 1)
)
stats = (
    stat("out_son_fiyat", "Son Fiyat", "gold")
    + stat("out_son_deger", "Son Portföy Değeri", "green")
    + stat("out_kat", "Katlanma", "green")
    + stat("out_gun", "Süre", "navy")
)
table = """          <table class="calc-table">
            <thead><tr><th>Gün</th><th>Fiyat</th><th>Portföy</th></tr></thead>
            <tbody id="out_tablo"></tbody>
          </table>"""
explain = """          <h2>Nasıl hesaplanır?</h2>
          <ul>
            <li>Her gün fiyat = Önceki × 1,10 (%10 tavan)</li>
            <li>Portföy = Fiyat × Lot</li>
            <li>Bu tamamen teorik bir senaryodur</li>
          </ul>
          <div class="calc-disclaimer warn">Bu araç bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.<br><strong>Dikkat:</strong> Ardışık tavan senaryosu teoriktir; gerçekleşme garantisi yoktur.</div>"""
(root / "araclar" / "tavan-serisi.html").write_text(
    page(
        "Tavan Serisi Hesaplama - BM Capital Akademi",
        "Ardışık yüzde 10 tavan senaryosunda fiyat ve portföy değerini görün.",
        "https://www.bmcapitalakademi.com/araclar/tavan-serisi.html",
        "Tavan Serisi",
        "Tavan Serisi",
        "Ardışık %10 tavanların fiyatı ve bakiyeyi nasıl katladığını görün.",
        "tavan",
        shell(inputs, stats, table),
        explain,
        "tavan-serisi.html",
    ),
    encoding="utf-8",
)

print("OK pages")
