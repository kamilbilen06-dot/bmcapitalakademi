<?php
/**
 * Türkiye il verisi — şehir bazlı borsa eğitimi sayfaları için.
 *
 * tier: sayfa içeriğinin hangi katılım modelini anlattığını belirler.
 *   1 = merkez, düzenli yüz yüze (İzmir)
 *   2 = büyük şehir, grup talebine göre yüz yüze + canlı online
 *   3 = İzmir'e yakın il, İzmir yüz yüze eğitimine ulaşım pratik + canlı online
 *   4 = canlı online (Türkiye geneli varsayılan)
 */

function cities_all(): array {
    static $cities = null;
    if ($cities !== null) {
        return $cities;
    }

    // [plaka, ad, slug, bölge, tier]
    $rows = [
        [1, 'Adana', 'adana', 'Akdeniz', 4],
        [2, 'Adıyaman', 'adiyaman', 'Güneydoğu Anadolu', 4],
        [3, 'Afyonkarahisar', 'afyonkarahisar', 'Ege', 3],
        [4, 'Ağrı', 'agri', 'Doğu Anadolu', 4],
        [5, 'Amasya', 'amasya', 'Karadeniz', 4],
        [6, 'Ankara', 'ankara', 'İç Anadolu', 2],
        [7, 'Antalya', 'antalya', 'Akdeniz', 2],
        [8, 'Artvin', 'artvin', 'Karadeniz', 4],
        [9, 'Aydın', 'aydin', 'Ege', 3],
        [10, 'Balıkesir', 'balikesir', 'Marmara', 3],
        [11, 'Bilecik', 'bilecik', 'Marmara', 4],
        [12, 'Bingöl', 'bingol', 'Doğu Anadolu', 4],
        [13, 'Bitlis', 'bitlis', 'Doğu Anadolu', 4],
        [14, 'Bolu', 'bolu', 'Karadeniz', 4],
        [15, 'Burdur', 'burdur', 'Akdeniz', 4],
        [16, 'Bursa', 'bursa', 'Marmara', 2],
        [17, 'Çanakkale', 'canakkale', 'Marmara', 4],
        [18, 'Çankırı', 'cankiri', 'İç Anadolu', 4],
        [19, 'Çorum', 'corum', 'Karadeniz', 4],
        [20, 'Denizli', 'denizli', 'Ege', 3],
        [21, 'Diyarbakır', 'diyarbakir', 'Güneydoğu Anadolu', 4],
        [22, 'Edirne', 'edirne', 'Marmara', 4],
        [23, 'Elazığ', 'elazig', 'Doğu Anadolu', 4],
        [24, 'Erzincan', 'erzincan', 'Doğu Anadolu', 4],
        [25, 'Erzurum', 'erzurum', 'Doğu Anadolu', 4],
        [26, 'Eskişehir', 'eskisehir', 'İç Anadolu', 4],
        [27, 'Gaziantep', 'gaziantep', 'Güneydoğu Anadolu', 4],
        [28, 'Giresun', 'giresun', 'Karadeniz', 4],
        [29, 'Gümüşhane', 'gumushane', 'Karadeniz', 4],
        [30, 'Hakkari', 'hakkari', 'Doğu Anadolu', 4],
        [31, 'Hatay', 'hatay', 'Akdeniz', 4],
        [32, 'Isparta', 'isparta', 'Akdeniz', 4],
        [33, 'Mersin', 'mersin', 'Akdeniz', 4],
        [34, 'İstanbul', 'istanbul', 'Marmara', 2],
        [35, 'İzmir', 'izmir', 'Ege', 1],
        [36, 'Kars', 'kars', 'Doğu Anadolu', 4],
        [37, 'Kastamonu', 'kastamonu', 'Karadeniz', 4],
        [38, 'Kayseri', 'kayseri', 'İç Anadolu', 4],
        [39, 'Kırklareli', 'kirklareli', 'Marmara', 4],
        [40, 'Kırşehir', 'kirsehir', 'İç Anadolu', 4],
        [41, 'Kocaeli', 'kocaeli', 'Marmara', 4],
        [42, 'Konya', 'konya', 'İç Anadolu', 4],
        [43, 'Kütahya', 'kutahya', 'Ege', 3],
        [44, 'Malatya', 'malatya', 'Doğu Anadolu', 4],
        [45, 'Manisa', 'manisa', 'Ege', 3],
        [46, 'Kahramanmaraş', 'kahramanmaras', 'Akdeniz', 4],
        [47, 'Mardin', 'mardin', 'Güneydoğu Anadolu', 4],
        [48, 'Muğla', 'mugla', 'Ege', 3],
        [49, 'Muş', 'mus', 'Doğu Anadolu', 4],
        [50, 'Nevşehir', 'nevsehir', 'İç Anadolu', 4],
        [51, 'Niğde', 'nigde', 'İç Anadolu', 4],
        [52, 'Ordu', 'ordu', 'Karadeniz', 4],
        [53, 'Rize', 'rize', 'Karadeniz', 4],
        [54, 'Sakarya', 'sakarya', 'Marmara', 4],
        [55, 'Samsun', 'samsun', 'Karadeniz', 4],
        [56, 'Siirt', 'siirt', 'Güneydoğu Anadolu', 4],
        [57, 'Sinop', 'sinop', 'Karadeniz', 4],
        [58, 'Sivas', 'sivas', 'İç Anadolu', 4],
        [59, 'Tekirdağ', 'tekirdag', 'Marmara', 4],
        [60, 'Tokat', 'tokat', 'Karadeniz', 4],
        [61, 'Trabzon', 'trabzon', 'Karadeniz', 4],
        [62, 'Tunceli', 'tunceli', 'Doğu Anadolu', 4],
        [63, 'Şanlıurfa', 'sanliurfa', 'Güneydoğu Anadolu', 4],
        [64, 'Uşak', 'usak', 'Ege', 3],
        [65, 'Van', 'van', 'Doğu Anadolu', 4],
        [66, 'Yozgat', 'yozgat', 'İç Anadolu', 4],
        [67, 'Zonguldak', 'zonguldak', 'Karadeniz', 4],
        [68, 'Aksaray', 'aksaray', 'İç Anadolu', 4],
        [69, 'Bayburt', 'bayburt', 'Karadeniz', 4],
        [70, 'Karaman', 'karaman', 'İç Anadolu', 4],
        [71, 'Kırıkkale', 'kirikkale', 'İç Anadolu', 4],
        [72, 'Batman', 'batman', 'Güneydoğu Anadolu', 4],
        [73, 'Şırnak', 'sirnak', 'Güneydoğu Anadolu', 4],
        [74, 'Bartın', 'bartin', 'Karadeniz', 4],
        [75, 'Ardahan', 'ardahan', 'Doğu Anadolu', 4],
        [76, 'Iğdır', 'igdir', 'Doğu Anadolu', 4],
        [77, 'Yalova', 'yalova', 'Marmara', 4],
        [78, 'Karabük', 'karabuk', 'Karadeniz', 4],
        [79, 'Kilis', 'kilis', 'Güneydoğu Anadolu', 4],
        [80, 'Osmaniye', 'osmaniye', 'Akdeniz', 4],
        [81, 'Düzce', 'duzce', 'Karadeniz', 4],
    ];

    $cities = [];
    foreach ($rows as [$plate, $name, $slug, $region, $tier]) {
        $cities[$slug] = [
            'plate' => $plate,
            'name' => $name,
            'slug' => $slug,
            'region' => $region,
            'tier' => $tier,
        ];
    }
    return $cities;
}

