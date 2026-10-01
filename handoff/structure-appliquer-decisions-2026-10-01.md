# Mission : appliquer l'arbitrage SEO des 645 sous-comparatifs

Tu es l'instance qui gère la structure multi-comparatifs de meilleurtest.fr. Tu as produit `sections-a-discuter-2026-10-01.csv` : 645 sous-comparatifs publiés sur lesquels la vérification automatique n'a pas su trancher. L'instance SEO a rempli les colonnes `DÉCISION SEO (garder / privé)` et `MOTIF SEO`. À toi d'appliquer ces décisions sur le site, après quelques contrôles.

## Fichiers

- **Décisions :** `C:\Webdev\wp-avis\sections-a-discuter-2026-10-01-decisions.csv`. Même format que ton fichier d'origine : séparateur `;`, UTF-8 avec BOM.
- **Récapitulatif :** `C:\Webdev\wp-avis\recap-arbitrage-seo-2026-10-01.md`. Il contient la méthode, les limites et la liste des cas à trancher par Samuel.
- **S'ils ne sont pas dans ce dossier, ou si tu n'as pas accès au disque local**, télécharge-les ici (dépôt public, sans authentification) :
  - https://raw.githubusercontent.com/marketingprofr/update-bricks/claude/verify-seo-skill-install-u596xu/data/2026-10-01/sections-a-discuter-2026-10-01-decisions.csv
  - https://raw.githubusercontent.com/marketingprofr/update-bricks/claude/verify-seo-skill-install-u596xu/data/2026-10-01/recap-arbitrage-seo-2026-10-01.md

## Les quatre valeurs de décision

| Décision | Pages | Ce que tu fais |
|---|---|---|
| garder | 234 | Rien : la page reste publiée. |
| privé | 351 | 301 vers le multi parent, puis statut privé, exactement comme pour les 196 pages de la première vague. |
| à signaler | 49 | Rien. Ce sont des doublons et des pages qui visent un autre produit que leur multi : Samuel tranchera. |
| supprimer | 11 | Rien. Ce sont des pages adultes du plan de suppression (code 410), gérées par une autre instance. Leurs multis vont aussi disparaître. |

## Étape 1 : contrôles avant application (lecture seule)

1. **Cohérence avec les pages sœurs.** Dans une même série, on garde tout ou on cache tout, jamais une série à moitié visible. L'instance SEO n'a pu aligner les séries qu'à l'intérieur des 645 pages.
   - Compare chaque page « garder » ou « privé » avec ses sœurs déjà tranchées dans `comparatifs-structure-2026-09-30-site.csv` (même multi parent, même catégorie d'attribut, colonne `Décision finale`).
   - Une série, c'est une même catégorie d'attribut pour : taille / capacité / quantité, prix, marque, couleur / style, public visé, matière / composition.
   - Pour technologie / sous-type et usage / contexte, ce n'est une série que si les attributs sont des alternatives sur un même axe (ex. DDR3 / DDR4 / DDR5, intérieur / extérieur). Ce n'en est pas une pour des caractéristiques indépendantes (ex. « bluetooth » et « sans caisson de basses »).
   - Si une page contredit ses sœurs, ne l'applique pas. Mets-la de côté avec la décision de ses sœurs.
2. **Cible de la redirection.**
   - Le multi parent doit être publié et répondre 200. Il ne doit être ni en privé, ni à supprimer, ni à signaler.
   - Attention aux 45 pages « privé » de niveau 2 : si leur parent de niveau 1 a été mis en privé lors de la première vague, redirige vers le premier ancêtre publié.
3. **Pas de chaîne de redirections.** Si des redirections existantes pointent vers une page qui passe en privé, fais-les pointer directement vers la cible finale.

## Étape 2 : validation

Présente à Samuel, dans la conversation, en listes à puces :
- le nombre de pages prêtes à passer en privé ;
- les pages mises de côté, avec la raison (incohérence de série, parent non publié…) ;
- quelques exemples.

Attends son OK.

## Étape 3 : application (après OK)

1. **Sauvegarde.** Exporte les pages concernées (base de données ou JSON) avant toute modification.
2. **Mise en privé.** Pour chaque page « privé » : redirection 301 vers le multi parent, puis statut privé. Procède par lots, avec un journal. Si les multis ont des ancres de section stables et que tu les as utilisées pour la première vague, fais de même.
3. **Suivi.** Mets à jour ton fichier de suivi : `Décision finale` = « En privé » ou « Garder publiée ».
4. **Caches.** Purge les caches : Breeze / Varnish, puis Cloudflare.

## Étape 4 : vérification et compte rendu

- Chaque URL passée en privé répond 301 vers une page qui répond 200 : ni chaîne, ni 404.
- Les pages « garder » répondent toujours 200.
- Le sitemap ne liste plus les pages passées en privé.
- Écris le journal dans `C:\Webdev\wp-avis\application-decisions-2026-10-01.csv`.
- Fais un compte rendu court : pages appliquées, pages mises de côté avec leurs raisons, anomalies.

## À ne pas faire

- Ne touche pas aux pages « à signaler » ni « supprimer ».
- Ne change pas toi-même une décision SEO : signale les cas litigieux à Samuel.

**Communication.** Réponds en français, de façon concise, avec des listes à puces. Ne renvoie pas Samuel vers des fichiers CSV : présente-lui les listes directement dans la conversation.
