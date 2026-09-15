<?php
/**
 * Şehir bazlı borsa eğitimi sayfası.
 * URL: /borsa-egitimi/<il-slug>  (.htaccess → il.php?il=<slug>)
 */
require_once __DIR__ . '/../api/cities.php';

$slug = isset($_GET['il']) ? (string)$_GET['il'] : '';
$city = city_find($slug);

if (!$city) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">'
        . '<meta name="robots" content="noindex"><title>Şehir bulunamadı - BM Capital Akademi</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;padding:0 20px;">'
        . '<h1>Şehir bulunamadı</h1>'
        . '<p>Aradığınız il sayfası mevcut değil. Tüm illerin listesi için '
        . '<a href="/borsa-egitimi/">şehirlere göre borsa eğitimi</a> sayfasına gidin.</p>'
        . '</body></html>';
    exit;
}

$name = $city['name'];
$region = $city['region'];
$tier = (int)$city['tier'];
$plate = (int)$city['plate'];
$neighbors = city_neighbors($city, 6);
$origin = 'https://www.bmcapitalakademi.com';
$url = $origin . '/borsa-egitimi/' . $city['slug'];

/** Türkçe "-den/-dan" eki — şehir adının son ünlüsüne göre. */
function city_ablative(string $name): string {
    return city_locative($name) . 'n';
}

/** Türkçe "-de/-da" eki — son ünlüye göre kalınlık, son sese göre sertlik uyumu. */
function city_locative(string $name): string {
    $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lastVowel = 'e';
    foreach (array_reverse($chars) as $ch) {
        $lower = mb_strtolower($ch, 'UTF-8');
        if (in_array($lower, ['a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü'], true)) {
            $lastVowel = $lower;
            break;
        }
    }
    $hard = in_array(mb_strtolower(mb_substr($name, -1, 1, 'UTF-8'), 'UTF-8'), ['p', 'ç', 't', 'k', 'f', 'h', 's', 'ş'], true);
    $back = in_array($lastVowel, ['a', 'ı', 'o', 'u'], true);
    return $name . "'" . ($hard ? 't' : 'd') . ($back ? 'a' : 'e');
}

$loc = city_locative($name);
$abl = city_ablative($name);

// Metinsel çeşitlilik: plaka koduna göre deterministik varyant seçimi
$openers = [
    'Borsa eğitimi, hisse senedi piyasasının işleyişini, fiyat hareketlerinin nedenlerini ve karar süreçlerini sistemli biçimde öğreten uygulamalı bir eğitim programıdır.',
    'Borsa eğitimi, sermaye piyasalarında veriye dayalı karar vermeyi öğreten; teknik analiz, temel analiz ve kurumsal para akışı okumasını bir araya getiren uygulamalı bir programdır.',
    'Borsa eğitimi, yatırım kararlarını duyum ve tahmin yerine ölçülebilir veriye dayandırmayı öğreten yapılandırılmış bir eğitim sürecidir.',
];
$opener = $openers[$plate % count($openers)];

$formatHeadings = [
    'Katılım formatları',
    'Eğitime nasıl katılırsınız?',
    'Eğitim formatları ve katılım',
];
$formatHeading = $formatHeadings[$plate % count($formatHeadings)];

