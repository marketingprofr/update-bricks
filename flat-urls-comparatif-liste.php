<?php
/**
 * ============================================================================
 * MEILLEURTEST — URLs plates pour comparatifs ET listes taggés (2026/2027/2028)
 * ============================================================================
 *
 * OBJECTIF
 *   Faire servir les comparatifs ET listes taggés "2026", "2027" ou "2028"
 *   sur le format d'URL PLAT :
 *     /comparatif-{slug}/   au lieu de  /comparatif/{slug}/
 *     /liste-{slug}/        au lieu de  /liste/{slug}/
 *
 *   Les posts NON taggés restent sur leur URL native (/comparatif/{slug}/ ou
 *   /liste/{slug}/).
 *
 * POINT DE VÉRITÉ UNIQUE
 *   mlt_post_uses_flat_url($post_id) décide si un post doit utiliser l'URL
 *   plate. Fonctionne pour les deux CPTs.
 *
 * MÉCANISMES
 *   1. post_type_link : réécrit le permalien en /{base}-{slug}/
 *   2. Rewrite rules : fait que /{base}-{slug}/ RÉPONDE (sinon 404)
 *   3. Redirections 301 bidirectionnelles avec verrou d'idempotence
 *   4. Flush auto versionné des rewrite rules
 * ============================================================================
 */


// ============================================================================
// CONFIGURATION
// ============================================================================

if (!defined('MLT_FLAT_URL_TAGS')) {
    define('MLT_FLAT_URL_TAGS', serialize(array('2026', '2027', '2028')));
}

if (!defined('MLT_FLAT_RULES_VERSION')) {
    define('MLT_FLAT_RULES_VERSION', '3');
}

if (!defined('MLT_COMPARATIF_BASE')) {
    define('MLT_COMPARATIF_BASE', 'comparatif');
}

if (!defined('MLT_LISTE_BASE')) {
    define('MLT_LISTE_BASE', 'liste');
}

/**
 * CPTs gérés par ce snippet. Clé = post_type, valeur = base URL native.
 */
if (!function_exists('mlt_flat_url_cpts')) {
    function mlt_flat_url_cpts() {
        return array(
            'comparatif' => MLT_COMPARATIF_BASE,
            'liste'      => MLT_LISTE_BASE,
        );
    }
}


// ============================================================================
// POINT DE VÉRITÉ : ce post utilise-t-il l'URL plate ?
// ============================================================================

if (!function_exists('mlt_post_uses_flat_url')) {
    function mlt_post_uses_flat_url($post_id) {
        static $cache = array();

        $post_id = (int) $post_id;
        if ($post_id < 1) {
            return false;
        }

        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }

        $post_type = get_post_type($post_id);
        $cpts = mlt_flat_url_cpts();

        if (!isset($cpts[$post_type])) {
            return $cache[$post_id] = false;
        }

        $tags = wp_get_post_terms($post_id, 'post_tag', array('fields' => 'slugs'));
        if (is_wp_error($tags) || empty($tags)) {
            return $cache[$post_id] = false;
        }

        $trigger_tags = unserialize(MLT_FLAT_URL_TAGS);
        $has_trigger = (bool) array_intersect($tags, $trigger_tags);

        return $cache[$post_id] = $has_trigger;
    }
}

// Rétrocompatibilité : l'ancien nom de fonction reste disponible
if (!function_exists('mlt_comparatif_uses_flat_url')) {
    function mlt_comparatif_uses_flat_url($post_id) {
        return mlt_post_uses_flat_url($post_id);
    }
}


// ============================================================================
// 1. PERMALIEN : réécrire en /{base}-{slug}/ pour les posts "flat"
// ============================================================================

