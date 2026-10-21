# Passation de l'instance « SEO - Templates (meilleurtest) » — 21 octobre 2026

Samuel arrête l'instance précédente. Ce document permet à une nouvelle instance de reprendre sans rien supposer.
Lis-le en entier, puis lis `CLAUDE.md` (racine du dépôt) et `php-css/multi-v3/LISEZ-MOI.md`.

## 1. Pourquoi l'instance précédente a été arrêtée (à ne pas reproduire)

Le 21 octobre, Samuel a constaté 30 à 40 % de clics sortants en moins (vers Amazon et les boutiques) depuis que la
phrase « Sur les 55 climatiseurs mobiles que nous avons analysés, X est le meilleur… » a remplacé l'ancienne liste
« Notre sélection ». Il a demandé de s'inspirer de Frandroid. L'instance précédente a alors :
- livré une liste « Notre sélection » **sans lien vers les offres** (commit a5fad5e) : à NE PAS coller tel quel ;
- construit plusieurs maquettes de mémoire, **sans regarder d'abord le modèle demandé** ;
- inventé une mise en forme (grosses puces rondes avec flèche, point médian avant le prix, nom non gras…) ;
- regardé les mauvais blocs de Frandroid (les cartes « Le top 3 » et les encarts produit) au lieu de la liste
  « Notre sélection » que Samuel désignait, puis lui a affirmé à tort que cette liste n'existait pas.

Règles qui en découlent :
1. **Quand Samuel donne un modèle, l'ouvrir dans un vrai navigateur (JavaScript actif, mobile et ordinateur), en
   faire une capture du bloc exact qu'il désigne, et le copier à l'identique.** Ne jamais décrire ou supposer un
   modèle sans capture. Si le bloc est introuvable, demander à Samuel le lien exact ou une capture, sans affirmer
   qu'il n'existe pas.
2. **Le haut des comparatifs se juge aux clics sortants.** Ne jamais retirer ni repousser un lien d'offre. Avant toute
   proposition, mesurer à 390 px la position du premier lien d'offre et du premier bouton d'offre (px et écrans),
   avant et après.
3. Faire ce qui est demandé, exactement, sans ajout de son cru. Phrases courtes, pas de jargon.

## 2. La demande en cours, prioritaire : « Notre sélection » en haut des comparatifs

LE MODÈLE (montré par Samuel en capture le 21 octobre, relevé dans le navigateur) : Frandroid,
https://www.frandroid.com/guide-dachat/smartphones/367607_meilleurs-smartphones-photos , bloc H2 « Notre sélection des
meilleurs téléphones pour la photo », placé APRÈS l'intro et les cartes « Le top 3 ». Captures :
`handoff/frandroid-notre-selection-ordi.png` et `handoff/frandroid-notre-selection-mobile.png`.
Structure exacte : `<h2>Notre sélection des meilleurs …</h2><ul class="wp-block-list"><li><a href="{offre}" target="_blank"
rel="noreferrer noopener affiliate nofollow"><strong>Google Pixel 11 Pro</strong> à 1199 €</a></li>…</ul>` (8 produits).
Styles relevés : H2 26 px (22 px sur mobile), gras, bleu ; lignes 18 px ; lien bleu, graisse 500, non souligné ; nom
en <strong> (700) ; « à 1199 € » dans le même lien, graisse normale ; petite icône sac/panier claire APRÈS le prix ;
petites puces rondes. Toute la ligne mène à l'offre.

Spécification de Samuel (21 octobre), conforme à ce modèle :
- titre « Notre sélection » **plus grand** que le texte de la liste ;
- **petites** puces ;
- une **icône panier** devant chaque produit, pour montrer que le lien mène à l'achat ;
- **le lien va jusqu'au prix** (toute la ligne mène à l'offre) ;
- « **à** » avant le prix (pas de point médian) ;
- **produit en gras, prix en texte normal** ;
- 5 produits ; d'après Samuel, un clic sur la puce menait à l'avis du produit plus bas.

