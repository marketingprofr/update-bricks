<?php
/**
 * Score global (mltv5_score_total) à partir des avis Amazon — pondéré, IDEMPOTENT par base figée.
 * + Pénalité DISCO (-10) sur TOUS les produits tagués DISCO (avec OU sans avis Amazon).
 *
 * Usage :
 *   wp eval-file wp-score-total.php dry    # SIMULATION (défaut)
 *   wp eval-file wp-score-total.php live   # ÉCRITURE (+ sauvegarde)
 *
 * Principe (rejouable sans dérive) :
 *   base = mltv5_score_initial  (score ÉDITORIAL figé)
 *     - si absent → on le CAPTURE depuis mltv5_score_total actuel ; si vide → 'none' (pas de base).
 *     - 'none' → pas de base éditoriale → score = Amazon direct.
 *   amazon  = s10(note, nb_avis) * 10                       (score /100)
 *   mélange = base * (1 - w) + amazon * w        où w = min(nb,100)/100
 *   baisse (mélange <= base) : score = mélange                       (complet)
 *   hausse (mélange >  base) : score = base + 0.5*(mélange - base)   (50% de l'écart)
 *   DISCO   : score = max(0, score - 10)         (produit définitivement parti)
 *   score_total = round(score)                              (entier le plus proche)
 *
 * → Portée = produits avec avis Amazon (ASIN+note+nb) OU produits tagués DISCO.
 *   Un DISCO SANS avis part de sa base éditoriale figée et perd 10 pts (rien d'autre).
 *   La base ne bouge jamais → on peut relancer autant qu'on veut, sans cumul.
 *   Ne modifie QUE mltv5_score_total (+ capture mltv5_score_initial la 1re fois).
 *
 * ⚠️ Réversibilité : un produit AVEC avis se recorrige tout seul si son tag DISCO
 *   disparaît (il reste dans le périmètre « avis » et est recalculé sans pénalité).
 *   Un produit SANS avis, lui, sort du périmètre si on retire son tag DISCO → son
 *   score reste à base-10 (à recaler à la main dans le rare cas d'un DISCO qui revient).
 */

$MODE = strtolower($args[0] ?? 'dry');
$LIVE = ($MODE === 'live');

$POST_TYPE = 'avis';
$F_SCORE   = 'mltv5_score_avis_clients';   // note /5  (r)
$F_COUNT   = 'mltv5_nombre_avis_clients';  // nb avis  (n)
$F_TOTAL   = 'mltv5_score_total';          // cible /100
$F_INITIAL = 'mltv5_score_initial';        // base figée (num | 'none' | absent)
$F_ASIN    = 'mltv5_asin_amazon';

// Pénalité : -X pts si le produit porte le tag DISCO (définitivement parti)
$DISCO_PENALTY = 10;
$DISCO_TAG     = 'DISCO';
$DISCO_TAX     = 'post_tag';

$BATCH = 500;

if (!function_exists('ecom_s10')) {
    function ecom_s10($r, $n) {
        if ($n < 1) return 0.0;
        if ($n <= 100) return 2.0 * (3.0 + ($r - 3.0) * log10((float) $n) / 2.0);
        $lr = log10($n / 100.0);
        return 2.0 * ($r + (5.0 - $r) * $lr / (2.0 + $lr));
    }
}
if (!function_exists('mt_parse_num')) {
    function mt_parse_num($v) {
        if ($v === null || $v === '') return null;
        $s = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $v));
        return preg_match('/[0-9]+(\.[0-9]+)?/', $s, $m) ? (float) $m[0] : null;
    }
}

// --- Produits avec avis Amazon exploitables (ASIN + note + nombre) ---
$eligible = get_posts([
    'post_type' => $POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    'meta_query' => ['relation' => 'AND',
        [ 'key' => $F_ASIN,  'compare' => 'EXISTS' ],
        [ 'key' => $F_SCORE, 'compare' => 'EXISTS' ],
        [ 'key' => $F_COUNT, 'compare' => 'EXISTS' ],
    ],
]);

// --- Produits tagués DISCO (pénalité -X), avec OU sans avis ---
$disco_ids = get_posts([
    'post_type' => $POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    'tax_query' => [[ 'taxonomy' => $DISCO_TAX, 'field' => 'name', 'terms' => $DISCO_TAG ]],
]);
$disco_set = array_flip($disco_ids);

// --- Périmètre = avis Amazon ∪ DISCO (les DISCO sans avis sont pénalisés aussi) ---
$ids = array_values(array_unique(array_merge($eligible, $disco_ids)));

