# Mission : supprimer le contenu adulte et pirate de meilleurtest.fr

Cette tâche t'est confiée parce que tu tournes sur le poste de l'utilisateur : ta connexion sort avec une IP française. La session cloud qui a préparé la liste était bloquée par la règle Cloudflare « pays francophones uniquement ».

## Contexte

- **Le site.** meilleurtest.fr publie des comparatifs de produits. Il tourne sous WordPress avec Bricks Builder, Rank Math SEO et WPCodeBox. Il est hébergé chez Cloudways (cache Varnish/Breeze), derrière Cloudflare.
- **Pourquoi supprimer.** Le trafic Google s'est effondré. Première étape du redressement, validée par l'éditeur : retirer tout le contenu adulte et pirate. Ce contenu dégrade la confiance de Google dans l'ensemble du domaine (cohérence thématique, plaintes DMCA).
- **Hors périmètre.** Tout le reste du plan : fusion des variantes, réécriture, indexation. N'y touche pas.
- **Formats d'URL.**
  - Comparatifs : `/comparatif-{slug}/`. Les anciennes URL `/comparatif/{slug}/` redirigent en 301 vers ce format.
  - Fiches produits : `/fiche-{slug}/`.
  - Articles : à la racine.
- **Snippet 410 existant.** WPCodeBox contient déjà un snippet « 410 Gone + 301 Redirects » (priorité 0). Complète-le plutôt que de créer une règle concurrente.

## Règles

- **Communication.**
  - Parle en français.
  - L'utilisateur ne veut pas ouvrir de fichiers CSV. Présente-lui les listes directement dans la conversation, sous forme de listes à puces classées.
