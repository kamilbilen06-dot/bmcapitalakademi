<?php
/**
 * Yönetilebilir özellik bayrakları.
 *
 * Dr. Mete Akyol'u tekrar göstermek için:
 * Değerler settings tablosundan okunur; yeni kurulumda varsayılanları pasiftir.
 *
 * Kapatılabilen içerikler:
 * - 13–17 Nisan 2026 tarihli eğitim
 * - Dr. Mete Akyol
 * - Metematiksel Analiz kitabı ve hediyeler
 */
define('FEATURE_METE_AKYOL_ACTIVE', false);

function feature_flag_active(?array $settings, string $key, bool $fallback = false): bool {
    if (is_array($settings) && array_key_exists($key, $settings)) {
        return trim((string)$settings[$key]) === '1';
    }
    return $fallback;
}

function feature_mete_akyol_active(?array $settings = null): bool {
    return feature_flag_active($settings, 'feature_mete_akyol', (bool)FEATURE_METE_AKYOL_ACTIVE);
}

function feature_nisan_2026_active(?array $settings = null): bool {
    return feature_flag_active($settings, 'feature_nisan_2026', false);
}

function feature_metematiksel_hediye_active(?array $settings = null): bool {
    return feature_flag_active($settings, 'feature_metematiksel_hediye', false);
}

/** Mete eğitmen kaydını flag ile senkronize et (silmez). */
function feature_sync_mete_instructor(PDO $pdo): void {
    $active = 0;
    try {
        $st = $pdo->query("SELECT v FROM settings WHERE k = 'feature_mete_akyol' LIMIT 1");
        $configured = $st ? $st->fetchColumn() : false;
        $active = trim((string)$configured) === '1'
            ? 1
            : ((bool)FEATURE_METE_AKYOL_ACTIVE ? 1 : 0);
        $pdo->prepare(
            "UPDATE instructors SET is_active = ?
             WHERE name LIKE ? OR slug LIKE ? OR slug LIKE ?"
        )->execute([$active, '%Mete%', '%mete%', '%akyol%']);
    } catch (Throwable $e) {
        // tablo yoksa sessiz geç
    }
}

/** Public API çıktısından pasif özellik içeriklerini çıkar. */
function feature_filter_public_payload(array &$payload, ?array $settings = null): void {
    $meteActive = feature_mete_akyol_active($settings);
    $giftActive = feature_metematiksel_hediye_active($settings);
    $nisanActive = feature_nisan_2026_active($settings);

    $meteSocials = [
        'instagram.com/meteakyol1975',
        'x.com/DrMeteAkyol',
        'twitter.com/DrMeteAkyol',
    ];

    if (!$meteActive && isset($payload['site']) && is_array($payload['site'])) {
        foreach (['instagram', 'twitter'] as $k) {
            $url = (string)($payload['site'][$k] ?? '');
            foreach ($meteSocials as $needle) {
                if ($url !== '' && stripos($url, $needle) !== false) {
                    $payload['site'][$k] = '';
                    break;
                }
            }
        }
    }

    foreach (['egitimler', 'urunler'] as $bucket) {
        if (empty($payload[$bucket]) || !is_array($payload[$bucket])) {
            continue;
        }
        foreach ($payload[$bucket] as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if (!$meteActive && !empty($item['egitmenler'])) {
                $item['egitmenler'] = feature_strip_mete_from_instructors((string)$item['egitmenler']);
            }
            if (!$giftActive) {
                $item['hediye'] = [];
                $item['hediyeGorsel'] = '';
            }
        }
        unset($item);
        if ($bucket === 'egitimler' && !$nisanActive) {
            $payload[$bucket] = array_values(array_filter(
                $payload[$bucket],
                static function ($item): bool {
                    return !is_array($item) || (string)($item['id'] ?? '') !== 'teknik-temel-algoritmik';
                }
            ));
        }
    }

    if (!empty($payload['sss']) && is_array($payload['sss'])) {
        $payload['sss'] = array_values(array_filter($payload['sss'], function ($faq) use ($meteActive, $giftActive) {
            $q = mb_strtolower((string)($faq['soru'] ?? ''), 'UTF-8');
            $a = mb_strtolower((string)($faq['cevap'] ?? ''), 'UTF-8');
            if (!$giftActive && strpos($q, 'hediye') !== false) {
                return false;
            }
            if (!$meteActive && !$giftActive && (strpos($a, 'mete') !== false || strpos($a, 'metematiksel') !== false)) {
                return false;
            }
            return true;
        }));
    }
}

function feature_strip_mete_from_instructors(string $raw): string {
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $kept = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (stripos($p, 'Mete') !== false || stripos($p, 'Akyol') !== false) {
            continue;
        }
        $kept[] = $p;
    }
    return implode(', ', $kept);
}
