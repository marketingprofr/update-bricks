<?php
/* =====================================================================
   FAUX WORDPRESS — banc d'essai des blocs Code (voir README.md).
   Simule juste ce que les blocs appellent (get_field, get_all_template_variables,
   termes, métas…) avec un jeu de données fictif : 1 comparatif parent (ID 100)
   + sous-comparatifs 101-105 + 14 fiches avis (ID 1-14).
   Ce n'est PAS le site réel : ça valide la logique PHP et le HTML produit
   (pas de doublon d'ID, ancres, JSON-LD, titres), pas le rendu Bricks/CSS.
   Variables d'environnement : AS_ADMIN=1 (éditeur connecté), NAVIS=n (nombre
   d'avis publiés renvoyé par WP_Query pour le compteur du hero).
   ===================================================================== */
define( 'MT_REPO', dirname( __DIR__, 2 ) );
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['CUR'] = 100;
$GLOBALS['CAN'] = getenv( 'AS_ADMIN' ) === '1';
$GLOBALS['post'] = null;
$GLOBALS['FOOTER'] = array();

/* ---------- Données ---------- */
$P = array(); $TERMS = array(); $META = array(); $TV = array(); $ACF = array();
function mkp( $id, $type, $slug, $title, $status = 'publish' ) {
  global $P; $o = new stdClass; $o->ID = $id; $o->post_type = $type; $o->post_name = $slug; $o->post_title = $title;
  $o->post_status = $status; $o->post_date = '2026-05-01 10:00:00'; $o->post_date_gmt = '2026-05-01 08:00:00'; $o->post_content = "<p>Test du produit $id.</p>";
  $P[ $id ] = $o;
}
function tm( $arr ) { $o = array(); foreach ( $arr as $id => $n ) { $t = new stdClass; $t->term_id = $id; $t->name = $n; $o[] = $t; } return $o; }

mkp( 100, 'comparatif', 'comparatif-climatiseur-mobile', 'Climatiseur mobile' );
$TERMS[100] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseur' ) ), 'post-type-attribut' => tm( array( 20 => 'Mobile' ) ) );
$TV[100] = array( 'top_avis_ids' => array( 1, 2, 3, 4, 5 ), 'type_de_produit_au_pluriel' => 'climatiseurs mobiles', 'type_de_produit_au_singulier' => 'climatiseur mobile', 'masculinsfeminins' => 'Meilleurs', 'introduction' => '<p>Intro principale.</p>' );
$META[100][ 'mltv5_sous_comparatifs' ] = serialize( array( '101', '102', '103', '104', '105', '100' ) );

mkp( 101, 'comparatif', 'comparatif-climatiseur-mobile-9000-btu', 'Climatiseur mobile 9000 BTU' );
$TERMS[101] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseur' ) ), 'post-type-attribut' => tm( array( 20 => 'Mobile', 21 => 'réversible' ) ) );
$TV[101] = array( 'top_avis_ids' => array( 6, 1, 7, 8, 9 ), 'type_de_produit_au_pluriel' => 'climatiseurs mobiles 9000 BTU', 'introduction' => '<p>Longue intro 9000.</p>' );
$META[101][ 'mltv5_intro_sous_comparatif' ] = "Intro dédiée 9000 BTU.\n\nDeuxième paragraphe <b>gras</b>.";

mkp( 102, 'comparatif', 'comparatif-climatiseur-mobile-12000-btu', 'Climatiseur mobile 12000 BTU' );
$TERMS[102] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseur' ) ), 'post-type-attribut' => tm( array( 22 => '12000 BTU', 20 => 'Mobile' ) ) );
$TV[102] = array( 'top_avis_ids' => array( 2, 10, 6, 11, 12 ), 'introduction' => '<p>Vous cherchez un climatiseur mobile puissant pour une grande pièce ? Voici notre sélection. Elle est <a href="/x">mise à jour</a> chaque mois.</p><p>Second paragraphe.</p>', 'forcer_affichage_du_titre' => '' );

mkp( 103, 'comparatif', 'comparatif-climatiseur-mobile-7000-btu', 'Brouillon 7000', 'draft' );
$P[101]->post_status = 'private';
mkp( 104, 'comparatif', 'comparatif-climatiseur-mobile-radiateur', 'Mauvais type' );
$TERMS[104] = array( 'post-type-produit' => tm( array( 11 => 'Radiateur' ) ), 'post-type-attribut' => tm( array( 20 => 'Mobile', 23 => 'Silencieux' ) ) );
$TV[104] = array( 'top_avis_ids' => array( 13, 14 ), 'forcer_affichage_du_titre' => 'Titre forcé du 104' );
mkp( 105, 'comparatif', 'comparatif-climatiseur-mobile-16-000-btu', 'Vide 16000' );
$TERMS[105] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseur' ) ), 'post-type-attribut' => tm( array( 20 => 'Mobile', 24 => '16 000 BTU' ) ) );
$TV[105] = array( 'top_avis_ids' => array() );

