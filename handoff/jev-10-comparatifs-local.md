# Mission : analyser les 10 comparatifs les plus importants de meilleurtest.fr avec jev-seo

L'instance SEO, qui tourne dans le cloud, ne peut pas lire meilleurtest.fr : Cloudflare bloque les adresses IP hors des pays francophones. Toi, tu tournes sur le poste de Samuel. Tu collectes donc les données avec jev-seo, et l'instance SEO écrira l'analyse.

**Les outils :**
- **jev-seo** (https://github.com/AgriciDaniel/jev-seo) : contrôles SEO tirés de Google Search Central, jugement de chaque page par Jev (utilité, spécificité, confiance, intention, pages en concurrence), Core Web Vitals via PageSpeed.
- **Un petit script**, `jev_pages.py`, qui fait tourner jev-seo sur une liste de pages précises au lieu de tout le site. Il a été testé de bout en bout.

## Étape 1 : faire valider la liste par Samuel

Voici la liste proposée, dans l'ordre de priorité du fichier `guidestoindex.csv` de Samuel :

1. https://meilleurtest.fr/comparatif-climatiseur-sans-evacuation/
2. https://meilleurtest.fr/comparatif-climatiseur-mobile/
3. https://meilleurtest.fr/comparatif-ventilateur-de-plafond/
4. https://meilleurtest.fr/comparatif-climatiseur-sans-unite-exterieure/
5. https://meilleurtest.fr/comparatif-matelas/
6. https://meilleurtest.fr/comparatif-robot-lave-vitre/
7. https://meilleurtest.fr/comparatif-rafraichisseur-d-air/
8. https://meilleurtest.fr/comparatif-climatiseur/
9. https://meilleurtest.fr/comparatif-telepeage/
10. https://meilleurtest.fr/comparatif-poele-en-inox/

Remplaçants, dans l'ordre : https://meilleurtest.fr/comparatif-appareil-auditif/, puis https://meilleurtest.fr/comparatif-traceur-gps-sans-abonnement/.

1. **Montre cette liste à Samuel** et demande-lui de la confirmer ou de la modifier. Il connaît ses revenus par comparatif.
2. **Vérifie que chaque URL retenue répond 200, sans redirection.** Certains comparatifs ont pu être fusionnés dans un multi-comparatif. Remplace ceux qui redirigent ou sont privés par le remplaçant suivant, et dis-le à Samuel.

## Étape 2 : installer jev-seo

Saute cette étape si jev-seo est déjà installé. Sinon, dans PowerShell :

```powershell
cd C:\Webdev
git clone https://github.com/AgriciDaniel/jev-seo.git
cd jev-seo
py -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

Copie ensuite `.env.example` en `.env` dans le dossier `jev-seo`, et renseigne :
- `TYPESAFE_API_KEY` : la clé Jev de Samuel ;
- `PAGESPEED_API_KEY` : sa clé API Google.

Demande-les-lui. Ne committe jamais `.env`, et n'affiche jamais les clés.

## Étape 3 : lancer l'analyse

Dans le dossier `jev-seo`, avec l'environnement activé :

```powershell
Invoke-WebRequest "https://raw.githubusercontent.com/marketingprofr/update-bricks/claude/verify-seo-skill-install-u596xu/scripts/jev_pages.py" -OutFile jev_pages.py -UseBasicParsing
# Écris les 10 URL validées dans comparatifs.txt, une par ligne
python jev_pages.py --jevseo . --urls comparatifs.txt --out jev-seo-reports\comparatifs-2026-10-01
python -m jevseo render jev-seo-reports\comparatifs-2026-10-01 --formats xlsx,md
```

- **Durée :** 5 à 10 minutes, surtout pour PageSpeed (10 pages, sur mobile et sur ordinateur).
- **Coût Jev :** environ 0,005 USD, plafonné à 0,25 USD.
- **Progression :** donne à Samuel une ligne par étape.
- **Avertissements :** si le script affiche une ligne « ATTENTION » (page redirigée, en erreur, non atteinte), signale-la.
- **Blocage :** si Cloudflare bloque l'exploration, même depuis ce poste (erreur 403 ou page « Just a moment »), arrête-toi et préviens Samuel. Il peut autoriser temporairement l'IP de son poste dans Cloudflare. Ne contourne jamais la protection.

## Étape 4 : transmettre les résultats

1. Copie le dossier `jev-seo-reports\comparatifs-2026-10-01` dans le dépôt `marketingprofr/update-bricks`, branche `claude/verify-seo-skill-install-u596xu`, sous `data/jev-comparatifs-2026-10-01/`. Il contient `audit.json`, `digest.md`, `report.md`, `report.xlsx` et `charts/`.
2. Fais un commit et un push. Le dépôt est public : n'y mets ni `.env` ni aucune clé.
3. Réponds à Samuel en 5 lignes : les 10 pages analysées, le score, le coût Jev, les avertissements éventuels, et la confirmation du push.

L'instance SEO rédigera ensuite l'analyse détaillée des 10 comparatifs.
