# Pour étude : des informations introuvables ailleurs, sans travail comparatif par comparatif

**Destinataires :** la coordination des audits Jev, qui répartit le travail, l'instance template et l'instance architecture de l'information.

## Contexte

La technique et la structure de meilleurtest.fr sont en place, ainsi que les recommandations de Jev et des skills SEO. Le levier qui reste, c'est d'apporter sur nos pages **des informations que Google ne trouve nulle part ailleurs**, et de mieux montrer qui écrit et comment.

**La contrainte de Samuel est stricte.** Tout doit être automatique et s'appliquer à l'ensemble du site d'un seul coup :
- **aucun travail comparatif par comparatif** ;
- **aucun token d'IA** pour rédiger 5 000 guides.

**Ce qui est autorisé :** des calculs en PHP sur des données déjà en base ou disponibles en données ouvertes, des phrases types avec plusieurs variantes, et des graphiques SVG.

**Le piège à éviter.** Un bloc identique sur 5 000 pages, c'est du remplissage. Un bloc n'a de valeur que si ses chiffres changent vraiment d'une page à l'autre. Règle commune : **quand les données manquent ou sont trop pauvres, le bloc ne s'affiche pas.** On ne met jamais de texte de remplacement.

## Étape 0 : l'inventaire des données (instance template)

