# Nettoyage des catégories WordPress (6000 → 2 niveaux max)

Scripts à coller dans des snippets PHP **WPCodeBox** (un snippet par fichier,
coller le contenu à la suite de la balise `<?php` de l'éditeur).

Chaque script ne s'exécute **que sur demande**, via une URL avec le paramètre
`?catcleanup=...`, et uniquement pour un **administrateur connecté**. Activer
un snippet ne fait donc rien tant que vous ne visitez pas son URL.

## Ordre d'exécution

| # | Script | URL de déclenchement | Modifie la DB ? |
|---|--------|----------------------|-----------------|
| 1 | `scripts/01-analyze.php` | `https://votre-site.fr/?catcleanup=analyze` | Non |
| 2 | `scripts/02-preview.php` | `https://votre-site.fr/?catcleanup=preview` | Non (écrit un CSV dans uploads) |
| 3 | `scripts/03-backup.php` | `https://votre-site.fr/?catcleanup=backup` | Non (écrit un .sql dans uploads) |
| 4 | `scripts/04-apply.php` | `https://votre-site.fr/?catcleanup=apply` | **Oui** (simulation par défaut) |
| 5 | `scripts/05-verify.php` | `https://votre-site.fr/?catcleanup=verify` | Non |

Diagnostic : `https://votre-site.fr/?catcleanup=ping` liste les snippets
réellement chargés sur la requête.

## Réparation : `scripts/06-remap.php` (`?catcleanup=remap`)

Page interactive pour recatégoriser les posts restés **sans catégorie**
après le nettoyage, en s'appuyant sur la taxonomie produit
(`post-type-produit`, configurable en haut du snippet) :

- une ligne par terme produit ayant des posts sans catégorie, triées par
  volume : `Casque audio — 19/189 sans catégorie`
- **catégorie détectée** : celle que portent déjà majoritairement les
  autres posts du même terme (avec taux de confiance, ex. `Audio —
  154/170 posts classés`)
- case à cocher pour valider la détection (cochée par défaut), ou champ
  manuel prioritaire acceptant un **ID**, un **slug** ou un chemin
  **`Parent > Enfant`**
- bouton « Appliquer » : chaque terme validé attribue la catégorie choisie
  **uniquement aux posts sans aucune catégorie** — purement additif,
  protégé par nonce, relançable à volonté
- la page signale aussi les posts sans catégorie **et** sans terme produit
  (à traiter à la main)

Définition de « sans catégorie » (alignée sur ce qu'on voit dans l'admin) :

- **« Non classé »** (catégorie par défaut) ne compte pas comme une vraie
  catégorie (`$catcleanup_ignore_default_cat`, activé par défaut) ;
- les **relations résiduelles** vers des catégories supprimées ou à moitié
  supprimées pendant le nettoyage (ligne `wp_terms` manquante) ne comptent
  pas non plus.

Vues supplémentaires :

- `?catcleanup=remap&mode=all` : liste **tous** les termes produit (filtre
  de recherche inclus) et permet d'appliquer la catégorie choisie à **tous
  les posts du terme** (toujours additif) — pour corriger des posts ayant
  reçu une mauvaise catégorie de repli ;
- `?catcleanup=remap&inspect=<slug ou ID du terme>` : posts d'un terme avec
  leurs catégories actuelles ;
- `?catcleanup=remap&post=<ID>` : relations brutes d'un post (détecte les
  relations fantômes/demi-fantômes) et verdict « catégorisé ou non » ;
- `?catcleanup=remap&fixcache=1` : purge le cache objet des relations de
  catégories de tous les posts (par lots, rechargement automatique). À
  lancer si l'admin affiche des catégories qui ne correspondent pas à la
  base (symptôme : l'inspecteur `&post=` dit « catégorisé » mais la
  colonne Catégories de wp-admin est vide) — conséquence des
  réaffectations SQL directes quand un cache objet persistant
  (Redis/Memcached) est actif.

Si une catégorie a été supprimée à tort : la recréer dans
Articles → Catégories, puis mettre son ID dans le champ manuel.

## Dépannage : « l'étape apply a crashé en cours de route »

Pas de panique : l'ordre des opérations garantit qu'aucun post ne perd ses
catégories (les L2 sont ajoutées **avant** tout retrait, les retraits
**avant** toute suppression). Un crash laisse un état incomplet mais sain.

1. Attendez 5-10 minutes : la requête interrompue côté navigateur peut
   continuer côté serveur (`ignore_user_abort`).
