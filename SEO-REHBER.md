# BM Capital Akademi — SEO / GEO Yol Haritası



Site modeli: **canlı online + İzmir yüz yüze** borsa eğitimi.

Alan adı: **www.bmcapitalakademi.com**



---



## Sitede tamamlananlar (kod)



- Title, description, keywords, canonical, Open Graph, Twitter kartı

- GEO etiketleri (İzmir / TR-35)

- Yapısal veriler: EducationalOrganization, Course (online + onsite Instance, startDate/endDate, sertifika), Product, FAQPage, BreadcrumbList

- SSS: İzmir yüz yüze + online soruları (yanlış “fiziki yok” kaldırıldı)

- robots.txt: admin/api kapalı + GPTBot / ClaudeBot / OAI-SearchBot / PerplexityBot / Google-Extended Allow

- sitemap.xml, .htaccess (HTTPS/www), 404, og-cover.png



> Hissedar Finans (Ağustos / Alsancak Event) bu sitede yok; ayrı turda yapılacak.



---



## SENİN YAPMAN GEREKENLER (öncelik)



### 1) Hostinge bu SEO güncellemesini yükle

Aşağıdaki dosyaları `public_html` içine üzerine yazarak yükle (Dosya Yöneticisi veya FileZilla).  

**`api/config.php` dosyasını silme / ezme** — hostingdeki DB şifren kalmalı.



### 2) Google Search Console

1. https://search.google.com/search-console  

2. Sitemap: `sitemap.xml` gönderilmiş olmalı  

3. Ana sayfa → URL denetimi → **Dizine eklenmesini iste**



### 3) Google İşletme Profili

1. https://business.google.com  

2. Ad: **BM Capital Akademi**  

3. Kategori: Eğitim Merkezi  

4. Cadde adresi yoksa: **hizmet bölgesi İzmir** (adres gösterme)  

5. Site: `https://www.bmcapitalakademi.com`  

6. Telefon, fotoğraf, doğrulama, düzenli gönderi



### 4) Google yorumları

Öğrencilerden gerçek yorum iste (yerel sıralamada kritik).



### 5) Yandex Webmaster (önerilir)

https://webmaster.yandex.com → site ekle → `sitemap.xml`



### 6) Sosyal

Profil site linki: `bmcapitalakademi.com` (X’te tek alan yeterli)



---



## Panel kuruluysa SSS güncellemesi



Koddaki `data.js` yedektir. Veritabanında eski SSS varsa admin → **S.S.S.** bölümünden  

“yüz yüze + online” cevaplarını elle güncelleyin.  

`install.php` yalnızca boş DB’de seed eder; mevcut kayıtları değiştirmez.



---



## Beklenti

- 1–2 hafta: `site:bmcapitalakademi.com`  

- 2–4 hafta: “BM Capital Akademi” marka araması  

- 1–3 ay: “İzmir borsa eğitimi” / online-yüz yüze (GBP + yorum + içerik)



## Bilinçli olarak yapılmayanlar

AMP, sahte cadde adresi, black-hat backlink, “AI 1. sıra garantisi”