- **Validation.** Aucune suppression sans son « OK » explicite sur la liste finale.
- **Pas de suppression définitive.** Mets à la corbeille uniquement (REST `DELETE` sans `force=true`). Ne vide la corbeille que s'il le demande.
- **Code 410.** Les URL supprimées doivent répondre en 410. Jamais de 301 vers une autre page.
- **Pages à garder.** Ne touche pas aux pages de la section « À garder ».
- **Fiches produits partagées.** Ne supprime une fiche que si tous les comparatifs qui la citent sont supprimés. Sinon, signale-la.
- **Accès au site.**
  - Utilise les identifiants REST déjà configurés sur ce poste (mot de passe d'application), et ne les affiche jamais.
  - S'il n'y en a pas, demande à l'utilisateur d'en créer un : Utilisateurs → Profil → Mots de passe d'application.
  - Si tu as un accès SSH/WP-CLI au serveur Cloudways, tu peux l'utiliser.
  - Reste sous ~5 requêtes par seconde, pour ne pas déclencher Cloudflare.

## Étape 1 : inventaire (lecture seule)

1. Appelle `GET /wp-json/wp/v2/types` pour identifier les types de contenu (comparatifs, fiches, articles, pages, autres CPT) et leur `rest_base`.
2. Récupère tout le contenu, tous statuts confondus (publish, draft, pending, private, future). Pour chaque contenu, note : ID, type, statut, slug, titre, lien, catégories, étiquettes et autres taxonomies.
3. Pour chaque URL de la liste validée plus bas :
   - confirme qu'elle existe ;
   - note son ID et son statut ;
   - signale celles qui n'existent plus.
4. Complète la liste. Les archives web utilisées pour la construire sont incomplètes.
   - **Fiches liées aux comparatifs supprimés.** Trouve comment les fiches produits sont reliées aux comparatifs (champ de relation ACF, taxonomie, méta…). Liste toutes les fiches liées aux comparatifs supprimés. Il y en a probablement plusieurs centaines, alors que la liste n'en contient que 47.
   - **Recherche par mots-clés.** Cherche dans les titres, les slugs et les termes de taxonomie les mots-clés de la section « Mots-clés ». Relis chaque résultat à la main : la section liste aussi les pièges connus.
   - **Fiches de sites web.** Passe en revue les fiches de sites web (catégorie ou type dédié). Il existe des fiches de sites pirates que les archives n'ont pas trouvées, par exemple `fiche-coflix` et `fiche-movbor-com`.
   - **Catégories qui deviendront vides.** Repère les catégories et étiquettes qui n'auront plus aucun contenu après la suppression. Leurs pages d'archive devront aussi disparaître (410).
   - **Images.** Liste les images rattachées uniquement aux contenus supprimés. Ne les supprime pas : propose-le à l'étape 2.
5. Repère les liens internes vers les pages à supprimer :
   - dans le contenu des articles, y compris le contenu Bricks stocké en méta s'il est accessible ;
   - dans les menus (`/wp-json/wp/v2/menu-items`) ;
   - dans les modèles Bricks et les blocs de guides liés.

## Étape 2 : validation

Présente à l'utilisateur, dans la conversation :

- **La liste finale à supprimer.** Classe-la par Piratage / Adulte, puis par comparatifs / fiches / articles / catégories. Signale ce que tu as ajouté par rapport à la liste d'origine, et ce qui n'existe plus.
- **Les cas « à décider ».** Donne ta recommandation pour chacun.
- **Les liens internes.** Le nombre de liens à retirer, et les pages concernées.
- **Les images.** Celles qu'on pourrait supprimer ensuite.

Attends son OK avant d'aller plus loin.

## Étape 3 : exécution (après OK)

1. **Sauvegarde.** Fais un `wp db export` si tu as WP-CLI. Sinon, demande à l'utilisateur de confirmer qu'une sauvegarde Cloudways récente existe. Exporte aussi chaque contenu en JSON (`context=edit`) avant de le mettre à la corbeille.
2. **Corbeille.** Mets les contenus à la corbeille par lots, et journalise chaque ID.
3. **Codes 410.** Prépare le code à ajouter au snippet 410 existant : liste des chemins, y compris les archives de catégories vidées. Donne-le à l'utilisateur à coller dans WPCodeBox, sauf si tu peux modifier le snippet toi-même proprement.
4. **Liens internes.** Retire-les en gardant le texte et en enlevant le lien. Retire aussi les entrées de menu.
5. **Caches.** Fais purger les caches : Breeze/Varnish sur Cloudways, puis Cloudflare. Fais-le toi-même si tu as l'accès, sinon demande-le à l'utilisateur.

## Étape 4 : vérification et compte rendu

- Chaque URL supprimée doit répondre 410 (ni 200, ni 301, ni 404). Teste aussi l'ancienne forme `/comparatif/{slug}/`.
- Le sitemap Rank Math ne doit plus les lister.
- Aucune page conservée ne doit encore contenir de lien vers elles.
- Fais un compte rendu court : nombre de contenus supprimés par catégorie, anomalies rencontrées, actions restantes.

## Liste validée par l'utilisateur (146 URL)

Chaque ligne donne des slugs, à préfixer par `https://meilleurtest.fr/` puis `comparatif-` ou `fiche-` selon la section.

Les éléments marqués * sont à vérifier d'un coup d'œil sur le contenu. Si le contenu contredit la classification (par exemple un gel d'hygiène intime et non un lubrifiant), garde la page et signale-le.

### Piratage : comparatifs (14)

Vus dans les archives, sauf ceux marqués *, qui sont dans la liste des guides en ligne.

- **IPTV :** abonnement-iptv, application-iptv, boitier-iptv-4k, box-android-iptv
- **Foot en streaming gratuit :** site-de-foot-streaming-gratuit, site-de-foot-streaming-gratuit-2, site-de-foot-streaming-gratuit-3, foot-streaming-4-sites-pour-voir-matchs-direct-gratuitement
- **Films et animés en streaming gratuit :** site-de-streaming-gratuit-pour-regarder-des-films, site-de-streaming-pour-regarder-des-animes
- **Téléchargement :** convertisseur-youtube-mp3, site-de-torrent, logiciel-de-torrent*, site-de-telechargement-de-musique-gratuite*

### Piratage : fiches (13)

- coflix, movbor-com, the-pirate-bay, 1337x, torrent9, y2mate-com, ytmp3, flvto, tubidy, musicdownload-zone, abonnement-iptv-apple-tv, bittorrent*, qbittorrent*

### Piratage : article (1)

- `/sites-de-streaming-gratuits-pour-regarder-des-series/`

### Adulte : comparatifs (71)

Tous dans la liste des guides en ligne, sauf « preservatif ».

- **Godes :** gode, gode-anal, gode-pour-homme, gode-vibrant, gode-realiste, gode-ejaculateur, gode-ventouse, gode-xxl, gode-double, gode-telecommande, gode-gonflable, gode-en-verre, gode-noir, gode-ceinture, gode-ceinture-creux, gode-ceinture-vibrant, gode-ceinture-ejaculateur, gode-ceinture-sans-harnais, gode-ceinture-double-penetration
- **Vibromasseurs et stimulateurs :** vibromasseur, vibromasseur-rabbit, vibromasseur-anal, vibromasseur-realiste, vibromasseur-double, mini-vibromasseur, stimulateur-clitoridien, oeuf-vibrant, anneau-vibrant, boules-de-geisha
- **Masturbateurs et poupées :** masturbateur, masturbateur-automatique, masturbateur-realiste, masturbateur-anal, fleshlight, fleshlight-transparent, vaginette, fuck-machine, poupee-sexuelle
- **BDSM :** fouet-bdsm, corde-bdsm, baillon-bdsm, lingerie-bdsm, coffret-bdsm, collier-bdsm, jouet-bdsm, menottes-bdsm, cagoule-bdsm, harnais-bdsm, cage-de-chastete
- **Anal :** plug-anal, plug-anal-pour-homme, bijou-anal, chaine-anale, lubrifiant-anal
- **Sextoys, sex-shops, libertin :** sex-toy, sextoy-pour-homme, site-pour-acheter-des-sextoys-en-ligne, sex-shop-ligne, site-libertin, jeu-erotique
- **Lubrifiants et gels :** lubrifiant-sexuel, lubrifiant-effet-porn-sperm, lubrifiant-a-base-deau*, gel-intime*, gel-intime-pour-femme*
- **Compléments sexuels :** complement-alimentaire-pour-ameliorer-erection, booster-de-libido-pour-femme
- **Préservatifs :** preservatif, preservatif-sans-latex, preservatif-feminin, preservatif-durex

### Adulte : fiches produits (47)

Vues dans les archives. Cette partie est très incomplète : voir l'étape 1.

- **Masturbateurs :** fleshlight-ice-turbo-thrust, fleshlight-quickshot-vantage, fleshlight-stu, sextoy-pour-homme-fleshlight-pink-lady-stamina-training-unit, sextoy-pour-homme-fleshlight-quickshot-riley-reid, masturbateur-satisfyer-men-vibration, teasers-masturbateur-automatique
- **Vibromasseurs et stimulateurs :** womanizer-pro40, satisfyer-pro-2, we-vibe-nova-2, lovense-edge-2, lovehoney-perfect-partner-20-cm
- **Godes, plugs, anneaux :** doc-johnson-godemichet-classique, doc-johnson-vac-u-lock-dual-density-ultraskyn-set-vanilla, gode-ceinture-creux-7-fetish-fantasy-series, you2toys-strap-on-duo-2, seven-creations-everlasting-duet, seven-creations-plug-anal-vibrant-gonflant, you2toys-plug-anal-doigt-transparent, booty-sparks-plug-light-up-small, perle-anale-graduee-en-silicone-greygasms, cockring-enduro-plus, pompe-a-penis-bathmate-hydro7
- **BDSM :** corde-de-bondage-rouge-easytoys-fetish-collection, mini-corde-de-bondage-japonaise-ouch, ouch-japanese-rope, ouch-cagoule-extreme, strict-cagoule-de-bondage-avec-queue-de-cheval, strict-leather-cagoule-en-cuir, fetish-fantasy-series-spandex-3-hole-hood, pipedream-fetish-fantasy-series, pipedream-delight-fetish-fantasy-series
- **Lubrifiants :** pjur-back-door, biolove-anal-relax, intimy-classic-gel-intime-lubrifiant, durex-hot-gel-lubrifiant, durex-play-crazy-cherry, yesforlov-massage-integral-et-lubrifiant*
- **Préservatifs :** durex-real-feel, manix-sans-latex, skyn-original, preservatif-extra-fin-sans-latex-ceylor, preservatif-sensation-naturel-skyn, preservatif-stimulateur-et-retardateur-durex-surprise-me-deluxe, preservatifs-hex-original-boite-de-12, preservatifs-skins-parfumes
- **Boutique :** adameteve-fr

## À décider avec l'utilisateur

- **Lingerie et mode.** Recommandation : garder, c'est de la mode et Google ne la classe pas en contenu adulte. Fais confirmer par l'utilisateur à l'étape 2.
  - Comparatifs : string, maillot-de-bain-string, maillot-de-bain-sexy, nuisette-sexy, body-sexy, cache-teton
  - Fiches : iclosam-soutien-gorge-dos-nu-invisible-sexy, lot-de-strings-ficelles-sexys-pour-homme-summer-code, so-sexy-terpan
- **comparatif-huile-de-massage.** Lis la page. Garder si c'est du bien-être, supprimer si c'est érotique.
- **comparatif-dns-gatuit-et-rapide.** Lis la page. Garder si c'est un guide technique, supprimer si elle explique comment contourner le blocage des sites pirates.
- **comparatif-plateforme-de-streaming** et **comparatif-service-streaming-de-musique.** Ce sont probablement les anciennes URL des versions « légales » : vérifie qu'elles redirigent en 301 vers comparatif-plateformes-de-streaming-legales et comparatif-service-streaming-de-musique-legal.

## À garder : faux positifs vérifiés, ne pas supprimer

- **Streaming légal :** comparatif-site-de-streaming-animes-legal, comparatif-plateformes-de-streaming-legales, comparatif-service-streaming-de-musique-legal, comparatif-offre-de-streaming-tv, comparatif-appareil-de-streaming, comparatif-micro-de-streaming, comparatif-webcam-pour-streaming
- **Logiciels et services gratuits :** comparatif-vpn-gratuit, comparatif-vpn-gratuit-android, comparatif-antivirus-gratuit, comparatif-banque-en-ligne-gratuite
- **Fitness :** comparatif-plateforme-vibrante, comparatif-plateforme-vibrante-oscillante
- **Rencontre classique :** comparatif-site-de-rencontre, comparatif-appli-de-rencontre

## Mots-clés pour compléter la liste (étape 1)

- **Adulte, termes :** sexe, sexuel, sextoy, gode, godemiché, vibromasseur, plug anal, anal, masturbateur, vaginette, poupée sexuelle, érotique, porn, libertin, échangiste, coquin, BDSM, bondage, fétiche, chasteté, cockring, pénis, lubrifiant intime / anal / sexuel, gel intime, préservatif, érection, libido, aphrodisiaque, retardant, boules de geisha, stimulateur clitoridien, strap-on.
- **Adulte, marques :** Womanizer, Satisfyer, Lelo, We-Vibe, Lovense, Lovehoney, Fleshlight, Tenga, Doc Johnson, Pipedream, Fetish Fantasy, You2Toys, Seven Creations, Ouch!, Strict, EasyToys, Pjur, Durex, Manix, Skyn, Bathmate, Marc Dorcel, Adam et Ève.
- **Piratage, termes :** streaming gratuit, site de streaming, foot / match en streaming, IPTV, torrent, téléchargement gratuit, DDL, convertisseur YouTube, YouTube MP3, crack, keygen, warez, Kodi, débrideur, hébergeur de fichiers.
- **Piratage, noms de sites :** Coflix, Movbor, Wiflix, French Stream, Papadustream, Wawacity, Zone Téléchargement, YggTorrent, Cpasbien, Torrent9, 1337x, The Pirate Bay, Y2mate, YTmp3, Flvto, Tubidy, Anime-Sama, Voiranime, Japscan, Uptobox, 1fichier.
- **Pièges connus (faux positifs) :**
  - « plug-and-play » (panneaux solaires), bouchons d'oreilles « plug » ;
  - « aspirateur » (contient « pirat »), « specialist » (contient « cialis »), « paddle » (contient « ddl ») ;
  - fouet de cuisine, plateformes et ceintures vibrantes de fitness ;
  - streaming légal (Netflix, Deezer, Fire TV Stick, matériel de streamer) ;
  - VPN, antivirus et banques « gratuits » ;
  - casque de vélo Bontrager « XXX », lubrifiant de chaîne de vélo, matelas latex, platines vinyle, canapés convertibles, trottinettes et lits « adulte ».

## Source

Le fichier complet (IDs, titres, sources) est `suppression_adulte_piratage.csv`, dans le dépôt GitHub `marketingprofr/update-bricks`, branche `claude/verify-seo-skill-install-u596xu`.

Il a été construit à partir de trois sources :
- `guidestoindex.csv` : 5 745 guides en ligne. Les 72 guides à supprimer y sont déjà passés en `ToSubmit = No`.
- Les archives de la Wayback Machine.
- Les données Search Console d'octobre 2025.
