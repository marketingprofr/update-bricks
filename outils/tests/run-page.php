<?php
/* Page multi-comparatif complète (sommaire + résumé + tests + tableau V2) sur le faux site.
   Usage : php run-page.php   (AS_ADMIN=1 php run-page.php pour la vue éditeur)
   Écrit out/page-public.html (ou page-admin.html) et affiche les contrôles. */
require __DIR__ . '/wp-stubs.php';
$B = MT_REPO . '/php-css/v2/';
@mkdir( __DIR__ . '/out' );
ob_start();
foreach ( array( 'multi-sommaire', 'multi-resume', 'multi-tests', 'multi-tableau' ) as $f ) {
  echo "\n<!-- ===== $f ===== -->\n";
  (function( $file ) { include $file; })( $B . $f . '.code.php' );
}
foreach ( $GLOBALS['FOOTER'] as $cb ) { $cb(); }
$html = ob_get_clean();
file_put_contents( __DIR__ . '/out/page-' . ( $GLOBALS['CAN'] ? 'admin' : 'public' ) . '.html', $html );

/* Contrôles */
libxml_use_internal_errors( true );
$d = new DOMDocument(); $d->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
$errs = array_filter( libxml_get_errors(), function( $e ) { return $e->level > LIBXML_ERR_WARNING && strpos( $e->message, 'Tag ' ) === false; } );
echo "HTML errors: " . count( $errs ) . "\n"; foreach ( array_slice( $errs, 0, 5 ) as $e ) echo "  " . trim( $e->message ) . " (line {$e->line})\n";
$x = new DOMXPath( $d );
$ids = array(); foreach ( $x->query( '//*[@id]' ) as $n ) { $ids[] = $n->getAttribute( 'id' ); }
$dups = array_diff_assoc( $ids, array_unique( $ids ) );
echo "ids=" . count( $ids ) . " duplicates=" . implode( ',', $dups ) . "\n";
$missing = array();
foreach ( $x->query( '//a[starts-with(@href,"#")]' ) as $a ) { $h = substr( $a->getAttribute( 'href' ), 1 ); if ( ! in_array( $h, $ids, true ) ) $missing[ $h ] = 1; }
echo "anchors without target: " . implode( ',', array_keys( $missing ) ) . "\n";
echo "H1/H2/H3 counts: " . $x->query('//h1')->length . '/' . $x->query('//h2')->length . '/' . $x->query('//h3')->length . "\n";
foreach ( $x->query('//h2') as $h ) echo "  H2: " . trim( preg_replace('/\s+/', ' ', $h->textContent ) ) . "\n";
echo "test articles: "; foreach ( $x->query('//article[contains(@class,"ed-a-piece")]') as $a ) echo $a->getAttribute('id') . ' '; echo "\n";
echo "TOC: "; foreach ( $x->query('//aside[@data-mt-toc]//a') as $a ) echo $a->getAttribute('href') . '=' . trim($a->textContent) . ($a->getAttribute('data-min')!==''?'['.$a->getAttribute('data-min').']':'') . ' | '; echo "\n";
$rt = $x->query("//*[contains(@class,\"mt-toc-total\")]"); echo "reading total: " . ( $rt->length ? $rt->item(0)->textContent : "(supprimé)" ) . "\n";
echo "table columns: " . $x->query('//tr[2]/td[contains(@class,"product-cell")]')->length . " ; origins: "; foreach ( $x->query('//span[@class="pn-origin"]') as $s ) echo trim($s->textContent) . ' | '; echo "\n";
echo "table spec rows: "; foreach ( $x->query('//tr[contains(@class,"spec-row")]/td[1]') as $s ) echo $s->textContent . ' | '; echo "\n";
echo "banners: "; foreach ( $x->query('//span[contains(@class,"col-banner")]') as $s ) echo trim($s->textContent) . ' | '; echo "\n";
echo "eyebrows: \n"; foreach ( $x->query('//div[@class="ed-a-eyebrow"]') as $s ) echo "  " . trim( preg_replace('/\s+/', ' ', $s->textContent ) ) . "\n";
echo "angle 6 used: " . ( strpos( $html, 'ANGLE-9000-DU-6' ) !== false ? 'yes' : 'no' ) . "\n";
echo "legacy anchors: "; foreach ( $x->query('//span[@class="mtv2-legacy-anchor"]') as $s ) echo $s->getAttribute('id') . ' '; echo "\n";
foreach ( $x->query('//script[@type="application/ld+json"]') as $s ) {
  $j = json_decode( $s->textContent, true );
  echo "JSON-LD valid=" . ( $j ? 'yes' : 'NO' ) . " nodes=" . count( $j['@graph'] ?? array() ) . "\n";
  $prod = array_filter( $j['@graph'], function( $n ) { return $n['@type'] === 'Product'; } );
  $pids = array_column( $prod, '@id' );
  echo "  products=" . count( $prod ) . " unique=" . count( array_unique( $pids ) ) . "\n";
  foreach ( $j['@graph'] as $n ) if ( $n['@type'] === 'ItemList' ) {
    $refs = array(); foreach ( $n['itemListElement'] as $li ) { $r = $li['item']['@id'] ?? ( 'URL:' . $li['url'] ); $refs[] = $li['position'] . ':' . basename( parse_url( $r, PHP_URL_PATH ) ); if ( isset( $li['item'] ) && ! in_array( $li['item']['@id'], $pids, true ) ) echo "  !! dangling ref\n"; }
    echo "  ItemList {$n['@id']} '{$n['name']}' -> " . implode( ' ', $refs ) . "\n";
  }
  $p1 = array_values( $prod )[0]; echo "  sample offers type: " . ( $p1['offers']['@type'] ?? 'none' ) . " brand: " . ( $p1['brand']['name'] ?? '-' ) . "\n";
}