for ( $i = 1; $i <= 14; $i++ ) {
  mkp( $i, 'avis', 'avis-produit-' . $i, 'Produit ' . $i );
  $ACF[ $i ] = array(
    'mltv5_marque_du_produit' => 'Marque' . $i, 'mltv5_modele_du_produit' => 'Modèle ' . $i,
    'mltv5_score_recent' => 95 - $i, 'mltv5_prix_indicatif' => ( $i % 3 ? 200 + $i * 10 : '' ),
    'mltv5_asin_amazon' => ( $i % 2 ? 'B0TEST' . $i : '' ), 'mltv5_lien_du_produit_1' => 'https://www.cdiscount.fr/p' . $i,
    'mltv5_resume_produit' => 'Résumé ' . $i, 'mltv5_score_avis_clients' => '4,' . ( $i % 9 ), 'mltv5_nombre_avis_clients' => 100 + $i,
    'mltv5_points_positifs_produit' => array( array( 'mltv5_point_positif' => 'Pro A' . $i ), array( 'mltv5_point_positif' => 'Pro B' ) ),
    'mltv5_points_negatifs_produit' => array( array( 'mltv5_point_negatif' => 'Con ' . $i ) ),
    'mltv5_utilisations_du_produit' => ( $i === 6 ? array( array( 'mltv5_nom_utilisation_produit' => '9000 btu, mobile', 'mltv5_avantages_inconvenients_utilisation' => '<p>ANGLE-9000-DU-6</p>' ) ) : null ),
    'mltv5_caracteristiques_du_produit' => array(
      array( 'mltv5_caracteristique_produit' => 'Puissance', 'mltv5_valeur_caracteristique_produit' => ( 7000 + $i * 500 ) . ' BTU' ),
      array( 'mltv5_caracteristique_produit' => 'Bruit', 'mltv5_valeur_caracteristique_produit' => ( 50 + $i ) . ' dB' ),
      array( 'mltv5_caracteristique_produit' => ( $i > 5 ? 'Spec secondaire' : 'Poids' ), 'mltv5_valeur_caracteristique_produit' => $i . ' kg' ),
    ),
  );
}