// Tier'a göre başlık, katılım modeli ve açılış paragrafı
switch ($tier) {
    case 1:
        $h1 = $name . ' Borsa Eğitimi — Yüz Yüze ve Canlı Online';
        $title = $name . ' Borsa Eğitimi | Yüz Yüze ve Canlı Online - BM Capital Akademi';
        $metaDesc = $loc . ' yüz yüze ve canlı online borsa eğitimi. BIST 100, hisse senedi analizi, teknik analiz, AKD ve takas analizi. Dr. Kamil Bilen ile grup veya birebir özel ders.';
        $modeBadge = 'Yüz Yüze + Canlı Online';
        $intro = $name . ', BM Capital Akademi\'nin merkezidir. ' . $loc
            . ' borsa eğitimi hem sınıf ortamında yüz yüze hem de canlı online olarak düzenli biçimde verilir. '
            . 'Yüz yüze gruplar ' . $loc . ' toplanır; şehir dışından katılmak isteyenler aynı dersi canlı online izleyebilir.';
        $formats = [
            ['Yüz yüze grup eğitimi', $loc . ' düzenli açılan sınıf gruplarında, eğitmenle aynı ortamda uygulama yaparak öğrenirsiniz. Grafik okuma ve veri yorumlama alıştırmaları canlı piyasa verisi üzerinde yapılır.'],
            ['Canlı online eğitim', 'Aynı müfredat, aynı eğitmen ve aynı soru-cevap imkânıyla internet üzerinden katılım. Ders kayıtları tekrar izlemeye açıktır.'],
            ['Birebir özel ders', 'Kendi portföyünüz ve hedeflerinize göre programın kişiye uyarlandığı format. Takvim katılımcıya göre planlanır.'],
        ];
        break;

    case 2:
        $h1 = $name . ' Borsa Eğitimi — Canlı Online ve Grup Talebine Göre Yüz Yüze';
        $title = $name . ' Borsa Eğitimi | Canlı Online ve Yüz Yüze - BM Capital Akademi';
        $metaDesc = $abl . ' katılabileceğiniz canlı online borsa eğitimi. BIST 100, hisse senedi analizi, teknik analiz, AKD ve takas analizi. ' . $loc . ' grup talebine göre yüz yüze organizasyon.';
        $modeBadge = 'Canlı Online + Grup Talebine Göre Yüz Yüze';
        $intro = $abl . ' katılan yatırımcılar eğitimi canlı online olarak alır; ders saatinde eğitmenle doğrudan iletişim kurar, soru sorar ve uygulamaları birlikte yapar. '
            . $loc . ' en az 6 kişilik bir grup oluştuğunda yüz yüze oturum ayrıca organize edilir. '
            . 'BM Capital Akademi\'nin ' . $loc . ' sabit bir şubesi yoktur; yüz yüze program talep üzerine planlanır.';
        $formats = [
            ['Canlı online eğitim (bireysel katılım)', $abl . ' bireysel katılımın standart yolu canlı online derstir. Kamera ve mikrofon zorunlu değildir; sorularınızı yazılı olarak da iletebilirsiniz.'],
            ['Grup talebine göre yüz yüze', $loc . ' 6 veya daha fazla katılımcı bir araya geldiğinde yüz yüze oturum düzenlenir. Tarih ve mekân grupla birlikte belirlenir.'],
            ['Birebir özel ders', 'Online veya ' . $loc . ' yüz yüze olarak, tamamen size uyarlanmış program.'],
        ];
        break;

    case 3:
        $h1 = $name . ' Borsa Eğitimi — Canlı Online ve İzmir Yüz Yüze Seçeneği';
        $title = $name . ' Borsa Eğitimi | Canlı Online ve Yüz Yüze - BM Capital Akademi';
        $metaDesc = $abl . ' katılabileceğiniz canlı online borsa eğitimi. BIST 100, hisse senedi analizi, teknik analiz, AKD ve takas analizi. İzmir yüz yüze gruplarına ulaşım seçeneği.';
        $modeBadge = 'Canlı Online + İzmir Yüz Yüze';
        $intro = $abl . ' borsa eğitimine iki yoldan katılabilirsiniz: canlı online dersler veya İzmir\'de düzenlenen yüz yüze gruplar. '
            . $name . ', ' . $region . ' Bölgesi\'nde İzmir\'e yakın konumda olduğu için yüz yüze eğitim günü için ulaşım çoğu katılımcı açısından pratiktir. '
            . 'Yine de müfredatın tamamı online olarak da verildiği için şehir dışına çıkmak zorunlu değildir.';
        $formats = [
            ['Canlı online eğitim', $abl . ' evinizden veya ofisinizden katılın. Dersler canlı işlenir, kayıtlar tekrar izlemeye açıktır.'],
            ['İzmir yüz yüze gruplar', 'İzmir\'de düzenli açılan sınıf gruplarına katılabilirsiniz. ' . $name . ' ile İzmir arası ulaşım kolay olduğu için tek günlük yoğun oturumlar tercih edilebilir.'],
            ['Birebir özel ders', 'Online veya İzmir\'de yüz yüze, kişiye uyarlanmış program.'],
        ];
        break;

    default:
        $h1 = $name . ' Borsa Eğitimi — Canlı Online Katılım';
        $title = $name . ' Borsa Eğitimi | Canlı Online Katılım - BM Capital Akademi';
        $metaDesc = $abl . ' katılabileceğiniz canlı online borsa eğitimi. BIST 100, hisse senedi analizi, profesyonel teknik analiz, AKD ve takas analizi. Dr. Kamil Bilen ile grup veya birebir özel ders.';
        $modeBadge = 'Canlı Online';
        $intro = $abl . ' borsa eğitimi almak için şehir değiştirmeniz gerekmez. BM Capital Akademi eğitimleri canlı online olarak verilir; '
            . $region . ' Bölgesi\'nden katılan yatırımcılar İzmir\'deki sınıf grubuyla aynı müfredatı, aynı eğitmenle ve aynı anda takip eder. '
            . 'Ders sırasında soru sorabilir, uygulamaları kendi ekranınızda birlikte yapabilirsiniz.';
        $formats = [
            ['Canlı online grup eğitimi', $abl . ' internet bağlantısı olan her yerden katılım. Dersler canlı işlenir; kaçırdığınız oturumu kayıttan tamamlayabilirsiniz.'],
            ['Birebir özel ders', 'Takvimi size göre planlanan, kendi portföyünüz üzerinden çalışılan format.'],
            ['Ders kayıtları', 'Tüm oturumlar kayıt altına alınır. ' . $loc . ' iş saatleriniz nedeniyle canlı katılamadığınız dersleri sonradan izleyebilirsiniz.'],
        ];
        break;
}

