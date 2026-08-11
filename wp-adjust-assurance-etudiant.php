<?php
/**
 * Pénalité ciblée : -10 pts sur le score des produits « assurance habitation »
 * portant l'attribut « étudiant », d'ID < 250000.
 *
 * Baisse mltv5_score_initial (base figée) ET mltv5_score_total (score affiché),
 * plancher 0. IDEMPOTENT via un marqueur (mltv5_adj_assurance_etudiant) → relancer
 * ne re-soustrait pas. Réversible (mode revert depuis la sauvegarde CSV).
 *
 * Usage :
 *   wp eval-file wp-adjust-assurance-etudiant.php dry               # SIMULATION (défaut)
 *   wp eval-file wp-adjust-assurance-etudiant.php live              # ÉCRITURE (+ sauvegarde CSV)
 *   wp eval-file wp-adjust-assurance-etudiant.php revert <csv>      # ANNULE depuis une sauvegarde
 *
 * Ne modifie QUE mltv5_score_initial, mltv5_score_total et le marqueur. Rien d'autre.
 */

$MODE   = strtolower($args[0] ?? 'dry');
$LIVE   = ($MODE === 'live');
$REVERT = ($MODE === 'revert');

// --- Ciblage (slug OU nom : le script résout et affiche ce qu'il a trouvé) ---
$POST_TYPE        = 'avis';
$TAX_PRODUIT      = 'post-type-produit';
$TERM_PRODUIT     = 'assurance habitation';   // « post type » produit
$TAX_ATTR         = 'post-type-attribut';
$TERM_ATTR        = 'étudiant';               // attribut
$INCLUDE_CHILDREN = true;                      // inclure les sous-termes (taxo hiérarchique)
$MAX_ID_EXCL      = 250000;                    // strict : on ne garde que ID < 250000
$PENALTY          = 10;

$F_INITIAL = 'mltv5_score_initial';
$F_TOTAL   = 'mltv5_score_total';
$F_ASIN    = 'mltv5_asin_amazon';
$F_MARKER  = 'mltv5_adj_assurance_etudiant';   // marqueur d'idempotence (stocke le delta appliqué)

if (!function_exists('mt_parse_num')) {
    function mt_parse_num($v) {
        if ($v === null || $v === '') return null;
        $s = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $v));
        return preg_match('/[0-9]+(\.[0-9]+)?/', $s, $m) ? (float) $m[0] : null;
    }
}
// Formatage numérique : entier si pas de décimale, sinon décimal court.
$fmt = function ($x) { return ($x == (int) $x) ? (string) (int) $x : rtrim(rtrim(sprintf('%.4f', $x), '0'), '.'); };

// ===================== MODE REVERT =====================
if ($REVERT) {
    $csv = $args[1] ?? '';
    if ($csv === '' || !file_exists($csv)) WP_CLI::error("revert : sauvegarde introuvable. Usage : revert <backup.csv>");
    $fh = fopen($csv, 'r'); $hdr = fgetcsv($fh); $ix = array_flip($hdr ?: []);
    foreach (['post_id', 'initial_avant', 'total_avant'] as $c) {
        if (!isset($ix[$c])) WP_CLI::error("Colonne manquante dans le CSV : {$c}");
    }
    $n = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $pid = (int) ($row[$ix['post_id']] ?? 0);
        if (!$pid || !get_post($pid)) continue;
        $ia = (string) ($row[$ix['initial_avant']] ?? '');
        $ta = (string) ($row[$ix['total_avant']] ?? '');
        // initial : restaurer la valeur d'origine (num | 'none') ou supprimer si était absent.
        if ($ia === '') delete_post_meta($pid, $F_INITIAL); else update_post_meta($pid, $F_INITIAL, $ia);
        // total : idem.
        if ($ta === '') delete_post_meta($pid, $F_TOTAL);   else update_post_meta($pid, $F_TOTAL, $ta);
        delete_post_meta($pid, $F_MARKER);
        $n++;
    }
    fclose($fh);
    WP_CLI::success("Revert terminé : {$n} produits restaurés (initial/total remis, marqueur retiré). Purge cache.");
    return;
}

// ===================== RÉSOLUTION DES TERMES =====================
function mt_resolve_term($tax, $val) {
    if (!taxonomy_exists($tax)) WP_CLI::error("Taxonomie inexistante : {$tax}");
    $t = get_term_by('slug', $val, $tax);
    if (!$t) $t = get_term_by('name', $val, $tax);
    if (!$t) $t = get_term_by('slug', sanitize_title($val), $tax);
    if (!$t) {
        $sample = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'number' => 20, 'fields' => 'names']);
        WP_CLI::error("Terme « {$val} » introuvable dans {$tax}.\n  Exemples de termes existants : " . implode(' | ', (array) $sample));
    }
    return $t;
}

