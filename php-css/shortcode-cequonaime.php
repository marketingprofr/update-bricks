<?php
/* =====================================================================
   MEILLEURTEST — Shortcodes [cequonaime] / [cequonaimepas]
   ---------------------------------------------------------------------
   [cequonaime]     -> <h4 class="cequonaime">Ce qui nous a convaincus</h4>
   [cequonaimepas]  -> <h4 class="cequonaimepas">Les défauts qu'on pardonne (ou pas)</h4>

   Même mise en forme que les intertitres du template « top5-tests »
   (cf. php-css/top5-tests.css, .ed-a-body h4 / h4.warn) : Inter 13px 700,
   MAJUSCULES, filet 1px à droite, accent bleu (primary) / rouge (danger).

   ⚠️ CE N'EST PAS un bloc Code Bricks. À coller dans un snippet PHP
   (extension « Code Snippets », ou functions.php du thème enfant).
   Les shortcodes s'utilisent ensuite dans le contenu WYSIWYG :
       [cequonaime]  ... paragraphes ...  [cequonaimepas]  ...

   100 % tokens Advanced Themer (var(--at-*)) -> bascule mode nuit auto.
   ===================================================================== */

if ( ! function_exists( 'mt_cequonaime_styles' ) ) {
  /* CSS imprimé UNE fois dans <head> (statique, minuscule, pas de FOUC,
     et pas d'enrobage <p> par wpautop comme le ferait un <style> inline). */
  function mt_cequonaime_styles() {
    echo '<style id="mt-cequonaime-css">'
       . 'h4.cequonaime,h4.cequonaimepas{'
       .   'font-family:"Inter",sans-serif!important;'
       .   'font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;'
       .   'margin:38px 0 14px;display:flex;align-items:center;gap:12px;'
       .   'color:var(--at-primary)'
       . '}'
       . 'h4.cequonaime::after,h4.cequonaimepas::after{'
       .   'content:"";flex:1;height:1px;background:var(--at-grey-l-3)'
       . '}'
       . 'h4.cequonaimepas{color:var(--at-danger-d-1)}'
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
