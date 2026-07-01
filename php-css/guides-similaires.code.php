<?php
/* =====================================================================
   MEILLEURTEST — « Tous les guides {type} » (grille de guides similaires)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément
   (guides-similaires.css). Réf. maquette : template-guides-similaires.html.

   Réutilise LE MÊME moteur de similarité que « Comparatifs similaires »
   (mt_sim_ranked_ids) mais en présentation « cartes » (image + titre + extrait).
   Différences avec le bloc pastilles :
     - le comparatif COURANT est EXCLU (« N autres guides ») ;
     - titres COMPLETS (pas de strip « Les meilleur(e)s… ») ;
     - carte = vignette (image à la une) + titre + extrait.
   Classement identique : produit + attributs (palier 2/3) puis catégorie (4),
   du plus proche au moins proche, en ≤ 2 WP_Query.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — slugs des taxonomies + réglages (à confirmer côté site)
   --------------------------------------------------------------------- */
$GS_TAX_PRODUCT = 'post-type-produit';    // taxonomie « produit »
$GS_TAX_ATTR    = 'post-type-attribut';   // taxonomie « attributs »
$GS_TAX_CAT     = 'category';             // catégorie principale (WP standard)
$GS_MAX         = 20;                     // nb de guides affichés (courant EXCLU)
$GS_DESC_ACF    = '';                     // champ ACF pour l'extrait ; vide = extrait auto
$GS_DESC_WORDS  = 22;                     // longueur de l'extrait (mots)
$GS_ANCHOR      = 'partie-guides-similaires';

