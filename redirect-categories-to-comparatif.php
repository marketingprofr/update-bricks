/**
 * Redirect deep category URLs (3+ levels) to /comparatif-{slug}/
 *
 * Ex: /maison/electromenager/ventilateur/ventilateur-de-table
 *  -> /comparatif-ventilateur-de-table/
 *
 * A coller dans WPCode / Codebox en tant que snippet PHP
 * Emplacement : Exécuter partout (Run Everywhere)
 * Priorité : 1 (le plus tôt possible)
 */

add_action( 'template_redirect', function () {

    // Ne rien faire dans l'admin ou pour les requêtes POST
    if ( is_admin() || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'];

    // Retirer la query string pour l'analyse
    $path = strtok( $request_uri, '?' );

    // Retirer le slash de fin pour compter les segments
    $path_trimmed = trim( $path, '/' );

    // Ignorer les chemins vides
    if ( empty( $path_trimmed ) ) {
        return;
    }

    $segments = explode( '/', $path_trimmed );

    // On ne redirige que s'il y a 3 segments ou plus
    // (au moins 2 niveaux de catégories + le slug final)
    if ( count( $segments ) < 3 ) {
        return;
    }

    // Ne pas toucher aux URLs qui commencent déjà par "comparatif-"
    if ( strpos( $segments[0], 'comparatif-' ) === 0 ) {
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

    // Construire l'URL de destination
    $destination = home_url( '/comparatif-' . $slug . '/' );

    // Conserver la query string si présente
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    if ( ! empty( $query_string ) ) {
        $destination .= '?' . $query_string;
    }

    // Redirect 301 permanent
    wp_redirect( $destination, 301 );
    exit;

}, 1 ); // Priorité 1 = s'exécute très tôt