WP_CLI::log(sprintf("%d avec avis Amazon | %d tagués %s | %d à parcourir (union) | Mode : %s",
    count($eligible), count($disco_ids), $DISCO_TAG, count($ids),
    $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)'));

$bh = null;
if ($LIVE) {
    $backup = 'score-total-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'score_initial', 'score_total_avant', 'score_total_apres']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'ineligible'=>0,'captured'=>0,'lowered'=>0,'raised'=>0,'unchanged'=>0,'set'=>0,
       'disco'=>0,'disco_no_reviews'=>0];
$ex = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;
    $meta  = get_post_meta($pid);
    $asin  = strtoupper(trim((string) ($meta[$F_ASIN][0] ?? '')));
    $r     = mt_parse_num($meta[$F_SCORE][0] ?? '');
    $n_raw = $meta[$F_COUNT][0] ?? '';
    $n     = ($n_raw === '') ? null : (int) preg_replace('/[^0-9]/', '', (string) $n_raw);
    $has_reviews = ($asin !== '' && $r !== null && $n !== null && $n >= 1);
    $is_disco    = isset($disco_set[$pid]);

    // Ni avis exploitables, ni DISCO → rien à faire (sécurité ; ne devrait pas arriver via l'union).
    if (!$has_reviews && !$is_disco) { $st['ineligible']++; continue; }

    $cur_total_raw = $meta[$F_TOTAL][0] ?? '';
    $cur_total     = mt_parse_num($cur_total_raw);
    $initial_raw   = $meta[$F_INITIAL][0] ?? '';

    // --- Base figée (lecture seule ici) ---
    if      ($initial_raw === 'none') $base = null;
    elseif  ($initial_raw !== '')     $base = mt_parse_num($initial_raw);
    else                              $base = $cur_total;   // capture provisoire depuis le total actuel

    // DISCO sans avis ET sans base éditoriale → rien à pénaliser.
    if (!$has_reviews && $base === null) { $st['ineligible']++; continue; }

    // Capture de la base figée si absente (uniquement quand on agit sur ce produit).
    if ($initial_raw === '') {
        $init_store = ($cur_total === null) ? 'none' : (string) $cur_total;
        if ($LIVE) update_post_meta($pid, $F_INITIAL, $init_store);
        $st['captured']++;
    }

    // --- Score AVANT pénalité ---
    if ($has_reviews) {
        $amazon = max(0.0, min(100.0, ecom_s10($r, $n) * 10.0));
        if ($base === null) {
            $pre = round($amazon);                                // pas de base → Amazon direct
        } else {
            $w = min($n, 100) / 100.0;
            $blended = $base * (1.0 - $w) + $amazon * $w;
            if ($blended > $base) $pre = round($base + 0.5 * ($blended - $base)); // hausse 50%
            else                  $pre = round($blended);                         // baisse pleine
        }
    } else {
        // DISCO sans avis : on part du score éditorial figé, aucun ajustement Amazon.
        $pre    = round($base);
        $amazon = null;
        $st['disco_no_reviews']++;
    }

    // --- Pénalité DISCO (plancher 0) ---
    $final = (int) $pre;
    if ($is_disco) { $final = max(0, $final - $DISCO_PENALTY); $st['disco']++; }

    // --- Net vs score_total actuel ---
    if      ($cur_total === null)               $kind = 'set';
    elseif  ($final < (int) round($cur_total))  $kind = 'lowered';
    elseif  ($final > (int) round($cur_total))  $kind = 'raised';
    else                                        $kind = 'unchanged';

    if ($LIVE && $kind !== 'unchanged') update_post_meta($pid, $F_TOTAL, $final);
    if ($LIVE && $bh) fputcsv($bh, [$pid, $asin, ($base === null ? 'none' : $base), $cur_total_raw, $final]);
    $st[$kind]++;

    if (!$LIVE && $ex < 12 && $kind !== 'unchanged') {
        WP_CLI::log(sprintf("  ex. #%d (%s) : base %s, %s | total %s → %d (%s)%s",
            $pid, ($asin === '' ? '—' : $asin), ($base === null ? 'none' : (string) $base),
            ($has_reviews ? sprintf("note %.1f, %d avis (w=%.2f), amazon %.1f", $r, $n, min($n, 100) / 100.0, $amazon)
                          : 'sans avis Amazon'),
            ($cur_total_raw === '' ? '(vide)' : $cur_total_raw), $final, $kind,
            $is_disco ? " [DISCO -{$DISCO_PENALTY}]" : ''));
        $ex++;
    }
    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$v = $LIVE ? '' : '(simulation) ';
WP_CLI::log(str_repeat('=', 56));
WP_CLI::log(sprintf("Parcourus : %d  |  ignorés (ni avis ni DISCO utile) : %d", $st['seen'], $st['ineligible']));
WP_CLI::log(sprintf("  %sbase capturée (1re fois) : %d", $v, $st['captured']));
WP_CLI::log(sprintf("  %sabaissés                 : %d", $v, $st['lowered']));
WP_CLI::log(sprintf("  %smontés (50%% de l'écart)  : %d", $v, $st['raised']));
WP_CLI::log(sprintf("  %sposés (score_total vide)  : %d", $v, $st['set']));
WP_CLI::log(sprintf("  %sinchangés                 : %d", $v, $st['unchanged']));
WP_CLI::log(sprintf("  %sdont pénalité DISCO (-%d)  : %d  (dont sans avis Amazon : %d)",
    $v, $DISCO_PENALTY, $st['disco'], $st['disco_no_reviews']));
WP_CLI::log(str_repeat('=', 56));

if ($LIVE) WP_CLI::success("Score global recalculé (base figée + pénalité DISCO). Purge cache.");
else       WP_CLI::success("SIMULATION — rien écrit. Vérifie, puis « live ».");