Où coder : `php-css/v2/multi-hero-gauche.code.php`, fonction `mt_quick_picks()` et réglages en tête
(`$MT_SHOW_QUICK_PICKS`, `$MT_REPONSE_INTRO`, `$MT_SHOW_INTRO_RECO`, `$MT_VOS_QUESTIONS`, `$MT_PHOTO_HERO`), CSS dans
`php-css/v2/multi-hero-gauche.css` (classes `.mt-picks*`). Lien d'offre : même lien que le bouton du top 5
(`primary_url` dans `multi-resume.code.php` ; Amazon `?tag=mlt00-21` ou lien marchand ; redir.js le réécrit). Prix :
`mltv5_prix_indicatif` (et l'unité `mt_prix_unite`).
État du dépôt : a5fad5e a mis `$MT_SHOW_QUICK_PICKS = true`, `$MT_REPONSE_INTRO = false`, `$MT_SHOW_INTRO_RECO = false`,
`$MT_VOS_QUESTIONS = ''`, `$MT_PHOTO_HERO = false`, et `mt_quick_picks()` sort une liste SANS lien d'offre. À corriger
avant toute livraison. En ligne aujourd'hui (non collé) : la phrase-réponse, dont les noms de produits portent des
liens d'offre, et l'encart de questions « L'essentiel en 30 secondes » (classe `.mt-faq-mini`).

Ancien site, vérifié sur l'Internet Archive (23 avril et 2 octobre 2025) : « 1. Meilleur climatiseur mobile en 2025 : »
en gras, puis le NOM EN LIEN VERS L'OFFRE (target _blank, nofollow), puis à la ligne « ➔ Voir notre avis » gris vers
`#verdict-produit-N`. Pas de prix, puces masquées.

Autres demandes de Samuel du 21 octobre (via la Coordination) :
- retirer l'encart de questions du haut de page (n'aide pas les lecteurs) ;
- sur mobile, le top 5 et ses boutons juste après la liste et une intro courte ; encart confiance, sa photo et
  l'encadré ADEME (`.mt-vigilance`) sous le top 5 ou repliés ;