if (!function_exists('mlt_flat_post_type_link')) {
    function mlt_flat_post_type_link($post_link, $post) {
        if (empty($post)) {
            return $post_link;
        }

        $cpts = mlt_flat_url_cpts();

        if (!isset($cpts[$post->post_type])) {
            return $post_link;
        }

        if (!mlt_post_uses_flat_url($post->ID)) {
            return $post_link;
        }

        $base = $cpts[$post->post_type];
        return home_url('/' . $base . '-' . $post->post_name . '/');
    }
}
add_filter('post_type_link', 'mlt_flat_post_type_link', 10, 2);


// ============================================================================
// 2. REWRITE RULES : faire répondre /{base}-{slug}/
// ============================================================================

if (!function_exists('mlt_add_flat_rewrite_rules')) {
    function mlt_add_flat_rewrite_rules() {
        $cpts = mlt_flat_url_cpts();

        foreach ($cpts as $post_type => $base) {
            add_rewrite_rule(
                '^' . $base . '-([^/]+)/?$',
                'index.php?post_type=' . $post_type . '&name=$matches[1]',
                'top'
            );
        }
    }
}
add_action('init', 'mlt_add_flat_rewrite_rules', 5);


if (!function_exists('mlt_maybe_flush_flat_rules')) {
    function mlt_maybe_flush_flat_rules() {
        $stored = get_option('mlt_flat_rules_version');

        if ($stored === MLT_FLAT_RULES_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('mlt_flat_rules_version', MLT_FLAT_RULES_VERSION);
    }
}
add_action('init', 'mlt_maybe_flush_flat_rules', 20);


// ============================================================================
// HELPER : normalisation d'URL
// ============================================================================

if (!function_exists('mlt_normalize_path')) {
    function mlt_normalize_path($url_or_path) {
        $path = parse_url($url_or_path, PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = $url_or_path;
        }
        $path = rawurldecode($path);
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path .= '/';
        }
        return $path;
    }
}


// ============================================================================
// 3. REDIRECTIONS 301 (bidirectionnelles, anti-boucle)
// ============================================================================

if (!function_exists('mlt_handle_flat_redirects')) {
    function mlt_handle_flat_redirects() {

        if (is_admin()) {
            return;
        }

        $request_path = mlt_normalize_path($_SERVER['REQUEST_URI']);
        $raw_path     = trim($request_path, '/');

        // --------------------------------------------------------------------
        // CAS A : un CPT géré a été résolu (comparatif ou liste)
        // --------------------------------------------------------------------
        $queried = get_queried_object();
        $cpts = mlt_flat_url_cpts();

        if ($queried instanceof WP_Post && isset($cpts[$queried->post_type])) {

            $post_id  = (int) $queried->ID;
            $is_flat  = mlt_post_uses_flat_url($post_id);
            $slug     = $queried->post_name;
            $base     = $cpts[$queried->post_type];

            $native_path = mlt_normalize_path('/' . $base . '/' . $slug);   // /{base}/{slug}/
            $flat_path   = mlt_normalize_path('/' . $base . '-' . $slug);   // /{base}-{slug}/

            if ($is_flat) {
                if ($request_path === $flat_path) {
                    return;
                }
                if ($request_path === $native_path) {
                    wp_safe_redirect(home_url($flat_path), 301);
                    exit;
                }
            } else {
                if ($request_path === $flat_path) {
                    wp_safe_redirect(home_url($native_path), 301);
                    exit;
                }
                if ($request_path === $native_path) {
                    return;
                }
            }

            return;
        }

        // --------------------------------------------------------------------
        // CAS B : compatibilité descendante — fiche-{slug} → /avis/{slug}/
        // --------------------------------------------------------------------
        if (preg_match('/^fiche-(.+)$/', $raw_path, $matches)) {
            $post_slug = $matches[1];

            $found = get_posts(array(
                'name'           => $post_slug,
                'post_type'      => 'avis',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ));

            if (!empty($found)) {
                $target = mlt_normalize_path('/avis/' . $post_slug);

                if ($request_path === $target) {
                    return;
                }

                wp_safe_redirect(home_url($target), 301);
                exit;
            }
        }
    }
}
add_action('template_redirect', 'mlt_handle_flat_redirects', 1);
