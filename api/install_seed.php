<?php
/** Egitim/SSS/ayar tohumlari — install.php ve otomatik kurulum ortak. */

function seed_settings($pdo) {
    $defaults = [
        'telefon' => '0533 449 09 66',
        'whatsapp' => '905334490966',
        'instagram' => 'https://www.instagram.com/meteakyol1975/',
        'twitter' => 'https://x.com/DrMeteAkyol',
        'iban' => 'TR61 0006 7010 0000 0076 3994 01',
        'banka' => 'Yapı Kredi',
        'hesap_adi' => 'Marmara Revizyon A.Ş.',
        'instructor_share_pct' => '60',
        'emailjs_public' => 'GCgkZATeZisTHfirF',
        'emailjs_service' => 'service_z1424c5',
        'emailjs_template' => 'template_ktpxiei',
        'emailjs_to' => 'kamilbilen06@gmail.com',
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = v");
    foreach ($defaults as $k => $v) { $stmt->execute([$k, $v]); }
}

function seed_faqs($pdo) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
    if ($cnt > 0) return;
    $faqs = [
        ['Eğitimler nasıl yapılıyor?', 'Eğitimlerimiz canlı online veya İzmir\'de yüz yüze yapılabilir. Belirtilen tarih ve saatlerde interaktif olarak katılır, sorularınızı canlı sorabilirsiniz. Grup dersi veya birebir özel ders seçenekleri mevcuttur.'],
        ['İzmir\'de borsa eğitimi veriyor musunuz?', 'Evet. BM Capital Akademi, İzmir\'de yüz yüze borsa eğitimi ve tüm Türkiye\'ye canlı online eğitim sunar. Teknik & temel analiz, takas/AKD analizi ve algoritmik trade eğitimlerine online veya İzmir yüz yüze katılabilirsiniz.'],
        ['Eğitimler yüz yüze mi online mı?', 'İkisi de. Canlı online (uzaktan) veya İzmir\'de yüz yüze eğitim alabilirsiniz. Kayıt sırasında tercihinizi belirtmeniz yeterlidir; birebir özel ders de mümkündür.'],
        ['Ön bilgi gerekiyor mu?', 'Hayır. Eğitim A\'dan Z\'ye kurgulanmıştır; sıfırdan başlayanlar da rahatlıkla takip edebilir.'],
        ['Ödeme nasıl yapılıyor?', 'Kart ile ödeme iyzico üzerinden yapılır (odeme.php). Havale/EFT seçerseniz dekontu iletmeniz gerekir; yönetici onayından sonra erişim açılır.'],
        ['Sertifika veriliyor mu?', 'Evet, eğitimi tamamlayan katılımcılara katılım sertifikası verilir.'],
        ['Hediye paketinde neler var?', 'Dr. Mete Akyol\'un "Metematiksel Analiz" kitabı ve 1 aylık ücretsiz WhatsApp analiz grubu üyeliği hediye edilir.'],
        ['Eğitim kaydı sonradan izlenebiliyor mu?', 'Kayıt/tekrar izleme imkânı için lütfen WhatsApp üzerinden bizimle iletişime geçin; güncel koşullar paylaşılacaktır.'],
        ['BIST Robotu satışta mı?', 'Evet, BIST Robotu ürünümüz hazırdır. Detaylı bilgi ve kurulum için doğrudan WhatsApp veya telefon ile bizimle iletişime geçebilirsiniz.'],
    ];
    $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)");
    foreach ($faqs as $i => $f) { $stmt->execute([$f[0], $f[1], $i]); }
}

/** Tarihsiz eğitim için eski kampanya SSS kaydını mevcut kurulumlardan kaldır. */
function remove_archived_training_faq(PDO $pdo): void {
    try {
        $pdo->prepare("DELETE FROM faqs WHERE question = ?")
            ->execute(['2026 Nisan döneminde teknik-temel eğitim ne zaman?']);
    } catch (Throwable $e) {
        error_log('eski eğitim SSS temizleme: ' . $e->getMessage());
    }
}

