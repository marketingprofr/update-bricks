<?php
/* =====================================================================
   MEILLEURTEST — Tableau comparatif (confrontation des produits du guide)
   Version éditoriale (cf. templates/template-tableau-comparatif.html).
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément
   (tableau-comparatif.css).
   Source : top_avis_ids (liste ordonnée du guide) -> 1 post = 1 produit.
   ↳ Même sourcing d'ID + même passe de collecte que top5-resume / top5-tests.
   Ancre de section : id="partie-tableau-comparatif" (lien du sommaire).
   Liens internes : nom produit + vignette -> #produit-n-{rang} (avis détaillé).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF + tag Amazon
   (specs : noms éprouvés de l'ancien tableau de prod, à conserver tels quels)
   --------------------------------------------------------------------- */
$TC_SPECS_FIELD       = 'mltv5_caracteristiques_du_produit';      // repeater fiche technique
$TC_SPEC_LABEL_KEY    = 'mltv5_caracteristique_produit';          // sous-champ intitulé
$TC_SPEC_VALUE_KEY    = 'mltv5_valeur_caracteristique_produit';   // sous-champ valeur
$TC_CUST_RATING_FIELD = 'mltv5_score_avis_clients';               // note clients /5
$TC_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';              // nombre d'avis clients
$TC_SPEC_MIN_SHARE    = 3;   // une caractéristique n'est affichée que si partagée par >= N produits
$TC_SPEC_VISIBLE      = 10;  // au-delà : repliées derrière le bouton « afficher plus »
$TC_MAX_PRODUCTS      = 5;
$TC_AMAZON_TAG        = 'mlt00-21';

/* ---------------------------------------------------------------------
   Helpers (partagés avec top5-resume / top5-tests — guards anti-redéclaration)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt5_num' ) ) {
  function mt5_num( $v ) {
    $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
    $v = str_replace( ',', '.', $v );
    return is_numeric( $v ) ? (float) $v : 0.0;
  }
}
if ( ! function_exists( 'mt5_reviews_label' ) ) {
  function mt5_reviews_label( $c ) {
    $n = (int) preg_replace( '/[^0-9]/', '', (string) $c );
    return $n > 0 ? number_format( $n, 0, ',', ' ' ) . ' avis' : '';
  }
}
if ( ! function_exists( 'mt5_merchant_name' ) ) {
  function mt5_merchant_name( $url ) {
    $host = parse_url( (string) $url, PHP_URL_HOST );
    if ( ! $host ) { return ''; }
    $host  = preg_replace( '/^www\./i', '', $host );
    $parts = explode( '.', $host );
    $label = isset( $parts[0] ) ? $parts[0] : '';
    return $label !== '' ? ucfirst( $label ) : '';
  }
}
if ( ! function_exists( 'mt5_join_et' ) ) {
  function mt5_join_et( $items ) {
    $items = array_values( array_filter( $items, function ( $v ) { return $v !== ''; } ) );
    $n     = count( $items );
    if ( $n === 0 ) { return ''; }
    if ( $n === 1 ) { return $items[0]; }
    $last = array_pop( $items );
    return implode( ', ', $items ) . ' et ' . $last;
  }
}
if ( ! function_exists( 'mt5_points' ) ) {
  function mt5_points( $field, $pid, $subkey ) {
    $rows = get_field( $field, $pid );
    $out  = array();
    if ( is_array( $rows ) ) {
      foreach ( $rows as $r ) {
        $p = isset( $r[ $subkey ] ) ? trim( (string) $r[ $subkey ] ) : '';
        if ( $p !== '' ) { $out[] = $p; }
      }
    }
    return $out;
  }
}
if ( ! function_exists( 'mtc_score_label' ) ) {
  /* Libellé de note /10 (fallback si get_acf_score_label() indisponible). */
  function mtc_score_label( $s10 ) {
    $s = (float) $s10;
    if ( $s >= 9 ) { return 'Exceptionnel'; }
    if ( $s >= 8 ) { return 'Excellent'; }
    if ( $s >= 7 ) { return 'Tr&egrave;s bien'; }
    if ( $s >= 6 ) { return 'Bien'; }
    if ( $s >= 5 ) { return 'Moyen'; }
    if ( $s >= 4 ) { return 'Passable'; }
    if ( $s >= 2 ) { return 'Mauvais'; }
    return 'Tr&egrave;s mauvais';
  }
}
if ( ! function_exists( 'mtc_score_level' ) ) {
  /* Couleur monochrome de la jauge selon la note /10 :
     >=9 primary | >=8 vert | >=7 jaune | >=6 orange | sinon rouge. */
  function mtc_score_level( $s10 ) {
    $s = (float) $s10;
    if ( $s >= 9 ) { return 'p'; }
    if ( $s >= 8 ) { return 'g'; }
    if ( $s >= 7 ) { return 'y'; }
    if ( $s >= 6 ) { return 'o'; }
    return 'r';
  }
}

