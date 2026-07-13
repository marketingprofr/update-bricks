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
   snippet, sauvegarder, revisiter l'URL. En cas de timeout serveur,
   revisiter la même URL : le script reprend où il s'est arrêté.
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
