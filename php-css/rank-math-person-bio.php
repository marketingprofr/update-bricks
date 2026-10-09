<?php
/**
 * Données structurées : la personne auteur (Person, générée par Rank Math) reçoit sa biographie
 *
 * Demande seo-aeo de la Coordination (2026-10-09) ; biographie de Samuel mise en ligne le même jour par Architecture, avec
 * son accord (description du compte utilisateur 1, et page À propos, ancre #samuel-petit). Rank Math produit le nœud
 * Person de l'auteur sans adresse, sans fonction et sans description : ce filtre les ajoute.
 *   - description : la biographie du compte de l'auteur (Profil, « Renseignements biographiques »), en texte seul ;
 *     vaut pour tous les rédacteurs qui en ont une ;
 *   - url et jobTitle : seulement pour les personnes listées dans $profils (page À propos).
 * Ne change rien à l'affichage des pages.
 *
 * WPCodeBox : coller tel quel, type PHP, exécuter sur « Frontend ».
 */
add_filter( 'rank_math/json_ld', function ( $data, $jsonld ) {
  /* Personnes qui ont une section sur la page À propos : identifiant du compte => adresse de la section et fonction */
  $profils = array(
    1 => array( 'url' => '/a-propos/#samuel-petit', 'jobTitle' => 'Fondateur et responsable éditorial' ),
  );
  if ( ! is_array( $data ) ) { return $data; }

  /* Comptes à essayer : l'auteur de la page, puis les personnes de $profils */
  $uids = array();
  if ( is_singular() ) { $uids[] = (int) get_post_field( 'post_author', get_queried_object_id() ); }
  $uids = array_values( array_unique( array_filter( array_merge( $uids, array_keys( $profils ) ) ) ) );

  foreach ( $data as $k => $node ) {
    if ( ! is_array( $node ) || ( $node['@type'] ?? '' ) !== 'Person' || empty( $node['name'] ) ) { continue; }
    foreach ( $uids as $uid ) {
      $u = get_userdata( $uid );
      if ( ! $u || trim( (string) $u->display_name ) !== trim( (string) $node['name'] ) ) { continue; }
      $bio = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) get_the_author_meta( 'description', $uid ) ) ) );
      if ( $bio !== '' && empty( $node['description'] ) ) { $node['description'] = $bio; }
      if ( isset( $profils[ $uid ] ) ) {
        $node['url']      = home_url( $profils[ $uid ]['url'] );
        $node['jobTitle'] = $profils[ $uid ]['jobTitle'];
      }
      $data[ $k ] = $node;
      break;
    }
  }
  return $data;
}, 99, 2 );