/* ---------------------------------------------------------------------
   Liste ordonnée des produits du guide (même source que top5-resume/tests)
   --------------------------------------------------------------------- */
$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();

$ids = isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] ) ? $page_tv['top_avis_ids'] : array();
if ( empty( $ids ) ) {
  $rel = get_field( 'mltv5_best_products', $page_id ); // fallback : champ relation
  if ( is_array( $rel ) ) {
    foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
  }
}
$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
$ids = array_slice( $ids, 0, $TC_MAX_PRODUCTS );
if ( count( $ids ) < 2 ) { return; } // un comparatif a besoin d'au moins 2 produits

/* ---------------------------------------------------------------------
   PASSE 1 : collecte (contexte post requis pour les helpers de score)
   --------------------------------------------------------------------- */
global $post;
$tc_saved_post = $post;

$products    = array();
$specs_order = array(); // intitulés dans l'ordre de première apparition
$specs_count = array(); // intitulé => nb de produits qui le renseignent
$pos         = 0;

foreach ( $ids as $pid ) {
  $p = get_post( $pid );
  if ( ! $p ) { continue; }
  $post = $p;
  setup_postdata( $post );
  $pos++;

  /* Identité (marque + modèle séparés pour l'affichage centré) */
  $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
  $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
  $modname = $model !== '' ? $model : get_the_title( $pid );
  $name    = trim( $brand . ' ' . $modname );

  /* Image */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );

  /* Score rédac /10 + libellés (helpers en contexte post) */
  $raw_score = get_field( 'mltv5_score_recent', $pid );
  $score10   = function_exists( 'get_acf_score_divided_by_10' )
    ? (float) get_acf_score_divided_by_10()
    : round( mt5_num( $raw_score ) / 10, 1 );
  $score_tag = function_exists( 'get_acf_score_label' ) ? trim( (string) get_acf_score_label() ) : '';
  if ( $score_tag === '' ) { $score_tag = mtc_score_label( $score10 ); }
  $label = function_exists( 'get_default_product_label' )
    ? trim( (string) get_default_product_label( $pid, $raw_score ) )
    : '';

  /* Avis clients (note /5 + nombre) */
  $cust_rating = trim( (string) get_field( $TC_CUST_RATING_FIELD, $pid ) );
  $cust_count  = trim( (string) get_field( $TC_CUST_COUNT_FIELD, $pid ) );

  /* Résumé + points +/- */
  $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );
  $pros    = mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' );
  $cons    = mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' );

  /* Caractéristiques techniques (keyées par intitulé + compteur de partage) */
  $specs = array();
  $seen  = array();
  if ( have_rows( $TC_SPECS_FIELD, $pid ) ) {
    while ( have_rows( $TC_SPECS_FIELD, $pid ) ) {
      the_row();
      $sname = trim( (string) get_sub_field( $TC_SPEC_LABEL_KEY ) );
      $sval  = trim( (string) get_sub_field( $TC_SPEC_VALUE_KEY ) );
      if ( $sname === '' || $sval === '' ) { continue; }
      $specs[ $sname ] = $sval;
      if ( ! isset( $specs_count[ $sname ] ) ) { $specs_count[ $sname ] = 0; $specs_order[] = $sname; }
      if ( ! in_array( $sname, $seen, true ) ) { $specs_count[ $sname ]++; $seen[] = $sname; }
    }
  }

  /* Offres : ASIN Amazon prioritaire, puis liens perso ACF (prix = banderole seulement) */
  $asin      = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix      = get_field( 'mltv5_prix_indicatif', $pid );
  $price_num = mt5_num( $prix );

  $offer_urls = array();
  if ( $asin !== '' ) {
    $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $TC_AMAZON_TAG;
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    if ( $u !== '' && strpos( $u, 'http' ) === 0 ) { $offer_urls[] = $u; }
  }
  $offer_urls = array_values( array_unique( $offer_urls ) );

  $products[] = array(
    'pos'         => $pos,
    'name'        => $name,
    'brand'       => $brand,
    'modname'     => $modname,
    'img'         => $img,
    'score10'     => $score10,
    'score_tag'   => $score_tag,
    'score_pct'   => max( 0, min( 100, (int) round( $score10 * 10 ) ) ),
    'score_lvl'   => mtc_score_level( $score10 ),
    'cust_rating' => $cust_rating,
    'cust_count'  => $cust_count,
    'label'       => $label,
    'summary'     => $summary,
    'pros'        => $pros,
    'cons'        => $cons,
    'specs'       => $specs,
    'offer_urls'  => $offer_urls,
    'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
    'price_num'   => $price_num,
  );
}
$post = $tc_saved_post;
wp_reset_postdata();

