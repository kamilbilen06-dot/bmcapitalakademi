<?php
/**
 * Şehirlere göre borsa eğitimi — hub sayfası (81 il).
 * URL: /borsa-egitimi/
 */
require_once __DIR__ . '/../api/cities.php';

$origin = 'https://www.bmcapitalakademi.com';
$url = $origin . '/borsa-egitimi/';
$groups = cities_by_region();
$total = count(cities_all());

$title = 'Şehirlere Göre Borsa Eğitimi | 81 İlden Canlı Online Katılım - BM Capital Akademi';
$metaDesc = 'Türkiye\'nin 81 ilinden canlı online katılabileceğiniz borsa eğitimi. İlinizi seçin: eğitim formatları, müfredat ve katılım koşullarını görün. İzmir\'de yüz yüze, İstanbul ve Ankara\'da grup talebine göre.';

$faqs = [
    [
        'q' => 'Borsa eğitimine hangi şehirlerden katılabilirim?',
        'a' => 'Eğitim canlı online verildiği için Türkiye\'nin 81 ilinden katılım mümkündür. İnternet bağlantısı olan bir bilgisayar veya tablet yeterlidir; şehir değiştirmeniz gerekmez.',
    ],
    [
        'q' => 'Yüz yüze borsa eğitimi hangi şehirde yapılıyor?',
        'a' => 'Yüz yüze sınıf eğitimi İzmir\'de düzenli olarak yapılır. İstanbul, Ankara, Bursa ve Antalya\'da en az 6 kişilik grup talebi oluştuğunda yüz yüze oturum ayrıca organize edilir. Bu şehirlerde sabit şubemiz bulunmaz.',
    ],
    [
        'q' => 'Online eğitim ile yüz yüze eğitim arasında müfredat farkı var mı?',
        'a' => 'Hayır. Her iki formatta da aynı müfredat, aynı eğitmen ve aynı uygulama setleri kullanılır. Online katılımcılar ders kayıtlarına da erişir.',
    ],
    [
        'q' => 'Küçük bir ilde yaşıyorum, grup açılmasını beklemem gerekir mi?',
        'a' => 'Gerekmez. Canlı online gruplar Türkiye genelinden katılımcıyla oluşturulduğu için ilinizde talep olup olmaması katılımınızı etkilemez.',
    ],
];

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $f): array => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
];

$itemList = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Şehirlere göre borsa eğitimi sayfaları',
    'numberOfItems' => $total,
    'itemListElement' => [],
];
$pos = 1;
foreach (cities_all() as $city) {
    $itemList['itemListElement'][] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'name' => $city['name'] . ' Borsa Eğitimi',
        'url' => $origin . '/borsa-egitimi/' . $city['slug'],
    ];
}

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
?><!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $e($title) ?></title>
  <meta name="description" content="<?= $e($metaDesc) ?>">
  <meta name="keywords" content="şehirlere göre borsa eğitimi, online borsa eğitimi, illere göre borsa kursu, borsa eğitimi Türkiye, canlı online borsa eğitimi">
  <meta name="author" content="BM Capital Akademi">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <meta name="theme-color" content="#1f2d3d">
  <link rel="canonical" href="<?= $e($url) ?>">
  <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">

  <meta name="geo.region" content="TR">
  <meta name="geo.placename" content="Türkiye">

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

  <script type="application/ld+json" data-bm-ld="ItemList"><?= json_encode($itemList, $jsonFlags) ?></script>
  <script type="application/ld+json" data-bm-ld="FAQPage"><?= json_encode($faqSchema, $jsonFlags) ?></script>
  <script>
    window.BM_PAGE_BREADCRUMB = [
      { name: "Şehirlere Göre Borsa Eğitimi", path: "/borsa-egitimi/" }
    ];
  </script>
</head>
<body>
  <div id="site-header"></div>

  <section class="page-hero">
    <div class="container">
      <div class="tools-kicker">81 İlden Canlı Online Katılım</div>
      <h1>Şehirlere Göre Borsa Eğitimi</h1>
      <p class="tools-lead">Eğitim canlı online verildiği için Türkiye'nin her ilinden katılabilirsiniz. İlinizi seçin; katılım formatını, müfredatı ve sık sorulan soruları görün.</p>
      <div class="breadcrumb"><a href="../index.html">Ana Sayfa</a> / Şehirler</div>
    </div>
  </section>

  <section class="section bg-surface">
    <div class="container">
      <div class="rich" style="max-width:860px;margin:0 auto 40px;">
        <h2>Nerede yaşıyor olursanız olun aynı eğitimi alırsınız</h2>
        <p>BM Capital Akademi'nin borsa eğitimleri canlı online yürütülür. Bu, katılımcının bulunduğu ilin eğitim içeriğini
          veya kalitesini değiştirmediği anlamına gelir: Manisa'dan katılan bir yatırımcı da İzmir'deki sınıf grubuyla aynı
          müfredatı, aynı eğitmenle ve aynı anda takip eder.</p>
        <p>Yüz yüze eğitim İzmir'de düzenli olarak yapılır. İstanbul, Ankara, Bursa ve Antalya'da en az 6 kişilik grup
          talebi oluştuğunda yüz yüze oturum organize edilir; bu şehirlerde sabit bir şubemiz bulunmaz. Ege Bölgesi'nde
          İzmir'e yakın illerden (Manisa, Aydın, Denizli, Balıkesir, Muğla, Uşak) katılanlar dilerse tek günlük yoğun
          oturumlar için İzmir'e gelebilir.</p>
        <p>Müfredat üç ana bölümden oluşur: <a href="../egitim-detay.html?id=teknik-temel-algoritmik">teknik, temel ve
          algoritmik analiz</a>; <a href="../egitim-detay.html?id=takas-akd-analizi">takas ve aracı kurum dağılımı (AKD)
          analizi</a>; risk yönetimi ve portföy disiplini.</p>
      </div>

      <?php foreach ($groups as $region => $list): ?>
        <?php if (!$list) continue; ?>
        <div style="margin-bottom:36px;">
          <h2 class="section-title" style="font-size:24px;margin-bottom:16px;"><?= $e($region) ?> Bölgesi</h2>
          <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach ($list as $city): ?>
            <a href="<?= $e($city['slug']) ?>" class="btn btn-outline" style="font-size:14px;padding:8px 14px;">
              <?= $e($city['name']) ?> Borsa Eğitimi
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section bg-soft">
    <div class="container">
      <div class="rich" style="max-width:860px;margin:0 auto;">
        <h2>Sıkça sorulan sorular</h2>
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
        <p style="margin-top:28px;color:#5b6572;font-size:14px;">
          Bu sayfadaki içerik bilgilendirme amaçlıdır; yatırım tavsiyesi değildir.
        </p>
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
