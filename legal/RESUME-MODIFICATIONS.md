# Resume des modifications — Documents juridiques meilleurtest.fr

Date : 20 juillet 2026

## Problemes identifies dans les versions actuelles

### Incoherences entre les pages
- **CGU** : referencent "Samuel Petit" comme exploitant + "lois d'Estonie"
- **Mentions legales** : referencent "Media Bansko United" en Bulgarie
- **Cookies** : adresse differente ("Ul. Momini Dvori 2" vs "Murphy's Lodge")
- → **Harmonise** : tout sous "Media Bansko United" / Bulgarie / droit bulgare.
  Attention : le choix du droit applicable (Bulgarie vs Estonie) est une
  decision business a valider avec un juriste.

### Lacunes majeures comblees

| Protection ajoutee | Mentions | CGU | Confidentialite | Avertissement |
|---|---|---|---|---|
| Images IA (generees, non contractuelles, illustratives) | art.6 | art.5 | — | art.4 |
| Contenus assistes par IA (erreurs, hallucinations, biais) | art.7 | art.4 | art.10 | art.3 |
| Images non representatives des produits reels | art.6 | art.5 | — | art.4 |
| Images = pas de preuve de test physique | art.6 | art.5 | — | art.4 |
| Personnes sur images = fictives / IA / modeles | — | — | — | art.4 |
| Methodologie des tests = subjective, propre a la redaction | art.11 | art.6 | — | art.5 |
| Tests pas forcement physiques sauf mention contraire | — | art.6 | — | art.5 |
| Labels internes (Meilleur choix, etc.) = pas de certification | — | — | — | art.5 |
| Comparatifs non exhaustifs | art.11 | art.6 | — | art.5 |
| Prix = purement indicatifs, non contractuels, potentiellement obsoletes | art.8 | art.7 | — | art.7 |
| Disponibilite produits non garantie | — | art.7 | — | art.7 |
| Caracteristiques techniques = indicatives, potentiellement fausses | art.9 | art.18 | — | art.2 |
| Affiliation et commissions (transparence) | art.8 | art.7 | — | art.6 |
| Le site ne vend rien (pas partie a la transaction) | — | art.3 | — | art.9 |
| Limitation de responsabilite renforcee (100€ max cumule) | art.10 | art.17 | — | art.8 |
| Aucun conseil pro (juridique, medical, financier, technique) | art.5 | art.3+16 | — | art.1 |
| Scraping interdit y compris pour entrainement IA | — | art.9.2 | — | — |
| Marques = propriete de leurs detenteurs, usage informatif | — | — | — | art.12 |
| Sante et securite : renvoyer vers fabricant/professionnel | — | — | — | art.13 |
| Protection des mineurs (< 16 ans) | — | — | art.12 | — |
| Transferts internationaux | — | — | art.6 | — |
| Durees de conservation donnees | — | — | art.7 | — |
| Bases juridiques RGPD detaillees | — | — | art.3 | — |
| Traitement automatise / IA et donnees personnelles | — | — | art.10 | — |
| Droit de plainte CNIL / autorite competente | — | — | art.14 | — |
| Lien plateforme ODR (reglement litiges en ligne UE) | — | art.31 | — | — |
| Force majeure elargie (pandemie, cyberattaque, etc.) | — | art.23 | — | — |
| Indemnisation renforcee | — | art.22 | — | — |

## Comment deployer

1. **Mentions legales** : remplacer le contenu de la page WordPress par
   `legal/mentions-legales.html`
2. **CGU** : remplacer par `legal/conditions-generales-dutilisation.html`
3. **Declaration de confidentialite** : remplacer par
   `legal/declaration-de-confidentialite.html`
4. **Avertissement** : remplacer par `legal/avertissement.html`
5. **Politique de cookies** : suivre les instructions de
   `legal/politique-cookies-notes.md` (reglages Complianz + bloc a ajouter)
6. **Purger le cache** Varnish + Breeze apres chaque mise a jour

## Points a valider avec un juriste

- [ ] Droit applicable : Bulgarie vs Estonie (les CGU actuelles disent Estonie,
      les nouvelles disent Bulgarie — a trancher selon le siege reel)
- [ ] Entite juridique : "Media Bansko United" est-elle une LLC, EURL, etc. ?
      Ajouter la forme juridique et le numero d'immatriculation si disponible
- [ ] Directeur de publication : confirmer "Samuel Petit"
- [ ] DPO : si applicable, nommer un DPO ou confirmer l'absence
- [ ] Numero de TVA de Media Bansko United (si applicable)
- [ ] Verifier la conformite avec le droit bulgare et le droit europeen
