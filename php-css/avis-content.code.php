<?php
/* =====================================================================
   FICHE PRODUIT / AVIS — CONTENU — avis-content.code.php
   Doit être placé APRÈS avis-hero.code.php dans le template Bricks.
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (avis-content.css).
   ===================================================================== */

/* Récupération des données chargées par avis-hero.code.php */
if ( ! isset( $GLOBALS['fp_data'] ) ) return;
extract( $GLOBALS['fp_data'] );

?>

  <?php /* ════════════ BODY ════════════ */ ?>
  <div class="fp-body">
    <div class="fp-main">

    <?php foreach ( $FP_BLOCKS as $fp_b ) : switch ( $fp_b ) :

      /* ─── REVIEW ─── */
      case 'review':
        if ( empty( trim( strip_tags( $review_html ) ) ) ) break;
        ?>
        <h2 class="fp-stitle lead">Notre avis sur <?php echo esc_html( mb_strtolower( $brand ) !== '' ? 'le ' . $product_name : get_the_title( $pid ) ); ?></h2>
        <div class="fp-review"><?php echo $review_html; ?></div>
        <?php break;

      /* ─── COMPARATIFS ─── */
      case 'comparatifs':
        if ( empty( $fp_comparatifs ) ) break;
        $nb_comp = count( $fp_comparatifs );
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo esc_html( $product_name ); ?> est dans le top de <?php echo $nb_comp; ?> comparatif<?php echo $nb_comp > 1 ? 's' : ''; ?></h3>
          <div class="fp-comp-list">
            <?php foreach ( $fp_comparatifs as $ci => $c ) : ?>
            <a class="fp-comp-card<?php echo $ci >= $FP_COMP_VISIBLE ? ' fp-comp-extra' : ''; ?>" href="<?php echo esc_url( $c['url'] ); ?>"<?php echo $ci >= $FP_COMP_VISIBLE ? ' style="display:none"' : ''; ?>>
              <?php if ( ! empty( $c['thumb'] ) ) : ?>
              <div class="comp-thumb"><img src="<?php echo esc_url( $c['thumb'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
              <?php endif; ?>
              <div class="comp-info">
                <?php if ( $c['rank'] > 0 ) : ?>
                  <span class="comp-rank <?php echo fp_medal( $c['rank'] ); ?>"><?php echo $FP_SVG_STAR; ?> Classé n°<?php echo $c['rank']; ?> dans</span>
                <?php endif; ?>
                <h4 class="comp-title"><?php echo esc_html( $c['title'] ); ?></h4>
                <?php if ( ! empty( $c['excerpt'] ) ) : ?>
                  <span class="comp-excerpt"><?php echo esc_html( $c['excerpt'] ); ?></span>
                <?php endif; ?>
                <span class="comp-cta">Voir le comparatif →</span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php if ( $nb_comp > $FP_COMP_VISIBLE ) : ?>
          <button type="button" class="fp-comp-toggle" onclick="(function(b){var e=b.closest('.fp-interblock').querySelectorAll('.fp-comp-extra'),v=e[0]&&e[0].style.display==='none';e.forEach(function(c){c.style.display=v?'flex':'none'});b.querySelector('.show-t').style.display=v?'none':'inline';b.querySelector('.hide-t').style.display=v?'inline':'none'})(this)">
            <span class="show-t">Afficher les <?php echo $nb_comp - $FP_COMP_VISIBLE; ?> autres comparatifs <?php echo $FP_SVG_CHEV; ?></span>
            <span class="hide-t" style="display:none">Masquer <?php echo $FP_SVG_CHEV; ?></span>
          </button>
          <?php endif; ?>
        </div>
        <?php break;

      /* ─── PROS / CONS ─── */
      case 'pros_cons':
        if ( empty( $pros ) && empty( $cons ) ) break;
        ?>
        <h3 class="fp-stitle">Points forts et points faibles</h3>
        <div class="fp-pc-grid">
          <?php if ( ! empty( $pros ) ) : ?>
          <div class="fp-pc pros">
            <h4>Points forts</h4>
            <ul><?php foreach ( $pros as $p ) : ?><li><?php echo esc_html( $p ); ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
          <?php if ( ! empty( $cons ) ) : ?>
          <div class="fp-pc cons">
            <h4>Points faibles</h4>
            <ul><?php foreach ( $cons as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
        </div>
        <?php break;

      /* ─── AUDIENCE ─── */
      case 'audience':
        if ( $audience === '' ) break;
        ?>
        <h3 class="fp-stitle">À qui s'adresse ce produit ?</h3>
        <div class="fp-review"><p><?php echo wp_kses_post( $audience ); ?></p></div>
        <?php break;

      /* ─── PRICE HISTORY ─── */
      case 'price_history':
        if ( count( $ph_vals ) !== 6 ) break;
        $ph_min = min( $ph_vals );
        $ph_max = max( $ph_vals );
        $ph_cur = end( $ph_vals );
        $ph_avg = round( array_sum( $ph_vals ) / 6 );
        $ph_months = array();
        $ph_month_names = array( 1=>'Jan', 2=>'Fév', 3=>'Mar', 4=>'Avr', 5=>'Mai', 6=>'Juin', 7=>'Juil', 8=>'Août', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Déc' );
        for ( $mi = 5; $mi >= 0; $mi-- ) {
          $m = (int) date( 'n', strtotime( '-' . $mi . ' months' ) );
          $ph_months[] = $ph_month_names[ $m ];
        }
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle">Évolution du prix sur les 6 derniers mois</h3>
          <div class="fp-price-hist">
            <div class="fp-ph-summary">
              <div class="fp-ph-stat"><div class="k">Prix actuel</div><div class="v<?php echo $ph_cur <= $ph_min ? ' low' : ''; ?>"><?php echo fp_format_price( $ph_cur ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Plus bas</div><div class="v"><?php echo fp_format_price( $ph_min ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Plus haut</div><div class="v high"><?php echo fp_format_price( $ph_max ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Moyenne</div><div class="v"><?php echo fp_format_price( $ph_avg ); ?></div></div>
            </div>
            <?php /* Chart SVG — 6 points, oldest (gauche) → newest (droite) */
            $w   = 760;
            $h   = 130;
            $pad = 40;
            $range = max( 1, $ph_max - $ph_min );
            $points = array();
            for ( $i = 0; $i < 6; $i++ ) {
              $x = $pad + ( $i / 5 ) * ( $w - 2 * $pad );
              $y = $h - 10 - ( ( $ph_vals[ $i ] - $ph_min ) / $range ) * ( $h - 30 );
              $points[] = array( $x, $y, $ph_vals[ $i ] );
            }
            $line_d = 'M' . implode( ' L', array_map( function( $p ) { return round( $p[0], 1 ) . ',' . round( $p[1], 1 ); }, $points ) );
            $area_d = $line_d . ' L' . round( end( $points )[0], 1 ) . ',' . $h . ' L' . round( $points[0][0], 1 ) . ',' . $h . ' Z';
            ?>
            <div class="fp-ph-chart">
              <svg viewBox="0 0 <?php echo $w; ?> <?php echo $h + 20; ?>" role="img" aria-label="Évolution du prix">
                <path class="area" d="<?php echo $area_d; ?>"/>
                <path class="line" d="<?php echo $line_d; ?>"/>
                <?php foreach ( $points as $i => $p ) :
                  $is_now = ( $i === 5 );
                ?>
                <text class="val<?php echo $is_now ? ' now' : ''; ?>" x="<?php echo round( $p[0], 1 ); ?>" y="<?php echo round( $p[1] - 10, 1 ); ?>"><?php echo fp_format_price( $p[2] ); ?></text>
                <circle class="dot<?php echo $is_now ? ' now' : ''; ?>" cx="<?php echo round( $p[0], 1 ); ?>" cy="<?php echo round( $p[1], 1 ); ?>" r="<?php echo $is_now ? 5 : 4; ?>"/>
                <?php endforeach; ?>
              </svg>
            </div>
            <div class="fp-ph-labels"><?php foreach ( $ph_months as $ml ) : ?><span><?php echo $ml; ?></span><?php endforeach; ?></div>
            <?php if ( $ph_cur <= $ph_min ) : ?>
            <p class="fp-ph-foot">→ <b>C'est le moment d'acheter :</b> le prix est actuellement au plus bas.</p>
            <?php endif; ?>
            <?php if ( ! empty( $offers ) ) : ?>
            <div class="fp-buy">
              <?php $ph_primary = $offers[0]; $ph_others = array_slice( $offers, 1 ); ?>
              <div class="fp-buy-opt">
                <a class="fp-buy-btn" href="<?php echo esc_url( $ph_primary['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $ph_primary['text'] ); ?> <?php echo $FP_SVG_EXT; ?></a>
                <?php if ( ! empty( $ph_others ) ) : ?>
                  <div class="fp-also">Également sur <?php
                    $ph_names = array();
                    foreach ( $ph_others as $o ) { $ph_names[] = '<a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $o['name'] ?: $o['text'] ) . '</a>'; }
                    echo implode( ', ', $ph_names );
                  ?></div>
                <?php endif; ?>
              </div>
              <?php if ( $fp_idealo_url !== '' ) : ?>
              <div class="fp-buy-or"><span>ou</span></div>
              <div class="fp-buy-opt">
                <a class="fp-buy-btn secondary" href="<?php echo esc_url( $fp_idealo_url ); ?>" target="_blank" rel="nofollow noopener">Meilleur prix sur Idealo <?php echo $FP_SVG_ARROW; ?></a>
                <div class="fp-price-avg">Prix moyen constaté : <b><?php echo $price_fmt; ?></b></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php break;

      /* ─── SPECS ─── */
      case 'specs':
        if ( empty( $specs ) ) break;
        $half = (int) ceil( count( $specs ) / 2 );
        ?>
        <h3 class="fp-stitle">Fiche technique</h3>
        <div class="fp-specs">
          <div class="fp-specs-group-title">Caractéristiques principales</div>
          <div class="fp-specs-cols">
            <div class="fp-specs-col">
              <?php for ( $i = 0; $i < $half; $i++ ) : if ( ! isset( $specs[ $i ] ) ) continue; ?>
              <div class="fp-spec-row"><span class="k"><?php echo esc_html( $specs[ $i ][0] ); ?></span><span class="v"><?php echo wp_kses_post( $specs[ $i ][1] ); ?></span></div>
              <?php endfor; ?>
            </div>
            <div class="fp-specs-col">
              <?php for ( $i = $half; $i < count( $specs ); $i++ ) : if ( ! isset( $specs[ $i ] ) ) continue; ?>
              <div class="fp-spec-row"><span class="k"><?php echo esc_html( $specs[ $i ][0] ); ?></span><span class="v"><?php echo wp_kses_post( $specs[ $i ][1] ); ?></span></div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
        <?php break;

      /* ─── ALTERNATIVES ─── */
      case 'alternatives':
        if ( $alt_premium_id <= 0 && $alt_budget_id <= 0 ) break;
        $alts = array();
        foreach ( array(
          array( 'id' => $alt_premium_id, 'type' => 'premium', 'kicker' => 'Choix haut de gamme' ),
          array( 'id' => $alt_budget_id,  'type' => 'budget',  'kicker' => 'Choix pas cher' ),
        ) as $alt_def ) {
          if ( $alt_def['id'] <= 0 ) continue;
          $aid = $alt_def['id'];
          $d = fp_product_data( $aid, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
          $d['id']      = $aid;
          $d['type']    = $alt_def['type'];
          $d['kicker']  = $alt_def['kicker'];
          $d['url']     = get_permalink( $aid );
          $a_pros = function_exists( 'mt5_points' )
            ? mt5_points( $FP_PROS, $aid, $FP_PROS_SUB )
            : array();
          if ( empty( $a_pros ) ) {
            $raw_pros = get_field( $FP_PROS, $aid ) ?: array();
            if ( is_array( $raw_pros ) ) {
              foreach ( $raw_pros as $rp ) {
                $txt = isset( $rp[ $FP_PROS_SUB ] ) ? trim( $rp[ $FP_PROS_SUB ] ) : '';
                if ( $txt !== '' ) $a_pros[] = $txt;
              }
            }
          }
          $d['pros'] = array_slice( $a_pros, 0, 3 );
          $alts[] = $d;
        }
        $nb_alts = count( $alts );
        if ( $nb_alts === 0 ) break;
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo $nb_alts; ?> alternative<?php echo $nb_alts > 1 ? 's' : ''; ?> à considérer en fonction de votre budget</h3>
          <div class="fp-pick-prose">
            <p>Si <?php echo esc_html( $product_name ); ?> ne correspond pas tout à fait à votre budget, <?php echo $nb_alts > 1 ? 'deux options méritent' : 'une option mérite'; ?> le détour.</p>
            <ul>
              <?php foreach ( $alts as $a ) : ?>
              <li><strong><?php echo esc_html( $a['kicker'] ); ?></strong> : <?php if ( $type_label !== '' ) echo esc_html( $type_label ) . ', '; ?><?php echo fp_product_link( $a['id'], $a['url'], $a['name'] ); ?><?php
                if ( $a['score'] > 0 ) echo ' (' . number_format( $a['score'], 1, ',', '' ) . '/10)';
                echo '.';
                if ( ! empty( $a['pros'] ) ) echo ' ' . esc_html( implode( ', ', array_map( 'mb_strtolower', $a['pros'] ) ) ) . '.';
                if ( $a['price'] > 0 ) echo ' Prix moyen constaté : ' . fp_format_price( $a['price'] ) . '.';
              ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <?php break;

      /* ─── VS (un bloc par alternative) ─── */
      case 'vs':
        if ( empty( $fp_vs_list ) ) break;
        foreach ( $fp_vs_list as $vs ) :
          $cur_wins_score = ( $score >= $vs['score'] );
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo esc_html( $product_name ); ?> ou <?php echo esc_html( $vs['name'] ); ?> ?</h3>
          <div class="fp-vs">
            <div class="fp-vs-head">
              <div class="fp-vs-prod<?php echo $cur_wins_score ? ' win' : ''; ?>">
                <?php if ( ! empty( $hero_img ) ) : ?><div class="vthumb"><img src="<?php echo esc_url( $hero_img ); ?>" alt="" style="width:100%;height:100%;object-fit:contain"></div><?php else : ?><div class="vthumb"></div><?php endif; ?>
                <span class="fp-vs-tag this">Ce produit</span>
                <h4><?php echo esc_html( $product_name ); ?></h4>
                <div class="vscore"><span class="n"><?php echo number_format( $score, 1, ',', '' ); ?></span><span class="d">/10</span></div>
              </div>
              <div class="fp-vs-badge">VS</div>
              <div class="fp-vs-prod<?php echo ! $cur_wins_score ? ' win' : ''; ?>">
                <?php if ( ! empty( $vs['img'] ) ) : ?><div class="vthumb"><img src="<?php echo esc_url( $vs['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain"></div><?php else : ?><div class="vthumb"></div><?php endif; ?>
                <h4><?php echo esc_html( $vs['name'] ); ?></h4>
                <div class="vscore"><span class="n"><?php echo number_format( $vs['score'], 1, ',', '' ); ?></span><span class="d">/10</span></div>
              </div>
            </div>
            <div class="fp-vs-rows">
              <?php if ( $price_num > 0 && $vs['price'] > 0 ) :
                $pw = ( $price_num <= $vs['price'] );
              ?>
              <div class="fp-vs-row">
                <div class="fp-vs-side left<?php echo $pw ? ' win' : ''; ?>"><span class="val"><?php echo $price_fmt; ?></span></div>
                <div class="lbl">Prix</div>
                <div class="fp-vs-side<?php echo ! $pw ? ' win' : ''; ?>"><span class="val"><?php echo fp_format_price( $vs['price'] ); ?></span></div>
              </div>
              <?php endif; ?>
              <div class="fp-vs-row">
                <div class="fp-vs-side left<?php echo $cur_wins_score ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $score, 1, ',', '' ); ?></span><span class="mbar"><span style="width:<?php echo min( 100, $score * 10 ); ?>%"></span></span></div>
                <div class="lbl">Score global</div>
                <div class="fp-vs-side<?php echo ! $cur_wins_score ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $vs['score'], 1, ',', '' ); ?></span><span class="mbar"><span style="width:<?php echo min( 100, $vs['score'] * 10 ); ?>%"></span></span></div>
              </div>
              <?php if ( is_numeric( $score_avis ) && $score_avis > 0 && is_numeric( $vs['score_avis'] ) && $vs['score_avis'] > 0 ) :
                $aw = ( (float) $score_avis >= (float) $vs['score_avis'] );
              ?>
              <div class="fp-vs-row avis">
                <div class="fp-vs-side left<?php echo $aw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( (float) $score_avis, 1, ',', '' ); ?><small style="color:var(--muted);font-weight:400"> /5</small></span><span class="stars"><?php echo fp_stars( (float) $score_avis ); ?></span><span class="nb"><?php echo esc_html( $nb_avis_fmt ); ?></span></div>
                <div class="lbl">Avis clients</div>
                <?php $vs_nb = function_exists( 'mt5_reviews_label' ) ? mt5_reviews_label( $vs['nb_avis'] ) : ''; ?>
                <div class="fp-vs-side<?php echo ! $aw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( (float) $vs['score_avis'], 1, ',', '' ); ?><small style="color:var(--muted);font-weight:400"> /5</small></span><span class="stars"><?php echo fp_stars( (float) $vs['score_avis'] ); ?></span><span class="nb"><?php echo esc_html( $vs_nb ); ?></span></div>
              </div>
              <?php endif; ?>
              <?php
              $vs_common = array_intersect_key( $fp_cur_criteria, $vs['criteria'] );
              foreach ( $vs_common as $crit_name => $cur_val ) :
                $rival_val = $vs['criteria'][ $crit_name ];
                $cw = ( $cur_val >= $rival_val );
              ?>
              <div class="fp-vs-row">
                <div class="fp-vs-side left<?php echo $cw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $cur_val, 1, ',', '' ); ?></span><span class="mbar"><span class="<?php echo fp_bar_class( $cur_val ); ?>" style="width:<?php echo min( 100, $cur_val * 10 ); ?>%"></span></span></div>
                <div class="lbl"><?php echo esc_html( $crit_name ); ?></div>
                <div class="fp-vs-side<?php echo ! $cw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $rival_val, 1, ',', '' ); ?></span><span class="mbar"><span class="<?php echo fp_bar_class( $rival_val ); ?>" style="width:<?php echo min( 100, $rival_val * 10 ); ?>%"></span></span></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php if ( $summary !== '' || $vs['summary'] !== '' ) : ?>
            <div class="fp-vs-verdict">
              <div class="fp-vs-vcard this">
                <div class="vh"><span class="dot"></span>En bref — <?php echo esc_html( $product_name ); ?></div>
                <?php if ( $summary !== '' ) : ?><p><?php echo wp_kses_post( $summary ); ?></p><?php endif; ?>
              </div>
              <div class="fp-vs-vcard rival">
                <div class="vh"><span class="dot"></span>En bref — <?php echo esc_html( $vs['name'] ); ?></div>
                <?php if ( $vs['summary'] !== '' ) : ?><p><?php echo wp_kses_post( $vs['summary'] ); ?></p><?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; break;

      /* ─── CAROUSEL : similaires ─── */
      case 'carousel_similar':
        if ( empty( $fp_similar ) ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Vous aimerez aussi</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_similar as $s ) :
              $s_link = ( (int) $s['id'] >= $FP_LINK_MIN_ID );
            ?>
            <<?php echo $s_link ? 'a' : 'div'; ?> class="fp-mini-card"<?php if ( $s_link ) echo ' href="' . esc_url( $s['url'] ) . '"'; ?>>
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo"><h4><?php echo esc_html( $s['name'] ); ?></h4><?php if ( $s['price'] > 0 ) : ?><div class="mprice">À partir de <b><?php echo fp_format_price( $s['price'] ); ?></b></div><?php endif; ?></div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </<?php echo $s_link ? 'a' : 'div'; ?>>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

      /* ─── CAROUSEL : même gamme de prix ─── */
      case 'carousel_price':
        if ( empty( $fp_same_price ) ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Dans la même gamme de prix</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_same_price as $s ) :
              $s_link = ( (int) $s['id'] >= $FP_LINK_MIN_ID );
            ?>
            <<?php echo $s_link ? 'a' : 'div'; ?> class="fp-mini-card"<?php if ( $s_link ) echo ' href="' . esc_url( $s['url'] ) . '"'; ?>>
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo">
                <h4><?php echo esc_html( $s['name'] ); ?></h4>
                <?php if ( $s['price'] > 0 ) : ?>
                <div class="mprice"><b><?php echo fp_format_price( $s['price'] ); ?></b><?php
                  if ( isset( $s['delta'] ) && $s['delta'] != 0 ) {
                    $cls = $s['delta'] < 0 ? 'down' : 'up';
                    $sign = $s['delta'] < 0 ? '' : '+';
                    echo ' · <span class="mdelta ' . $cls . '">' . $sign . fp_format_price( abs( $s['delta'] ) ) . '</span>';
                  }
                ?></div>
                <?php endif; ?>
              </div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </<?php echo $s_link ? 'a' : 'div'; ?>>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

      /* ─── CAROUSEL : top de la marque ─── */
      case 'carousel_brand':
        if ( empty( $fp_brand_top ) || count( $fp_brand_top ) < 2 ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Nos <?php echo count( $fp_brand_top ); ?> produits <?php echo esc_html( $brand ); ?> préférés</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_brand_top as $s ) :
              $s_link = ( (int) $s['id'] >= $FP_LINK_MIN_ID );
            ?>
            <<?php echo $s_link ? 'a' : 'div'; ?> class="fp-mini-card<?php echo $s['current'] ? ' current' : ''; ?>"<?php if ( $s_link ) echo ' href="' . esc_url( $s['url'] ) . '"'; ?>>
              <span class="fp-mini-badge <?php echo fp_medal( $s['rank'] ); ?>"><?php echo $s['rank']; ?></span>
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo">
                <h4><?php echo esc_html( $s['name'] ); ?></h4>
                <div class="mprice"><?php echo $s['current'] ? 'Ce produit · ' : 'À partir de '; ?><b><?php echo fp_format_price( $s['price'] ); ?></b></div>
              </div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </<?php echo $s_link ? 'a' : 'div'; ?>>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

    endswitch; endforeach; ?>

    </div><?php /* /fp-main */ ?>

    <?php /* ════════════ SIDEBAR ════════════ */
    if ( $FP_SHOW_SIDEBAR && ! empty( $fp_ranking ) ) : ?>
    <aside class="fp-side">
      <div class="fp-side-block">
        <div class="fp-side-title">Classement<?php echo $type_label !== '' ? ' des ' . esc_html( mb_strtolower( $type_label ) ) : ''; ?></div>
        <div class="fp-side-count"><?php echo $fp_rank_total; ?> produit<?php echo $fp_rank_total > 1 ? 's' : ''; ?> testé<?php echo $fp_rank_total > 1 ? 's' : ''; ?></div>
        <table class="fp-rank-table">
          <thead><tr><th></th><th>Produit</th><th>Score</th></tr></thead>
          <tbody>
            <?php foreach ( $fp_ranking as $r ) : ?>
            <tr class="<?php echo $r['current'] ? 'current' : ''; ?> <?php echo $r['rank'] > $FP_RANK_VISIBLE ? 'extra' : ''; ?>">
              <td class="rk<?php echo $r['rank'] <= 3 ? ' top' : ''; ?>"><?php echo $r['rank']; ?></td>
              <td class="pname"><?php echo fp_product_link( $r['id'], $r['url'], $r['name'] ); ?></td>
              <td class="pscore <?php echo fp_score_class( $r['score'] ); ?>"><?php echo number_format( $r['score'], 1, ',', '' ); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
          <?php if ( count( $fp_ranking ) > $FP_RANK_VISIBLE ) : ?>
          <button type="button" class="fp-rank-collapse" onclick="var p=this.closest('.fp-side-block'),ex=p.querySelectorAll('tr.extra'),on=ex[0]&&ex[0].style.display!=='table-row';ex.forEach(function(r){r.style.display=on?'table-row':'none'});this.querySelector('.show-txt').style.display=on?'none':'inline';this.querySelector('.hide-txt').style.display=on?'inline':'none';var sv=this.querySelector('svg');if(sv)sv.style.transform=on?'rotate(180deg)':''">
            <span class="show-txt">Afficher le classement complet</span>
            <span class="hide-txt">Réduire le classement</span>
            <?php echo $FP_SVG_CHEV; ?>
          </button>
          <?php endif; ?>
          <?php if ( $fp_ref_comp ) : ?>
          <a class="fp-rank-viewall" href="<?php echo esc_url( $fp_ref_comp['url'] ); ?>">Voir le comparatif <?php echo esc_html( $type_label !== '' ? 'des ' . mb_strtolower( $type_label ) : '' ); ?> <?php echo $FP_SVG_ARROW; ?></a>
          <?php endif; ?>
        </div>
      </div>
    </aside>
    <?php endif; ?>

  </div><?php /* /fp-body */ ?>

  <?php /* ════════════ GUIDES ASSOCIÉS ════════════ */
  if ( $FP_SHOW_GUIDES && ! empty( $fp_guides ) ) : ?>
  <section class="fp-fw">
    <div class="fp-fw-head">
      <h2>Nos guides sur le même sujet</h2>
    </div>
    <div class="fp-guide-grid">
      <?php foreach ( $fp_guides as $g ) : ?>
      <a class="fp-gcard" href="<?php echo esc_url( $g['url'] ); ?>">
        <div class="fp-gcard-thumb"><?php if ( ! empty( $g['thumb'] ) ) : ?><img src="<?php echo esc_url( $g['thumb'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?></div>
        <div class="fp-gcard-body">
          <?php if ( $g['cat'] !== '' ) : ?><span class="fp-gcard-cat"><?php echo esc_html( $g['cat'] ); ?></span><?php endif; ?>
          <h3><?php echo esc_html( $g['title'] ); ?></h3>
          <?php if ( ! empty( $g['excerpt'] ) ) : ?><p><?php echo esc_html( $g['excerpt'] ); ?></p><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div><?php /* /fp-avis */ ?>

<script>
(function(){
  document.querySelectorAll('.fp-avis .fp-interblock').forEach(function(b){
    var t=b.querySelector('.fp-carousel-track'),n=b.querySelector('.fp-carousel-nav');
    if(!t||!n)return;
    var bs=n.querySelectorAll('.fp-carousel-btn');
    function s(){var c=t.querySelector('.fp-mini-card');return c?c.offsetWidth+12:200}
    function u(){var m=t.scrollWidth-t.clientWidth-1;bs[0].disabled=t.scrollLeft<=0;bs[1].disabled=t.scrollLeft>=m}
    bs.forEach(function(b){b.addEventListener('click',function(){t.scrollBy({left:+b.dataset.dir*s(),behavior:'smooth'})})});
    t.addEventListener('scroll',u,{passive:true});window.addEventListener('resize',u);u();
  });
})();
</script>
