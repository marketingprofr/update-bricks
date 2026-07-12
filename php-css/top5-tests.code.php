<?php
/* =====================================================================
   MEILLEURTEST — Top 5 « tests complets » (avis détaillés, 1 par produit)
   Version éditoriale (cf. templates/template-top5-tests.html).
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (top5-tests.css).
   Source : top_avis_ids (liste ordonnée du guide) -> 1 post = 1 produit.
   ↳ Même sourcing d'ID que top5-resume / build-hero (validé).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF (à confirmer côté site si besoin)
   --------------------------------------------------------------------- */
$TT_CUST_RATING_FIELD = 'mltv5_score_avis_clients';   // note clients /5
$TT_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';  // nombre d'avis clients
$TT_VERDICT_FIELD     = 'mltv5_verdict_court';        // libellé récompense (eyebrow), ex. « Notre coup de cœur »
$TT_FORWHO_FIELD      = 'mltv5_pour_qui';             // « À qui ça s'adresse » (optionnel, masqué si vide)
$TT_SPECS_FIELD       = 'mltv5_caracteristiques_du_produit'; // repeater fiche technique
$TT_SPEC_LABEL_KEY    = 'mltv5_caracteristique_intitule';    // sous-champ intitulé
$TT_SPEC_VALUE_KEY    = 'mltv5_caracteristique_valeur';      // sous-champ valeur
$TT_AMAZON_TAG        = 'mlt00-21';                          // tag affilié Amazon

/* ---------------------------------------------------------------------
   Helpers (partagés avec top5-resume — guards anti-redéclaration)
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
if ( ! function_exists( 'mt5_specs' ) ) {
  /* Repeater fiche technique -> tableau [ [intitulé, valeur], ... ].
     Tolérant : si les sous-clés configurées sont absentes, prend les deux
     premières valeurs scalaires non vides de la ligne (intitulé / valeur). */
  function mt5_specs( $field, $pid, $lbl_key, $val_key ) {
    $rows = get_field( $field, $pid );
    $out  = array();
    if ( ! is_array( $rows ) ) { return $out; }
    foreach ( $rows as $r ) {
      if ( ! is_array( $r ) ) { continue; }
      $lbl = isset( $r[ $lbl_key ] ) ? trim( (string) $r[ $lbl_key ] ) : '';
      $val = isset( $r[ $val_key ] ) ? trim( (string) $r[ $val_key ] ) : '';
      if ( $lbl === '' && $val === '' ) {
        $scal = array();
        foreach ( $r as $vv ) {
          if ( is_scalar( $vv ) && trim( (string) $vv ) !== '' ) { $scal[] = trim( (string) $vv ); }
        }
        if ( count( $scal ) >= 2 )      { $lbl = $scal[0]; $val = $scal[1]; }
        elseif ( count( $scal ) === 1 ) { $lbl = $scal[0]; }
      }
      if ( $lbl !== '' || $val !== '' ) { $out[] = array( $lbl, $val ); }
    }
    return $out;
  }
}
if ( ! function_exists( 'mt5_spec_val' ) ) {
  /* Valeur de spec : rend le HTML léger (icônes FA <i>, <br>, gras…) au lieu
     de l'échapper (sinon « <i class="fa-solid fa-check"></i> » s'affiche en
     texte). Whitelist stricte -> pas de script/style/img/iframe arbitraire. */
  function mt5_spec_val( $html ) {
    return wp_kses( (string) $html, array(
      'i'      => array( 'class' => true, 'aria-hidden' => true, 'title' => true, 'style' => true ),
      'span'   => array( 'class' => true, 'aria-hidden' => true, 'style' => true ),
      'br'     => array(),
      'strong' => array(), 'b' => array(), 'em' => array(),
      'sub'    => array(), 'sup' => array(),
      'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
    ) );
  }
}
if ( ! function_exists( 'mt5_norm_keys' ) ) {
  /* Normalise une liste de libellés d'attributs -> minuscules, sans espace de
     bord, sans vide ni doublon, triés alpha. Sert à comparer le jeu d'attributs
     du comparatif avec celui d'un « angle d'utilisation » d'avis consolidé
     (comparaison insensible à la casse ET à l'ordre). */
  function mt5_norm_keys( $list ) {
    $out = array();
    foreach ( (array) $list as $s ) {
      $s = str_replace( "\xC2\xA0", ' ', (string) $s ); // nbsp -> espace normal
      $s = preg_replace( '/\s+/u', ' ', $s );           // espaces multiples -> un seul
      $s = trim( $s );
      /* mb_strtolower : indispensable pour les accents (« Réduction » -> « réduction »),
         strtolower ASCII ne baisserait pas la casse des lettres accentuées. */
      $s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
      if ( $s !== '' ) { $out[] = $s; }
    }
    $out = array_values( array_unique( $out ) );
    sort( $out, SORT_STRING );
    return $out;
  }
}

