# TP7 — Interface Web accessible et intégration de bout en bout

**Groupe :** ALIDOU Arafat – AHOUANTCHEME Danik – DOVONOU Ozias (L3 SIL)

## 1. Parcours utilisateurs implémentés (`public/app.html`)

- **Parcours Étudiant (4 écrans/états) :**
  1. Connexion (`#vue-connexion`) avec validation accessible et gestion de session.
  2. Formulaire de dépôt de demande (`#form-demande`) lié à `/api/entreprises` et `POST /api/demandes`.
  3. Liste de ses demandes (`#liste-demandes-etudiant`) affichant le statut et le motif éventuel.
  4. Action de soumission (`BROUILLON -> SOUMISE`) ou de reprise après refus (`REFUSEE -> BROUILLON`).
- **Parcours Responsable pédagogique (3 écrans/états) :**
  1. Connexion (`#vue-connexion`).
  2. Liste globale des demandes (`#liste-demandes-responsable`).
  3. Décision de validation (`VALIDEE`) ou de refus motivé (`#panneau-refus` avec champ `motif` obligatoire).

## 2. Accessibilité, gestion d'erreurs et protection XSS

- **Accessibilité :** chaque champ possède une balise `<label for="...">` associée à son `id`, et chaque message d'erreur possède l'attribut `role="alert"`.
- **Gestion des codes HTTP dans `public/app.js` :**
  - `400` : affichage du message de l'API directement sous le champ concerné (`#erreur-entrepriseId`, `#erreur-motif`).
  - `401` : bascule automatique vers l'écran de connexion avec message explicite.
  - `500` : message neutre sans exposition de détail technique.
- **Protection XSS :** aucune donnée provenant de l'API ou saisie par l'utilisateur n'est injectée via `innerHTML` ; tout est inséré via `textContent`.

## 3. Scénario de bout en bout vérifié

1. Connexion en tant qu'étudiant (`danik.ahouantcheme@univ.test` / `Demo1234!`).
2. Dépôt d'une demande pour *Atelier Numerique* -> création confirmée et visible dans la liste.
3. Déconnexion puis connexion en tant que responsable (`resp.pedago@univ.test` / `Demo1234!`).
4. Validation du dossier -> passage au statut `VALIDEE`.
5. **Rafraîchissement complet de la page (`F5`)** -> la session reste active via `GET /api/moi` et le statut `VALIDEE` persiste depuis la base MySQL/MariaDB.
