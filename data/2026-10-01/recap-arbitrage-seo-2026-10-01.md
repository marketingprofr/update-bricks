# Arbitrage SEO des 645 sous-comparatifs — récapitulatif (1er octobre 2026)

Fichier de décisions : `sections-a-discuter-2026-10-01-decisions.csv` (même format que le fichier d'origine, colonnes `DÉCISION SEO (garder / privé)` et `MOTIF SEO` remplies).

## Résultat

| Décision | Pages | Action attendue de l'instance de structure |
|---|---|---|
| garder | 234 | Rien : la page reste publiée. |
| privé | 351 | 301 vers le multi parent, puis statut privé (comme les 196 pages de la première vague). |
| à signaler | 49 | Ne rien faire : décision de Samuel attendue (doublons et pages qui visent un autre produit, listés plus bas). |
| supprimer | 11 | Ne rien faire : pages adultes déjà dans le plan de suppression (410), gérées par une autre instance. |

## Méthode

- **Critère Google, relu à la main.** Les 5 premiers résultats de chaque page ont été ré-étiquetés (112 verdicts différaient de l'étiquetage automatique). Les sites comptés comme guides ont été vérifiés sur leur page d'accueil : 31 verdicts ont changé parce que le « guide » était une boutique ou un prestataire.
- **Règle Google :** garder si au moins 2 guides spécialisés indépendants dans le top 5, ou un guide spécialisé en position 1 ou 2 ; sinon privé.
- **Trafic (Search Console, mars à octobre 2025, avant la chute) :** une page est gardée quel que soit le verdict Google si elle avait au moins 20 clics, ou au moins 5 clics sur des requêtes contenant son attribut, ou une position dans le top 10-20 sur ces requêtes. Les impressions en position 50+ ne comptent pas (gonflées en 2025 par les outils de suivi).
- **Séries :** 15 pages alignées sur leur série (même multi, même axe d'attribut : RAM DDR3/4/5, croquettes par race, matelas par taille, réfrigérateurs par format…). Les caractéristiques indépendantes (ex. barre de son « bluetooth » et « sans caisson ») ne sont pas traitées comme une série.

## Limites

- **Trafic :** historique mars-octobre 2025, pas les 3 à 16 derniers mois (export Search Console récent indisponible). Depuis la chute, presque toutes ces pages sont à zéro impression : l'historique est le signal le plus discriminant.
- **Séries :** alignées à l'intérieur des 645 pages seulement. La cohérence avec les décisions déjà prises pour les pages sœurs (`comparatifs-structure-2026-09-30-site.csv`, colonne `Décision finale`) reste à contrôler : voir le prompt.
- **Baumes du tigre rouge et blanc (41567, 41568) :** gardés grâce à un seul guide, sur firn.fr, dont la nature n'a pas pu être vérifiée (site protégé contre les robots). Si c'est une pharmacie en ligne, les deux pages passent en privé.

## Points de vigilance pour l'application

- Aucune page « privé » n'a son multi parent dans ce fichier avec une décision privé, supprimer ou à signaler : pas de chaîne de redirections interne au fichier.
- Aucune page « privé » ne redirige vers un multi du plan de suppression adulte.
- **45 pages « privé » sont de niveau 2** : vérifier que leur parent (niveau 1) est toujours publié. S'il a été mis en privé lors de la première vague, rediriger vers le premier ancêtre publié.

## À trancher par Samuel (49 pages « à signaler »)

### Autre produit que le multi (6)

- 38986 « ampoules H4 LED » (multi « ampoules LED ») : Ampoules de phare auto (culot H4), pas des ampoules LED d'éclairage domestique. (Google : privé)
- 38561 « caissons de basse amplifiés voiture » (multi « enceintes pour voiture ») : Caisson de basse = complément audio, pas une enceinte voiture ; à vérifier (Google : privé)
- 39218 « caméras de surveillance factice » (multi « caméras de surveillance ») : Caméra factice = leurre sans enregistrement, pas une caméra de surveillance. (Google : privé)
- 38242 « draisiennes électriques » (multi « draisiennes ») : À vérifier : Google comprend draisienne électrique adulte (engin de mobilité), pas draisienne enfant (Google : garder)
- 39182 « eyeliners magnétiques » (multi « eyeliners ») : Eyeliner servant à fixer des faux-cils magnétiques (accessoire de faux-cils) (Google : privé)
- 39732 « primers pour cils » (multi « primers ») : base de mascara (cils), produit distinct du primer de teint du multi (Google : garder)

### Synonymes de leur multi (21) — recommandation : privé (301 vers le multi)

- 37986 « cabanons de jardin » → multi 38217 « abris de jardin »
- 39249 « mutuelles pour chien » → multi 42506 « assurances pour chien »
- 37478 « banques traditionnelles en France » → multi 42027 « banques physiques »
- 37822 « supports d'écran PC à bras articulé » → multi 37305 « bras d'écrans d'ordinateur »
- 41599 « brise-vues de jardin » → multi 37493 « brise-vues »
- 41446 « cartes graphiques gamer » → multi 37991 « cartes graphiques »
- 36869 « caves à vin électriques » → multi 41770 « caves à vin »
- 39618 « chauffe-eaux sans réservoir » → multi 38603 « chauffes-eau instantanés »
- 41438 « coques iPhone 13 sur Amazon » → multi 42081 « coques pour iPhone 13 »
- 42017 « enceintes bibliothèques Hi-Fi » → multi 39292 « enceintes bibliothèques »
- 39366 « gels douche pour le corps » → multi 38057 « gels douche »
- 40118 « lave-vaisselles 6 couverts » → multi 36444 « mini lave-vaisselle »
- 39202 « lave-vaisselles portables » → multi 36444 « mini lave-vaisselle »
- 38858 « lecteurs CD portables pour enfant » → multi 37728 « lecteurs CD pour enfants »
- 42358 « matelas viscoélastiques » → multi 37550 « matelas à mémoire de forme »
- 37529 « protéines en poudre pour hommes » → multi 39282 « protéines en poudre »
- 38304 « rafraîchisseurs d'air évaporatifs » → multi 36762 « rafraîchisseurs d'air »
- 41385 « souris sans fil sur Amazon » → multi 38232 « souris sans fil »
- 38251 « tapis de marche électriques » → multi 38007 « tapis de marche »
- 39382 « tondeuses à cheveux pour homme » → multi 37970 « tondeuses à cheveux »
- 37689 « pico projecteurs » → multi 41381 « mini vidéoprojecteurs »

### Paires en concurrence (9 paires) — garder l'une, rediriger l'autre vers elle

- 36547 « réfrigérateurs encastrables » / 42718 « réfrigérateurs congélateurs encastrables »
- 37404 « poêles à granulés ventouse » / 37489 « poêles à granulés étanches »
- 38004 « isolants thermiques des murs par l'intérieur » / 42149 « isolants thermiques muraux »
- 38780 « montres GPS pour enfant » / 37298 « montres connectées pour enfants »
- 39329 « crèmes hydratantes pour peau sèche » / 42018 « crèmes hydratantes visage pour peau très sèche »
- 39660 « écrans PC 1440p (QHD / WQHD) » / 36645 « écrans PC gamer 1440p »
- 40049 « oreillers ergonomiques » / 39134 « oreillers orthopédiques »
- 41367 « caméras de chasse 4G » / 37475 « caméras de chasse GSM »
- 42457 « cafés en grain pour machine automatique » / 41978 « cafés en grain pour machine De'Longhi »

### Doublons d'une page hors de ce fichier (4)

- 39075 « climatiseurs monoblocs muraux » = 38289 « climatiseurs sans unité extérieure »
- 38013 « collagènes en poudre » = 42054 « poudres de collagène »
- 41902 « machines à expresso avec broyeur » = le multi « machines à café à grains »
- 37120 « chauffe-eaux électriques instantanés » = le multi 38603 « chauffe-eau instantanés »

## Pages « supprimer » (11, contenu adulte déjà validé)

42402 « gels intimes pour femme », 42229 « godes ceintures creux », 42249 « godes ceintures double pénétration », 42250 « godes ceintures sans harnais », 41860 « godes ceintures vibrants », 42251 « godes ceintures éjaculateurs », 41877 « fleshlights transparents », 39842 « masturbateurs anaux », 42347 « masturbateurs automatiques », 42349 « masturbateurs réalistes », 41492 « vibromasseurs doubles »