function seed_modules($pdo) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    if ($cnt > 0) return;

    $mufredat1 = [
        ['baslik' => "1. GÜN: A'dan Z'ye Temel Analiz", 'bolumler' => [
            ['baslik' => '1. TEMEL ANALİZE GİRİŞ', 'maddeler' => ['Temel analiz nedir','Temel analiz vs teknik analiz','Değer yatırımcılığı yaklaşımı','Piyasa fiyatı vs gerçek değer','Finansal analiz neden yapılır']],
            ['baslik' => '2. MAKRO EKONOMİ ANALİZİ', 'maddeler' => ['Ekonomik büyüme (GSYH)','Enflasyon','Faiz oranları','Para politikası','Döviz kuru','Yatırımcı için kritik göstergeler']],
            ['baslik' => '3. SEKTÖR ANALİZİ', 'maddeler' => ['Sektör büyüklüğü','Rekabet yapısı','Giriş engelleri','Teknolojik değişim','Regülasyonlar']],
            ['baslik' => '4. ŞİRKET ANALİZİ', 'maddeler' => []],
        ]],
        ['baslik' => "2. GÜN: A'dan Z'ye Temel Analiz – Bölüm 2", 'bolumler' => [
            ['baslik' => '5. FİNANSAL TABLO ANALİZİ', 'maddeler' => ['Bilanço analizi','Gelir tablosu analizi','Nakit akış analizi']],
            ['baslik' => '6. ORAN ANALİZİ', 'maddeler' => ['Likidite oranları','Faaliyet oranları','Karlılık oranları','Borçluluk oranları']],
            ['baslik' => '7. NAKİT DÖNGÜSÜ VE İŞLETME SERMAYESİ', 'maddeler' => []],
            ['baslik' => '8. BÜYÜME ANALİZİ', 'maddeler' => []],
            ['baslik' => '9. DEĞERLEME YÖNTEMLERİ', 'maddeler' => []],
            ['baslik' => '10. SWOT ANALİZİ', 'maddeler' => []],
            ['baslik' => '11. YATIRIM KARAR MODELİ', 'maddeler' => []],
        ]],
        ['baslik' => '3. GÜN: Teknik Analiz', 'bolumler' => [
            ['baslik' => '1. TEKNİK ANALİZ NEDİR?', 'maddeler' => []],
            ['baslik' => '2. GRAFİK TÜRLERİ', 'maddeler' => []],
            ['baslik' => '3. ZAMAN DİLİMLERİ VE ÖNEMİ', 'maddeler' => []],
            ['baslik' => '4. DESTEK VE DİRENÇ', 'maddeler' => []],
            ['baslik' => '5. TREND ANALİZİ', 'maddeler' => []],
            ['baslik' => '6. FORMASYON ANALİZİ', 'maddeler' => []],
            ['baslik' => '7. TUZAKLAR VE TESPİTİ', 'maddeler' => []],
            ['baslik' => '8. HACİM VE FİYAT İLİŞKİSİ', 'maddeler' => []],
            ['baslik' => '9. KISA VADELİ TRADİNG STRATEJİLERİ', 'maddeler' => []],
        ]],
        ['baslik' => '4. GÜN: AKD ve Takas Analizi', 'bolumler' => [
            ['baslik' => '1. ARACI KURUM DAĞILIMI ANALİZİNE GİRİŞ', 'maddeler' => []],
            ['baslik' => '2. KURUMLAR ANALİZİ', 'maddeler' => []],
            ['baslik' => '3. KURUM STRATEJİLERİ', 'maddeler' => []],
            ['baslik' => '4. PARA GİRİŞ/ÇIKIŞ ANALİZİ', 'maddeler' => []],
            ['baslik' => '5. MALİYET ANALİZİ', 'maddeler' => []],
            ['baslik' => '6. ARACI KURUM DAĞILIMI ANALİZİ UYGULAMASI', 'maddeler' => []],
            ['baslik' => '7. TAKAS ANALİZİ UYGULAMASI', 'maddeler' => []],
            ['baslik' => '8. AKD VE TAKAS ANALİZİNİN BİRLİKTE KULLANILMASI', 'maddeler' => []],
        ]],
        ['baslik' => '5. GÜN: Algoritmik Trade & Soru Cevap', 'bolumler' => [
            ['baslik' => '1. İNDİKATÖRLER', 'maddeler' => []],
            ['baslik' => '2. TARAMALAR', 'maddeler' => []],
            ['baslik' => '3. TRADİNGVİEW TARAMA NASIL YAPILIR?', 'maddeler' => []],
            ['baslik' => '4. MATRİKS TARAMA NASIL YAPILIR?', 'maddeler' => []],
            ['baslik' => '5. İNDİKATÖR OPTİMİZASYONU', 'maddeler' => []],
            ['baslik' => '6. ALIM-SATIM SİSTEMİ OLUŞTURMA', 'maddeler' => []],
            ['baslik' => '7. BACKTEST VE OPTİMİZASYON', 'maddeler' => []],
            ['baslik' => '8. SORU CEVAP OTURUMU', 'maddeler' => ['Eğitim sırasında sorularınız cevaplandırılır','Canlı uygulamalı örnek çözümleri','Kişisel danışmanlık ve rehberlik','İleri düzey konulardaki açıklamalar']],
        ]],
    ];

    $modules = [
        [
            'type' => 'egitim', 'slug' => 'teknik-temel-algoritmik',
            'title' => 'Teknik - Temel Analiz & Algoritmik Trade Eğitimi',
            'short_desc' => "Temel analizden algoritmik trade sistemlerine kadar A'dan Z'ye yatırımcı eğitimi. Canlı online veya İzmir yüz yüze, uygulamalı.",
            'image' => 'assets/img/egitim-analiz.svg', 'video' => null, 'video_poster' => null,
            'price' => '20.000 TL', 'price_note' => '(KDV dahil)', 'duration' => '20 saat',
            'egitim_turu' => 'Canlı Online veya İzmir Yüz Yüze Eğitim', 'instructors' => 'Dr. Kamil BİLEN', 'etiket' => null,
            'katilim_not' => 'Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.',
            'tarih_not' => null, 'featured' => 1, 'sort_order' => 1,
            'data' => [
                'ozellikler' => ['Temel analiz ile şirket değerini doğru okuma','Teknik analiz ile doğru zamanlama yapabilme','AKD ve takas analizi ile kurumsal para hareketlerini yorumlama','Algoritmik düşünce yapısı kazanma','Kendi sisteminizi oluşturma ve test etme becerisi'],
                'aciklama' => ['Finansal piyasalarda başarılı olmak tesadüf değildir.','Bilimsel yöntem, veri analizi ve disiplinli sistem gerektirir.','Bu eğitim, yatırım kararlarınızı duygusal değil analitik temelde alabilmeniz için tasarlanmıştır. Riskini yöneten ve sürdürülebilir getiri hedefleyen yatırımcılar için idealdir.'],
                'hediye' => [], 'hediyeGorsel' => '', 'tarihler' => [],
                'mufredat' => $mufredat1,
            ],
        ],
        [
            'type' => 'egitim', 'slug' => 'takas-akd-analizi',
            'title' => 'Takas & Aracı Kurum Dağılımı (AKD) Analizi Eğitimi',
            'short_desc' => 'Kurumsal para hareketlerini takip edin. Takas verisi ve aracı kurum dağılımı ile büyük oyuncuların adımlarını okuyun.',
            'image' => 'assets/img/egitim-takas.svg', 'video' => null, 'video_poster' => null,
            'price' => '10.000 TL', 'price_note' => '(KDV dahil)', 'duration' => 'Modüler',
            'egitim_turu' => 'Canlı Online veya İzmir Yüz Yüze Eğitim', 'instructors' => 'Dr. Mete AKYOL, Dr. Kamil BİLEN', 'etiket' => null,
            'katilim_not' => 'Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.',
            'tarih_not' => null, 'featured' => 1, 'sort_order' => 2,
            'data' => [
                'ozellikler' => ['Aracı kurum dağılımı (AKD) verisini okuma','Takas analizi ile kurumsal alım-satım tespiti','Para giriş/çıkış ve maliyet analizi','AKD ve takas analizini birlikte kullanma'],
                'aciklama' => ['Piyasada büyük hacimli kurumsal oyuncuların hareketlerini takip etmek, doğru zamanlama için kritik bir avantajdır.','Bu eğitimde takas verisini ve aracı kurum dağılımını okuyarak paranın nereye aktığını yorumlamayı öğrenirsiniz.'],
                'hediye' => [], 'hediyeGorsel' => '', 'tarihler' => [],
                'mufredat' => [['baslik' => 'Takas & AKD Analizi', 'bolumler' => [
                    ['baslik' => '1. ARACI KURUM DAĞILIMI ANALİZİNE GİRİŞ', 'maddeler' => []],
                    ['baslik' => '2. KURUMLAR ANALİZİ VE STRATEJİLERİ', 'maddeler' => []],
                    ['baslik' => '3. PARA GİRİŞ/ÇIKIŞ ANALİZİ', 'maddeler' => []],
                    ['baslik' => '4. MALİYET ANALİZİ', 'maddeler' => []],
                    ['baslik' => '5. AKD & TAKAS UYGULAMALARI', 'maddeler' => []],
                    ['baslik' => '6. AKD VE TAKAS ANALİZİNİN BİRLİKTE KULLANILMASI', 'maddeler' => []],
                ]]],
            ],
        ],
        [
            'type' => 'egitim', 'slug' => 'algoritmik-trade',
            'title' => 'Algoritmik Trade Eğitimi',
            'short_desc' => 'Stratejinizi kurallara dökün. İndikatör, tarama, backtest ve otomatik alım-satım sistemi kurmayı öğrenin.',
            'image' => 'assets/img/egitim-algo.svg', 'video' => null, 'video_poster' => null,
            'price' => '10.000 TL', 'price_note' => '(KDV dahil)', 'duration' => 'Modüler',
            'egitim_turu' => 'Canlı Online veya İzmir Yüz Yüze Eğitim', 'instructors' => 'Dr. Mete AKYOL, Dr. Kamil BİLEN', 'etiket' => null,
            'katilim_not' => 'Canlı online veya İzmir yüz yüze · Grup dersi veya birebir özel ders olarak alınabilir.',
            'tarih_not' => null, 'featured' => 1, 'sort_order' => 3,
            'data' => [
                'ozellikler' => ['İndikatör kullanımı ve optimizasyonu','TradingView & Matriks tarama teknikleri','Alım-satım sistemi oluşturma','Backtest ve optimizasyon ile sistem doğrulama'],
                'aciklama' => ['Algoritmik trade, yatırım kararlarını kurallara dökerek duygudan arındıran ve disiplinli hale getiren bir yaklaşımdır.','Bu eğitimde kendi alım-satım sisteminizi kurmayı, test etmeyi ve optimize etmeyi öğrenirsiniz.'],
                'hediye' => [], 'hediyeGorsel' => '', 'tarihler' => [],
                'mufredat' => [['baslik' => 'Algoritmik Trade', 'bolumler' => [
                    ['baslik' => '1. İNDİKATÖRLER', 'maddeler' => []],
                    ['baslik' => '2. TARAMALAR', 'maddeler' => []],
                    ['baslik' => '3. TRADİNGVİEW TARAMA NASIL YAPILIR?', 'maddeler' => []],
                    ['baslik' => '4. MATRİKS TARAMA NASIL YAPILIR?', 'maddeler' => []],
                    ['baslik' => '5. İNDİKATÖR OPTİMİZASYONU', 'maddeler' => []],
                    ['baslik' => '6. ALIM-SATIM SİSTEMİ OLUŞTURMA', 'maddeler' => []],
                    ['baslik' => '7. BACKTEST VE OPTİMİZASYON', 'maddeler' => []],
                ]]],
            ],
        ],
        [
            'type' => 'urun', 'slug' => 'bist-robotu',
            'title' => 'BIST Robotu',
            'short_desc' => 'BIST hisselerinde stratejinize göre otomatik alım-satım sinyalleri üreten algoritmik trade robotu.',
            'image' => 'assets/img/urun-robot.svg', 'video' => 'assets/video/algo-robot.mp4', 'video_poster' => 'assets/img/algo-robot-poster.jpg',
            'price' => null, 'price_note' => '', 'duration' => null,
            'egitim_turu' => null, 'instructors' => null, 'etiket' => 'Algoritmik Trade',
            'katilim_not' => null, 'tarih_not' => null, 'featured' => 1, 'sort_order' => 1,
            'data' => [
                'ozellikler' => ['Strateji tabanlı otomatik alım-satım sinyalleri','TradingView / Matriks uyumlu tarama mantığı','İndikatör optimizasyonu ve backtest desteği','Disiplinli, duygusuz işlem yönetimi'],
                'aciklama' => ['BIST Robotu, kendi stratejinizi kurallara dökerek piyasayı tarayan ve size sinyal üreten bir algoritmik trade çözümüdür.','Ürünümüz hazırdır. Detaylı bilgi ve kurulum için hemen bizimle iletişime geçebilirsiniz.'],
            ],
        ],
        [
            'type' => 'urun', 'slug' => 'strateji-yazdirma',
            'title' => 'Strateji Yazdırma',
            'short_desc' => 'Hayalinizdeki alım-satım stratejisini bize anlatın, sizin için kodlayalım. Siz hayal edin, biz yazalım.',
            'image' => 'assets/img/urun-strateji.svg', 'video' => null, 'video_poster' => null,
            'price' => null, 'price_note' => '', 'duration' => null,
            'egitim_turu' => null, 'instructors' => null, 'etiket' => 'Özel Yazılım',
            'katilim_not' => null, 'tarih_not' => null, 'featured' => 1, 'sort_order' => 2,
            'data' => [
                'ozellikler' => ['Size özel, isteğe göre kodlanan strateji','İndikatör ve kural bazlı sistemler','Backtest ile doğrulama ve optimizasyon','TradingView / Matriks uyumlu kurulum'],
                'aciklama' => ['Strateji Yazdırma hizmetimizle, aklınızdaki alım-satım fikrini kurallara döküp sizin için yazıyoruz.','Dilediğiniz stratejiyi yazdırabilirsiniz: siz hayal edin, biz yazalım. Fikrinizi bize iletin; kodlayıp test edelim.'],
            ],
        ],
    ];

    $sql = "INSERT INTO modules (type, slug, title, short_desc, image, video, video_poster, price, price_note, duration, egitim_turu, instructors, etiket, katilim_not, tarih_not, featured, sort_order, data)
        VALUES (:type, :slug, :title, :short_desc, :image, :video, :video_poster, :price, :price_note, :duration, :egitim_turu, :instructors, :etiket, :katilim_not, :tarih_not, :featured, :sort_order, :data)";
    $stmt = $pdo->prepare($sql);
    foreach ($modules as $m) {
        $stmt->execute([
            ':type' => $m['type'], ':slug' => $m['slug'], ':title' => $m['title'], ':short_desc' => $m['short_desc'],
            ':image' => $m['image'], ':video' => $m['video'], ':video_poster' => $m['video_poster'],
            ':price' => $m['price'], ':price_note' => $m['price_note'], ':duration' => $m['duration'],
            ':egitim_turu' => $m['egitim_turu'], ':instructors' => $m['instructors'], ':etiket' => $m['etiket'],
            ':katilim_not' => $m['katilim_not'], ':tarih_not' => $m['tarih_not'],
            ':featured' => $m['featured'], ':sort_order' => $m['sort_order'],
            ':data' => json_encode($m['data'], JSON_UNESCAPED_UNICODE),
        ]);
    }
}

/** Sadece temel eğitimin eski sabit tarihlerini kaldır. */
function normalize_technical_basic_course(PDO $pdo): void {
    try {
        $st = $pdo->prepare(
            "SELECT id, tarih_not, data
             FROM modules WHERE slug = ? LIMIT 1"
        );
        $st->execute(['teknik-temel-algoritmik']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $data = json_decode((string)($row['data'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }
        $data['tarihler'] = [];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if (
            trim((string)$row['tarih_not']) === ''
            && (string)$row['data'] === (string)$json
        ) {
            return;
        }

        $pdo->prepare(
            "UPDATE modules
             SET tarih_not = NULL, data = ?
             WHERE id = ?"
        )->execute([$json, (int)$row['id']]);
    } catch (Throwable $e) {
        error_log('teknik eğitim normalleştirme: ' . $e->getMessage());
    }
}