/* ---------------------------------------------------------------------
   Helpers (déclarés une fois — cohabitent avec les autres blocs Code)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_sim_term_ids' ) ) {
  /* IDs des termes d'un post pour une taxonomie (array vide si aucun). */
  function mt_sim_term_ids( $post_id, $tax ) {
    $terms = get_the_terms( $post_id, $tax );
    if ( ! is_array( $terms ) ) { return array(); }
    $ids = array();
    foreach ( $terms as $t ) {
      if ( is_object( $t ) && isset( $t->term_id ) ) { $ids[] = (int) $t->term_id; }
    }
    return $ids;
  }
}
if ( ! function_exists( 'mt_sim_ranked_ids' ) ) {
  /* MOTEUR DE SIMILARITÉ PARTAGÉ (identique dans similaires.code.php et
     guides-similaires.code.php ; le 1er bloc chargé le définit, les autres
     réutilisent). Renvoie les IDs des comparatifs les plus proches de $cur_id
     (comparatif courant EXCLU), classés du plus proche au moins proche, en
     ≤ 2 WP_Query.
     $opts : tax_product, tax_attr, tax_cat (slugs) + max (nb max renvoyé).
     Paliers : 2 = même produit + TOUS les mêmes attributs (superset) ;
               3 = même produit (attributs différents) ; 4 = même catégorie.
     Tri : palier ↑, attributs partagés ↓, catégories partagées ↓, ID ↑. */
  function mt_sim_ranked_ids( $cur_id, $opts = array() ) {
    $tax_prod = isset( $opts['tax_product'] ) ? $opts['tax_product'] : 'post-type-produit';
    $tax_attr = isset( $opts['tax_attr'] )    ? $opts['tax_attr']    : 'post-type-attribut';
    $tax_cat  = isset( $opts['tax_cat'] )     ? $opts['tax_cat']     : 'category';
    $max      = isset( $opts['max'] )         ? (int) $opts['max']   : 20;

    $cur_id   = (int) $cur_id;
    $cur_type = get_post_type( $cur_id );
    if ( ! $cur_type || $max < 1 ) { return array(); }

    $cur_prod = mt_sim_term_ids( $cur_id, $tax_prod );
    $cur_attr = mt_sim_term_ids( $cur_id, $tax_attr );
    $cur_cat  = mt_sim_term_ids( $cur_id, $tax_cat );
    $cur_attr_total = count( $cur_attr );

    $seen  = array( $cur_id => true );   // jamais re-proposer le courant
    $cands = array();                    // id => données de tri

    /* Requête A — même taxonomie produit (paliers 2 & 3) */
    if ( ! empty( $cur_prod ) ) {
      $qa = new WP_Query( array(
        'post_type'           => $cur_type,
        'post_status'         => 'publish',
        'posts_per_page'      => 60,          // marge : on trie puis on coupe
        'post__not_in'        => array( $cur_id ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'tax_query'           => array( array(
          'taxonomy' => $tax_prod, 'field' => 'term_id', 'terms' => $cur_prod,
        ) ),
      ) );
      foreach ( $qa->posts as $p ) {
        $pid = (int) $p->ID;
        if ( isset( $seen[ $pid ] ) ) { continue; }
        $seen[ $pid ] = true;

        $a           = mt_sim_term_ids( $pid, $tax_attr );
        $attr_shared = count( array_intersect( $cur_attr, $a ) );
        $has_all_att = ( $cur_attr_total === 0 ) ? true : ( $attr_shared === $cur_attr_total );
        $c           = mt_sim_term_ids( $pid, $tax_cat );

        $cands[ $pid ] = array(
          'id'   => $pid,
          'tier' => $has_all_att ? 2 : 3,
          'attr' => $attr_shared,
          'cat'  => count( array_intersect( $cur_cat, $c ) ),
        );
      }
      wp_reset_postdata();
    }

    /* Requête B — même catégorie WordPress (palier 4), hors déjà pris */
    $need = $max - count( $cands );
    if ( ! empty( $cur_cat ) && $need > 0 ) {
      $qb = new WP_Query( array(
        'post_type'           => $cur_type,
        'post_status'         => 'publish',
        'posts_per_page'      => $need,
        'post__not_in'        => array_keys( $seen ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'tax_query'           => array( array(
          'taxonomy' => $tax_cat, 'field' => 'term_id', 'terms' => $cur_cat,
        ) ),
      ) );
      foreach ( $qb->posts as $p ) {
        $pid = (int) $p->ID;
        if ( isset( $seen[ $pid ] ) ) { continue; }
        $seen[ $pid ] = true;

        $c = mt_sim_term_ids( $pid, $tax_cat );
        $cands[ $pid ] = array(
          'id'   => $pid,
          'tier' => 4,
          'attr' => 0,
          'cat'  => count( array_intersect( $cur_cat, $c ) ),
        );
      }
      wp_reset_postdata();
    }

    /* Tri du plus proche au moins proche + coupe */
    $list = array_values( $cands );
    usort( $list, function ( $x, $y ) {
      if ( $x['tier'] !== $y['tier'] ) { return $x['tier'] - $y['tier']; }
      if ( $x['attr'] !== $y['attr'] ) { return $y['attr'] - $x['attr']; }
      if ( $x['cat']  !== $y['cat']  ) { return $y['cat']  - $x['cat']; }
      return $x['id'] - $y['id'];
    } );
    $list = array_slice( $list, 0, $max );

    $ids = array();
    foreach ( $list as $it ) { $ids[] = $it['id']; }
    return $ids;
  }
}
if ( ! function_exists( 'mt_gsim_excerpt' ) ) {
  /* Extrait court d'un guide : champ ACF si fourni, sinon extrait manuel WP,
     sinon début du contenu — tronqué à $words mots, sans HTML ni shortcodes. */
  function mt_gsim_excerpt( $post_id, $acf_field = '', $words = 22 ) {
    $text = '';
    if ( $acf_field !== '' && function_exists( 'get_field' ) ) {
      $v = get_field( $acf_field, $post_id );
      if ( is_string( $v ) ) { $text = $v; }
    }
    if ( trim( $text ) === '' ) {
      $text = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
    }
    $text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    return $text === '' ? '' : wp_trim_words( $text, (int) $words, '…' );
  }
}

/* ---------------------------------------------------------------------
   Contexte : type de produit au pluriel (titre) + classement
   --------------------------------------------------------------------- */
$page_id   = get_the_ID();
$page_tv   = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

$ids = mt_sim_ranked_ids( $page_id, array(
  'tax_product' => $GS_TAX_PRODUCT,
  'tax_attr'    => $GS_TAX_ATTR,
  'tax_cat'     => $GS_TAX_CAT,
  'max'         => (int) $GS_MAX,
) );

/* ---------------------------------------------------------------------
   Rendu — masqué s'il n'y a aucun guide proche
   --------------------------------------------------------------------- */
if ( ! empty( $ids ) ) :
  $n     = count( $ids );
  $title = $type_plur !== '' ? 'Tous les guides ' . $type_plur : 'Guides similaires';
  $sub   = $n . ' autre' . ( $n > 1 ? 's' : '' ) . ' guide' . ( $n > 1 ? 's' : '' )
         . ' plus spécifique' . ( $n > 1 ? 's' : '' ) . ' pour affiner votre choix';
  ?>
  <section class="mt-gsim" id="<?php echo esc_attr( $GS_ANCHOR ); ?>" aria-labelledby="mt-gsim-title">
    <h2 class="mt-gsim-h2" id="mt-gsim-title"><?php echo esc_html( $title ); ?></h2>
    <p class="mt-gsim-sub"><?php echo esc_html( $sub ); ?></p>
    <div class="mt-gsim-grid">
      <?php foreach ( $ids as $pid ) :
        $url = get_permalink( $pid );
        if ( ! $url ) { continue; }
        $ttl  = get_the_title( $pid );
        $img  = get_the_post_thumbnail_url( $pid, 'medium' );
        $desc = mt_gsim_excerpt( $pid, $GS_DESC_ACF, $GS_DESC_WORDS );
        ?>
        <a class="mt-gsim-card" href="<?php echo esc_url( $url ); ?>">
          <span class="mt-gsim-thumb<?php echo $img ? '' : ' is-empty'; ?>">
            <?php if ( $img ) : ?>
              <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $ttl ); ?>" loading="lazy">
            <?php endif; ?>
          </span>
          <h3 class="mt-gsim-title-c"><?php echo esc_html( $ttl ); ?></h3>
          <?php if ( $desc !== '' ) : ?>
            <p class="mt-gsim-desc"><?php echo esc_html( $desc ); ?></p>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
