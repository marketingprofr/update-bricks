<?php
/* =====================================================================
   MEILLEURTEST — Top 5 « résumé » (carte hero n°1 + rangées 2-5)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément.
   Source : top_avis_ids (liste ordonnée du guide) -> 1 post = 1 produit.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF (note clients) + tag Amazon
   --------------------------------------------------------------------- */
$T5_CUST_RATING_FIELD = 'mltv5_score_avis_clients';    // note clients /5 (étoile)
$T5_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';   // nombre d'avis clients
$T5_AMAZON_TAG        = 'mlt00-21';                     // tag affilié Amazon

/* ---------------------------------------------------------------------
   Helpers (déclarés une fois)
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
  /* Extrait le nom du marchand depuis le domaine d'une URL.
     https://cdiscount.fr/sdf -> "Cdiscount" ; amazon.fr -> "Amazon". */
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
  /* Joint des chaînes : "A", "A et B", "A, B et C". */
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
  /* Repeater de points -> tableau de chaînes nettoyées. */
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

/* ---------------------------------------------------------------------
   Liste ordonnée des produits du guide
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
   PASSE 1 : collecte des données (contexte post requis pour les helpers)
   --------------------------------------------------------------------- */
global $post;
$mt5_saved_post = $post;

$products      = array();
$count_price   = 0;
$count_rating  = 0;
$pos           = 0;

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

  /* Image : featured en priorite, sinon URL externe ACF (hotlink partenaire) */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );
  if ( ! $img ) {
    $ext = get_field( 'mltv5_image_external_url', $pid );
    if ( is_array( $ext ) ) { $ext = isset( $ext['url'] ) ? $ext['url'] : ''; }
    $ext = trim( (string) $ext );
    if ( $ext !== '' ) { $img = $ext; }
  }

  /* Score rédac /10 + libellés */
  $score10 = function_exists( 'get_acf_score_divided_by_10' )
    ? get_acf_score_divided_by_10()
    : round( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10, 1 );
  $score_tag  = function_exists( 'get_acf_score_label' ) ? get_acf_score_label() : '';
  $prod_label = function_exists( 'get_default_product_label' )
    ? trim( (string) get_default_product_label( $pid, get_field( 'mltv5_score_recent', $pid ) ) )
    : '';

  /* Note clients (champs ACF du produit) */
  $cust_rating = trim( (string) get_field( $T5_CUST_RATING_FIELD, $pid ) );
  $cust_count  = trim( (string) get_field( $T5_CUST_COUNT_FIELD, $pid ) );

  /* Points + / - (max 2 / 1) */
  $pros = array_slice( mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' ), 0, 2 );
  $cons = array_slice( mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' ), 0, 1 );

  /* Offres : lien ASIN Amazon prioritaire, sinon liens perso ACF */
  $asin      = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix      = get_field( 'mltv5_prix_indicatif', $pid );
  $has_price = ( $prix !== '' && $prix !== null && mt5_num( $prix ) > 0 );

  $offer_urls   = array();
  $btn_fallback = '';
  if ( $asin !== '' ) {
    $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $T5_AMAZON_TAG;
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    $t = trim( (string) get_field( 'mltv5_texte_du_bouton_' . $i, $pid ) );
    if ( $u !== '' && strpos( $u, 'http' ) === 0 ) {
      $offer_urls[] = $u;
      if ( $btn_fallback === '' && $t !== '' ) { $btn_fallback = $t; }
    }
  }
  $offer_urls = array_values( array_unique( $offer_urls ) );

  $has_rating = ( trim( (string) $cust_rating ) !== '' && mt5_num( $cust_rating ) > 0 );
  if ( $has_price )  { $count_price++; }
  if ( $has_rating ) { $count_rating++; }

  $products[] = array(
    'pos'         => $pos,
    'name'        => $name,
    'brand'       => $brand,
    'tagline'     => $tagline,
    'summary'     => $summary,
    'label'       => $prod_label,
    'img'         => $img,
    'score10'     => $score10,
    'score_tag'   => $score_tag,
    'cust_rating' => $cust_rating,
    'cust_count'  => $cust_count,
    'pros'        => $pros,
    'cons'        => $cons,
    'offer_urls'  => $offer_urls,
    'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
    'cta_text'    => $has_price ? 'Voir le prix' : ( $btn_fallback !== '' ? $btn_fallback : "Voir l'offre" ),
    'price_num'   => mt5_num( $prix ),
    'rating_num'  => mt5_num( $cust_rating ),
    'modified'    => (int) get_post_modified_time( 'U', true, $pid ),
  );
}
$post = $mt5_saved_post;
wp_reset_postdata();

if ( empty( $products ) ) { return; }

/* ---------------------------------------------------------------------
   Onglets de tri (conditionnels)
   - Prix : au moins 3 produits avec prix
   - Avis clients : au moins 3 produits avec note clients
   - Aucun des deux -> bouton "Le plus récent" (tri par date de modif)
   --------------------------------------------------------------------- */
$show_price  = ( $count_price >= 3 );
$show_rating = ( $count_rating >= 3 );
$show_recent = ( ! $show_price && ! $show_rating );

/* ---------------------------------------------------------------------
   Titre dynamique : "Les {n} {meilleures/meilleurs} {type} en un coup d'œil"
   --------------------------------------------------------------------- */
$nb        = count( $products );
$mf        = isset( $page_tv['masculinsfeminins'] ) ? trim( (string) $page_tv['masculinsfeminins'] ) : '';
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
if ( $mf === '' ) { $mf = 'meilleures'; }
$head_title = 'Les ' . $nb . ' ' . esc_html( lcfirst( $mf ) )
  . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' )
  . ' en un coup d&rsquo;&oelig;il';
?>
<section class="mt-top5" aria-labelledby="mt-top5-title">
  <header class="t5-head">
    <div>
      <h2 class="t5-h2" id="mt-top5-title"><?php echo $head_title; ?></h2>
      <p class="t5-meta">Notre classement <?php echo esc_html( date_i18n( 'Y' ) ); ?>, totalement impartial et v&eacute;rifi&eacute; par la r&eacute;daction</p>
    </div>
  </header>

  <div class="t5-bar">
    <span class="lbl" id="mt-top5-sortlbl">Trier par</span>
    <div class="t5-tabs" role="group" aria-labelledby="mt-top5-sortlbl">
      <button type="button" class="t5-tab" aria-pressed="true" data-sort="rank"><span class="ico" aria-hidden="true">&#9733;</span> Notre s&eacute;lection</button>
      <?php if ( $show_price ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="price"><span class="ico" aria-hidden="true">&euro;</span> Prix</button>
      <?php endif; ?>
      <?php if ( $show_rating ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="rating"><span class="ico" aria-hidden="true">&#9829;</span> Avis des clients</button>
      <?php endif; ?>
      <?php if ( $show_recent ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="recent"><span class="ico" aria-hidden="true">&#8635;</span> Le plus r&eacute;cent</button>
      <?php endif; ?>
    </div>
    <a class="t5-howto" href="#methodologie">Comment nous &eacute;valuons <span class="arr" aria-hidden="true">&rarr;</span></a>
  </div>
  <p class="sr-only" role="status" data-t5-status></p>

  <ol class="t5-list" data-t5-list>
<?php foreach ( $products as $it ) : ?>
    <li class="t5-item<?php echo ( trim( (string) $it['cust_rating'] ) === '' ? ' t5-no-cust' : '' ); ?>" data-rank="<?php echo esc_attr( $it['pos'] ); ?>" data-price="<?php echo esc_attr( $it['price_num'] ); ?>" data-rating="<?php echo esc_attr( $it['rating_num'] ); ?>" data-modified="<?php echo esc_attr( $it['modified'] ); ?>">
      <article class="t5-card">
        <p class="t5-banner"><span class="b-num">N&deg;<?php echo $it['pos']; ?></span> <span class="b-label">Le choix de la r&eacute;daction</span></p>
        <span class="t5-rnum" aria-hidden="true"><?php echo $it['pos']; ?></span>
        <div class="t5-media">
          <div class="ph">
            <?php if ( $it['img'] ) : ?>
              <img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( $it['name'] ); ?>" loading="lazy">
            <?php else : ?>
              <span class="ph-cap"><?php echo esc_html( $it['name'] ); ?></span>
            <?php endif; ?>
            <span class="t5-laurel" aria-label="Meilleur choix"><i class="t5-laurel-ic" aria-hidden="true"></i></span>
          </div>
        </div>
        <div class="t5-body">
          <?php if ( $it['brand'] !== '' ) : ?><p class="t5-eyebrow"><?php echo esc_html( $it['brand'] ); ?></p><?php endif; ?>
          <h3 class="t5-name"><a href="#produit-n-<?php echo esc_attr( $it['pos'] ); ?>"><?php if ( $it['brand'] !== '' ) : ?><span class="t5-brand-inline"><?php echo esc_html( $it['brand'] ); ?> </span><?php endif; ?><?php echo esc_html( $it['name'] ); ?></a></h3>
          <?php if ( $it['label'] !== '' ) : ?><p class="t5-label"><?php echo esc_html( $it['label'] ); ?></p><?php endif; ?>
          <?php if ( $it['summary'] !== '' ) : ?><p class="t5-summary"><?php echo esc_html( $it['summary'] ); ?></p><?php endif; ?>
          <?php if ( $it['pros'] || $it['cons'] ) : ?>
          <ul class="t5-pc">
            <?php foreach ( $it['pros'] as $p ) : ?>
              <li class="pro"><span class="sr-only">Avantage : </span><?php echo esc_html( $p ); ?></li>
            <?php endforeach; ?>
            <?php foreach ( $it['cons'] as $c ) : ?>
              <li class="con"><span class="sr-only">Inconv&eacute;nient : </span><?php echo esc_html( $c ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="t5-readmore" href="#produit-n-<?php echo esc_attr( $it['pos'] ); ?>">Lire l'avis complet <span class="arr" aria-hidden="true">&darr;</span></a>
        </div>
        <div class="t5-aside">
          <div class="t5-ratings">
            <div class="t5-ed">
              <span class="r-lbl">Notre note</span>
              <div class="r-line"><span class="n"><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?><small>/10</small></span><?php if ( $it['score_tag'] !== '' ) : ?><span class="tag"><?php echo esc_html( $it['score_tag'] ); ?></span><?php endif; ?></div>
            </div>
            <?php if ( trim( (string) $it['cust_rating'] ) !== '' ) : ?>
            <div class="t5-cust">
              <span class="r-lbl">Avis clients</span>
              <span class="stars" aria-hidden="true">&#9733;</span> <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?></span>
              <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><span class="cust-count">&middot; <?php echo esc_html( $cl ); ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="t5-buy">
            <?php if ( $it['primary_url'] !== '' ) : ?>
            <a class="t5-cta" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['cta_text'] ); ?><span class="sr-only"> &mdash; <?php echo esc_attr( $it['name'] ); ?> (lien commercial)</span> <span class="arr" aria-hidden="true">&rarr;</span></a>
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
            <div class="t5-merchants">chez <?php echo mt5_join_et( $links ); ?></div>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </li>
<?php endforeach; ?>
  </ol>

  <p class="t5-disclosure">Liens commerciaux : Meilleurtest peut percevoir une commission sur les achats effectu&eacute;s via ces liens, sans impact sur le prix ni sur nos verdicts.</p>
</section>

<script>
(function () {
  var roots = document.querySelectorAll('.mt-top5');
  roots.forEach(function (root) {
    if (root.dataset.t5Init) return;
    root.dataset.t5Init = '1';
    var list = root.querySelector('[data-t5-list]');
    var tabs = root.querySelectorAll('.t5-tab');
    var status = root.querySelector('[data-t5-status]');
    var items = Array.prototype.slice.call(list.querySelectorAll('.t5-item'));
    var labels = { rank: 'notre sélection', price: 'prix croissant', rating: 'avis des clients', recent: 'le plus récent' };
    var bannerLabels = { rank: 'Le choix de la rédaction', price: 'Le meilleur pas cher', rating: 'Le choix de la communauté', recent: 'Le plus récent' };
    function num(el, key) { return parseFloat(el.getAttribute('data-' + key)) || 0; }
    var comparators = {
      rank:   function (a, b) { return num(a, 'rank') - num(b, 'rank'); },
      price:  function (a, b) { return num(a, 'price') - num(b, 'price'); },
      rating: function (a, b) { return num(b, 'rating') - num(a, 'rating'); },
      recent: function (a, b) { return num(b, 'modified') - num(a, 'modified'); }
    };
    function sortBy(key) {
      var first = items.map(function (c) { return c.getBoundingClientRect().top; });
      var ordered = items.slice().sort(comparators[key] || comparators.rank);
      var banner = bannerLabels[key] || bannerLabels.rank;
      ordered.forEach(function (c, i) {
        list.appendChild(c);
        var pos = i + 1;
        var bnum = c.querySelector('.t5-banner .b-num');
        if (bnum) bnum.textContent = 'N°' + pos;
        var blabel = c.querySelector('.t5-banner .b-label');
        if (blabel) blabel.textContent = banner;
        var rnum = c.querySelector('.t5-rnum');
        if (rnum) rnum.textContent = pos;
      });
      var moved = [];
      items.forEach(function (c, i) {
        var dy = first[i] - c.getBoundingClientRect().top;
        if (!dy) return;
        c.style.transition = 'none';
        c.style.transform = 'translateY(' + dy + 'px)';
        moved.push(c);
      });
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          moved.forEach(function (c) {
            c.style.transition = 'transform .45s cubic-bezier(.22,.61,.36,1)';
            c.style.transform = '';
          });
        });
      });
      setTimeout(function () { moved.forEach(function (c) { c.style.transition = ''; c.style.transform = ''; }); }, 600);
      if (status) status.textContent = 'Classement trié par ' + (labels[key] || labels.rank) + '.';
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.setAttribute('aria-pressed', 'false'); });
        tab.setAttribute('aria-pressed', 'true');
        sortBy(tab.getAttribute('data-sort'));
      });
    });
  });
})();
</script>