Avant de choisir quoi que ce soit, on a besoin de savoir ce qu'on a. Liste, avec un exemple réel par donnée et la proportion de produits couverts :
- **Keepa :** les champs stockés et sur quelle durée (historique de prix neuf et occasion, Amazon contre vendeurs tiers, note, nombre d'avis, rang des ventes, date de première mise en vente, ruptures de stock).
- **Les caractéristiques techniques des produits :** champs ACF ou autres, et leur degré de normalisation (unités homogènes ? puissance en W, poids en kg, BTU, dB, dimensions ?).
- **La référence fabricant du modèle**, la marque et l'EAN/GTIN.
- **Les clics sur les liens d'affiliation :** sont-ils suivis par produit et par page ?
- **La date de dernière mise à jour** des prix et des fiches.

## Les pistes, à évaluer

Pour chaque piste, on veut trois réponses :
- **Faisable ?** Avec quelles données, et pour quelle part des produits.
- **Coût de mise en place**, en jours.
- **Gain attendu**, du point de vue de Jev : utilité, spécificité, confiance.

### A. À partir de nos propres données (Keepa, fiches, clics)

1. **Historique des prix et meilleur moment pour acheter** (déjà retenu, priorité n°1) :
   - prix le plus bas, le plus haut et médian sur 12 mois ;
   - écart entre le prix du jour et la médiane ;
   - mois habituellement le moins cher ;
   - comportement lors du Prime Day et du Black Friday précédents.
2. **Évolution de la note des acheteurs.** Exemple : « la note est passée de 4,5 à 4,1 en un an, avec 800 avis de plus ». C'est un signal de qualité que personne ne montre. Les données sont dans l'historique Keepa, si elles sont stockées.
3. **Disponibilité.** La part du temps où le produit était en stock sur 12 mois, avec les ruptures fréquentes. Utile pour éviter de recommander un produit qui disparaît.
4. **Âge du modèle.** La date de première mise en vente. Exemple : « en vente depuis 2021 ; un modèle plus récent existe peut-être ». Ce n'est montré que si la donnée est fiable.
5. **Prix ramené à l'usage**, calculé depuis les caractéristiques. Exemples : €/1 000 BTU pour les climatiseurs, €/litre pour les réfrigérateurs, €/m² couvert. Cela produit un classement « rapport qualité-prix » objectif à l'intérieur du comparatif.
6. **Coût d'utilisation sur 5 ans.** Puissance × durée d'usage type × tarif réglementé de l'électricité (la valeur est mise à jour à un seul endroit). On obtient le coût total : prix d'achat plus énergie. C'est très parlant pour la climatisation, le chauffage, le froid et le lavage. Les hypothèses d'usage sont affichées clairement.
7. **Le choix de nos lecteurs.** « Le modèle le plus choisi par nos lecteurs ce mois-ci », à partir des clics d'affiliation. C'est une donnée de première main, propre au site et honnête. Il faut un seuil minimal de clics pour l'afficher.

### B. À partir de données publiques ouvertes (aucune saisie manuelle)

8. **L'étiquette énergie officielle, via EPREL**, la base européenne publique avec une API. Classe énergétique, consommation et bruit officiels, rapprochés par la référence fabricant. **À vérifier :** le taux de rapprochement réel avec nos produits.
9. **Les rappels de produits, via RappelConso**, les données ouvertes de l'État avec une API. « Aucun rappel connu pour cette marque dans cette catégorie », ou l'inverse, avec le lien vers la fiche officielle. C'est un signal de confiance fort, rarement présent ailleurs.
10. **Les indices de réparabilité et de durabilité**, obligatoires en France pour plusieurs catégories (téléviseurs, lave-linge, smartphones, aspirateurs…). **À vérifier :** il faut trouver une source exploitable automatiquement. Sinon, on abandonne la piste.

### C. Au niveau du site entier

11. **Un baromètre des prix mensuel**, sur une page générée automatiquement : « ce mois-ci, les prix des climatiseurs mobiles ont baissé de 9 % ». C'est le seul type de contenu qui peut attirer **des liens naturels gratuits** (journalistes, forums), sans netlinking payant. Il renvoie vers les comparatifs concernés. **Question pour l'architecture :** une page unique, ou une page par grande famille ?
12. **Des compteurs reliés aux vraies données.** Sur la page méthode et dans l'en-tête des guides, les chiffres sont calculés en base : « 48 213 prix relevés chaque jour », « 225 modèles analysés pour ce guide », « dernière vérification le 12 octobre ». Les compteurs sont gardés, comme le recommandent Jev et les skills SEO, et deviennent vérifiables.

## Pages auteurs et page méthode (instance architecture, avec l'instance template)

**Pages auteurs :**
- présentation de la personne : parcours, gammes suivies, ancienneté sur le site ;
- **parties automatiques :** liste de ses guides par catégorie, nombre de guides, date de sa dernière mise à jour ;
- données structurées `Person` reliées à chaque guide (`author.url`, `sameAs` si des profils publics existent).

**Page méthode,** à retravailler :
- ce qui est fait réellement, catégorie par catégorie. Pour l'électroménager, c'est une analyse des caractéristiques, des mesures publiées, des avis et de l'historique des prix, et non des tests en main. Ne jamais prétendre l'inverse ;
- **les sources de données citées :** Keepa, EPREL, RappelConso, les fiches fabricants ;
- les compteurs automatiques de la piste 12 ;
- la façon dont la sélection est faite et mise à jour, et le mode de rémunération (affiliation) ;
- un lien depuis chaque guide, près de la date de mise à jour.

## Points de vigilance à vérifier avant de lancer

- **Contrat Partenaires Amazon.** Il impose des règles strictes sur l'affichage des prix : source, horodatage et fraîcheur. Vérifier qu'afficher un historique et des prix passés est conforme.
- **Conditions Keepa.** Vérifier qu'elles autorisent la publication de leurs données ou de graphiques dérivés.
- **Formulations.** Pas de « prix barré » ni de « promotion » trompeurs. On parle d'« écart avec le prix médian sur 12 mois ».
- **Performances.** Tous les calculs sont mis en cache, rafraîchis par une tâche planifiée, et jamais exécutés au chargement de la page. Le score Performance de Jev est déjà le plus faible après la préparation à la recherche par IA (76/100).
- **Le bloc disparaît quand les données sont insuffisantes.**

## Ce qu'on attend

**Coordination Jev :**
- un avis sur chaque piste (1 à 12), en s'appuyant sur les audits Jev déjà faits ;
- un **ordre de priorité** ;
- **un pilote :** les 10 comparatifs audités plus 10 autres, avec autant de guides témoins inchangés, un nouvel audit Jev des pages modifiées, puis une mesure dans la Search Console à 8 semaines et à 3 mois.

**Template :**
- l'inventaire des données (étape 0) ;
- pour chaque piste retenue : faisabilité, champs utilisés, couverture, temps de développement ;
- une maquette du bloc historique des prix sur un comparatif.

**Architecture :**
- les pages auteurs, la page méthode et le baromètre des prix : emplacement, URL, liens internes et données structurées.

**Réponses** à Samuel, en français, concises, en listes à puces.

**Nouvelles idées bienvenues,** si elles respectent la règle : automatique, sur tout le site, sans tokens d'IA, avec des chiffres qui changent vraiment d'une page à l'autre.
