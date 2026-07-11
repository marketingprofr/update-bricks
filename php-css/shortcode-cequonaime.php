<?php
/* =====================================================================
   MEILLEURTEST — Shortcodes éditoriaux « avis » (style top5-tests)
   ---------------------------------------------------------------------
   [cequonaime]     -> <h4 class="cequonaime">Ce qui nous a convaincus</h4>
   [cequonaimepas]  -> <h4 class="cequonaimepas">Les défauts qu'on pardonne (ou pas)</h4>
   [pourqui]        -> encart « À qui s'adresse ce {type} ? » + contenu du
                       champ ACF mltv5_pour_qui (masqué si le champ est vide).

   Mise en forme = celle du template « top5-tests »
   (cf. php-css/top5-tests.css : .ed-a-body h4 / h4.warn / .ed-a-forwho) :
     - intertitres Inter 13px 700 MAJUSCULES, filet 1px à droite ;
       « convaincus » en primary sombre, « défauts » en rouge sombre ;
     - encart « pour qui » fond primary très clair, titre h5 primary.

   ⚠️ CE N'EST PAS un bloc Code Bricks. À coller dans un snippet PHP
   (extension « Code Snippets », ou functions.php du thème enfant).
   Usage dans le contenu WYSIWYG (chaque shortcode sur sa propre ligne) :
       [cequonaime]  ...paragraphes...  [cequonaimepas]  ...  [pourqui]

   100 % tokens Advanced Themer (var(--at-*)) -> bascule mode nuit auto.
   ===================================================================== */

if ( ! function_exists( 'mt_cequonaime_styles' ) ) {
  /* CSS imprimé UNE fois dans <head> (statique, minuscule, pas de FOUC,
     et pas d'enrobage <p> par wpautop comme le ferait un <style> inline). */
  function mt_cequonaime_styles() {
    echo '<style id="mt-cequonaime-css">'
       /* --- intertitres [cequonaime] / [cequonaimepas] --- */
       . 'h4.cequonaime,h4.cequonaimepas{'
       .   'font-family:"Inter",sans-serif!important;'
       .   'font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;'
       .   'margin:38px 0 14px;display:flex;align-items:center;gap:12px;'
       .   'color:var(--at-primary-d-2)'
       . '}'
       . 'h4.cequonaime::after,h4.cequonaimepas::after{'
       .   'content:"";flex:1;height:1px;background:var(--at-grey-l-3)'
       . '}'
       . 'h4.cequonaimepas{color:var(--at-danger-d-2)}'
       /* --- encart [pourqui] --- */
       . '.mt-forwho{font-family:"Inter",sans-serif;margin:36px 0 8px;padding:24px 26px;'
       .   'background:var(--at-primary-l-6);border-radius:14px}'
       . '.mt-forwho h5{font-family:"Inter",sans-serif!important;font-size:12px;font-weight:700;'
       .   'letter-spacing:.08em;text-transform:uppercase;color:var(--at-primary);margin:0 0 10px}'
       . '.mt-forwho p{font-size:15.5px;line-height:1.65;color:var(--at-black-l-2);margin:0}'
       . '.mt-forwho p b{color:var(--at-black-l-1);font-weight:600}'
       . '</style>';
  }
  add_action( 'wp_head', 'mt_cequonaime_styles', 20 );
}

if ( ! function_exists( 'mt_sc_cequonaime' ) ) {
  function mt_sc_cequonaime() {
    return '<h4 class="cequonaime">Ce qui nous a convaincus</h4>';
  }
  add_shortcode( 'cequonaime', 'mt_sc_cequonaime' );
}

if ( ! function_exists( 'mt_sc_cequonaimepas' ) ) {
  function mt_sc_cequonaimepas() {
    return '<h4 class="cequonaimepas">Les d&eacute;fauts qu\'on pardonne (ou pas)</h4>';
  }
  add_shortcode( 'cequonaimepas', 'mt_sc_cequonaimepas' );
}

if ( ! function_exists( 'mt_sc_pourqui' ) ) {
  /* [pourqui] : encart « À qui s'adresse ce {type} ? » construit dynamiquement,
     rendu uniquement si le champ ACF mltv5_pour_qui de la page courante est rempli. */
  function mt_sc_pourqui() {
    $pid = get_the_ID();
    if ( ! $pid && function_exists( 'get_queried_object_id' ) ) { $pid = (int) get_queried_object_id(); }
    if ( ! $pid ) { return ''; }                       // pas de contexte de post -> rien

    $forwho = function_exists( 'get_field' ) ? get_field( 'mltv5_pour_qui', $pid ) : '';
    $forwho = trim( (string) $forwho );
    if ( $forwho === '' ) { return ''; }               // champ vide -> encart masqué

    /* Sous-titre dynamique : « À qui s'adresse ce {type au singulier} ? ». */
    $type_sing = 'produit';
    if ( function_exists( 'get_all_template_variables' ) ) {
      $tv = get_all_template_variables( $pid );
      if ( is_array( $tv ) && ! empty( $tv['type_de_produit_au_singulier'] ) ) {
        $type_sing = trim( (string) $tv['type_de_produit_au_singulier'] );
      }
    }

    return '<div class="mt-forwho">'
         . '<h5>&Agrave; qui s\'adresse ce ' . esc_html( $type_sing ) . '&nbsp;?</h5>'
         . '<p>' . nl2br( esc_html( $forwho ) ) . '</p>'
         . '</div>';
  }
  add_shortcode( 'pourqui', 'mt_sc_pourqui' );
}
