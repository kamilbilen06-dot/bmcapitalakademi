# Canlı yayın — Domain → iyzico → Satış

Teknopark şirketi aktifse sıra şirket kurmak değil; **marka + domain + iyzico** bağlamaktır.
**Yeni domain (HTTPS) olmadan canlı kart ödemesi açılamaz.**

Kontrol: `https://SIZIN-DOMAIN/api/launch_status.php`  
Admin → Ayarlar → “Canlı Yayın” kutusu. iyzico: `/api/iyzico_status.php`

## Sıra

### 1) Marka + domain
1. 3–5 aday isim; `.com` / `.com.tr` müsaitlik + sosyal kullanıcı adı.
2. Domain’i mümkünse teknopark **şirket adına** alın.
3. Projede:
   ```text
   api/site_brand.local.example.php  →  api/site_brand.local.php
   ```
   `BRAND_*`, `PUBLIC_SITE_URL=https://www....`, `BRAND_DOMAIN_READY=true` doldurun.
   Yasal satıcı unvan/adres/vergi/MERSİS: Admin → Ayarlar.

### 2) Hosting + DNS + HTTPS
- PHP + MySQL; video için **yüksek disk** (`uploads/courses`).
- DNS A/CNAME → hosting; Let’s Encrypt SSL.
- `uploads/courses` yazılabilir olsun.
- Apache ise `uploads/.htaccess` video/pdf/zip doğrudan URL’yi kapatır. Nginx ise aynı kuralları sunucu bloğuna taşıyın.

### 3) Kod + DB
1. cPanel **Git™ Version Control** → Update from Remote → **Deploy HEAD Commit** (`.cpanel.yml` `public_html`’e kopyalar; `*.local.php` ve `uploads/courses` dokunulmaz).
2. `api/config.local.php` → canlı DB + `BOOTSTRAP_ADMIN_*` (bir kez). MySQL içeriği (kurs, öğrenci) Git’te yoktur; phpMyAdmin import **bir kez**.
3. `api/install.php` canlıda 403 verir.

**Yüklemeyin (gizli):** `api/*.local.php`, `.tools/`, `backups/`, yerel DB dump.

### 4) iyzico
1. [Üye işyeri](https://merchant.iyzipay.com) — sandbox: sandbox-merchant.iyzipay.com.
2. API anahtarları:
   ```text
   api/iyzico_config.local.example.php  →  api/iyzico_config.local.php
   ```
   `IYZICO_MERCHANT_ID` abonelik webhook imzası için **zorunlu**.
3. Merchant Bildirimleri URL:
   ```text
   https://DOMAIN/api/iyzico_webhook.php
   ```
   Kurs ve abonelik bildirimi aynı adres. İmzası uymayan olay **işlenmez**.
4. Test: `IYZICO_TEST_MODE=1` → `odeme.php` test kartı. Canlıda `0`.
5. Eski `odeme.html` (PayTR) kamu CTA’dan kaldırıldı; yönlendirme `odeme.php`’ye gider. Havale/EFT `odeme.php` içindedir.

### 5) Abonelik
- Sandbox: günlük çekim. Canlı: aylık.
- Öğrenci iptali: `ogrenci/aboneliklerim.php` (iade butonu yok).
- WhatsApp grubu elle eklenir. Kamuya açık tanıtım: `/abonelik.html`.
- Dönemi geçmiş `active` kayıtlar cron ile `expired` yazılır.

### 6) Cron + yedek
- Her 15 dk: `https://DOMAIN/api/cron.php?key=CRON_KEY`  
  (iade senkronu, abonelik expiry, eski jeton temizliği)
- Günlük: `powershell -File scripts/backup.ps1` → `backups/` (MySQL + uploads). Kopyayı VPS dışında tutun.

### 7) Hoca paylaşımı
1. Eğitmen paneli → kurs **Yayında**.
2. **Yayın Durumu** → kurs / ödeme linki (`odeme.php`).
3. Aboneler sekmesi, yönetici ayarındaki bağlı eğitmene göre listelenir.

## Hızlı dosyalar

| Dosya | Ne işe yarar |
|-------|----------------|
| `api/site_brand.local.php` | Marka + PUBLIC_SITE_URL |
| `api/iyzico_config.local.php` | API key / secret / merchant id |
| `api/mail_config.local.php` | SMTP |
| `api/cron.php` | Mutabakat |
| `api/launch_status.php` | Checklist JSON |

## Bilinçli sonra
Kupon, e-arşiv, GA, eğitmen hakediş CSV.