// Şehre özel SSS
$faqs = [
    [
        'q' => $abl . ' borsa eğitimine katılabilir miyim?',
        'a' => 'Evet. BM Capital Akademi eğitimleri canlı online verildiği için ' . $abl
            . ' katılım için ek bir koşul yoktur. İnternet bağlantısı olan bir bilgisayar veya tablet yeterlidir.'
            . ($tier === 1 ? ' ' . $loc . ' ayrıca yüz yüze sınıf grupları düzenlenir.' : ''),
    ],
    [
        'q' => $loc . ' yüz yüze borsa eğitimi var mı?',
        'a' => $tier === 1
            ? 'Evet. ' . $name . ', akademinin merkezidir; yüz yüze gruplar düzenli olarak ' . $loc . ' açılır.'
            : ($tier === 2
                ? $loc . ' sabit bir şubemiz yoktur. En az 6 kişilik grup talebi oluştuğunda ' . $loc . ' yüz yüze oturum organize edilir; bireysel katılımlar canlı online yürütülür.'
                : ($tier === 3
                    ? $loc . ' sabit bir sınıfımız yoktur; yüz yüze eğitim İzmir\'de yapılır. ' . $name . ' İzmir\'e yakın olduğu için tek günlük yoğun oturumlara ulaşım pratiktir. Dilerseniz tüm programı online tamamlayabilirsiniz.'
                    : $loc . ' yüz yüze sınıf eğitimi düzenlenmemektedir. Yüz yüze program İzmir\'de yapılır; ' . $abl . ' katılım canlı online olarak sağlanır.')),
    ],
    [
        'q' => 'Sıfırdan başlayanlar için uygun mu?',
        'a' => 'Uygundur. Müfredat borsanın temel işleyişinden başlar; hisse senedi nedir, emir tipleri nasıl çalışır, grafik nasıl okunur gibi konular baştan anlatılır. '
            . 'Sonrasında teknik analiz, temel analiz ve AKD/takas analizi gibi ileri seviye başlıklara geçilir.',
    ],
    [
        'q' => 'Canlı derse katılamazsam ne olur?',
        'a' => 'Tüm oturumlar kayda alınır ve katılımcı panelinizden tekrar izlenebilir. '
            . $loc . ' çalışma saatleriniz ders saatiyle çakışırsa konuyu kayıttan tamamlayıp sorularınızı bir sonraki canlı oturumda sorabilirsiniz.',
    ],
    [
        'q' => 'Eğitim sonunda sertifika veriliyor mu?',
        'a' => 'Programı tamamlayan katılımcılara katılım sertifikası verilir. Sertifika bir yetki belgesi değildir; eğitimin tamamlandığını gösterir.',
    ],
];

