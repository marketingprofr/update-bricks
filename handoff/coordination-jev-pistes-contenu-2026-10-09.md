# Pour avis : pistes d'amélioration du contenu de meilleurtest.fr

Tu coordonnes les audits Jev de meilleurtest.fr. La technique, la structure (multi-comparatifs, consolidation des variantes), la suppression du contenu adulte et pirate, et les recommandations de Jev et des principaux skills SEO sont en place.

Ce qui reste concerne le contenu. Il s'agit de ce que Google juge sur l'ensemble du site : la valeur ajoutée de nos pages par rapport aux autres, et la confiance qu'elles inspirent.

L'instance SEO a repris les critiques d'une analyse de Gemini, et Samuel les a commentées. Ce qui suit est la synthèse. **Donne ton avis sur chaque point**, en t'appuyant sur ce que les audits Jev ont mesuré :
- d'accord, pas d'accord, ou à tester ;
- ce que tu ferais concrètement ;
- ce qu'il faut mesurer pour savoir si ça marche.

## 1. Une information introuvable ailleurs : l'historique des prix (priorité n°1)

**Proposition.** Ajouter sur chaque comparatif, et sur les fiches produits, un bloc « historique des prix et meilleur moment pour acheter », à partir des données Keepa déjà collectées :
- prix le plus bas, le plus haut et médian sur 12 mois ;
- écart entre le prix actuel et la médiane ;
- mois où le prix est habituellement le plus bas ;
- nombre de baisses importantes ;
- stabilité du prix ;
- disponibilité dans le temps.

À l'échelle d'un comparatif, des agrégats par catégorie : « les prix des climatiseurs mobiles de ce comparatif ont baissé de 12 % en un an ».

**Pourquoi.** C'est de l'information nouvelle, que ni les sites faits par IA ni les concurrents n'ont. C'est ce que Google valorise de plus en plus.

**Avis de Samuel.** Bonne idée. Mais tout doit être automatisé, sans payer de tokens d'IA pour rédiger 5 000 guides.

**Piste technique, à confirmer avec l'instance template :**
- Calcul en PHP à partir des données Keepa stockées en base, mis en cache, sans aucun appel à un modèle d'IA.
- Texte produit par des modèles de phrases conditionnels, avec 3 à 5 variantes par cas, pour éviter un texte identique partout. Exemples :
  - « Le prix actuel est 15 % sous sa moyenne des 12 derniers mois : c'est un bon moment pour acheter. »
  - « Ce modèle baisse généralement en mars. »
- Graphique en SVG généré depuis les données.
- Données structurées : le prix reste dans `Offer`, l'historique n'a pas besoin de balisage.

**Questions pour toi :**
1. Jev voit-il un gain sur la spécificité et l'utilité des pages avec ce type de bloc ?
2. Faut-il le tester d'abord sur les 10 comparatifs audités ?

## 2. Les compteurs (« heures de recherche », « produits analysés », « avis étudiés »)

**Proposition de départ :** les retirer. **Corrigée après discussion :** Jev et les skills SEO recommandent de les garder, et c'est d'accord. Une seule condition : chaque chiffre doit être exact et vérifiable, et formulé honnêtement. « 225 modèles analysés » si on ne les a pas eus en main, plutôt que « 225 testés ».

**Question pour toi :** les formulations actuelles passent-elles ce test ?

## 3. Pages auteurs et page méthode

**Proposition :**
- **Des pages auteurs plus fournies**, présentant une vraie personne : parcours, gammes suivies depuis des années, liste de ses guides. L'auteur est déclaré comme `Person` dans les données structurées, avec un lien vers sa page.
- **Retravailler la page méthode existante.** Elle doit dire franchement ce qui est fait : pour l'électroménager, une analyse des caractéristiques techniques, des mesures publiées et des retours d'usage ; pour certaines catégories, une prise en main. Google accepte la recherche documentaire déclarée. Il sanctionne le test prétendu.

**Avis de Samuel.** D'accord. Les pages auteurs relèvent de l'instance architecture de l'information, avec l'instance template pour la mise en place.

**Question pour toi :** qu'est-ce que les audits Jev disent aujourd'hui des signaux de confiance (auteur, méthode) ?

## 4. Forme du gabarit

**Proposition :**
- Chaque produit a un verdict rédigé (« pour qui, et pour qui pas ») plutôt que des listes uniformes d'avantages et d'inconvénients.
- Le bouton d'achat vient après l'argument.
- Le tableau comparatif est conservé.
- Le guide d'achat est taillé (3 à 5 critères concrets, sans passages génériques) plutôt que déplacé sur une autre page.

**Avis de Samuel.** Pas sûr que ce soit une bonne idée. À discuter, et à tester avant toute généralisation.

**Questions pour toi :**
1. Les audits Jev montrent-ils un problème de forme : listes, boutons, longueur, passages génériques ?
2. Quel test A/B ou quel pilote proposerais-tu ?

## 5. Preuves visuelles (photos réelles)

**Proposition :** des photos réelles sur les 30 à 50 guides principaux. Les sources possibles sont les archives des rédacteurs, les photos envoyées par les lecteurs, et les visuels des fabricants annotés (jamais les images Amazon, interdites de modification).

**Avis de Samuel.** Faisable mais difficile, et l'impact n'est pas sûr. Pour l'électroménager, personne ne teste réellement les produits : on analyse les caractéristiques. Pour les matelas, beaucoup des meilleurs ne sont vendus qu'en ligne.

**Proposition de compromis :** priorité basse. Pas de faux « nous avons testé ». Éventuellement un test limité sur une catégorie où c'est simple.

**Question pour toi :** Jev pénalise-t-il l'absence d'images propres au site ?

## 6. Nouveaux formats de pages tirés de vraies requêtes

**Proposition.** Sur les familles les plus rentables, créer des pages :
- **face-à-face** : « X ou Y pour une pièce de 25 m² » ;
- **pannes fréquentes et durée de vie** ;
- **« ça vaut le coup ? »** sur un produit précis.

Les sujets se choisissent à partir des requêtes réelles de la Search Console (fichier `comparatif_top_queries.csv` du dépôt). Les pages captaient déjà des requêtes comme « ouiezen avis », « bouilloire en inox danger » ou « noirot fusion 2 ».

**Question pour toi :** cette piste est-elle cohérente avec ce que Jev a relevé sur l'intention de recherche et les pages en concurrence ?

## 7. Points écartés après discussion (pour information)

- **Liens (netlinking).** D'expérience, les liens obtenus sont presque tous payants ou issus de réseaux de sites. Les grands médias placent des dizaines de liens sur leurs propres sites. Pour un comparatif, ce serait environ 10 000 € de liens : ce n'est pas faisable.
- **Newsletter « bons plans ».** Déjà essayée sans succès : les visiteurs préfèrent Dealabs.
- **Encart « Google favorise l'IA, nous avons perdu nos revenus ».** Rejeté : il n'aide pas le lecteur.

## Méthode proposée

Aucune généralisation sans pilote :
- **20 guides modifiés**, en commençant par les 10 comparatifs audités par Jev ;
- **20 guides comparables inchangés**, comme témoins ;
- **mesure à 8 semaines puis à 3 mois**, dans la Search Console et dans Bing Webmaster Tools.

## Ce qu'on attend de toi

Réponds à Samuel en français, de façon concise, avec des listes à puces. Pour chaque point de 1 à 6 :
- ton avis ;
- ce que disent les audits Jev déjà faits ;
- ce que tu proposerais concrètement, et à quelle instance confier le travail (template, architecture de l'information, structure).

Termine par ton ordre de priorité.
