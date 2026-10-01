# Mission : lancer l'audit SEO jev-seo sur meilleurtest.fr depuis ce poste

L'instance SEO, qui tourne dans le cloud, ne peut pas explorer meilleurtest.fr : Cloudflare renvoie une erreur 403 à toutes les adresses IP hors des pays francophones, et l'outil interdit de contourner une protection. Depuis ce poste (IP française), ça passe. Toi, tu collectes les données ; l'instance SEO écrira l'analyse.

**L'outil :** https://github.com/AgriciDaniel/jev-seo. C'est un audit en direct : exploration du site, 52 contrôles tirés de Google Search Central, Core Web Vitals via PageSpeed, et jugement de chaque page par Jev, le modèle de TypeSafe.

## Étapes

1. **Installation (PowerShell)**

   ```powershell
   cd C:\Webdev
   git clone https://github.com/AgriciDaniel/jev-seo.git
   cd jev-seo
   py -m venv .venv
   .venv\Scripts\Activate.ps1
   pip install -r requirements.txt
   ```

2. **Clés.** Copie `.env.example` en `.env` et remplis :
   - `PAGESPEED_API_KEY` : la clé API Google de Samuel, celle déjà utilisée pour PageSpeed. Demande-la-lui si besoin.
   - `TYPESAFE_API_KEY` : si Samuel en a une (compte sur typesafe.ai). Sans elle, l'audit tourne quand même, mais sans les jugements de pages (utilité, spécificité, confiance, intention, pages en concurrence). C'est la partie la plus utile pour ce site : demande-lui.
   - `DATAFORSEO_USERNAME` et `DATAFORSEO_PASSWORD` : seulement s'il veut le mode complet (positions, mots-clés, concurrents, backlinks), pour environ 0,30 USD.

   Ne committe jamais `.env`, et n'affiche jamais les clés.

3. **Vérification.** Lance `python -m jevseo doctor`. Sous Windows, weasyprint peut apparaître en erreur : c'est normal, on ne génère pas le PDF.

4. **Audit.** Dans le dossier `jev-seo`, avec l'environnement activé :

   ```powershell
   python -m jevseo audit https://meilleurtest.fr --max-pages 300 --jev-pages 150 --psi-pages 5 --time-budget 1800 --out jev-seo-reports\meilleurtest.fr-2026-10-01
   ```

   - **Avec DataForSEO**, ajoute `--full --location-code 2250 --language fr`, pour le marché français.
   - **Progression.** L'audit avance en 7 étapes : donne à Samuel une ligne par étape.
   - **Blocage.** Si Cloudflare bloque aussi depuis ce poste (erreur 403 ou page « Just a moment »), arrête-toi et signale-le. C'est un constat à remonter : ne contourne pas la protection.

5. **Rendu.** Lance :

   ```powershell
   python -m jevseo render jev-seo-reports\meilleurtest.fr-2026-10-01 --formats xlsx,md
   ```

   Ajoute `pdf` à la liste seulement si GTK est installé.

6. **Transmission.**
   - Copie le dossier de résultats, sans `.env`, dans le dépôt `marketingprofr/update-bricks`, branche `claude/verify-seo-skill-install-u596xu`, dossier `data/audit-jev-2026-10-01/`. Puis commit et push.
   - Fichiers indispensables : `digest.md` et `audit.json`. Ajoute `report.md` et `report.xlsx`.
   - Le dépôt est public : le rapport y sera visible. Il ne contient que des données du site public.

7. **Compte rendu.** Réponds à Samuel en 5 lignes, en français : nombre de pages explorées, score, coût Jev, blocages éventuels, et confirmation du push.