/* ---------------------------------------------------------------------
   Liste ordonnée des produits du guide (même source que top5-resume)
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
if ( empty( $ids ) ) { return; }

/* ---------------------------------------------------------------------
   Attributs du comparatif courant (taxonomie post-type-attribut) :
   jeu normalisé (lowercase + tri alpha) qui sert, plus bas, à choisir le bon
   « angle d'utilisation » d'un avis consolidé. Vide -> contenu principal partout.
   --------------------------------------------------------------------- */
$comp_names = array();
$comp_terms = get_the_terms( $page_id, 'post-type-attribut' );
if ( is_array( $comp_terms ) ) {
  foreach ( $comp_terms as $t ) { $comp_names[] = isset( $t->name ) ? $t->name : ''; }
}
$comp_attr_keys = mt5_norm_keys( $comp_names );

/* ---------------------------------------------------------------------
   PASSE 1 : collecte (contexte post requis pour les helpers de score)
   --------------------------------------------------------------------- */
global $post;
$tt_saved_post = $post;

$products = array();
$pos      = 0;

foreach ( $ids as $pid ) {
  $p = get_post( $pid );
  if ( ! $p ) { continue; }
  $post = $p;
  setup_postdata( $post );
  $pos++;

  /* Identité */
  $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
  $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
  $name    = $model !== '' ? $model : get_the_title( $pid );
  $tagline = trim( (string) get_field( 'mltv5_sous_titre', $pid ) );
  $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );
  $verdict = trim( (string) get_field( $TT_VERDICT_FIELD, $pid ) );
  $forwho  = trim( (string) get_field( $TT_FORWHO_FIELD, $pid ) );

  /* Image mise en avant : featured en priorite, sinon URL externe ACF (hotlink partenaire) */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );
  if ( ! $img ) {
    $ext = get_field( 'mltv5_image_external_url', $pid );
    if ( is_array( $ext ) ) { $ext = isset( $ext['url'] ) ? $ext['url'] : ''; }
    $ext = trim( (string) $ext );
    if ( $ext !== '' ) { $img = $ext; }
  }

  /* Score rédac /10 + libellé qualitatif */
  $score10 = function_exists( 'get_acf_score_divided_by_10' )
    ? get_acf_score_divided_by_10()
    : round( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10, 1 );
  $score_tag = function_exists( 'get_acf_score_label' ) ? get_acf_score_label() : '';

  /* Note clients */
  $cust_rating = trim( (string) get_field( $TT_CUST_RATING_FIELD, $pid ) );
  $cust_count  = trim( (string) get_field( $TT_CUST_COUNT_FIELD, $pid ) );

  /* Points + / - (répéteurs) */
  $pros = mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' );
  $cons = mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' );

  /* Fiche technique */
  $specs = mt5_specs( $TT_SPECS_FIELD, $pid, $TT_SPEC_LABEL_KEY, $TT_SPEC_VALUE_KEY );

  /* Corps de l'avis = contenu WordPress du produit */
  $body_html = apply_filters( 'the_content', get_the_content( null, false, $p ) );

  /* Avis consolidés : si le comparatif porte des attributs, chercher dans le
     repeater mltv5_utilisations_du_produit l'« angle d'utilisation » dont les
     attributs (mltv5_nom_utilisation_produit, virgule-séparés) forment EXACTEMENT
     le même jeu que ceux du comparatif (casse/ordre ignorés). Match -> on affiche
     son wysiwyg à la place du contenu principal ; sinon fallback contenu principal.
     Le wysiwyg ACF est déjà filtré (the_content) -> rendu cohérent avec ci-dessus. */
  if ( ! empty( $comp_attr_keys ) ) {
    $uses = get_field( 'mltv5_utilisations_du_produit', $pid );
    if ( is_array( $uses ) ) {
      foreach ( $uses as $u ) {
        if ( ! is_array( $u ) ) { continue; }
        $raw  = isset( $u['mltv5_nom_utilisation_produit'] ) ? (string) $u['mltv5_nom_utilisation_produit'] : '';
        $keys = mt5_norm_keys( explode( ',', $raw ) );
        if ( $keys === $comp_attr_keys ) {
          $rich = isset( $u['mltv5_avantages_inconvenients_utilisation'] ) ? (string) $u['mltv5_avantages_inconvenients_utilisation'] : '';
          if ( trim( $rich ) !== '' ) { $body_html = $rich; }
          break; // un seul angle attendu par jeu d'attributs
        }
      }
    }
  }

  /* Offres : ASIN Amazon prioritaire, puis liens perso ACF */
  $asin       = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix       = get_field( 'mltv5_prix_indicatif', $pid );
  $has_price  = ( $prix !== '' && $prix !== null && mt5_num( $prix ) > 0 );
  $offer_urls = array();
  $btn_first  = '';
  if ( $asin !== '' ) {
    $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $TT_AMAZON_TAG;
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    $t = trim( (string) get_field( 'mltv5_texte_du_bouton_' . $i, $pid ) );
    if ( $u !== '' && strpos( $u, 'http' ) === 0 ) {
      $offer_urls[] = $u;
      if ( $btn_first === '' && $t !== '' ) { $btn_first = $t; }
    }
  }
  $offer_urls = array_values( array_unique( $offer_urls ) );

  $products[] = array(
    'pos'         => $pos,
    'name'        => $name,
    'brand'       => $brand,
    'tagline'     => $tagline,
    'summary'     => $summary,
    'verdict'     => $verdict,
    'forwho'      => $forwho,
    'img'         => $img,
    'score10'     => $score10,
    'score_tag'   => $score_tag,
    'cust_rating' => $cust_rating,
    'cust_count'  => $cust_count,
    'pros'        => $pros,
    'cons'        => $cons,
    'specs'       => $specs,
    'body'        => $body_html,
    'offer_urls'  => $offer_urls,
    'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
    'cta_text'    => $has_price ? 'Voir le prix' : ( $btn_first !== '' ? $btn_first : "Voir l'offre" ),
  );
}
$post = $tt_saved_post;
wp_reset_postdata();

