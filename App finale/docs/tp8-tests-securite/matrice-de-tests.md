# TP8 : matrice de tests

Groupe : ALIDOU Arafat, AHOUANTCHEME Danik, DOVONOU Ozias (L3 SIL).

**48 tests** exécutés sur un vrai serveur PHP et une vraie base MariaDB, d'abord sur le code livré, puis sur le code corrigé.

| | Code livré | Code corrigé |
|---|---|---|
| Tests OK | 35 | 46 |
| Écarts | 13 | 2 (T47 et T48 : recommandations, voir la fiche de revue) |

Colonnes : **test, exigence, données, attendu, observé, preuve** (demandées par le TP), avec en plus l'observé avant et après correction.

Légende du statut : **OK** = conforme dès le départ ; **Corrigé** = écart constaté sur le code livré, conforme après correctif ; **Écart conservé** = non corrigé, traité en recommandation.

| N° | Test | Exigence | Données | Attendu | Observé (code livré) | Observé (code corrigé) | Statut | Preuve |
|---|---|---|---|---|---|---|---|---|
| T01 | Connexion valide (responsable) | EF-authentification | resp.pedago@univ.test / Demo1234! | 200 | 200 | 200 | OK | preuves-securite.md §8, ligne T01 |
| T02 | Mauvais mot de passe | Message d'échec identique | arafat.alidou@univ.test / faux | 401 | 401 | 401 | OK | preuves-securite.md §8, ligne T02 |
| T03 | Compte inconnu : même message que T02 | Message d'échec identique | inconnu@univ.test / faux | 401 | 401 | 401 | OK | preuves-securite.md §8, ligne T03 |
| T04 | Liste sans session | Contrôle d'accès | aucun cookie | 401 | 401 | 401 | OK | preuves-securite.md §8, ligne T04 |
| T05 | Identifiant de session régénéré après connexion | Anti-fixation de session | cookie avant/après connexion | 200 | 200 | 200 | OK | preuves-securite.md §8, ligne T05 |
| T06 | Cookie de session : HttpOnly et SameSite | Protection du cookie | en-tête Set-Cookie du login | 200 | 200 | 200 | OK | preuves-securite.md §7, ligne T06 |
| T07 | Étudiant B (Danik) lit le dossier de l'étudiant A (Arafat) : GET /api/demandes/1 | Cloisonnement des dossiers | danik -> dossier 1 (appartient à arafat) | 403 | 404 | 403 | Corrigé | preuves-securite.md §4, ligne T07 |
| T08 | Le propriétaire lit son propre dossier : GET /api/demandes/1 | Cloisonnement des dossiers | arafat -> dossier 1 | 200 | 404 | 200 | Corrigé | preuves-securite.md §4, ligne T08 |
| T09 | La liste d'un étudiant ne contient que ses dossiers | Cloisonnement des dossiers | danik : GET /api/demandes | 200 | 200 | 200 | OK | preuves-securite.md §4, ligne T09 |
| T10 | L'étudiant B modifie le dossier de l'étudiant A (soumission) | Cloisonnement des dossiers | danik : PATCH /api/demandes/1/statut SOUMISE | 403 | 403 | 403 | OK | preuves-securite.md §4, ligne T10 |
| T11 | Un étudiant tente de valider (rôle insuffisant) | Contrôle des rôles | danik : PATCH /api/demandes/2/statut VALIDEE | 403 | 403 | 403 | OK | preuves-securite.md §4, ligne T11 |
| T12 | « 1 OR 1=1 » comme identifiant dans l'URL (GET) | Requêtes préparées / validation | GET /api/demandes/1%20OR%201=1 (danik) | 400/404 | 404 | 404 | OK | preuves-securite.md §5, ligne T12 |
| T13 | « 1 OR 1=1 » comme identifiant dans l'URL (PATCH) | Requêtes préparées / validation | PATCH /api/demandes/1%20OR%201=1/statut (resp) | 400/404 | 404 | 404 | OK | preuves-securite.md §5, ligne T13 |
| T14 | Injection SQL dans l'e-mail de connexion | Requêtes préparées | email = ' OR '1'='1' --  | 401 | 401 | 401 | OK | preuves-securite.md §5, ligne T14 |
| T15 | « 1 OR 1=1 » comme entrepriseId (corps JSON) | Validation côté serveur | arafat : POST /api/demandes {entrepriseId:'1 OR 1=1'} | 400 | 400 | 400 | OK | preuves-securite.md §5, ligne T15 |
| T16 | « 2 OR 1=1 » comme encadreurId (corps JSON) | Validation côté serveur | resp : PATCH /api/demandes/3/encadreur {encadreurId:'2 OR 1=1'} | 400 | 200 | 400 | Corrigé | preuves-securite.md §5, ligne T16 |
| T17 | Demande inexistante | Ressource absente = 404 | resp : PATCH /api/demandes/999/statut VALIDEE | 404 | 404 | 404 | OK | preuves-securite.md §3, ligne T17 |
| T18 | Refus sans motif (champ absent) | Motif obligatoire | resp : PATCH /api/demandes/2/statut {statut:REFUSEE} | 400 | 400 | 400 | OK | preuves-securite.md §3, ligne T18 |
| T19 | Refus avec motif vide (espaces) | Motif obligatoire | resp : {statut:REFUSEE, motif:'   '} | 400 | 400 | 400 | OK | preuves-securite.md §3, ligne T19 |
| T20 | Validation (première fois) SOUMISE -> VALIDEE | Transition autorisée | resp : PATCH /api/demandes/2/statut VALIDEE | 200 | 200 | 200 | OK | preuves-securite.md §3, ligne T20 |
| T21 | Validation répétée | Conflit d'état = 409 | resp : PATCH /api/demandes/2/statut VALIDEE (2e fois) | 409 | 409 | 409 | OK | preuves-securite.md §3, ligne T21 |
| T22 | Date de soutenance invalide (31/02/2026 25:99) | Validation de la date | resp : POST /api/demandes/3/soutenance {dateHeure:'31/02/2026 25:99'} | 400 | 500 | 400 | Corrigé | preuves-securite.md §3, ligne T22 |
| T23 | Date inexistante au bon format (2026-02-31 10:00) | Validation de la date | resp : {dateHeure:'2026-02-31 10:00'} | 400 | 500 | 400 | Corrigé | preuves-securite.md §3, ligne T23 |
| T24 | Soutenance valide | Programmation d'une soutenance | resp : {dateHeure:'2026-12-15 09:00', salle:'Amphi A'} | 200 | 200 | 200 | OK | preuves-securite.md §3, ligne T24 |
| T25 | Salle trop longue (51 caractères) | Validation des longueurs | resp : salle = 'X' * 51 | 400 | 500 | 400 | Corrigé | preuves-securite.md §3, ligne T25 |
| T26 | Un étudiant tente d'affecter un encadreur | Contrôle des rôles | arafat : PATCH /api/demandes/3/encadreur | 403 | 403 | 403 | OK | preuves-securite.md §4, ligne T26 |
| T27 | Affectation sur un dossier non validé (BROUILLON) | Règle métier | resp : PATCH /api/demandes/1/encadreur | 400 | 400 | 400 | OK | preuves-securite.md §4, ligne T27 |
| T28 | Encadreur inexistant | Validation côté serveur | resp : encadreurId 99 | 400 | 400 | 400 | OK | preuves-securite.md §4, ligne T28 |
| T29 | Note hors bornes (25) | Note entre 0 et 20 | lea : PATCH /api/demandes/3/note {note:25} | 400 | 400 | 400 | OK | preuves-securite.md §4, ligne T29 |
| T30 | Note non numérique | Validation côté serveur | lea : {note:'abc'} | 400 | 400 | 400 | OK | preuves-securite.md §4, ligne T30 |
| T31 | Encadreur non affecté au dossier (Paul) | Cloisonnement des dossiers | paul : PATCH /api/demandes/3/note | 403 | 403 | 403 | OK | preuves-securite.md §4, ligne T31 |
| T32 | Étudiant tente de se noter | Contrôle des rôles | ozias : PATCH /api/demandes/3/note | 403 | 403 | 403 | OK | preuves-securite.md §4, ligne T32 |
| T33 | Note valide par l'encadreur affecté | Notation | lea : {note:14.5} | 200 | 200 | 200 | OK | preuves-securite.md §4, ligne T33 |
| T34 | Noter un dossier validé SANS soutenance programmée (dossier 2) | Cohérence métier | resp : PATCH /api/demandes/2/note {note:10} | 404/409 | 200 | 404 | Corrigé | preuves-securite.md §4, ligne T34 |
| T35 | Corps JSON invalide | Validation côté serveur | corps = '{pas du json' | 400 | 400 | 400 | OK | preuves-securite.md §8, ligne T35 |
| T36 | Statut inconnu (PERDU) | Validation côté serveur | resp : {statut:'PERDU'} | 400 | 400 | 400 | OK | preuves-securite.md §8, ligne T36 |
| T37 | Route inconnue | Routage | GET /api/n-importe-quoi | 404 | 404 | 404 | OK | preuves-securite.md §8, ligne T37 |
| T38 | Requête modifiante envoyée depuis un autre site (Origin: http://evil.test) | Protection CSRF | lea : PATCH note avec Origin étranger | 403 | 200 | 403 | Corrigé | preuves-securite.md §7, ligne T38 |
| T39 | Corps JSON posté en text/plain (formulaire forgé) avec Origin étranger | Protection CSRF | lea : Content-Type text/plain + Origin étranger | 403 | 200 | 403 | Corrigé | preuves-securite.md §7, ligne T39 |
| T40 | Requête légitime depuis la même origine (non-régression) | Protection CSRF | lea : PATCH note avec Origin = site | 200 | 200 | 200 | OK | preuves-securite.md §7, ligne T40 |
| T41 | Client sans en-tête Origin (Postman, curl) | Protection CSRF | lea : PATCH note sans Origin | 200 | 200 | 200 | OK | preuves-securite.md §7, ligne T41 |
| T42 | Déconnexion puis accès refusé | Fin de session | POST /api/deconnexion puis GET /api/moi | 401 | 401 | 401 | OK | preuves-securite.md §8, ligne T42 |
| T43 | Base injoignable : message neutre, aucun détail SQL | Pas de fuite d'information | serveur lancé avec un mauvais DB_PASS : POST /api/connexion | 500 | 500 | 500 | OK | preuves-securite.md §8, ligne T43 |
| T44 | Aucun mot de passe en clair : tous les hash sont du bcrypt (60 car., $2y$) | Mots de passe hachés | SELECT mot_de_passe_hash FROM utilisateur | 8/8 bcrypt | 8/8 bcrypt | 8/8 bcrypt | OK | preuves-securite.md §6, ligne T44 |
| T45 | Chaque compte a son propre hash (sel unique) | Mots de passe hachés | COUNT(DISTINCT mot_de_passe_hash) | 8 hash distincts | 1 hash distinct(s) pour 8 comptes | 8 hash distinct(s) pour 8 comptes | Corrigé | preuves-securite.md §6, ligne T45 |
| T46 | Identifiant renvoyé quand on modifie une soutenance existante (dossier 2) | Réponse fidèle à la base | resp : POST soutenance dossier 2 puis POST à nouveau | 200 | 200 | 200 | Corrigé | preuves-securite.md §8, ligne T46 |
| T47 | 40 échecs de connexion d'affilée, puis le bon mot de passe | Limitation des tentatives (anti force brute) | 40 × mauvais mot de passe sur resp.pedago, puis Demo1234! | 423/429 | 200 | 200 | Écart conservé | preuves-securite.md §8, ligne T47 |
| T48 | Compte désactivé pendant une session ouverte (actif = 0) | Révocation d'un accès | paul connecté, puis UPDATE utilisateur SET actif=0, puis GET /api/demandes | 401 | 200 | 200 | Écart conservé | preuves-securite.md §8, ligne T48 |

## Lecture rapide par exigence du TP

| Exigence du TP | Tests | Résultat final |
|---|---|---|
| Matrice d'au moins huit lignes | T01 à T48 (48 lignes) | fournie |
| Quatre cas limites : demande inexistante, refus sans motif, validation répétée, date invalide | T17, T18-T19, T20-T21, T22-T23 | 404, 400, 409, 400 |
| Autorisation : l'étudiant B lit le dossier de l'étudiant A (403) | T07 (+ T08, T09, T10) | 403 après correctif (404 avant : la route n'existait pas) |
| Chaîne « 1 OR 1=1 » comme identifiant | T12, T13, T15, T16 | refusée, aucune donnée renvoyée, aucune ligne modifiée |
| Mots de passe hachés en base | T44, T45 | 8/8 bcrypt, 8 hash distincts après correctif |
| Protection CSRF | T06, T38 à T41 | cookie SameSite=Lax + contrôle d'origine serveur |