$neighborNames = array_map(static fn(array $c): string => $c['name'], array_slice($neighbors, 0, 4));
if ($neighborNames) {
    $faqs[] = [
        'q' => $name . ' dışındaki illerden katılım mümkün mü?',
        'a' => 'Evet. Eğitim Türkiye\'nin 81 ilinden canlı online katılıma açıktır. '
            . $region . ' Bölgesi\'nde ' . implode(', ', $neighborNames)
            . ' gibi illerden de katılım alınmaktadır.',
    ];
}

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $f): array => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
];

$serviceSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Service',
    '@id' => $url . '#service',
    'name' => $name . ' Borsa Eğitimi',
    'serviceType' => 'Borsa eğitimi',
    'description' => $metaDesc,
    'url' => $url,
    'provider' => [
        '@type' => 'EducationalOrganization',
        '@id' => $origin . '/#organization',
        'name' => 'BM Capital Akademi',
        'url' => $origin . '/',
    ],
    'areaServed' => [
        '@type' => 'City',
        'name' => $name,
        'containedInPlace' => ['@type' => 'Country', 'name' => 'Türkiye'],
    ],
    'availableChannel' => array_values(array_filter([
        [
            '@type' => 'ServiceChannel',
            'name' => 'Canlı online eğitim',
            'serviceUrl' => $origin . '/egitimler.html',
        ],
        $tier <= 3 ? [
            '@type' => 'ServiceChannel',
            'name' => $tier === 1 ? $loc . ' yüz yüze eğitim' : ($tier === 2 ? $loc . ' grup talebine göre yüz yüze eğitim' : 'İzmir yüz yüze eğitim'),
            'serviceUrl' => $origin . '/iletisim.html',
        ] : null,
    ])),
];

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $e($title) ?></title>
  <meta name="description" content="<?= $e($metaDesc) ?>">
  <meta name="keywords" content="<?= $e($name . ' borsa eğitimi, ' . $name . ' borsa kursu, ' . $name . ' online borsa eğitimi, ' . $name . ' hisse senedi eğitimi, ' . $name . ' teknik analiz eğitimi, borsa eğitimi ' . $name . ', online borsa eğitimi, BIST 100 eğitimi') ?>">
  <meta name="author" content="BM Capital Akademi">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="canonical" href="<?= $e($url) ?>">
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">

  <meta name="geo.region" content="<?= $e('TR-' . str_pad((string)$plate, 2, '0', STR_PAD_LEFT)) ?>">
  <meta name="geo.placename" content="<?= $e($name) ?>">

  <meta property="og:type" content="website">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:site_name" content="BM Capital Akademi">
  <meta property="og:url" content="<?= $e($url) ?>">
  <meta property="og:title" content="<?= $e($title) ?>">
  <meta property="og:description" content="<?= $e($metaDesc) ?>">
  <meta property="og:image" content="<?= $e($origin) ?>/assets/img/og-cover.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $e($title) ?>">
  <meta name="twitter:description" content="<?= $e($metaDesc) ?>">
  <meta name="twitter:image" content="<?= $e($origin) ?>/assets/img/og-cover.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css?v=20260915a">

  <script type="application/ld+json" data-bm-ld="Service"><?= json_encode($serviceSchema, $jsonFlags) ?></script>
  <script type="application/ld+json" data-bm-ld="FAQPage"><?= json_encode($faqSchema, $jsonFlags) ?></script>
  <script>
    window.BM_PAGE_BREADCRUMB = [
      { name: "Şehirlere Göre Borsa Eğitimi", path: "/borsa-egitimi/" },
      { name: <?= json_encode($name . ' Borsa Eğitimi', $jsonFlags) ?>, path: <?= json_encode('/borsa-egitimi/' . $city['slug'], $jsonFlags) ?> }
    ];
  </script>