- multi-comparatif sur mobile : un sommaire (repliable ou collant), et ne plus répéter des dizaines de cartes
  (n°1 de chaque section en carte, les autres en lignes avec lien d'offre, produit déjà présenté = une ligne).
- Maquette existante (rejetée sur la liste, le reste n'a pas été jugé) : `wp-avis/maquette-mobile-multi.html`. Scripts
  pour la refaire : `outils/maquette-mobile/` (injection sur la page en ligne dans Playwright, rien n'est écrit sur le
  site ; adapter les chemins du dossier temporaire en tête des fichiers). Mesures v4 à 390 px sur climatiseur mobile :
  premier bouton du top 5 3 155 px → 1 125 px ; premier avis détaillé 14 041 → 8 754 px.
- Toujours faire une MAQUETTE HORS LIGNE validée par Samuel avant de coder.

## 3. Rôle et règles permanentes

- Instance « SEO - Templates (meilleurtest) » : modèles Bricks de meilleurtest.fr (éléments Code PHP et CSS).
  Dépôt `C:\Webdev\update-bricks-templates`, branche `claude/bricks-templates-html-update-i56041`, poussée sur
  GitHub `marketingprofr/update-bricks`. Ne pas toucher la branche de `C:\Webdev\update-bricks`.
- **Ne rien modifier sur le site** sans l'accord explicite de Samuel (ni post, ni champ, ni réglage, ni REST en
  écriture). Lecture REST possible ; avant d'écrire, demander. Les accès sont dans `C:\Webdev\wp-avis\config.json` :
  ne jamais les afficher ni les committer. Les écritures de données passent par l'instance Architecture, avec
  l'accord de Samuel dans SA session.
- Samuel colle les fichiers de `php-css/multi-v3/` dans le modèle V3 (commun à tous les comparatifs depuis la bascule
  du 14 octobre ; anciens modèles passés en brouillon). Éléments : 1 hero gauche (CSS dans le bandeau, fichier 1),
  2 encadré confiance (CSS dans le bandeau, fichier 2), 3 sommaire, 4 résumé top 5, 5 avis détaillés, 6 FAQ,
  7 tableau, 8 duels, 9 colonne gauche, 10 colonne droite.
- Après chaque livraison : `python outils/verifier-multi-v3.py --recopier` puis sans option (19 « identique »),
  `python outils/check_css_strip.py` (« TOUT IDENTIQUE »), notes dans `CLAUDE.md` et `LISEZ-MOI.md`, commit en
  français terminé par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, push. Donner à Samuel les liens
  GitHub `php-css/multi-v3/…` et l'endroit exact où coller, puis : signer dans Bricks, vider FlyingPress puis
  Cloudflare (jamais Breeze ni Varnish).
- Tests : banc PHP `outils/tests/` (PHP 8.3 :
  `C:/Users/Samuel/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe`),
  et Playwright sur la page en ligne avec CSS injecté dans le navigateur de test seulement.
- Style avec Samuel : français, phrases courtes, puces, pas de deux-points dans les phrases, pas de codes internes,
  juger en visiteur, ne jamais inventer de données, livrer testé avec ce qui a été vérifié.
- Mémoire : `C:\Users\Samuel\.claude\projects\C--Webdev-wp-avis\memory\` (lire `MEMORY.md`, en particulier
  `liens-offre-haut-de-page.md`, `feedback-relecture-v3-samuel.md`, `samuel-solutions-simples.md`,
  `bascule-v3-bricks-modeles.md`, `templates-lire-tableau-coordination.md`).
- Instances pairs (SendMessage) : « SEO - Coordination audits Jev (meilleurtest) » (tableau
  `wp-avis/coordination-audits-jev.md`, chercher « À FAIRE PAR TEMPLATES »), « SEO - Architecture des comparatifs
  (meilleurtest) » (données, écritures REST), « SEO - Technique (meilleurtest) » (performance), « SEO - FAQ
  optimization ».

## 4. Livraisons du 12 au 21 octobre (commits), état de collage à vérifier en ligne

Samuel n'a pas confirmé chaque collage : vérifier sur la page en ligne (rendu frais `?nc=…`) avant de supposer.
- 0cd1376 encadré : « Nous avons noté » en premier ; colonne de gauche « {Type} analysés » (éléments 2 et 9).
- 7d4666c encadré : liens des sources soulignés une fois (2, CSS).
- 3c29ed0 + a0d0fea FAQ : « Le meilleur … de notre comparatif est X, noté N/10 », sans guillemets à « meilleur » (6).
- 4708228 catégories à 60 par page : `category.code.php` ($CAT_PER_PAGE) ET `fix-category-pagination.php`
  (WPCodeBox, posts_per_page 60). Contrôle : /maison/page/30/ en 200, /maison/page/31/ en 404.
- 3b6dc41 + d9276c0 encadré : citation retirée (elle est sur /notre-engagement/), votes resserrés, interligne
  1,5 (2, Code + CSS). Vu en ligne le 14 octobre : citation absente, votes resserrés.
- 4a12279 + 66fd45b + 38afa0a + 770bc69 top 5 : lien « Lire l'avis complet » à droite du bouton (carte F), carte
  téléphone seulement pour une liste < 460 px, tri « Avis », sans « en 12 ans », « produits analysés » (4, Code + CSS).
- 9e9e908 notes clients > 5 non affichées (4, 5, 6, 7 Code) ; la donnée du Miele 262477 est corrigée par
  l'Architecture (4,75, choix de Samuel).
- a5fad5e haut de page : NE PAS COLLER tel quel (voir section 2).

Décisions à respecter : ItemList du JSON-LD gardés tels quels (ni retrait ni alignement V1/V3) ; titres forcés vidés
sur les 10 comparatifs du 2 octobre ; résumé du produit toujours à la place des points forts et faibles dans les
cartes ; carte F choisie par Samuel pour les listes de 460 à 820 px.

## 5. Premier message conseillé à Samuel

Court : dire ce que tu as compris de la demande « Notre sélection », montrer la capture du bloc Frandroid que tu vas
copier (ou demander son lien exact si tu ne le trouves pas), puis proposer la maquette.
