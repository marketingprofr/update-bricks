/**
 * Redirect deep category URLs (3+ levels) to the correct comparatif canonical.
 *
 * Ex: /maison/electromenager/ventilateur/ventilateur-de-table
 *  -> /comparatif-ventilateur-de-table/  (si taggé 2026/2027/2028)
 *  -> /comparatif/ventilateur-de-table/  (si non taggé)
 *
 * Compatible avec le snippet "URLs plates pour comparatifs taggés" :
 * utilise mlt_comparatif_uses_flat_url() pour envoyer directement vers
 * la bonne URL canonique, sans chaîne de redirections.
 *
 * A coller dans WPCode / Codebox en tant que snippet PHP
 * Emplacement : Exécuter partout (Run Everywhere)
 * Priorité : 2 (après le snippet URLs plates qui est en priorité 1)
 */

add_action( 'template_redirect', function () {

    if ( is_admin() || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'];

    // Retirer la query string pour l'analyse
    $path = strtok( $request_uri, '?' );

    // Retirer le slash de fin pour compter les segments
    $path_trimmed = trim( $path, '/' );

    if ( empty( $path_trimmed ) ) {
        return;
    }

    $segments = explode( '/', $path_trimmed );

    // On ne redirige que s'il y a 3 segments ou plus
    if ( count( $segments ) < 3 ) {
        return;
    }

    // Ne pas toucher aux URLs qui commencent déjà par "comparatif"
    if ( strpos( $segments[0], 'comparatif' ) === 0 ) {
        return;
    }

    // Exclure les chemins système de WordPress
    $excluded_prefixes = array(
        'wp-admin',
        'wp-content',
        'wp-includes',
        'wp-json',
        'feed',
        'author',
        'tag',
        'page',
    );

    if ( in_array( $segments[0], $excluded_prefixes, true ) ) {
        return;
    }

    // Le dernier segment est le slug du comparatif
    $slug = end( $segments );

    // Vérifier que ce comparatif existe réellement
    $posts = get_posts( array(
        'name'           => $slug,
        'post_type'      => 'comparatif',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( empty( $posts ) ) {
        return; // Pas de comparatif trouvé — laisser WP gérer (404 ou autre)
    }

    $post_id = (int) $posts[0];

    // Déterminer la bonne URL canonique selon le tag
    if ( function_exists( 'mlt_comparatif_uses_flat_url' ) && mlt_comparatif_uses_flat_url( $post_id ) ) {
        // Comparatif taggé → URL plate
        $destination = home_url( '/comparatif-' . $slug . '/' );
    } else {
        // Comparatif non taggé → URL native du CPT
        $destination = home_url( '/comparatif/' . $slug . '/' );
    }

    // Conserver la query string si présente
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    if ( ! empty( $query_string ) ) {
        $destination .= '?' . $query_string;
    }

    wp_redirect( $destination, 301 );
    exit;

}, 2 ); // Priorité 2 = après le snippet URLs plates (priorité 1)