</head>
<body>
  <div id="site-header"></div>

  <section class="page-hero">
    <div class="container">
      <div class="tools-kicker"><?= $e($modeBadge) ?></div>
      <h1><?= $e($h1) ?></h1>
      <p class="tools-lead"><?= $e($region . ' Bölgesi, ' . $plate . ' plakalı ' . $name . ' için borsa eğitimi seçenekleri, müfredat ve katılım koşulları.') ?></p>
      <div class="breadcrumb">
        <a href="../index.html">Ana Sayfa</a> /
        <a href="./">Şehirler</a> /
        <?= $e($name) ?>
      </div>
    </div>
  </section>

  <section class="section bg-surface">
    <div class="container">
      <div class="detail-grid">
        <div class="detail-main">
          <div class="rich">
            <h2><?= $e($name . ' borsa eğitimi nedir?') ?></h2>
            <p><?= $e($opener) ?></p>
            <p><?= $e($intro) ?></p>

            <h2><?= $e($formatHeading) ?></h2>
            <ul class="check-list">
              <?php foreach ($formats as [$fTitle, $fDesc]): ?>
              <li><i class="fa-solid fa-circle-check"></i> <strong><?= $e($fTitle) ?>:</strong> <?= $e($fDesc) ?></li>
              <?php endforeach; ?>
            </ul>

            <h2>Müfredat: ne öğreneceksiniz?</h2>
            <p><?= $e($abl . ' katılan yatırımcılar aşağıdaki modülleri takip eder. Modüller birbirini tamamlar; ayrı ayrı da alınabilir.') ?></p>
            <h3>1. Teknik, Temel ve Algoritmik Analiz</h3>
            <p>Grafik okuma, destek ve direnç seviyeleri, trend yapıları, hareketli ortalamalar ve momentum göstergeleri;
              bilanço ve gelir tablosu okuma, çarpan analizi ile şirket değerleme; kuralları belirlenmiş bir işlem sisteminin
              kurulması ve geriye dönük test edilmesi. Detaylar:
              <a href="../egitim-detay.html?id=teknik-temel-algoritmik">Teknik, Temel ve Algoritmik Analiz Eğitimi</a>.</p>
            <h3>2. Takas ve Aracı Kurum Dağılımı (AKD) Analizi</h3>
            <p>Bir hissede hangi aracı kurumun alıcı, hangisinin satıcı olduğunun okunması; kurumsal para giriş ve çıkışının
              takip edilmesi; BIST kurum dağılımı verisinin fiyat hareketiyle birlikte yorumlanması. Detaylar:
              <a href="../egitim-detay.html?id=takas-akd-analizi">Takas ve AKD Analizi Eğitimi</a>.</p>
            <h3>3. Risk yönetimi ve portföy disiplini</h3>
            <p>Pozisyon büyüklüğü belirleme, zarar kesme seviyesi tanımlama, portföy dağılımı ve beklenti yönetimi.
              Bu bölüm, önceki modüllerde öğrenilen analiz yöntemlerinin gerçek portföyde nasıl uygulanacağını kapsar.</p>

            <h2><?= $e($abl . ' kayıt ve başlangıç adımları') ?></h2>
            <ol>
              <li><a href="../egitimler.html">Eğitimler sayfasından</a> size uygun modülü seçin.</li>
              <li>Kontenjan ve güncel takvim için <a href="../iletisim.html">iletişim formunu</a> doldurun veya WhatsApp'tan yazın.</li>
              <li>Kaydınız onaylandığında ders bağlantısı ve hazırlık materyalleri e-posta ile iletilir.</li>
              <li>İlk oturumda platform kullanımı ve veri kaynakları gösterilir; teknik hazırlık gerekmez.</li>
            </ol>

            <h2>Eğitmen</h2>
            <p>Eğitimler Dr. Kamil Bilen tarafından verilir. Program, akademik yöntem ile saha uygulamasını birleştiren
              bir yaklaşımla hazırlanmıştır; her konu canlı piyasa verisi üzerinde örneklenir.
              <a href="../egitmen-profil.html?id=kamil-bilen">Eğitmen profilini görüntüleyin</a>.</p>

            <h2><?= $e($name . ' için sıkça sorulan sorular') ?></h2>
            <div class="accordion">
              <?php foreach ($faqs as $f): ?>
              <div class="acc-item">
                <button class="acc-header" aria-expanded="false">
                  <span class="acc-icon">+</span><span class="acc-title"><?= $e($f['q']) ?></span>
                </button>
                <div class="acc-panel"><p><?= $e($f['a']) ?></p></div>
              </div>
              <?php endforeach; ?>
            </div>

            <?php if ($neighbors): ?>
            <h2><?= $e($region . ' Bölgesi\'ndeki diğer iller') ?></h2>
            <p>Yakın illerden de aynı programa canlı online katılım mümkündür:</p>
            <p>
              <?php foreach ($neighbors as $i => $n): ?>
                <a href="<?= $e($n['slug']) ?>"><?= $e($n['name'] . ' borsa eğitimi') ?></a><?= $i < count($neighbors) - 1 ? ' · ' : '' ?>
              <?php endforeach; ?>
            </p>
            <p><a href="./">Tüm illerin listesine bakın</a>.</p>
            <?php endif; ?>

            <p style="margin-top:28px;color:#5b6572;font-size:14px;">
              Bu sayfadaki içerik bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.
              Sermaye piyasası işlemleri risk içerir.
            </p>
          </div>
        </div>

        <aside>
          <div class="side-card">
            <h3><i class="fa-solid fa-location-dot text-gold"></i> <?= $e($name) ?></h3>
            <p style="margin:0 0 6px;color:#5b6572;font-size:14px;"><strong>Bölge:</strong> <?= $e($region) ?></p>
            <p style="margin:0 0 6px;color:#5b6572;font-size:14px;"><strong>Plaka:</strong> <?= $e((string)$plate) ?></p>
            <p style="margin:0 0 16px;color:#5b6572;font-size:14px;"><strong>Katılım:</strong> <?= $e($modeBadge) ?></p>
            <a href="../egitimler.html" class="btn btn-primary btn-block btn-lg">Eğitimleri Gör</a>
            <a href="../iletisim.html" class="btn btn-outline btn-block" style="margin-top:12px;">Bilgi Al</a>
          </div>
          <div class="side-card" style="margin-top:20px;">
            <h3><i class="fa-solid fa-book-open text-gold"></i> Rehberler</h3>
            <ul style="margin:0;padding-left:18px;color:#5b6572;font-size:14px;line-height:1.9;">
              <li><a href="../rehber/akd-analizi-nedir.html">AKD analizi nedir?</a></li>
              <li><a href="../rehber/bist-takas-analizi.html">BIST takas analizi</a></li>
              <li><a href="../rehber/hisse-senedi-analizi-nasil-yapilir.html">Hisse senedi analizi nasıl yapılır?</a></li>
              <li><a href="../rehber/bist-100-nedir.html">BIST 100 nedir?</a></li>
              <li><a href="../rehber/borsa-egitimi-nasil-secilir.html">Borsa eğitimi nasıl seçilir?</a></li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <section class="section bg-soft">
    <div class="container">
      <div class="cta-band">
        <h2><?= $e($name . ' için kontenjan ve takvim bilgisi') ?></h2>
        <p>Güncel grup tarihleri ve ücretler için bize ulaşın.</p>
        <div class="hero-actions" style="justify-content:center;">
          <a href="../egitimler.html" class="btn btn-primary btn-lg">Eğitimleri İncele</a>
          <a href="../iletisim.html" class="btn btn-ghost btn-lg">İletişime Geç</a>
        </div>
      </div>
    </div>
  </section>

  <div id="site-footer"></div>
  <div id="site-floaters"></div>

  <script src="../assets/js/feature-flags.js"></script>
  <script src="../assets/js/data.js?v=20260915a"></script>
  <script src="../assets/js/catalog.js?v=20260915a"></script>
  <script src="../api/ga_config.php"></script>
  <script src="../assets/js/main.js?v=20260915a"></script>
</body>
</html>