if ( empty( $products ) ) { return; }

/* ---------------------------------------------------------------------
   En-tête de section (affiché une seule fois)
   --------------------------------------------------------------------- */
$nb        = count( $products );
$type_sing = isset( $page_tv['type_de_produit_au_singulier'] ) ? trim( (string) $page_tv['type_de_produit_au_singulier'] ) : '';
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
if ( $type_sing === '' ) { $type_sing = $type_plur; }

$head_h2 = 'Le test complet de chaque' . ( $type_sing !== '' ? ' ' . esc_html( $type_sing ) : ' produit' );
$head_p  = 'Notre r&eacute;daction a pass&eacute; en revue ' . (int) $nb
  . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : ' produits' )
  . ', en analysant leurs caract&eacute;ristiques, les retours utilisateurs et le rapport qualit&eacute;-prix. Voici notre avis complet, produit par produit.';
?>
<section class="ed-a contenu-principal" id="partie-tests-complets" aria-labelledby="ed-a-title">
  <div class="ed-a-head">
    <p class="kick">Nos avis d&eacute;taill&eacute;s</p>
    <h2 class="ed-a-serif" id="ed-a-title"><?php echo $head_h2; ?></h2>
    <p><?php echo $head_p; ?></p>
    <div class="rule"></div>
  </div>

<?php foreach ( $products as $it ) :
  $fill = max( 0, min( 100, round( mt5_num( $it['cust_rating'] ) / 5 * 100 ) ) );
