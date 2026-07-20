<?php
/**
 * Export des ASINs depuis WordPress.
 * Usage : wp eval-file wp-export-asins.php > asins.csv
 *
 * Cherche le champ ACF contenant l'ASIN Amazon sur chaque produit.
 * Adaptez $ASIN_FIELD_KEY au nom exact de votre champ ACF.
 */

// --- Configuration ---
$ASIN_FIELD_KEY  = 'mltv5_asin_amazon';      // Nom du champ ACF contenant l'ASIN
$POST_TYPE       = 'post-type-produit';       // CPT produit
$FALLBACK_FIELDS = [                          // Champs alternatifs si le principal est vide
    'mltv5_lien_du_bouton_1',                 // L'ASIN est parfois dans l'URL affiliée
];
// ---------------------

global $wpdb;

// En-tête CSV
echo "post_id,asin,title\n";

$posts = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

$count = 0;
$skipped = 0;

foreach ($posts as $post_id) {
    $asin = trim(get_post_meta($post_id, $ASIN_FIELD_KEY, true));

    // Fallback : extraire l'ASIN d'une URL Amazon
    if (empty($asin)) {
        foreach ($FALLBACK_FIELDS as $field) {
            $val = get_post_meta($post_id, $field, true);
            if (preg_match('/\/dp\/([A-Z0-9]{10})/', $val, $m)) {
                $asin = $m[1];
                break;
            }
            if (preg_match('/\btag=/', $val) && preg_match('/([A-Z0-9]{10})/', $val, $m)) {
                $asin = $m[1];
                break;
            }
        }
    }

    // Valider le format ASIN (10 caractères alphanumériques)
    $asin = strtoupper($asin);
    if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
        $skipped++;
        continue;
    }

    $title = str_replace(['"', "\n", "\r"], ['""', ' ', ''], get_the_title($post_id));
    echo "{$post_id},{$asin},\"{$title}\"\n";
    $count++;
}

fwrite(STDERR, "Exportés : {$count} produits avec ASIN\n");
fwrite(STDERR, "Ignorés  : {$skipped} produits sans ASIN valide\n");
