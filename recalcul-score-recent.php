<?php
// WPCodeBox 2 snippet
// Recalcule mltv5_score_recent pour les "avis".
//
// PRINCIPE (v3) : décroissance temporelle.
//   score_recent = score_total - pénalité selon l'âge de l'avis
//   - 12 premiers mois : aucune pénalité
//   - ensuite : -1 pt par mois
//   - pénalité plafonnée à 24 pts
//   La date ACF « mltv5_date_dernier_test », si renseignée, prime sur la
//   date de publication (re-test = récence remise à zéro).
//
// PAS de cron. Recalcul déclenché :
//   - à la sauvegarde d'un comparatif (hook en bas de fichier)
//   - manuellement via mlt_recalculate_recent_scores_all()
//   - via URL (admin uniquement) :
//       ?mlt_recent_single_id=123   → recalcule le score récent de l'avis #123
//       ?mlt_recent_bulk=run        → recalcule tous les avis par lots de 500

/** Score récent d'un avis selon son âge. Null si score total absent. */
function mlt_calc_score_recent($avis_id) {
    $total = get_field('mltv5_score_total', $avis_id);
    if ( $total === null || $total === '' ) {
        return null;
    }
    $total = (int) $total;

    $ref    = get_field('mltv5_date_dernier_test', $avis_id);
    $ref_ts = $ref ? strtotime($ref) : get_post_time('U', true, $avis_id);
    if ( ! $ref_ts ) {
        return null;
    }

    // Âge en mois calendaires (tous les avis du même mois = même pénalité).
    $now_ts = time();
    $months = ((int) gmdate('Y', $now_ts) - (int) gmdate('Y', $ref_ts)) * 12
            + ((int) gmdate('n', $now_ts) - (int) gmdate('n', $ref_ts));
    $months = max(0, $months);

    $grace = 6; // mois sans pénalité
    $rate  = 1;  // pts perdus par mois ensuite
    $cap   = 24; // pénalité maximale

    $penalty = min($cap, max(0, $months - $grace) * $rate);

    return max(0, $total - $penalty);
}

/** Écrit le score s'il a changé. Retourne true si mis à jour. */
function mlt_update_score_recent($avis_id) {
    $new_score = mlt_calc_score_recent($avis_id);
    if ( $new_score === null ) {
        return false;
    }
    $current = get_field('mltv5_score_recent', $avis_id);
    if ( $current !== null && $current !== '' && (int) $current === $new_score ) {
        return false;
    }
    update_field('mltv5_score_recent', $new_score, $avis_id);
    return true;
}

/**
 * Recalcule mltv5_score_recent pour tous les avis liés à un comparatif.
 * NOM / SIGNATURE / PÉRIMÈTRE identiques à avant :
 * avis ayant le produit du comparatif + au moins ses attributs.
 */
function mlt_recalculate_recent_scores($comparatif_id) {
    $comparatif_id = (int) $comparatif_id;
    if ( $comparatif_id < 1 ) {
        return 0;
    }
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) {
        return 0;
    }

    $produit_terms = wp_get_post_terms($comparatif_id, 'post-type-produit', ['fields' => 'ids']);
    $produit_terms = is_wp_error($produit_terms) ? [] : $produit_terms;
    if ( empty($produit_terms) ) {
        return 0;
    }

    $attribut_terms = wp_get_post_terms($comparatif_id, 'post-type-attribut', ['fields' => 'ids']);
    $attribut_terms = is_wp_error($attribut_terms) ? [] : $attribut_terms;

    $tax_query = [
        'relation' => 'AND',
        [
            'taxonomy'         => 'post-type-produit',
            'field'            => 'term_id',
            'terms'            => $produit_terms,
            'include_children' => false,
            'operator'         => 'IN',
        ],
    ];
    foreach ( $attribut_terms as $attr_id ) {
        $tax_query[] = [
            'taxonomy'         => 'post-type-attribut',
            'field'            => 'term_id',
            'terms'            => [ $attr_id ],
            'include_children' => false,
            'operator'         => 'IN',
        ];
    }

    $q = new WP_Query([
        'post_type'      => 'avis',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => $tax_query,
    ]);

    $updated = 0;
    foreach ( $q->posts as $avis_id ) {
        if ( mlt_update_score_recent($avis_id) ) {
            $updated++;
        }
    }
    return $updated;
}

/**
 * Bulk (même nom qu'avant). Passe directement par les avis : couvre aussi
 * ceux hors comparatif, et beaucoup moins de requêtes.
 */
function mlt_recalculate_recent_scores_all() {
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) {
        return 0;
    }
    $avis_ids = get_posts([
        'post_type'      => 'avis',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $updated = 0;
    foreach ( $avis_ids as $avis_id ) {
        if ( mlt_update_score_recent($avis_id) ) {
            $updated++;
        }
    }
    return $updated;
}

// ----- Recalcul via URL (admin uniquement) -----
add_action('init', function () {

    // ?mlt_recent_single_id=123
    if ( isset($_GET['mlt_recent_single_id']) ) {
        if ( ! current_user_can('manage_options') ) {
            wp_die('Accès refusé.', 403);
        }
        $avis_id = (int) $_GET['mlt_recent_single_id'];
        if ( $avis_id < 1 ) {
            wp_die('ID invalide.');
        }
        $updated = mlt_update_score_recent($avis_id);
        $score   = get_field('mltv5_score_recent', $avis_id);
        wp_die(
            $updated
                ? "Score récent mis à jour pour l'avis #{$avis_id} → {$score}"
                : "Aucun changement pour l'avis #{$avis_id} (score actuel : {$score})",
            'Recalcul score récent',
            ['response' => 200]
        );
    }

    // ?mlt_recent_bulk=run
    if ( isset($_GET['mlt_recent_bulk']) && $_GET['mlt_recent_bulk'] === 'run' ) {
        if ( ! current_user_can('manage_options') ) {
            wp_die('Accès refusé.', 403);
        }
        $batch   = 500;
        $page    = 1;
        $updated = 0;
        $total   = 0;

        do {
            $ids = get_posts([
                'post_type'      => 'avis',
                'post_status'    => 'publish',
                'posts_per_page' => $batch,
                'paged'          => $page,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]);
            foreach ( $ids as $id ) {
                if ( mlt_update_score_recent($id) ) {
                    $updated++;
                }
                $total++;
            }
            $page++;
        } while ( count($ids) === $batch );

        wp_die(
            "{$updated} score(s) mis à jour sur {$total} avis traités.",
            'Recalcul bulk scores récents',
            ['response' => 200]
        );
    }
});

// ----- Recalcul à la sauvegarde d'un comparatif (inchangé) -----
add_action('acf/save_post', function ($post_id) {
    if ( get_post_type($post_id) !== 'comparatif' ) {
        return;
    }
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision($post_id) ) {
        return;
    }
    mlt_recalculate_recent_scores($post_id);
}, 15);

// ----- Nettoyage : retire le cron programmé par la version précédente -----
add_action('init', function () {
    if ( wp_next_scheduled('mlt_daily_recent_scores') ) {
        wp_clear_scheduled_hook('mlt_daily_recent_scores');
    }
});