if ( count( $products ) < 2 ) { return; }

/* ---------------------------------------------------------------------
   Caractéristiques de référence : partagées par >= TC_SPEC_MIN_SHARE produits,
   dans l'ordre de première apparition (lecture plus naturelle).
   --------------------------------------------------------------------- */
$ref_specs = array();
foreach ( $specs_order as $sname ) {
  if ( $specs_count[ $sname ] >= $TC_SPEC_MIN_SHARE ) { $ref_specs[] = $sname; }
}
$nb_specs        = count( $ref_specs );
$has_hidden_spec = ( $nb_specs > $TC_SPEC_VISIBLE );

/* ---------------------------------------------------------------------
   Banderoles : « Meilleur choix » (rang 1, primary) + « Meilleur prix »
   (le moins cher hors rang 1, autre couleur). Sans prix exploitable ->
   « Meilleure alternative » sur le rang 2.
   --------------------------------------------------------------------- */
$price_rank = null; $cheapest = PHP_INT_MAX; $n_price = 0;
foreach ( $products as $it ) {
  if ( $it['price_num'] > 0 ) {
    $n_price++;
    if ( $it['pos'] !== 1 && $it['price_num'] < $cheapest ) { $cheapest = $it['price_num']; $price_rank = $it['pos']; }
  }
}
$show_price = ( $n_price >= 2 && $price_rank !== null );
$alt_rank   = $show_price ? null : 2;

/* Lignes éditoriales : n'afficher une rangée que si au moins un produit a la donnée */
$any_verdict = false; $any_summary = false; $any_pros = false; $any_cons = false; $any_offer = false; $any_cust = false;
foreach ( $products as $it ) {
  if ( $it['label'] !== '' )     { $any_verdict = true; }
  if ( $it['summary'] !== '' )   { $any_summary = true; }
  if ( ! empty( $it['pros'] ) )  { $any_pros = true; }
  if ( ! empty( $it['cons'] ) )  { $any_cons = true; }
  if ( $it['primary_url'] !== '' ) { $any_offer = true; }
  if ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 ) { $any_cust = true; }
}

/* Libellé de la ligne d'achat : « Où l'acheter » si au moins un prix ACF,
   sinon « Meilleures offres » (classements sans prix : séries, etc.). */
$buy_label = ( $n_price >= 1 ) ? "O&ugrave; l'acheter" : 'Meilleures offres';

/* ---------------------------------------------------------------------
   Titre + sous-titre (adaptés au type de produit)
   --------------------------------------------------------------------- */
$nb        = count( $products );
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
$head_title = 'Tableau comparatif' . ( $type_plur !== '' ? ' : top ' . $nb . ' ' . esc_html( lcfirst( $type_plur ) ) : '' );
$sub = ( $type_plur !== '' )
  ? 'Nos ' . $nb . ' ' . esc_html( lcfirst( $type_plur ) ) . ', en face &agrave; face sur les crit&egrave;res qui comptent vraiment.'
  : 'Nos ' . $nb . ' laur&eacute;ats, en face &agrave; face sur les crit&egrave;res qui comptent vraiment.';