2. Lancez `?catcleanup=verify` (lecture seule) : le check n°1 indique
   combien de catégories de niveau 3+ restent. Zéro → le traitement était
   en fait terminé, passez à la réindexation SEO + purge des caches.
3. S'il en reste : revisitez `?catcleanup=apply` (toujours avec
   `dry_run = false`) et laissez la page se recharger jusqu'au message
   final. Le script reprend là où il s'est arrêté.

## Dépannage : « l'URL affiche/redirige vers l'accueil »

Ce symptôme signifie que le snippet ne s'est pas exécuté. Dans l'ordre :

1. **Visitez `?catcleanup=ping`.** Chaque snippet chargé s'y déclare.
   - Si le snippet manquant n'apparaît pas : il n'est pas actif dans
     WPCodeBox, ou son mode d'exécution est restreint (il doit tourner
     **partout**, pas « admin only » ni conditionné à une page).
   - Si `ping` lui-même affiche l'accueil : aucun snippet n'est chargé,
     ou un cache/CDN sert la page avant PHP — ajoutez un paramètre
     aléatoire (`&x=123`) pour contourner le cache, et testez aussi
     `https://votre-site.fr/wp-admin/?catcleanup=ping` (l'admin n'est
     jamais mis en cache).
2. **Message « acces refuse » (403)** : vous n'êtes pas reconnu comme
   administrateur sur cette URL. Utilisez exactement le même domaine que
   wp-admin (www/non-www, https) dans le même navigateur.
3. Les handlers s'exécutent sur `init` en priorité 0, avant les plugins de
   redirection (multilingue, SEO). Si une redirection persiste malgré un
   ping OK, testez l'URL en passant par `/wp-admin/?catcleanup=...`.

## Procédure

1. **Analyse** : vérifier l'arbre L1-L2 conservé et les stats par niveau.
2. **Prévisualisation** : télécharger le CSV et contrôler que les
   réaffectations sont correctes (ex. « Casques bluetooth pas chers » →
   « Audio », pas « Vidéo »).
3. **Backup** : télécharger le fichier SQL et le conserver hors du serveur.
4. **Application en simulation** : visiter l'URL apply avec
   `$catcleanup_dry_run = true` (valeur par défaut) et lire le rapport.
5. **Application réelle** : passer `$catcleanup_dry_run = false` dans le
   snippet, sauvegarder, revisiter l'URL. Le traitement se fait **par lots**
   (300 suppressions max ou ~20 s par requête) : la page se recharge toute
   seule entre les lots — laissez-la ouverte jusqu'au message
   « Opération terminée ». En cas de crash ou timeout, revisitez la même
   URL : l'état est recalculé à chaque requête, le script reprend
   exactement où il s'est arrêté. Un verrou empêche deux exécutions
   simultanées (il se libère seul après 10 minutes en cas de crash).
6. **Vérification** : contrôler les 6 checks du rapport.
7. **Après coup** : réindexer le plugin SEO, vider les caches, désactiver
   tous les snippets, supprimer les fichiers CSV/SQL générés dans
   `wp-content/uploads/`.

## Garanties

- Un post en catégorie profonde reçoit **l'ancêtre de niveau 2 de sa propre
  branche** (remontée parent par parent), jamais une autre branche.
- Un post avec plusieurs catégories profondes dans des branches différentes
  reçoit tous les ancêtres L2 distincts, sans doublon (`INSERT IGNORE`).
- La **catégorie par défaut** WordPress n'est jamais supprimée, même si elle
  est en niveau 3+.
- Les catégories orphelines (parent inexistant) sont traitées comme des
  racines et signalées dans l'analyse.
- Les cycles de parenté (A parent de B, B parent de A — données corrompues)
  sont détectés : leurs membres sont conservés et signalés dans l'analyse
  pour correction manuelle.
- `04-apply` refuse de tourner si `03-backup` n'a pas été exécuté (transient
  valable 7 jours).
- Les scripts sont idempotents : les relancer ne casse rien.

## Notes techniques

- Les fonctions partagées sont préfixées `catcleanup_` et protégées par
  `function_exists()` : plusieurs snippets peuvent être actifs en même temps
  sans conflit.
- Les réaffectations utilisent du SQL direct (`INSERT IGNORE ... SELECT`)
  pour la vitesse ; la suppression des termes passe par `wp_delete_term()`
  pour déclencher les hooks (SEO, cache...). Les compteurs sont recalculés à
  la fin (`wp_update_term_count_now`).
- Les gros volumes sont traités par lots (relations par 500, titres par
  1000, fichiers écrits en flux) pour rester sous le `memory_limit`.
