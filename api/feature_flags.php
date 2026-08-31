<?php
/**
 * Geçici özellik bayrakları.
 *
 * Dr. Mete Akyol'u tekrar göstermek için:
 *   FEATURE_METE_AKYOL_ACTIVE → true
 * ve assets/js/feature-flags.js içinde meteAkyolActive → true
 *
 * Kapatılanlar (false iken):
 * - Mete eğitmen profili (is_active=0)
 * - Mete sosyal medya (Instagram/X)
 * - Metematiksel Analiz kitabı + 1 aylık WhatsApp hediyesi
 */
define('FEATURE_METE_AKYOL_ACTIVE', false);

function feature_mete_akyol_active(): bool {
    return (bool)FEATURE_METE_AKYOL_ACTIVE;
}

/** Mete eğitmen kaydını flag ile senkronize et (silmez). */
function feature_sync_mete_instructor(PDO $pdo): void {
    $active = feature_mete_akyol_active() ? 1 : 0;
    try {
        $pdo->prepare(
            "UPDATE instructors SET is_active = ?
             WHERE name LIKE ? OR slug LIKE ? OR slug LIKE ?"
        )->execute([$active, '%Mete%', '%mete%', '%akyol%']);
    } catch (Throwable $e) {
        // tablo yoksa sessiz geç
    }
}

/** Public API çıktısından Mete / hediye içeriğini çıkar. */
function feature_filter_public_payload(array &$payload): void {
    if (feature_mete_akyol_active()) {
        return;
    }

    $meteSocials = [
        'instagram.com/meteakyol1975',
        'x.com/DrMeteAkyol',
        'twitter.com/DrMeteAkyol',
    ];

    if (isset($payload['site']) && is_array($payload['site'])) {
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
            if (!empty($item['egitmenler'])) {
                $item['egitmenler'] = feature_strip_mete_from_instructors((string)$item['egitmenler']);
            }
            $item['hediye'] = [];
            $item['hediyeGorsel'] = '';
        }
        unset($item);
    }

    if (!empty($payload['sss']) && is_array($payload['sss'])) {
        $payload['sss'] = array_values(array_filter($payload['sss'], function ($faq) {
            $q = mb_strtolower((string)($faq['soru'] ?? ''), 'UTF-8');
            $a = mb_strtolower((string)($faq['cevap'] ?? ''), 'UTF-8');
            if (strpos($q, 'hediye') !== false) {
                return false;
            }
            if (strpos($a, 'mete') !== false || strpos($a, 'metematiksel') !== false) {
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