/* ---------- Stubs WP ---------- */
function get_the_ID() { return $GLOBALS['CUR']; }
function get_post( $id ) { global $P; return is_object( $id ) ? $id : ( $P[ (int) $id ] ?? null ); }
function get_post_field( $f, $id ) { $p = get_post( $id ); return $p ? $p->$f : ''; }
function get_post_status( $p ) { $p = get_post( $p ); return $p ? $p->post_status : false; }
function get_the_title( $p = 0 ) { $p = get_post( $p ?: $GLOBALS['CUR'] ); return $p ? $p->post_title : ''; }
function get_the_terms( $id, $tax ) { global $TERMS; return $TERMS[ $id ][ $tax ] ?? false; }
function get_post_meta( $id, $k, $single = false ) { global $META; return $META[ $id ][ $k ] ?? ''; }
function get_term_meta( $id, $k, $single = false ) { global $TERMMETA; return $TERMMETA[ $id ][ $k ] ?? ''; }
function maybe_unserialize( $v ) { $u = @unserialize( $v ); return $u === false ? $v : $u; }
function get_all_template_variables( $id ) { global $TV; return $TV[ $id ] ?? array(); }
function get_field( $k, $id = null ) { global $ACF; $id = $id ?? ( $GLOBALS['post']->ID ?? 0 ); return $ACF[ $id ][ $k ] ?? null; }
function get_permalink( $p = 0 ) { $p = get_post( $p ?: $GLOBALS['CUR'] ); return $p ? 'https://www.meilleurtest.fr/' . $p->post_name . '/' : false; }
function sanitize_title( $s ) { $s = strtolower( trim( $s ) ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
function wpautop( $s ) { $ps = preg_split( '/\n\s*\n/', trim( $s ) ); return '<p>' . implode( "</p>\n<p>", $ps ) . "</p>\n"; }
function wp_kses_post( $s ) { return $s; }
function wp_kses( $s, $a ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function setup_postdata( $p ) { return true; }
function wp_reset_postdata() {}
function get_the_post_thumbnail_url( $id, $s ) { return 'https://img/' . $id . '.jpg'; }
function get_post_thumbnail_id( $id ) { return 1000 + (int) $id; }
function wp_get_attachment_image_src( $att, $size = 'thumbnail' ) { return array( 'https://img/' . $att . '.jpg', 300, 240, false ); }
function has_tag( $t, $id ) { return false; }
function get_post_modified_time( $f, $g, $id ) { return 1700000000 + $id; }
function current_user_can( $c ) { return $GLOBALS['CAN']; }
function is_user_logged_in() { return $GLOBALS['CAN']; }
function wp_create_nonce( $a ) { return 'nonce'; }
function admin_url( $p ) { return '/wp-admin/' . $p; }
function date_i18n( $f, $ts = null ) { return date( $f, $ts ?? time() ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { if ( $h === 'wp_footer' ) { $GLOBALS['FOOTER'][] = $cb; } }
function add_filter( $h, $cb, $p = 10, $n = 1 ) {}
function apply_filters( $h, $v ) { return $v; }
function get_the_content( $a, $b, $p ) { return $p->post_content; }
function get_acf_score_divided_by_10() { return round( get_field( 'mltv5_score_recent' ) / 10, 1 ); }
function get_acf_score_label() { return 'Excellent'; }
function get_default_product_label( $pid, $s ) { return 'Le meilleur ' . $pid; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $i ) { $o[] = is_object( $i ) ? $i->$f : $i[ $f ]; } return $o; }
function get_edit_post_link( $id, $c ) { return '/wp-admin/post.php?post=' . $id; }
function wp_date( $f, $t ) { return date( $f, $t ); }
function get_post_timestamp( $p ) { return strtotime( $p->post_date_gmt ); }
function is_admin() { return false; }
function did_action( $h ) { return 0; }
function is_singular( $t = '' ) { return true; }
function _prime_post_caches( $ids, $a, $b ) { $GLOBALS['PRIMED'] = $ids; }
$ROWS = array();
function have_rows( $f, $id ) { global $ROWS; if ( ! isset( $ROWS[ $id ] ) ) { $ROWS[ $id ] = array( 'rows' => get_field( $f, $id ) ?: array(), 'i' => -1 ); } $r = &$ROWS[ $id ]; if ( $r['i'] + 1 < count( $r['rows'] ) ) { return true; } unset( $ROWS[ $id ] ); return false; }
function the_row() { global $ROWS; $k = array_key_last( $ROWS ); $ROWS[ $k ]['i']++; $GLOBALS['ROWCUR'] = $ROWS[ $k ]['rows'][ $ROWS[ $k ]['i'] ]; }
function get_sub_field( $k ) { return $GLOBALS['ROWCUR'][ $k ] ?? ''; }
class WP_Query { public $posts = array(); function __construct( $a ) { if ( ( $a['post_type'] ?? '' ) === 'avis' && getenv( 'NAVIS' ) ) { $this->posts = range( 1, (int) getenv( 'NAVIS' ) ); } } }
function get_posts( $a ) { return array_keys( array_filter( $GLOBALS['P'], function ( $p ) use ( $a ) { return $p->post_type === $a['post_type'] && in_array( $p->post_status, (array) $a['post_status'], true ); } ) ); }
function has_term( $t, $tax, $id ) { return ! empty( $GLOBALS['TAGGED'][ $id ] ); }
/* Stubs supplémentaires utilisés par le hero gauche */
function get_post_type( $id = 0 ) { $p = get_post( $id ?: $GLOBALS['CUR'] ); return $p ? $p->post_type : false; }
function get_the_modified_time( $f ) { return 1700000000; }
function update_post_meta( $id = 0, $k = '', $v = '' ) { $GLOBALS['WRITTEN'][ $k ] = $v; }
function wp_update_post() {}
function intro() { return 'Intro de test.'; }
function get_author_posts_url() { return '#'; }
function wp_get_attachment_image() { return ''; }
function do_shortcode( $s ) { return ''; }
function get_the_post_thumbnail() { return ''; }
function get_the_post_thumbnail_caption() { return ''; }
function has_post_thumbnail() { return false; }
function update_object_term_cache() {}
function update_meta_cache() {}


/* ASIN en méta brute (comme ACF) + doublon volontaire : produit 12 = même ASIN que le 3 */
for ( $i = 1; $i <= 14; $i++ ) { if ( ! empty( $ACF[ $i ]['mltv5_asin_amazon'] ) ) { $META[ $i ]['mltv5_asin_amazon'] = $ACF[ $i ]['mltv5_asin_amazon']; } }
$META[12]['mltv5_asin_amazon'] = 'B0TEST3'; $ACF[12]['mltv5_asin_amazon'] = 'B0TEST3';