$colspan = $nb + 1;
$uid     = 'tc-' . substr( md5( (string) $page_id . '-' . $nb ), 0, 8 );
?>
<section class="mt-cmp-root" id="partie-tableau-comparatif" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
  <header class="mt-cmp-head">
    <h2 class="mt-cmp-h2" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo $head_title; ?></h2>
    <p class="mt-cmp-sub"><?php echo $sub; ?></p>
  </header>

  <p class="mt-cmp-hint" aria-hidden="true">Faites glisser le tableau pour comparer &rarr;</p>

  <div class="mt-cmp-wrap">
    <table class="mt-cmp" style="min-width: <?php echo (int) ( 160 + $nb * 190 ); ?>px;">
      <tbody>

        <!-- Rang (médaille + banderoles) + pastille engagement (1re colonne, rowspan 2) -->
        <tr>
          <td class="row-label brand-cell" rowspan="2">
            <div class="mt-cmp-brand">
              <span class="t5-laurel" aria-label="Notre engagement"><i class="t5-laurel-ic" aria-hidden="true"></i></span>
              <p class="mt-cmp-brand-note">Ce comparatif ne contient aucun produit sponsoris&eacute;</p>
            </div>
          </td>
          <?php foreach ( $products as $it ) :
            $banner = '';
            if ( $it['pos'] === 1 )                              { $banner = 'best'; }
            elseif ( $show_price && $it['pos'] === $price_rank )  { $banner = 'price'; }
            elseif ( $alt_rank !== null && $it['pos'] === $alt_rank ) { $banner = 'alt'; }
          ?>
          <td class="pos-cell">
            <div class="banner-slot">
              <?php if ( $banner === 'best' ) : ?><span class="col-banner b-best">&#9733; Meilleur choix</span>
              <?php elseif ( $banner === 'price' ) : ?><span class="col-banner b-price">&euro; Meilleur prix</span>
              <?php elseif ( $banner === 'alt' ) : ?><span class="col-banner b-alt">&#9829; Meilleure alternative</span>
              <?php endif; ?>
            </div>
            <span class="rank r<?php echo (int) $it['pos']; ?>"><span class="rank-n"><?php echo (int) $it['pos']; ?></span></span>
          </td>
          <?php endforeach; ?>
        </tr>

        <!-- Image + nom (marque au-dessus, modèle en dessous) -->
        <tr>
          <?php foreach ( $products as $it ) : ?>
          <td class="product-cell">
            <a class="product-thumb<?php echo $it['img'] ? '' : ' empty'; ?>" href="#produit-n-<?php echo (int) $it['pos']; ?>">
              <?php if ( $it['img'] ) : ?><img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( $it['name'] ); ?>" loading="lazy"><?php endif; ?>
            </a>
            <a class="product-name" href="#produit-n-<?php echo (int) $it['pos']; ?>">
              <?php if ( $it['brand'] !== '' ) : ?><span class="pn-brand"><?php echo esc_html( $it['brand'] ); ?></span><?php endif; ?>
              <span class="pn-model"><?php echo esc_html( $it['modname'] ); ?></span>
            </a>
          </td>
          <?php endforeach; ?>
        </tr>

        <?php if ( $any_verdict ) : ?>
        <!-- Verdict (texte « quote », primary) -->
        <tr>
          <td class="row-label">Verdict</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="verdict-cell">
            <?php if ( $it['label'] !== '' ) : ?><span class="verdict-txt"><?php echo esc_html( $it['label'] ); ?></span>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <!-- Note globale (jauge monochrome) -->
        <tr>
          <td class="row-label">Note globale</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="score-cell sc-<?php echo esc_attr( $it['score_lvl'] ); ?>">
            <div class="score-block"><b><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?></b><small>/10</small></div>
            <div class="score-gauge"><div class="score-gauge-fill" style="width: <?php echo (int) $it['score_pct']; ?>%;"></div></div>
            <?php if ( $it['score_tag'] !== '' ) : ?><div class="score-tag"><?php echo $it['score_tag']; ?></div><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>

        <?php if ( $any_cust ) : ?>
        <!-- Avis clients (note /5 + nombre) -->
        <tr>
          <td class="row-label">Avis clients</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="cust-cell">
            <?php if ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 ) : ?>
              <div class="cust-line"><span class="cust-star" aria-hidden="true">&#9733;</span> <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?><small>/5</small></span></div>
              <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><div class="cust-count"><?php echo esc_html( $cl ); ?></div><?php endif; ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_summary ) : ?>
        <!-- Résumé -->
        <tr>
          <td class="row-label">R&eacute;sum&eacute;</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp"><?php echo $it['summary'] !== '' ? esc_html( $it['summary'] ) : '<span class="mt-cmp-empty">&mdash;</span>'; ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_pros ) : ?>
        <!-- Points positifs (vert sombre, puces de séparation) -->
        <tr>
          <td class="row-label">Positif</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp pos">
            <?php if ( ! empty( $it['pros'] ) ) : ?>
              <?php echo implode( ' <span class="dot">&bull;</span> ', array_map( 'esc_html', $it['pros'] ) ); ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_cons ) : ?>
        <!-- Points négatifs (rouge sombre, puces de séparation) -->
        <tr>
          <td class="row-label">N&eacute;gatif</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp neg">
            <?php if ( ! empty( $it['cons'] ) ) : ?>
              <?php echo implode( ' <span class="dot">&bull;</span> ', array_map( 'esc_html', $it['cons'] ) ); ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_offer ) : ?>
        <!-- Achat (bouton + marchands, sans prix) -->
        <tr>
          <td class="row-label"><?php echo $buy_label; ?></td>
          <?php foreach ( $products as $it ) :
            $links = array();
            foreach ( $it['offer_urls'] as $u ) {
              $m = mt5_merchant_name( $u );
              if ( $m !== '' ) { $links[] = '<a href="' . esc_url( $u ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $m ) . '</a>'; }
            }
          ?>
          <td class="buy-cell">
            <?php if ( $it['primary_url'] !== '' ) : ?>
            <a class="cmp-btn" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener">Voir l'offre <span aria-hidden="true">&rarr;</span></a>
            <?php if ( ! empty( $links ) ) : ?><div class="buy-merchants">chez <?php echo mt5_join_et( $links ); ?></div><?php endif; ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $nb_specs > 0 ) : ?>
        <!-- Séparateur caractéristiques techniques -->
        <tr class="mt-cmp-sep"><td class="row-label section-head" colspan="<?php echo (int) $colspan; ?>">Caract&eacute;ristiques techniques</td></tr>

        <?php foreach ( $ref_specs as $i => $sname ) :
          $hidden = ( $i >= $TC_SPEC_VISIBLE );
        ?>
        <tr class="spec-row<?php echo $hidden ? ' spec-hidden' : ''; ?>"<?php echo $hidden ? ' style="display:none;"' : ''; ?> data-uid="<?php echo esc_attr( $uid ); ?>">
          <td class="row-label"><?php echo esc_html( $sname ); ?></td>
          <?php foreach ( $products as $it ) :
            $sv = isset( $it['specs'][ $sname ] ) ? $it['specs'][ $sname ] : '';
          ?>
          <td class="spec-cell"><?php echo $sv !== '' ? wp_kses_post( $sv ) : '<span class="mt-cmp-empty">&mdash;</span>'; ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>

      </tbody>
    </table>
  </div>

  <?php if ( $has_hidden_spec ) : ?>
  <div class="mt-cmp-more">
    <button type="button" class="mt-cmp-more-btn" data-uid="<?php echo esc_attr( $uid ); ?>" aria-expanded="false">
      <span class="more-on">Afficher toutes les caract&eacute;ristiques (<?php echo (int) $nb_specs; ?>)</span>
      <span class="more-off" style="display:none;">Masquer les caract&eacute;ristiques</span>
      <i class="fas fa-chevron-down chevron" aria-hidden="true"></i>
    </button>
  </div>
  <script>
  (function () {
    var uid = <?php echo wp_json_encode( $uid ); ?>;
    function init() {
      var btn = document.querySelector('.mt-cmp-more-btn[data-uid="' + uid + '"]');
      if (!btn) { return; }
      var rows = document.querySelectorAll('.spec-row.spec-hidden[data-uid="' + uid + '"]');
      var on = btn.querySelector('.more-on'), off = btn.querySelector('.more-off'), chev = btn.querySelector('.chevron');
      var open = false;
      btn.addEventListener('click', function () {
        open = !open;
        for (var i = 0; i < rows.length; i++) { rows[i].style.display = open ? 'table-row' : 'none'; }
        if (on)  { on.style.display  = open ? 'none' : 'inline'; }
        if (off) { off.style.display = open ? 'inline' : 'none'; }
        if (chev) { chev.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)'; }
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
  })();
  </script>
  <?php endif; ?>
</section>
