# BM Capital Akademi — SEO / GEO / AI Görünürlük Yol Haritası

Site modeli: **canlı online (81 il) + İzmir yüz yüze** borsa eğitimi.
Alan adı: **www.bmcapitalakademi.com**

Hedef: "borsa eğitimi" ve şehir bazlı varyasyonları ("manisa borsa eğitimi", "istanbul borsa
eğitimi", "online borsa eğitimi" …) ile birlikte AKD / takas analizi gibi niş konularda
hem Google'da hem de yapay zekâ asistanlarında (ChatGPT, Claude, Perplexity, Google AI) çıkmak.

---

## Sitede tamamlananlar (kod)

### Temel SEO
- Tüm sayfalarda title, description, keywords, canonical, Open Graph, Twitter kartı
- `robots` meta (`index, follow, max-image-preview:large`)
- GEO etiketleri: ana sayfa İzmir (TR-35), şehir sayfaları kendi plaka koduyla (TR-01 … TR-81)
- `sitemap.xml` (ana sayfalar + rehberler), `sitemap-sehirler.xml` (82 URL)
- `.htaccess`: HTTPS + www zorlaması, şehir sayfaları için temiz URL, sondaki eğik çizgi 301
- `404.html`, optimize edilmiş `og-cover.jpg`

### Şehir sistemi (81 il)
- `api/cities.php` — il verisi (ad, slug, plaka, bölge, katılım modeli)
- `borsa-egitimi/il.php` — tek şablon, ile göre farklılaşan içerik üretir
  (başlık, açılış paragrafı, katılım formatları, şehre özel SSS, bölge içi iç linkler)
- `borsa-egitimi/index.php` — bölgeye göre gruplanmış hub sayfası
- URL biçimi: `https://www.bmcapitalakademi.com/borsa-egitimi/manisa`
- Katılım modeli 4 kademeli: İzmir (merkez, yüz yüze) → İstanbul/Ankara/Bursa/Antalya
  (grup talebine göre yüz yüze) → İzmir'e yakın Ege illeri → diğer iller (canlı online)

### Rehber içerikleri
`rehber/` altında 6 uzun içerik + hub sayfası:
AKD analizi nedir, BIST takas analizi, hisse senedi analizi nasıl yapılır,
BIST 100 nedir, borsa yatırım stratejileri, borsa eğitimi nasıl seçilir.

### Yapısal veri (Schema.org)
- `EducationalOrganization` — `areaServed` Türkiye geneli + 19 il, `knowsAbout`, `founder`,
  `employee`, `hasOfferCatalog`
- `Course` — `instructor`, `about`, `teaches`, `inLanguage`, online + onsite `CourseInstance`
- `Service` — her şehir sayfasında, `areaServed: City` + `availableChannel`
- `Person` — eğitmen profilinde (`jobTitle`, `knowsAbout`, `subjectOf`)
- `FAQPage`, `BreadcrumbList`, `ItemList`, `Product`
- JSON-LD tekrar koruması: aynı tipte iki blok basılmaz (`data-bm-ld`)

### AI / AEO katmanı
- `llms.txt` — marka, eğitmen, eğitimler, rehberler ve araçların AI modelleri için özeti
- `robots.txt`: GPTBot, ClaudeBot, OAI-SearchBot, ChatGPT-User, Claude-Web, anthropic-ai,
  PerplexityBot, Google-Extended, Applebot-Extended, Bingbot, YandexBot, CCBot → Allow
- Alıntılanabilir tanımlar: her rehber sayfası konuyu ilk paragrafta tek cümleyle tanımlar
- SSS veritabanına şehir ve konu bazlı yeni sorular eklendi (`seed_seo_faqs()`, idempotent)

### Analitik
- Kendi sunucu tarafı analitiği (tekil ziyaretçi, 7g / 30g / 12a periyot seçimi, hesap eşleşmesi)
- GA4 entegrasyonu: ölçüm kimliği panelden girilir, çerez onayından sonra yüklenir
  (Google Consent Mode uyumlu)

> Hissedar Finans (Ağustos / Alsancak Event) bu sitede yok; ayrı turda yapılacak.

---

## SENİN YAPMAN GEREKENLER (öncelik sırasıyla)

### 1) Dosyaları hostinge yükle
`public_html` içine üzerine yazarak yükle (Dosya Yöneticisi veya FileZilla).

**Dikkat:**
- `api/config.php` dosyasını **silme / ezme** — hostingdeki DB şifren orada.
- `api/site_brand.local.php` varsa onu da ezme.
- `.htaccess` mutlaka yüklenmeli; şehir sayfaları onsuz 404 verir.
- Yeni klasörler: `borsa-egitimi/`, `rehber/`
- Yeni kök dosyalar: `sitemap-sehirler.xml`, `llms.txt`

### 2) Şehir sayfalarını kontrol et
Yükledikten sonra tarayıcıda şunları aç:
- `https://www.bmcapitalakademi.com/borsa-egitimi/` → 81 il listelenmeli
- `https://www.bmcapitalakademi.com/borsa-egitimi/manisa` → Manisa sayfası açılmalı
- `https://www.bmcapitalakademi.com/borsa-egitimi/izmir` → yüz yüze vurgulu içerik

Sayfa açılmıyorsa `.htaccess` yüklenmemiş veya `mod_rewrite` kapalıdır (cPanel → destek).

### 3) Google Search Console
1. https://search.google.com/search-console
2. Sitemap'ler → şu ikisini ekle:
   - `sitemap.xml`
   - `sitemap-sehirler.xml`
3. URL denetimi → **dizine eklenmesini iste** (öncelik sırası):
   - `/borsa-egitimi/`
   - `/borsa-egitimi/izmir`, `/borsa-egitimi/istanbul`, `/borsa-egitimi/ankara`,
     `/borsa-egitimi/manisa`, `/borsa-egitimi/bursa`
   - `/rehber/` ve 6 rehber sayfası
   - `/egitimler.html` (içeriği güncellendi)

> 81 ilin tamamını elle isteme; sitemap yeterli. Google öncelikli olanları hızlı, uzun
> kuyruğu birkaç hafta içinde tarar.

### 4) GA4 ölçüm kimliğini gir
1. https://analytics.google.com → mülk oluştur → **Ölçüm Kimliği** (`G-XXXXXXXXXX`)
2. Admin panel → Ayarlar → **GA Ölçüm Kimliği** alanına yapıştır → kaydet
3. Siteyi aç, çerez bandındaki onayı ver, GA4 "Gerçek Zamanlı" ekranında kendini gör

Panel kullanmıyorsan `api/site_brand.local.php` içine:
`define('GA_MEASUREMENT_ID', 'G-XXXXXXXXXX');`

### 5) Google İşletme Profili
1. https://business.google.com
2. Ad: **BM Capital Akademi** · Kategori: Eğitim Merkezi
3. Cadde adresi yoksa: **hizmet bölgesi** olarak İzmir'i ve çevre illeri ekle (adres gösterme)
4. Site: `https://www.bmcapitalakademi.com`
5. Telefon, fotoğraf, doğrulama
6. Haftada 1 gönderi (yeni rehber içeriği, grup duyurusu, piyasa notu)

### 6) Google yorumları
Öğrencilerden gerçek yorum iste. Yerel ve "yakınımdaki" aramalarında en güçlü sinyal budur.
Yorumu olan bir işletme profili, şehir sayfalarının sıralamasını da yukarı çeker.

### 7) Bing Webmaster Tools
https://www.bing.com/webmasters → Search Console'dan içe aktar (tek tıkla).
**Neden önemli:** ChatGPT'nin web aramaları Bing indeksini kullanır.

### 8) Yandex Webmaster
https://webmaster.yandex.com → site ekle → her iki sitemap'i gönder.

### 9) Sosyal ve dış sinyaller
- Profil linki: `bmcapitalakademi.com`
- YouTube / LinkedIn / X açıklamalarına rehber sayfası linkleri
- Eğitmen profilini (`/egitmen-profil.html?id=kamil-bilen`) sosyal biyografilerde kullan

---

## İçerik takvimi (sıralamayı büyüten kısım)

Kod tarafı bitti; bundan sonrası düzenli içerik. Ayda 2 yeni rehber içeriği hedefle:

| Öneri konu | Hedef arama |
| --- | --- |
| Temettü nedir, nasıl hesaplanır | temettü hesaplama |
| Bedelli / bedelsiz sermaye artırımı farkı | bedelli bedelsiz nedir |
| Halka arz nasıl değerlendirilir | halka arz analizi |
| Stop loss nasıl belirlenir | zarar kes seviyesi |
| Algoritmik trade nereden başlanır | algoritmik işlem eğitimi |
| Borsada ilk 1000 TL ile ne yapılır | borsaya nasıl başlanır |

Her yeni içerikte: tek cümlelik tanım (ilk paragraf), `Article` + `FAQPage` şeması,
ilgili şehir/rehber sayfalarına iç link, `sitemap.xml`'e satır ekle.

---

## Beklenti (gerçekçi takvim)

- **1–2 hafta:** `site:bmcapitalakademi.com` ile tüm şehir sayfaları görünür
- **2–4 hafta:** "BM Capital Akademi" marka araması net 1. sıra
- **1–3 ay:** "izmir borsa eğitimi", "online borsa eğitimi" ilk sayfa
- **3–6 ay:** şehir bazlı uzun kuyruk ("manisa borsa eğitimi", "denizli borsa kursu")
  ilk sayfa; AKD / takas analizi aramalarında ilk sıralar
- **6+ ay:** genel "borsa eğitimi" aramasında ilk sayfa — bu kısım backlink ve
  yorum sayısına bağlı, sadece kodla alınmaz

AI asistanlarında görünürlük indeksleme hızına bağlıdır; Bing kaydı yapıldıktan sonra
ChatGPT tarafında 2–6 hafta içinde alıntılanma başlar.

---

## Bilinçli olarak yapılmayanlar

- AMP
- Sahte cadde adresi / var olmayan şube ("İzmir dışında şubemiz yok" her şehir sayfasında açıkça yazılı)
- Satın alınmış backlink, PBN
- "AI'da 1. sıra garantisi" iddiası
- 81 ili aynı metinle dolduran kopya içerik (her sayfa katılım modeline göre farklılaşır)
