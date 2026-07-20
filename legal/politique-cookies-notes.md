# Notes — Politique de cookies

La politique de cookies est generee automatiquement par le plugin **Complianz**.
On ne la reecrit pas entierement, mais voici les actions a mener dans les
reglages Complianz / sur la page :

## A verifier / corriger dans Complianz

1. **Adresse** : harmoniser avec les autres pages
   → `Ul. Momini Dvori 2, 2770 Bansko, Bulgarie`
   (actuellement "Murphy's Lodge" dans les mentions legales)

2. **Section "Divers"** : 22 cookies en "Finalite en attente d'enquete"
   → les classer (fonctionnel / statistiques / marketing) ou les supprimer
   de la liste s'ils ne sont plus utilises (ex: `adminer_*` = outil admin,
   `brx_*` = Bricks Builder back-end, `LinkBoss*` = plugin admin).
   Les cookies admin-only ne devraient pas apparaitre dans la politique
   publique.

3. **Google Analytics** : verifier que le consentement est bien requis
   AVANT le depot des cookies GA (pas seulement classe en "Statistiques").

4. **Google Adsense** : idem, consentement requis pour "Marketing".

5. **Durees de retention** : plusieurs cookies n'ont pas de duree renseignee
   → les completer dans Complianz (ou les retirer si obsoletes).

## Texte a ajouter manuellement sur la page (apres le bloc Complianz)

Ajouter un bloc WordPress en bas de page avec le texte suivant :

---

**Utilisation d'outils d'intelligence artificielle**

Le Site utilise des outils d'intelligence artificielle (IA) dans le cadre
de la production de contenu editorial. Ces outils peuvent deposer des
cookies techniques ou analytiques propres a leurs fournisseurs. Le cas
echeant, ces cookies sont soumis aux memes regles de consentement que les
autres cookies du Site.

**Liens d'affiliation et cookies tiers**

Lorsque vous cliquez sur un lien d'affiliation present sur le Site, le
site marchand partenaire peut deposer ses propres cookies conformement a
sa propre politique de cookies. L'Editeur n'est pas responsable des
cookies deposes par des sites tiers. Nous vous invitons a consulter les
politiques de cookies des sites marchands concernes.