function city_find(string $slug): ?array {
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    if ($slug === '') {
        return null;
    }
    $all = cities_all();
    return $all[$slug] ?? null;
}

/** Aynı bölgedeki diğer iller — iç linkleme için. */
function city_neighbors(array $city, int $limit = 6): array {
    $out = [];
    foreach (cities_all() as $candidate) {
        if ($candidate['slug'] === $city['slug']) {
            continue;
        }
        if ($candidate['region'] !== $city['region']) {
            continue;
        }
        $out[] = $candidate;
    }
    // Slug ASCII sıralaması Türkçe alfabe sırasına pratikte denk düşer (ç→c, ı→i, ş→s ...).
    usort($out, static fn(array $a, array $b): int => $a['tier'] <=> $b['tier'] ?: strcmp($a['slug'], $b['slug']));
    return array_slice($out, 0, $limit);
}

/** İllerin bölgeye göre gruplanmış hâli — hub sayfası için. */
function cities_by_region(): array {
    $order = ['Marmara', 'Ege', 'Akdeniz', 'İç Anadolu', 'Karadeniz', 'Doğu Anadolu', 'Güneydoğu Anadolu'];
    $groups = array_fill_keys($order, []);
    foreach (cities_all() as $city) {
        $groups[$city['region']][] = $city;
    }
    foreach ($groups as &$list) {
        usort($list, static fn(array $a, array $b): int => strcmp($a['slug'], $b['slug']));
    }
    unset($list);
    return $groups;
}

function city_page_path(array $city): string {
    return '/borsa-egitimi/' . $city['slug'];
}
