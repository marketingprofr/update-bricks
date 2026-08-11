<?php
/**
 * Migration one-shot : peuple mltv5_score_initial depuis la sauvegarde du 1er score.
 *
 * Usage :
 *   wp eval-file wp-migrate-score-initial.php score-total-backup-20260803-095648.csv dry
 *   wp eval-file wp-migrate-score-initial.php score-total-backup-20260803-095648.csv live
 *
 * Pour chaque post du CSV : mltv5_score_initial = score_total_avant (le score
 * ÉDITORIAL d'origine, avant tout ajustement Amazon).
 *   - avant numérique → on stocke ce nombre.
 *   - avant vide      → on stocke la sentinelle 'none' (aucune base éditoriale).
 * Ne touche PAS un post qui a DÉJÀ un mltv5_score_initial (idempotent).
 * Ne modifie QUE mltv5_score_initial.
 */

$CSV  = $args[0] ?? 'score-total-backup-20260803-095648.csv';
$MODE = strtolower($args[1] ?? 'dry');
$LIVE = ($MODE === 'live');
$F_INITIAL = 'mltv5_score_initial';

if (!file_exists($CSV)) { WP_CLI::error("Fichier introuvable : {$CSV}"); }
$fh = fopen($CSV, 'r');
$header = fgetcsv($fh);
if (!$header) { WP_CLI::error("CSV vide : {$CSV}"); }
$idx = array_flip($header);
if (!isset($idx['post_id']) || !isset($idx['score_total_avant'])) {
    WP_CLI::error("Colonnes attendues : post_id, score_total_avant (trouvé : " . implode(',', $header) . ")");
}

WP_CLI::log(sprintf("Migration mltv5_score_initial depuis %s — Mode : %s",
    $CSV, $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)'));

wp_suspend_cache_addition(true);
$st = ['rows'=>0,'set_num'=>0,'set_none'=>0,'already'=>0,'nopost'=>0];
$ex = 0;

while (($row = fgetcsv($fh)) !== false) {
    $st['rows']++;
    $pid   = (int) ($row[$idx['post_id']] ?? 0);
    $avant = trim((string) ($row[$idx['score_total_avant']] ?? ''));
    if (!$pid) continue;
    if (!get_post($pid)) { $st['nopost']++; continue; }

    // On ne clobber jamais un initial déjà présent.
    if (get_post_meta($pid, $F_INITIAL, true) !== '') { $st['already']++; continue; }

    // Valeur à poser : nombre d'origine, ou 'none' si vide.
    $norm = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $avant));
    if ($avant !== '' && is_numeric($norm)) {
        $val = $norm;          // ex. "85"
        $st['set_num']++;
        $kind = "num({$val})";
    } else {
        $val = 'none';         // original vide → pas de base éditoriale
        $st['set_none']++;
        $kind = 'none(vide)';
    }

    if ($LIVE) update_post_meta($pid, $F_INITIAL, $val);

    if (!$LIVE && $ex < 8) {
        WP_CLI::log("  ex. #{$pid} : score_total_avant='{$avant}' → initial={$kind}");
        $ex++;
    }
}
fclose($fh);
wp_suspend_cache_addition(false);

WP_CLI::log(str_repeat('=', 54));
WP_CLI::log(sprintf("Lignes CSV : %d", $st['rows']));
WP_CLI::log(sprintf("  initial numérique %s : %d", $LIVE ? 'posé' : 'à poser', $st['set_num']));
WP_CLI::log(sprintf("  initial 'none' (vide) %s : %d", $LIVE ? 'posé' : 'à poser', $st['set_none']));
WP_CLI::log(sprintf("  déjà un initial (ignorés) : %d", $st['already']));
WP_CLI::log(sprintf("  post introuvable : %d", $st['nopost']));
WP_CLI::log(str_repeat('=', 54));

if ($LIVE) WP_CLI::success("Migration terminée. mltv5_score_initial peuplé.");
else       WP_CLI::success("SIMULATION — rien écrit. Vérifie les totaux (attendu ~13 465), puis « live ».");