$tp = mt_resolve_term($TAX_PRODUIT, $TERM_PRODUIT);
$ta = mt_resolve_term($TAX_ATTR, $TERM_ATTR);
WP_CLI::log(sprintf("Produit  : « %s » (#%d, slug=%s, %d posts)", $tp->name, $tp->term_id, $tp->slug, $tp->count));
WP_CLI::log(sprintf("Attribut : « %s » (#%d, slug=%s, %d posts)", $ta->name, $ta->term_id, $ta->slug, $ta->count));

// ===================== SÉLECTION =====================
$ids = get_posts([
    'post_type' => $POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    'tax_query' => ['relation' => 'AND',
        ['taxonomy' => $TAX_PRODUIT, 'field' => 'term_id', 'terms' => $tp->term_id, 'include_children' => $INCLUDE_CHILDREN],
        ['taxonomy' => $TAX_ATTR,    'field' => 'term_id', 'terms' => $ta->term_id, 'include_children' => $INCLUDE_CHILDREN],
    ],
]);
WP_CLI::log(sprintf("%d posts « %s » ∩ « %s » (toutes ID) | seuil : ID < %d | Mode : %s",
    count($ids), $tp->name, $ta->name, $MAX_ID_EXCL,
    $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)'));

$bh = null;
if ($LIVE) {
    $backup = 'adj-assurance-etudiant-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'initial_avant', 'total_avant', 'initial_apres', 'total_apres']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['matched'=>0,'skip_id'=>0,'already'=>0,'no_score'=>0,'captured'=>0,'adjusted'=>0,'with_asin'=>0];
$ex = 0;

foreach ($ids as $pid) {
    if ($pid >= $MAX_ID_EXCL) { $st['skip_id']++; continue; }   // garde ID < 250000
    $st['matched']++;
    $meta = get_post_meta($pid);

    // Idempotence : déjà ajusté → on ne retouche pas.
    if (($meta[$F_MARKER][0] ?? '') !== '') { $st['already']++; continue; }

    $asin = strtoupper(trim((string) ($meta[$F_ASIN][0] ?? '')));
    if ($asin !== '') $st['with_asin']++;

    $initial_raw = $meta[$F_INITIAL][0] ?? '';
    $total_raw   = $meta[$F_TOTAL][0] ?? '';
    $total_num   = mt_parse_num($total_raw);

    // Base à baisser : mltv5_score_initial (num) ; sinon capturée depuis le total ; sinon rien.
    if ($initial_raw === 'none')      { $st['no_score']++; continue; }   // pas de base éditoriale
    elseif ($initial_raw !== '')      { $base = mt_parse_num($initial_raw); }
    else                              { $base = $total_num; if ($base !== null) $st['captured']++; }
    if ($base === null)               { $st['no_score']++; continue; }   // aucun score → rien à baisser

    $new_initial = max(0.0, $base - $PENALTY);
    $new_total   = ($total_num === null) ? null : max(0.0, $total_num - $PENALTY);

    if ($LIVE) {
        update_post_meta($pid, $F_INITIAL, $fmt($new_initial));
        if ($new_total !== null) update_post_meta($pid, $F_TOTAL, $fmt($new_total));
        update_post_meta($pid, $F_MARKER, '-' . $PENALTY);
        if ($bh) fputcsv($bh, [$pid, $asin, $initial_raw, $total_raw, $fmt($new_initial),
                               ($new_total === null ? '' : $fmt($new_total))]);
    }
    $st['adjusted']++;

    if (!$LIVE && $ex < 15) {
        WP_CLI::log(sprintf("  ex. #%d (%s) : initial %s → %s | total %s → %s%s",
            $pid, ($asin === '' ? '—' : $asin),
            ($initial_raw === '' ? '(absent, base ' . $fmt($base) . ')' : $initial_raw), $fmt($new_initial),
            ($total_raw === '' ? '(vide)' : $total_raw), ($new_total === null ? '(vide)' : $fmt($new_total)),
            ($asin !== '' ? '  [a un ASIN]' : '')));
        $ex++;
    }
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$v = $LIVE ? '' : '(simulation) ';
WP_CLI::log(str_repeat('=', 56));
WP_CLI::log(sprintf("Correspondances ID < %d : %d   |   exclus (ID >= seuil) : %d", $MAX_ID_EXCL, $st['matched'], $st['skip_id']));
WP_CLI::log(sprintf("  %sajustés (-%d sur initial & total) : %d", $v, $PENALTY, $st['adjusted']));
WP_CLI::log(sprintf("  %sdont base capturée depuis total   : %d", $v, $st['captured']));
WP_CLI::log(sprintf("  %sdéjà ajustés (marqueur présent)   : %d", $v, $st['already']));
WP_CLI::log(sprintf("  %ssans score (rien à baisser)       : %d", $v, $st['no_score']));
WP_CLI::log(sprintf("  produits avec ASIN (info)          : %d", $st['with_asin']));
WP_CLI::log(str_repeat('=', 56));

if ($LIVE) WP_CLI::success("Ajustement -{$PENALTY} appliqué (initial & total). Purge cache. Annulable : revert <csv>.");
else       WP_CLI::success("SIMULATION — rien écrit. Vérifie le nb de correspondances + les slugs résolus, puis « live ».");
