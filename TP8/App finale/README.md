# TP8 — Module d'encadrement et de soutenances (L3 SIL)

Extension complète de l'application de gestion de stages intégrant la gestion des encadreurs, l'affectation aux dossiers validés, la programmation des soutenances et la notation.

## Tests et sécurité (TP8)

- Documents : `docs/tp8-tests-securite/` (matrice de tests, preuves, fiche de revue de code).
- Banc de test HTTP : `php tests/securite_http.php http://localhost:8000` (réimporter `001_schema.sql` et `002_seed.sql` avant chaque exécution).
- Test de service : `php tests/api_demandes.php`.
- Route ajoutée : `GET /api/demandes/{id}` (un étudiant ne lit que ses dossiers, un encadreur que ceux qu'il encadre).
- Protection CSRF : cookie `SameSite=Lax` et refus (403) des requêtes modifiantes dont l'en-tête `Origin` n'est pas celui du site.
