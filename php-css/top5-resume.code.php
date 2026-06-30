<?php
/* =====================================================================
   MEILLEURTEST — Top 5 « résumé » (carte hero n°1 + rangées 2-5)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément.
   Source : top_avis_ids (liste ordonnée du guide) -> 1 post = 1 produit.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — à ajuster si besoin (noms de variables note clients)
   --------------------------------------------------------------------- */
$T5_CUST_RATING_VAR = 'note_clients';      // valeur /5 (étoile)  -> get_all_template_variables()
$T5_CUST_COUNT_VAR  = 'nbr_avis_clients';  // nombre d'avis clients
$T5_AMAZON_TAG      = 'mlt00-21';          // tag affilié Amazon

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
$ids     = array();
if ( function_exists( 'get_all_template_variables' ) ) {
  $tv  = get_all_template_variables( $page_id );
  $ids = isset( $tv['top_avis_ids'] ) && is_array( $tv['top_avis_ids'] ) ? $tv['top_avis_ids'] : array();
}
if ( empty( $ids ) ) {
  $rel = get_field( 'mltv5_best_products', $page_id ); // fallback : champ relation
  if ( is_array( $rel ) ) {
    foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
  }
}
$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

if ( empty( $ids ) ) { return; }

/* Titres dynamiques de l'en-tête */
$nb_produits = count( $ids );
$type_plur   = '';
if ( function_exists( 'get_all_template_variables' ) ) {
  $tvp       = get_all_template_variables( $page_id );
  $type_plur = isset( $tvp['type_de_produit_au_pluriel'] ) ? $tvp['type_de_produit_au_pluriel'] : '';
}
$head_title = 'Les ' . $nb_produits . ' ' . ( $type_plur !== '' ? esc_html( $type_plur ) : 'meilleures' ) . ' en un coup d&rsquo;&oelig;il';

global $post;
$mt5_saved_post = $post;
?>
<section class="mt-top5" aria-labelledby="mt-top5-title">
  <header class="t5-head">
    <div>
      <h2 class="t5-h2" id="mt-top5-title"><?php echo $head_title; ?></h2>
      <p class="t5-meta">Notre classement <?php echo esc_html( date_i18n( 'Y' ) ); ?>, v&eacute;rifi&eacute; par la r&eacute;daction</p>
    </div>
  </header>

  <div class="t5-bar">
    <span class="lbl" id="mt-top5-sortlbl">Trier par</span>
    <div class="t5-tabs" role="group" aria-labelledby="mt-top5-sortlbl">
      <button type="button" class="t5-tab" aria-pressed="true"  data-sort="rank"><span class="ico" aria-hidden="true">&#9733;</span> Notre s&eacute;lection</button>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="price"><span class="ico" aria-hidden="true">&euro;</span> Prix</button>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="rating"><span class="ico" aria-hidden="true">&#9829;</span> Avis des clients</button>
    </div>
    <a class="t5-howto" href="#methodologie">Comment nous &eacute;valuons <span class="arr" aria-hidden="true">&rarr;</span></a>
  </div>
  <p class="sr-only" role="status" data-t5-status></p>

  <ol class="t5-list" data-t5-list>
