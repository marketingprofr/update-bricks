# Runbook — Onboarder un lot de nouveaux produits (avis / prix / images / scores Amazon)

> Checklist copier-coller pour enrichir un lot de produits `avis` récemment mis en
> ligne avec les données Amazon + Keepa, puis recalculer leurs scores. Tout tourne
> **sur le serveur Cloudways** (SSH), branche `claude/amazon-product-data-scraper-k4goq3`.
>
> Éprouvé le 02/09/2026 sur un lot de 231 produits (ID > 262300).

## Quand l'utiliser
Après avoir publié N nouveaux comparatifs/produits `avis` qui ont un ASIN dans
`mltv5_asin_amazon`. On ne traite **que** les produits au-dessus d'un seuil d'ID
(les nouveaux) ; les anciens ne sont pas re-fetchés.

## Pré-requis (déjà en place, à vérifier)
- `.env` présent (gitignoré) avec les clés **Amazon Creators** + **Keepa**.
- Libs Python : `requests`, `python-dotenv` (`pip install --user --break-system-packages requests python-dotenv` si besoin).
- Pas besoin d'auth GitHub : les scripts sont déjà sur le serveur. (Si un `git pull`
  demande un login GitHub, fais `Ctrl+C` — inutile ici. Voir la note en bas.)

## Le seul réglage : le seuil d'ID
```bash
cd ~/update-bricks
export APP=/home/master/applications/meilleurtestbricks/public_html
export MIN_ID=262300     # ← ne traiter que les produits d'ID > MIN_ID (le nouveau lot)
```
> Les fichiers de travail portent le suffixe `-new`. Si tu veux garder l'historique
> d'un lot, renomme-les (ex. `results-262300.json`) ; sinon chaque run écrase le précédent.

---

## Étape 0 — Reconnexion + self-tests (creds valides ?)
```bash
git checkout claude/amazon-product-data-scraper-k4goq3
# Amazon : doit obtenir un token OAuth et répondre (les ASINs peuvent être not_found, on s'en fiche)
python3 -u amazon-creators-api.py --selftest --asins B09V3KXJPB
# Keepa : doit renvoyer note + nombre d'avis et afficher les tokens restants
python3 -u keepa-reviews.py --selftest --asins B09V3KXJPB
```
Un échec d'auth (401 / invalid key / token refusé) = clé à renouveler dans `.env` **avant** de continuer.

## Étape 1 — Export des ASINs du lot (ID > MIN_ID)
```bash
wp eval-file wp-export-asins.php $MIN_ID --path=$APP > asins-new.csv
```
Vérifier le `Exportés : N produits avec ASIN` (stderr). C'est la taille réelle du lot.

## Étape 2 — Amazon : statut / prix / image (lecture seule, pas de token)
```bash
python3 -u amazon-creators-api.py --input asins-new.csv --output results-new.json
```
Regarder le **résumé de statuts** : `available` / `out_of_stock` / `not_found` /
`restricted` / `discontinued`. (`avec avis : 0` est **normal** — Amazon ne donne pas les avis, c'est Keepa.)

## Étape 3 — Keepa : nombre d'avis + note (token-pacé)
```bash
python3 -u keepa-reviews.py --from-results results-new.json --output keepa-new.json
```
`--from-results` saute automatiquement les `not_found`/`discontinued` (aucun token gaspillé).
**Token-pacé** : le script attend la recharge (5/min) et **n'abandonne jamais** un batch —
un gros lot peut prendre 20–40 min. Pour libérer le terminal : `Ctrl+C` puis relancer avec
`nohup … &` (il reprend au checkpoint).

## Étape 4 — Fusion Amazon + Keepa → `final-new.json`
```bash
python3 -u keepa-reviews.py --merge results-new.json keepa-new.json --output final-new.json
```

## Étape 5 — Import dans WordPress (dry → live)
Écrit **4 champs ACF** (match par ASIN) : `mltv5_nombre_avis_clients`,
`mltv5_score_avis_clients`, `mltv5_prix_indicatif` (**seulement si moins cher**, arrondi),
`mltv5_image_external_url`. Rien d'autre.
```bash
wp eval-file wp-import-amazon-data.php final-new.json dry  --path=$APP
wp eval-file wp-import-amazon-data.php final-new.json live --path=$APP   # crée acf-backup-*.csv
```

## Étape 6 — Tags de disponibilité OOS / DISCO (dry → live)
`OOS` ← `out_of_stock` ; `DISCO` ← `not_found` + `discontinued` ; le reste → aucun tag.
Ne touche **que** les posts dont l'ASIN est dans `results-new.json` (les autres tags intacts).
```bash
wp eval-file wp-set-availability-tags.php results-new.json dry  --path=$APP
wp eval-file wp-set-availability-tags.php results-new.json live --path=$APP   # crée availability-tags-backup-*.csv
```

## Étape 7 — Recalcul des scores (dry → live)
**Après** les tags (pour que la pénalité DISCO voie les tags à jour). Idempotent (base figée) :
les nouveaux produits sont `base capturée` puis scorés ; les anciens restent `inchangés`.
```bash
wp eval-file wp-score-total.php dry  --path=$APP
wp eval-file wp-score-total.php live --path=$APP   # crée score-total-backup-*.csv
```

## Étape 8 — Purge du cache (une seule fois, à la fin)
**FlyingPress** (+ **Varnish** Cloudways) — barre d'admin « Purge All » / panneau Cloudways.

---

## Notes / pièges
- **Match par ASIN, pas par ID.** Les étapes 5–7 parcourent *tous* les `avis` et
  agissent sur ceux dont l'ASIN est dans le feed. Si un ASIN du lot est **partagé**
  par un ancien produit, cet ancien est aussi rafraîchi (données fraîches et exactes,
  prix seulement s'il baisse → bénin). Pour restreindre *strictement* à `ID > MIN_ID`,
  il faudrait ajouter un garde d'ID à l'import — non recommandé.
- **DISCO sans score.** Un nouveau `not_found` sans score éditorial n'est pas pénalisé
  (rien à retrancher) → il tombe dans `ignorés`. Il sera pénalisé si on lui donne un score plus tard.
- **Toujours `dry` avant `live`** sur les étapes 5–7. Chaque `live` écrit une sauvegarde
  CSV (`acf-backup-*`, `availability-tags-backup-*`, `score-total-backup-*`) pour rollback.
- **`mltv5_score_recent` n'est JAMAIS touché.** Le statut/date Amazon ne sont pas écrits sur le site.
- **Git auth.** Faire tourner le pipeline ne nécessite aucun accès GitHub. Si tu dois
  récupérer une version de script mise à jour et que `git pull` demande un login, configure
  un token GitHub, ou fais-toi passer le fichier à coller directement.