?>
  <article class="ed-a-piece" id="produit-n-<?php echo (int) $it['pos']; ?>">
    <div class="ed-a-col">
      <div class="ed-a-eyebrow">
        <span class="num">N&deg;<?php echo esc_html( sprintf( '%02d', $it['pos'] ) ); ?></span>
        <?php if ( $it['verdict'] !== '' ) : ?>
        <span class="sep"></span>
        <span class="award gold"><?php echo esc_html( $it['verdict'] ); ?></span>
        <?php endif; ?>
      </div>
      <h3 class="ed-a-name"><?php if ( $it['brand'] !== '' ) : ?><?php echo esc_html( $it['brand'] ); ?> <?php endif; ?><span><?php echo esc_html( $it['name'] ); ?></span></h3>
      <?php if ( $it['tagline'] !== '' ) : ?><p class="ed-a-deck"><?php echo esc_html( $it['tagline'] ); ?></p><?php endif; ?>
    </div>

    <figure class="ed-a-figure">
      <div class="ph">
        <div class="ph-img">
          <?php if ( $it['img'] ) : ?>
            <img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( trim( $it['brand'] . ' ' . $it['name'] ) ); ?>" loading="lazy">
          <?php else : ?>
            <span class="ph-cap"><?php echo esc_html( $it['name'] ); ?></span>
          <?php endif; ?>
        </div>
        <?php if ( $it['summary'] !== '' ) : ?>
        <div class="ph-info">
          <p class="ph-verdict"><?php echo wp_kses_post( $it['summary'] ); ?></p>
        </div>
        <?php endif; ?>
      </div>
    </figure>

    <div class="ed-a-col">
      <?php
      $has_cust  = ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 );
      /* Pas d'avis clients : on comble le slot avec le 1er point fort. */
      $first_pro = ( ! $has_cust && ! empty( $it['pros'] ) ) ? $it['pros'][0] : '';
      $buy_class = $has_cust ? '' : ( $first_pro !== '' ? ' no-cust has-pro' : ' no-cust' );
      ?>
      <div class="ed-a-buy<?php echo $buy_class; ?>">
        <div class="note-block">
          <span class="r-lbl">Notre note</span>
          <div class="r-line">
            <span class="n"><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?><small>/10</small></span>
            <?php if ( $it['score_tag'] !== '' ) : ?><span class="tag"><?php echo esc_html( $it['score_tag'] ); ?></span><?php endif; ?>
          </div>
        </div>
        <?php if ( $has_cust ) : ?>
        <div class="cust-block">
          <span class="r-lbl">Avis clients</span>
          <div class="cust-line">
            <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?></span>
            <span class="stars-gauge" aria-label="note clients">
              <span>&#9733;&#9733;&#9733;&#9733;&#9733;</span>
              <span class="fill" style="width: <?php echo (int) $fill; ?>%;">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
            </span>
            <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><span class="cust-count"><?php echo esc_html( $cl ); ?></span><?php endif; ?>
          </div>
        </div>
        <?php elseif ( $first_pro !== '' ) : ?>
        <div class="pro-block">
          <span class="r-lbl">Son point fort</span>
          <p class="pro-line"><?php echo esc_html( $first_pro ); ?></p>
        </div>
        <?php endif; ?>
        <div class="cta-block">
          <?php if ( $it['primary_url'] !== '' ) : ?>
          <a class="cta" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['cta_text'] ); ?> <span class="arr">&#8594;</span></a>
          <?php endif; ?>
          <?php
          $links = array();
          foreach ( $it['offer_urls'] as $u ) {
            $mn = mt5_merchant_name( $u );
            if ( $mn !== '' ) {
              $links[] = '<a href="' . esc_url( $u ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $mn ) . '</a>';
            }
          }
          if ( ! empty( $links ) ) : ?>
          <div class="merchants">chez <?php echo mt5_join_et( $links ); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ( $it['pros'] || $it['cons'] ) : ?>
      <div class="ed-a-pc<?php echo ( $it['pros'] && $it['cons'] ) ? '' : ' single'; ?>">
        <?php if ( $it['pros'] ) : ?>
        <div class="col pros">
          <h5>Points positifs</h5>
          <ul>
            <?php foreach ( $it['pros'] as $pp ) : ?><li><?php echo esc_html( $pp ); ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ( $it['cons'] ) : ?>
        <div class="col cons">
          <h5>Points n&eacute;gatifs</h5>
          <ul>
            <?php foreach ( $it['cons'] as $cc ) : ?><li><?php echo esc_html( $cc ); ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="ed-a-body">
        <?php echo $it['body']; // post_content déjà filtré par the_content ?>

        <?php if ( $it['forwho'] !== '' ) : ?>
        <div class="ed-a-forwho">
          <h5>&Agrave; qui s'adresse ce <?php echo esc_html( $type_sing !== '' ? $type_sing : 'produit' ); ?>&nbsp;?</h5>
          <p><?php echo esc_html( $it['forwho'] ); ?></p>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $it['specs'] ) ) : ?>
        <details class="ed-a-specs">
          <summary>Fiche technique compl&egrave;te <span class="chev" aria-hidden="true">&#8964;</span></summary>
          <dl>
            <?php foreach ( $it['specs'] as $sp ) : ?>
            <div class="row"><dt><?php echo esc_html( $sp[0] ); ?></dt><dd><?php echo mt5_spec_val( $sp[1] ); ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </article>
<?php endforeach; ?>
</section>