<?php
$pos = 0;
foreach ( $ids as $pid ) {
  $post = get_post( $pid );
  if ( ! $post ) { continue; }
  setup_postdata( $post );
  $pos++;

  /* --- Identité --- */
  $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
  $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
  $name    = $model !== '' ? $model : get_the_title( $pid );
  $tagline = trim( (string) get_field( 'mltv5_sous_titre', $pid ) );
  $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );

  /* --- Image --- */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );

  /* --- Score rédac /10 + libellé --- */
  if ( function_exists( 'get_acf_score_divided_by_10' ) ) {
    $score10 = get_acf_score_divided_by_10();
  } else {
    $score10 = round( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10, 1 );
  }
  $score_tag = function_exists( 'get_acf_score_label' ) ? get_acf_score_label() : '';

  /* --- Label verdict (sous le nom) --- */
  $prod_label = '';
  if ( function_exists( 'get_default_product_label' ) ) {
    $prod_label = trim( (string) get_default_product_label( $pid, get_field( 'mltv5_score_recent', $pid ) ) );
  }

  /* --- Note clients (étoile + nb avis) --- */
  $cust_rating = '';
  $cust_count  = '';
  if ( function_exists( 'get_all_template_variables' ) ) {
    $ptv         = get_all_template_variables( $pid );
    $cust_rating = isset( $ptv[ $T5_CUST_RATING_VAR ] ) ? $ptv[ $T5_CUST_RATING_VAR ] : '';
    $cust_count  = isset( $ptv[ $T5_CUST_COUNT_VAR ] )  ? $ptv[ $T5_CUST_COUNT_VAR ]  : '';
  }

  /* --- Points positifs / négatifs (max 2 + / 1 -) --- */
  $pros = array_slice( mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' ), 0, 2 );
  $cons = array_slice( mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' ), 0, 1 );

  /* --- Offres (Amazon prioritaire, puis liens perso) --- */
  $offers = array();
  $asin   = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix   = get_field( 'mltv5_prix_indicatif', $pid );
  if ( $asin !== '' ) {
    $offers[] = array( 'name' => 'Amazon', 'url' => 'https://www.amazon.fr/dp/' . esc_attr( $asin ) . '?tag=' . $T5_AMAZON_TAG );
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    $t = trim( (string) get_field( 'mltv5_texte_du_bouton_' . $i, $pid ) );
    if ( $u !== '' && $t !== '' && ( strpos( $u, 'http' ) === 0 ) ) {
      $offers[] = array( 'name' => $t, 'url' => $u );
    }
  }
  $primary    = ! empty( $offers ) ? $offers[0] : null;
  $review_url = '#produit-n-' . $pos; // ancre avis détaillé (cf. bloc titre)

  /* --- Données de tri --- */
  $d_rank   = $pos;
  $d_price  = mt5_num( $prix );
  $d_rating = mt5_num( $cust_rating );
  ?>
    <li class="t5-item" data-rank="<?php echo esc_attr( $d_rank ); ?>" data-price="<?php echo esc_attr( $d_price ); ?>" data-rating="<?php echo esc_attr( $d_rating ); ?>">
      <article class="t5-card">
        <p class="t5-banner"><span class="b-num">N&deg;<?php echo $pos; ?></span> <span class="b-label">Le choix de la r&eacute;daction</span></p>
        <span class="t5-rnum" aria-hidden="true"><?php echo $pos; ?></span>
        <div class="t5-media">
          <div class="ph">
            <?php if ( $img ) : ?>
              <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" style="width:100%;height:100%;object-fit:contain;display:block;">
            <?php else : ?>
              <span class="ph-cap"><?php echo esc_html( $name ); ?></span>
            <?php endif; ?>
            <span class="t5-laurel" aria-label="Meilleur choix"><img src="/wp-content/uploads/laurel-gold.svg" alt="" width="44" height="44"></span>
          </div>
        </div>
        <div class="t5-body">
          <?php if ( $brand !== '' ) : ?><p class="t5-eyebrow"><?php echo esc_html( $brand ); ?></p><?php endif; ?>
          <h3 class="t5-name"><a href="<?php echo esc_url( $review_url ); ?>"><?php echo esc_html( $name ); ?></a></h3>
          <?php if ( $prod_label !== '' ) : ?><p class="t5-label"><?php echo esc_html( $prod_label ); ?></p><?php endif; ?>
          <?php if ( $tagline !== '' ) : ?><p class="t5-tagline"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
          <?php if ( $summary !== '' ) : ?><p class="t5-summary"><?php echo esc_html( $summary ); ?></p><?php endif; ?>
          <?php if ( $pros || $cons ) : ?>
          <ul class="t5-pc">
            <?php foreach ( $pros as $p ) : ?>
              <li class="pro"><span class="sr-only">Avantage : </span><?php echo esc_html( $p ); ?></li>
            <?php endforeach; ?>
            <?php foreach ( $cons as $c ) : ?>
              <li class="con"><span class="sr-only">Inconv&eacute;nient : </span><?php echo esc_html( $c ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="t5-readmore" href="<?php echo esc_url( $review_url ); ?>">Lire l'avis complet <span class="arr" aria-hidden="true">&darr;</span></a>
        </div>
        <div class="t5-aside">
          <div class="t5-ratings">
            <div class="t5-ed">
              <span class="r-lbl">Notre note</span>
              <div class="r-line"><span class="n"><?php echo esc_html( number_format( (float) $score10, 1, ',', '' ) ); ?><small>/10</small></span><?php if ( $score_tag !== '' ) : ?><span class="tag"><?php echo esc_html( $score_tag ); ?></span><?php endif; ?></div>
            </div>
            <?php if ( $cust_rating !== '' ) : ?>
            <div class="t5-cust">
              <span class="r-lbl">Avis clients</span>
              <span class="stars" aria-hidden="true">&#9733;</span> <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $cust_rating ), 1, ',', '' ) ); ?></span>
              <?php $cl = mt5_reviews_label( $cust_count ); if ( $cl !== '' ) : ?><span class="cust-count">&middot; <?php echo esc_html( $cl ); ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="t5-buy">
            <?php if ( $primary ) : ?>
            <a class="t5-cta" href="<?php echo esc_url( $primary['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener">V&eacute;rifier le prix<span class="sr-only"> de <?php echo esc_attr( $name ); ?> (lien commercial)</span> <span class="arr" aria-hidden="true">&rarr;</span></a>
            <?php endif; ?>
            <?php if ( count( $offers ) > 1 ) : ?>
            <div class="t5-merchants">chez <?php
              $names = array();
              foreach ( $offers as $o ) { $names[] = '<a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $o['name'] ) . '</a>'; }
              echo implode( ', ', $names );
            ?></div>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </li>
  <?php
}
$post = $mt5_saved_post;
wp_reset_postdata();
?>
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
    var labels = { rank: 'notre sélection', price: 'prix croissant', rating: 'avis des clients' };
    var bannerLabels = { rank: 'Le choix de la rédaction', price: 'Le meilleur pas cher', rating: 'Le choix de la communauté' };
    function num(el, key) { return parseFloat(el.getAttribute('data-' + key)) || 0; }
    var comparators = {
      rank:   function (a, b) { return num(a, 'rank') - num(b, 'rank'); },
      price:  function (a, b) { return num(a, 'price') - num(b, 'price'); },
      rating: function (a, b) { return num(b, 'rating') - num(a, 'rating'); }
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
