# -*- coding: utf-8 -*-
"""Generate KK / TK / KK-vs-TK pages and refresh all tool asides."""
from pathlib import Path
import re

root = Path(r"c:\bmcapital")
araclar = root / "araclar"

FONT = """  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css?v=araclar4">"""

TOOLS = [
    ("temettu.html", "Temettü"),
    ("kar-zarar.html", "Hisse Kâr / Zarar"),
    ("maliyet.html", "Maliyet Düşürme"),
    ("bedelli.html", "Bedelli Sermaye Artırımı"),
    ("bedelsiz.html", "Bedelsiz Sermaye Artırımı"),
    ("bilesik-faiz.html", "Bileşik Faiz"),
    ("bono.html", "Bono Hesaplama"),
    ("tavan-serisi.html", "Tavan Serisi"),
    ("konut-kredisi.html", "Konut Kredisi (KK)"),
    ("tasarruf-finansmani.html", "Tasarruf Finansmanı (TK)"),
    ("kk-vs-tk.html", "KK vs TK Karşılaştırma"),
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


def field(fid, label, unit, val, rmin, rmax, step):
    rid = fid + "_r"
    return f"""          <div class="calc-field">
            <label for="{fid}">{label}</label>
            <div class="calc-control">
              <input type="number" id="{fid}" inputmode="decimal" min="{rmin}" max="{rmax}" step="{step}" value="{val}">
              <span class="calc-unit">{unit}</span>
            </div>
            <input type="range" id="{rid}" min="{rmin}" max="{rmax}" step="{step}" value="{val}">
          </div>"""


def field_nr(fid, label, unit, val, step="1", mn="0"):
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


def page(title, desc, canonical, crumb, h1, lead, tool_id, body_main, explain, current, extra_head=""):
    return f"""<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="canonical" href="{canonical}">
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
{extra_head}{FONT}
</head>
<body class="page-tools">
  <div id="site-header"></div>
  <section class="page-hero">
    <div class="container">
      <div class="tools-kicker">Ücretsiz Finans Aracı</div>
      <h1>{h1}</h1>
      <p class="tools-lead">{lead}</p>
      <p class="tools-hint">Hızlıca kaydırın veya kesin rakamlar için kutucuklara yazın.</p>
      <div class="breadcrumb"><a href="../index.html">Ana Sayfa</a> / <a href="../araclar.html">Araçlar</a> / {crumb}</div>
    </div>
  </section>
  <section class="section-tight">
    <div class="container" data-tool="{tool_id}">
{body_main}
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
  <script src="../assets/js/araclar.js?v=4"></script>
</body>
</html>
"""


# --- KK ---
kk_inputs = (
    field("bedel", "Konut Bedeli", "TL", 3600000, 100000, 20000000, 10000)
    + field("pesinat", "Peşinat", "TL", 600000, 0, 10000000, 10000)
    + field("faiz", "Yıllık Faiz Oranı", "%", 2.89, 0.1, 8, 0.01)
    + field("vade", "Vade", "Ay", 120, 6, 240, 1)
    + field_nr("masraf", "Dosya / Ekspertiz / Sigorta", "TL", 15000, "100", "0")
)
kk_stats = (
    stat("out_kredi", "Kredi Tutarı", "navy")
    + stat("out_taksit", "Aylık Taksit", "gold")
    + stat("out_toplam", "Toplam Geri Ödeme + Masraf", "navy")
    + stat("out_faiz", "Toplam Faiz", "red")
)
kk_shell = f"""      <div class="calc-shell">
        <div class="calc-shell-grid">
          <div class="calc-inputs">
{kk_inputs}
          </div>
          <div class="calc-stats">
{kk_stats}
          </div>
        </div>
      </div>"""
kk_explain = """          <h2>Nasıl hesaplanır?</h2>
          <p>Kredi tutarı = Konut bedeli − Peşinat. Aylık taksit, sabit taksitli (annüite) formülle hesaplanır:</p>
          <ul>
            <li>Aylık faiz = Yıllık faiz / 12</li>
            <li>Taksit = Kredi × r × (1+r)^n / ((1+r)^n − 1)</li>
            <li>Toplam faiz = (Taksit × Vade) − Kredi tutarı</li>
          </ul>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; banka teklifi ve güncel faiz oranları esas alınmalıdır. Yatırım tavsiyesi değildir.</div>"""

(araclar / "konut-kredisi.html").write_text(
    page(
        "Konut Kredisi Hesaplama (KK) - BM Capital Akademi",
        "Konut kredisi aylık taksit, toplam faiz ve geri ödemeyi hesaplayın. Ücretsiz KK hesaplama aracı.",
        "https://www.bmcapitalakademi.com/araclar/konut-kredisi.html",
        "Konut Kredisi",
        "Konut Kredisi Hesaplama",
        "Konut bedeli, peşinat, faiz ve vadeyi girerek aylık taksit ile toplam maliyeti görün.",
        "konut-kredisi",
        kk_shell,
        kk_explain,
        "konut-kredisi.html",
    ),
    encoding="utf-8",
)

# --- TK ---
tk_inputs = (
    field("finansman", "Finansman Tutarı", "TL", 3000000, 100000, 20000000, 10000)
    + field("taksit", "Aylık Taksit (Teklif)", "TL", 29000, 1000, 200000, 500)
    + field("vade", "Vade", "Ay", 120, 6, 240, 1)
    + field("teslim", "Teslim / Tahsis Süresi", "Ay", 13, 0, 120, 1)
    + field_nr("org", "Organizasyon Ücreti", "TL", 210000, "1000", "0")
)
tk_stats = (
    stat("out_toplam", "Toplam Nominal Maliyet", "gold")
    + stat("out_taksit_top", "Toplam Taksitler", "navy")
    + stat("out_teslim_odeme", "Teslime Kadar Ödeme + Org.", "navy")
    + stat("out_kalan", "Teslim Sonrası Kalan Taksit", "green")
)
tk_shell = f"""      <div class="calc-shell">
        <div class="calc-shell-grid">
          <div class="calc-inputs">
{tk_inputs}
          </div>
          <div class="calc-stats">
{tk_stats}
          </div>
        </div>
        <p id="out_warn" class="calc-disclaimer warn" style="display:none;margin-top:16px;">Toplam ödemeler finansman tutarının altında; teklif kalemlerini kontrol edin.</p>
      </div>"""
tk_explain = """          <h2>Nasıl hesaplanır?</h2>
          <p>Tasarruf finansmanı teklifindeki aylık taksit ve organizasyon ücreti kullanılır (faiz oranı üretilmez).</p>
          <ul>
            <li>Toplam taksit = Aylık taksit × Vade</li>
            <li>Toplam nominal = Toplam taksit + Organizasyon ücreti</li>
            <li>Teslime kadar ödeme = (Taksit × Teslim süresi) + Organizasyon</li>
          </ul>
          <p>NPV / fırsat maliyeti karşılaştırması için <a href="kk-vs-tk.html">KK vs TK</a> aracını kullanın.</p>
          <div class="calc-disclaimer">Bu araç bilgilendirme amaçlıdır; şirket sözleşmesi ve tahsis koşulları esas alınmalıdır. Yatırım tavsiyesi değildir.</div>"""

(araclar / "tasarruf-finansmani.html").write_text(
    page(
        "Tasarruf Finansmanı Hesaplama (TK) - BM Capital Akademi",
        "Tasarruf finansmanı toplam maliyet, teslime kadar ödeme ve kalan taksitleri hesaplayın.",
        "https://www.bmcapitalakademi.com/araclar/tasarruf-finansmani.html",
        "Tasarruf Finansmanı",
        "Tasarruf Finansmanı Hesaplama",
        "Teklifteki taksit, vade, teslim süresi ve organizasyon ücretiyle toplam maliyeti görün.",
        "tasarruf-finansmani",
        tk_shell,
        tk_explain,
        "tasarruf-finansmani.html",
    ),
    encoding="utf-8",
)

# --- KK vs TK ---
cmp_inputs = (
    """          <div class="calc-field">
            <label>Kullanım Amacı</label>
            <div class="calc-purpose">
              <label class="calc-purpose-opt"><input type="radio" name="amac" value="oturum" checked> Oturmak için alıyorum</label>
              <label class="calc-purpose-opt"><input type="radio" name="amac" value="yatirim"> Yatırım için alıyorum</label>
            </div>
          </div>
"""
    + field("finansman", "Finansman Tutarı", "TL", 3000000, 100000, 20000000, 10000)
    + field("pesinat", "Peşinat (ortak, bilgilendirme)", "TL", 600000, 0, 10000000, 10000)
    + field("vade", "Vade", "Ay", 120, 6, 240, 1)
    + field("teslim", "TF Teslim Süresi", "Ay", 13, 0, 120, 1)
    + field("taksit_tk", "TF Aylık Taksit", "TL", 29000, 1000, 200000, 500)
    + field("taksit_kk", "KK Aylık Taksit", "TL", 35000, 1000, 200000, 500)
    + field_nr("org", "TF Organizasyon Ücreti", "TL", 210000, "1000", "0")
    + field_nr("masraf", "KK Kredi Masrafı", "TL", 15000, "100", "0")
    + field("enf", "Yıllık Enflasyon", "%", 25, 10, 50, 1)
    + field("konut", "Yıllık Konut Değer Artışı", "%", 20, 5, 40, 1)
    + field("alt", "Yıllık Alternatif Getiri", "%", 30, 10, 50, 1)
    + field("kira", "Aylık Emsal Kira", "TL", 25000, 0, 50000, 500)
)

cmp_body = f"""      <div class="calc-shell">
        <div class="calc-shell-grid calc-shell-grid--wide">
          <div class="calc-inputs">
{cmp_inputs}
          </div>
          <div class="calc-compare">
            <div class="calc-winner" id="winner_box">
              <div class="calc-winner-label">Kazanan (daha düşük NBD maliyet)</div>
              <div class="calc-stat-value green" id="out_kazanan">—</div>
              <div class="calc-winner-adv">Avantaj: <strong id="out_avantaj">—</strong></div>
            </div>
            <div class="calc-stats">
{stat("out_nbd_tk", "Gerçek NBD — Tasarruf (TK)", "navy")}
{stat("out_nbd_kk", "Gerçek NBD — Konut Kredisi (KK)", "navy")}
{stat("out_nom_tk", "Nominal Toplam — TK", "navy")}
{stat("out_nom_kk", "Nominal Toplam — KK", "navy")}
            </div>
            <div class="calc-table-wrap">
              <table class="calc-table calc-compare-table">
                <thead>
                  <tr><th>Kalem</th><th>Tasarruf (TK)</th><th>Konut Kredisi (KK)</th></tr>
                </thead>
                <tbody>
                  <tr><td>Ödemelerin bugünkü değeri</td><td id="row_odeme_tk">—</td><td id="row_odeme_kk">—</td></tr>
                  <tr><td>Organizasyon / kredi masrafı</td><td id="row_masraf_tk">—</td><td id="row_masraf_kk">—</td></tr>
                  <tr><td>Bekleme maliyeti (kira)</td><td id="row_bekleme_tk">—</td><td id="row_bekleme_kk">—</td></tr>
                  <tr><td>Erken sahip olma avantajı</td><td id="row_erken_tk">—</td><td id="row_erken_kk">—</td></tr>
                  <tr><td>Kira geliri (yatırımsa)</td><td id="row_kira_tk">—</td><td id="row_kira_kk">—</td></tr>
                  <tr class="is-total"><td>Gerçek NBD maliyet</td><td id="row_nbd_tk">—</td><td id="row_nbd_kk">—</td></tr>
                </tbody>
              </table>
            </div>
            <p class="tools-hint" id="out_amac_note" style="margin-top:12px;"></p>
            <p class="tools-hint">Reel nominal (bilgi): TK <span id="out_reel_tk">—</span> · KK <span id="out_reel_kk">—</span></p>
          </div>
        </div>
      </div>"""

cmp_explain = """          <h2>Nasıl hesaplanır?</h2>
          <p><strong>Temel kural:</strong> Sadece toplam ödemeye bakılmaz. Paranın zaman değeri (iskonto), bekleme kirası ve erken sahip olunan konutun değer artışı birlikte değerlendirilir.</p>
          <ul>
            <li>Aylık iskonto = (1 + alternatif getiri)^(1/12) − 1</li>
            <li>Ödemelerin PV = her ay taksit / (1+r)^t</li>
            <li>Oturum: TF için teslim öncesi emsal kira PV (bekleme maliyeti)</li>
            <li>KK erken avantaj = [Finansman × (1+g)^teslim − Finansman] / (1+r)^teslim</li>
            <li>Yatırım: kira geliri PV (TF teslimden sonra, KK ay 1’den)</li>
          </ul>
          <p><strong>Varsayım:</strong> TF teslimde sabit nominal finansman verir; konut fiyatı artarsa fark KK lehine sayılır. Peşinat her iki modelde aynı kabul edilir (karşılaştırmada iptal olur).</p>
          <div class="calc-disclaimer">Bu hesaplama varsayımsaldır. Gerçek teklifler için banka ve tasarruf finansman kuruluşlarıyla görüşün. Yatırım tavsiyesi değildir. Hiçbir veri kaydedilmez.</div>"""

(araclar / "kk-vs-tk.html").write_text(
    page(
        "Konut Kredisi vs Tasarruf Finansmanı (KK vs TK) - BM Capital Akademi",
        "NPV ile konut kredisi ve tasarruf finansmanını karşılaştırın: bekleme maliyeti ve erken sahip olma avantajı dahil.",
        "https://www.bmcapitalakademi.com/araclar/kk-vs-tk.html",
        "KK vs TK",
        "KK vs TK Karşılaştırma",
        "Konut kredisi ile tasarruf finansmanını net bugünkü maliyet (NPV) ile yan yana görün.",
        "kk-vs-tk",
        cmp_body,
        cmp_explain,
        "kk-vs-tk.html",
    ),
    encoding="utf-8",
)

# Patch asides in existing tool pages
aside_re = re.compile(
    r'<aside class="tools-aside">.*?</aside>',
    re.DOTALL,
)
for html in araclar.glob("*.html"):
    text = html.read_text(encoding="utf-8")
    name = html.name
    new_aside = aside(name)
    if aside_re.search(text):
        text = aside_re.sub(new_aside, text)
        # bump css/js cache
        text = text.replace("style.css?v=araclar3", "style.css?v=araclar4")
        text = text.replace("araclar.js?v=3", "araclar.js?v=4")
        html.write_text(text, encoding="utf-8")
        print("aside:", name)
    else:
        print("skip aside:", name)

print("OK generate")
