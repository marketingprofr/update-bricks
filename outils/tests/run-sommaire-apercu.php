<?php
/* Aperçu visuel du sommaire V2 (HTML + CSS du bloc, variables AT simulées).
   Usage : php run-sommaire-apercu.php > out/sommaire.html
           node shot.js out/sommaire.html out/sommaire.png   (capture Playwright, facultatif) */
require __DIR__ . '/wp-stubs.php';
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
ob_start(); include MT_REPO . '/php-css/v2/multi-sommaire.code.php'; $h = ob_get_clean();
$css = str_replace( '%root%', '.brxe-code', file_get_contents( MT_REPO . '/php-css/v2/multi-sommaire.css' ) );
echo '<!doctype html><html><head><meta charset="utf-8"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><style>
:root{--at-black-l-1:#14181d;--at-black-l-2:#2a3038;--at-grey:#666;--at-grey-l-1:#9aa3ad;--at-grey-l-3:#e8eaed;--at-grey-l-4:#f1f3f5;--at-grey-l-5:#f5f6f8;--at-grey-l-6:#fafbfc;--at-white:#fff;--at-primary:#1d6fd8;--at-primary-d-2:#14539f;--at-primary-l-5:#d4e4f8;--at-primary-l-6:#eaf2fc;--at-success:#2e9d57;--at-success-l-3:#dcf2e4;--at-success-l-2:#c3e9d1;--at-success-d-1:#27884a;--at-success-d-3:#1d5f36}
/* styles « thème » parasites volontaires, pour vérifier les remises à zéro */
body{margin:0;padding:30px;background:#fff;font-family:Inter} ul{padding-left:40px;margin:20px 0} ul li{margin-left:20px;padding-left:10px;list-style:disc} h4{margin:30px 0;text-transform:uppercase} .wrap{width:260px}
' . $css . '</style></head><body><div class="wrap brxe-code">' . $h . '</div></body></html>';
