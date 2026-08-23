# BM Capital — Yönetim Paneli Kurulumu (cPanel / PHP + MySQL)

> **Canlı yayın sırası (domain → iyzico → satış):** [CANLI-YAYIN.md](CANLI-YAYIN.md)  
> Kontrol API: `/api/launch_status.php` · iyzico: `/api/iyzico_status.php`

Bu site artık **statik sayfalar + PHP/MySQL yönetim paneli** olarak çalışır.
İçerik (eğitimler, ürünler, S.S.S.), iletişim mesajları ve ziyaretçi istatistikleri
panelden yönetilir; siteye giren herkes güncel içeriği görür.

## 1. Dosyaları yükleyin
Tüm klasörü (index.html, assets/, api/, admin/, yasal/ ...) hostingde sitenizin
kök dizinine (genelde `public_html`) yükleyin.

## 2. Veritabanı oluşturun (cPanel)
1. cPanel > **MySQL® Veritabanları**
2. Yeni bir veritabanı oluşturun (örn. `kullanici_bmcapital`).
3. Yeni bir kullanıcı oluşturun ve **güçlü bir şifre** verin.
4. Kullanıcıyı veritabanına ekleyin ve **TÜM YETKİLER**'i verin.

## 3. Bağlantı bilgilerini girin
`api/config.php` dosyasını açın (cPanel Dosya Yöneticisi > Düzenle) ve doldurun:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kullanici_bmcapital'); // oluşturduğunuz DB adı
define('DB_USER', 'kullanici_bmadmin');   // DB kullanıcısı
define('DB_PASS', 'GÜÇLÜ_ŞİFRE');         // DB şifresi
```

## 4. Kurulumu çalıştırın
Tarayıcıda şu adrese gidin:

```
https://siteadresiniz.com/api/install.php
```

- Yönetici **kullanıcı adı** ve **şifre** belirleyin (şifre en az 6 karakter).
- "Kurulumu Başlat" deyin. Tablolar oluşturulur ve mevcut içerik otomatik yüklenir.

## 5. Güvenlik ve operasyon
Canlı sunucuda `INSTALL_LOCKED=true` ve localhost dışı erişim **403** döner. Kurulum dosyasını silebilirsiniz.

Kod güncellemesi: `git pull` (önerilir) veya FTP. Zip paketinden `*.local.php` çıkmaz (`scripts/package-deploy.ps1`).

Cron (15 dk): `/api/cron.php?key=CRON_KEY` — iyzico iade senkronu, abonelik süresi, eski jetonlar.  
Yedek: `scripts/backup.ps1` (MySQL + `uploads/`).

## 6. Panele girin
```
https://siteadresiniz.com/admin/login.php
```
Belirlediğiniz kullanıcı adı/şifre ile giriş yapın.

## 6b. Eğitmen paneli
Aynı kullanıcı ile:
```
https://siteadresiniz.com/egitmen/login.php
```
Kurs oluşturma, açılış sayfası, hedef öğrenciler, müfredat ve **video yükleme** (dosyalar `uploads/courses/` altında).

**Video boyutu:** cPanel > Select PHP Version > Options içinde `upload_max_filesize` ve `post_max_size` değerlerini (ör. 128M) yükseltmeniz gerekebilir.

---

## Panelde neler var?
- **Panel:** Bugünkü ziyaretçi, son 7 gün, toplam, okunmamış mesaj sayıları + günlük grafik.
- **Eğitimler / Ürünler:** Ekle, düzenle, sil. Fiyat, açıklama, özellikler, tarihler, müfredat vb.
- **S.S.S.:** Soru-cevap ekle/düzenle/sil.
- **İletişim:** Formdan gelen tüm mesajlar; okundu işaretle, e-posta/WhatsApp ile yanıtla, sil.
- **İstatistik:** Günlük ve tekil ziyaretçi sayıları.
- **Ayarlar:** Telefon, WhatsApp, sosyal medya, IBAN, satıcı kimliği, abonelik, SMTP; **şifre değiştirme**.
- **İnceleme:** Para çekilmiş, erişim açılmamış siparişler. Elle kurs erişimi Öğrenciler sekmesindedir.

### Müfredat yazım biçimi
Eğitim düzenleme ekranındaki "Müfredat" kutusunda:
```
# 1. GÜN: Temel Analiz
## 1. TEMEL ANALİZE GİRİŞ
- Temel analiz nedir
- Değer yatırımcılığı
## 2. MAKRO EKONOMİ
- Enflasyon
# 2. GÜN: Teknik Analiz
## 1. TEKNİK ANALİZ NEDİR?
```
- `#` = Gün/ana başlık, `##` = bölüm başlığı, `-` = madde.

---

## İçeriği güncellemenin kısa yolu
Artık eğitim/ürün/SSS eklemek veya fiyat/metin değiştirmek için **koda dokunmanıza gerek yok.**
Panelden değiştirin, kaydedin — değişiklik anında canlı sitede görünür.

Kod/tasarım değişiklikleri için dosyaları hostinge yeniden yüklemeniz yeterlidir
(FileZilla/FTP ile sürükle-bırak veya cPanel Dosya Yöneticisi).

## Notlar
- PHP çalışmıyorsa (örn. yerelde `python -m http.server` ile açarsanız) site,
  `assets/js/data.js` içindeki yedek içerikle yine de açılır; ancak panel ve
  ziyaretçi/iletişim kayıtları yalnızca PHP'li hostingde çalışır.
- Yerelde denemek isterseniz PHP ile: `php -S localhost:8000` komutunu proje
  kökünde çalıştırıp `http://localhost:8000` adresini açın.
