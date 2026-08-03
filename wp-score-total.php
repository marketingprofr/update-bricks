<?php
/**
 * Score global (mltv5_score_total) à partir des avis Amazon.
 * Formule « ecom_score » portée en PHP (parité exacte vérifiée vs Python).
 *
 * Usage :
 *   wp eval-file wp-score-total.php dry    # SIMULATION (défaut) — n'écrit rien
 *   wp eval-file wp-score-total.php live   # ÉCRITURE (+ sauvegarde auto)
 *   (ajoute --path=… de ton install)
 *
 * - Entrées : note /5 (mltv5_score_avis_clients) + nb avis (mltv5_nombre_avis_clients).
 * - Score /100 = floor( s10(note, nb) * 10 )   [s10 = score /10 de la formule].
 * - Ne calcule QUE « quand ils en ont » (note + nb avis présents, nb >= 1).
 * - Écrit dans mltv5_score_total UNIQUEMENT si le score calculé est PLUS FAIBLE
 *   que la valeur existante. Ne modifie AUCUN autre champ.
 */

$MODE = strtolower($args[0] ?? 'dry');
$LIVE = ($MODE === 'live');

// false = ne touche QUE les score_total déjà renseignés (lecture stricte de la consigne).
// true  = pose aussi le score là où mltv5_score_total est vide.
$SET_IF_EMPTY = false;

$POST_TYPE = 'avis';
$F_SCORE   = 'mltv5_score_avis_clients';   // note /5  (r)
$F_COUNT   = 'mltv5_nombre_avis_clients';  // nb avis  (n)
$F_TOTAL   = 'mltv5_score_total';          // cible /100
$F_ASIN    = 'mltv5_asin_amazon';

$BATCH = 500;

// Formule (identique à factory/scripts/ecom_score.py, échelle /10)
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

// Posts « avis » publiés qui ont une note ET un nombre d'avis
$ids = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => $F_SCORE, 'compare' => 'EXISTS' ],
        [ 'key' => $F_COUNT, 'compare' => 'EXISTS' ],
    ],
]);
WP_CLI::log(sprintf("%d posts avec note+avis. Mode : %s | score_total vide : %s",
    count($ids), $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)',
    $SET_IF_EMPTY ? 'POSÉS' : 'ignorés'));

// Sauvegarde (mode live) : valeur AVANT de tout post modifié
$bh = null;
if ($LIVE) {
    $backup = 'score-total-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'mltv5_score_total_avant']);
    WP_CLI::log("Sauvegarde des valeurs actuelles → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'noreview'=>0,'lowered'=>0,'kept'=>0,'set_empty'=>0,'skip_empty'=>0];
$ex = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;
    $meta = get_post_meta($pid);

    $r     = mt_parse_num($meta[$F_SCORE][0] ?? '');
    $n_raw = $meta[$F_COUNT][0] ?? '';
    $n     = ($n_raw === '') ? null : (int) preg_replace('/[^0-9]/', '', (string) $n_raw);
    if ($r === null || $n === null || $n < 1) { $st['noreview']++; continue; }

    $score = (int) floor(ecom_s10($r, $n) * 10.0);
    $score = max(0, min(100, $score));

    $cur_raw = $meta[$F_TOTAL][0] ?? '';
    $cur     = mt_parse_num($cur_raw);

    $action = null;
    if ($cur === null) {
        if ($SET_IF_EMPTY) { $action = 'set_empty'; }
        else { $st['skip_empty']++; }
    } elseif ($score < $cur) {
        $action = 'lowered';
    } else {
        $st['kept']++;
    }

    if ($action) {
        $asin = strtoupper(trim((string) ($meta[$F_ASIN][0] ?? '')));
        if ($bh) fputcsv($bh, [$pid, $asin, $cur_raw]);
        if ($LIVE) update_post_meta($pid, $F_TOTAL, $score);
        $st[$action]++;
        if (!$LIVE && $ex < 10) {
            WP_CLI::log(sprintf("  ex. #%d (%s) : note %.1f, %d avis → calc %d/100 ; total %s → %d (%s)",
                $pid, $asin, $r, $n, $score,
                ($cur_raw === '' ? '(vide)' : $cur_raw), $score,
                $action === 'lowered' ? 'abaissé' : 'posé'));
            $ex++;
        }
    }

    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$v = $LIVE ? '' : '(simulation) ';
WP_CLI::log(str_repeat('=', 54));
WP_CLI::log(sprintf("Posts avec note+avis : %d  |  sans avis exploitables : %d", $st['seen'], $st['noreview']));
WP_CLI::log(sprintf("  %sabaissés (calc < existant) : %d", $v, $st['lowered']));
WP_CLI::log(sprintf("  %sgardés   (calc >= existant): %d", $v, $st['kept']));
if ($SET_IF_EMPTY) {
    WP_CLI::log(sprintf("  %sposés    (score_total vide): %d", $v, $st['set_empty']));
} else {
    WP_CLI::log(sprintf("  score_total vide (ignorés)  : %d", $st['skip_empty']));
}
WP_CLI::log(str_repeat('=', 54));

if ($LIVE) {
    WP_CLI::success("Score global mis à jour. ⚠️ Purge Varnish + Breeze.");
} else {
    WP_CLI::success("SIMULATION terminée — RIEN écrit. Vérifie l'échelle des « total » ci-dessus, puis relance avec « live ».");
}
