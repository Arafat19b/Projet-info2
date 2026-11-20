# TP8 : fiche de revue de code

Groupe : ALIDOU Arafat, AHOUANTCHEME Danik, DOVONOU Ozias (L3 SIL).

> **À savoir avant de rendre.** Le TP demande d'échanger le dépôt avec un autre groupe et d'en relever quatre remarques. Cet échange reste à faire par vous. Cette fiche contient :
> - **Partie A** : la revue de *notre* dépôt, avec des remarques **prouvées par les tests** (`matrice-de-tests.md`, `preuves-securite.md`). Elle peut servir de modèle ou de revue en retour.
> - **Partie B** : la grille et la liste de contrôles à utiliser sur le dépôt de l'autre groupe.

## Échelle de classement

Criticité = probabilité × impact (comme au chapitre 1 du cours), chacun noté de 1 à 3.

| | 1 | 2 | 3 |
|---|---|---|---|
| Probabilité | rare | possible | probable |
| Impact | faible | moyen | élevé |

Classe : **Critique** 7 à 9, **Majeure** 4 à 6, **Mineure** 1 à 3.

## Partie A : revue de notre dépôt

### Les quatre remarques principales (classées)

| Rang | N° | Remarque | Où | Preuve | P × I | Classe | Statut | Recommandation |
|---|---|---|---|---|---|---|---|---|
| 1 | R1 | Aucune limitation des tentatives de connexion : 40 échecs d'affilée puis le bon mot de passe est accepté | `AuthController::login` | T47 : 40 × 401, puis 200 | 3 × 3 = **9** | **Critique** | non corrigé | compter les échecs par e-mail et par IP (table `tentative_connexion`), répondre 429 après 5 échecs pendant 15 minutes, journaliser les échecs, exiger des mots de passe robustes |
| 2 | R2 | Validation des entrées incomplète : date de soutenance, longueur de la salle, identifiant d'encadreur (`"2 OR 1=1"` tronqué en 2) | `DemandeService::programmerSoutenance`, `assignerEncadreur` | T16, T22, T23, T25 : 500 ou 200 au lieu de 400 | 3 × 2 = **6** | **Majeure** | **corrigé** | valider format, existence réelle de la date, longueur et type avant d'appeler le dépôt |
| 3 | R3 | Aucune protection CSRF côté serveur : seul le cookie `SameSite` protège | `public/index.php` | T38 : la note passe à 1.00 avec `Origin: http://evil.test` | 2 × 3 = **6** | **Majeure** | **corrigé** | contrôle de l'en-tête `Origin` (403) ; évolution possible : jeton synchronisé `X-CSRF-Token` |
| 4 | R4 | Session : on stocke un instantané de l'utilisateur et on ne le revérifie jamais, sans expiration | `AuthController::login` (`$_SESSION['utilisateur']`), `DemandeController::exigerConnexion` | T48 : compte mis à `actif = 0`, la session répond encore 200 | 2 × 3 = **6** | **Majeure** | non corrigé | ne stocker que l'identifiant, recharger l'utilisateur (`actif`, `role`) à chaque requête, expirer la session après inactivité (`session.gc_maxlifetime` + horodatage) |

### Autres remarques

| N° | Remarque | Où | Preuve | P × I | Classe | Statut | Recommandation |
|---|---|---|---|---|---|---|---|
| R5 | Impossible de lire un seul dossier : l'autorisation « étudiant B lit le dossier de A » n'était pas testable | `public/index.php` | T07, T08 : 404 pour tout le monde | 2 × 2 = 4 | Majeure | **corrigé** | route `GET /api/demandes/{id}` avec contrôle de propriété |
| R6 | La page de connexion affiche les comptes de test et le mot de passe | `public/app.html`, bloc `comptes-demo` | lecture du fichier | 2 × 2 = 4 | Majeure | non corrigé (utile en démonstration) | retirer avant déploiement, ou n'afficher que si `APP_ENV=dev` |
| R7 | Noter un dossier sans soutenance répond 200 (succès silencieux) | `DemandeService::noterSoutenance` | T34 | 2 × 2 = 4 | Majeure | **corrigé** | 404 si la soutenance n'existe pas |
| R8 | Course possible entre deux décisions : le statut est lu **avant** `beginTransaction()`, sans verrou | `DemandeService::changerStatut` | analyse du code, **non reproduite** | 1 × 2 = 2 | Mineure | non corrigé | ouvrir la transaction, lire avec `SELECT ... FOR UPDATE`, vérifier la transition, puis `UPDATE` et `INSERT` historique |
| R9 | Identifiant de soutenance faux renvoyé à la modification, et `catch (\Throwable)` qui sert de repli SQLite mais masque aussi les vraies erreurs MySQL | `DemandeRepository::programmerSoutenance` | T46 : `id = 1` renvoyé, `id = 2` en base | 3 × 1 = 3 | Mineure | **corrigé** (id) | isoler le code spécifique à SQLite ; ne pas attraper `Throwable` de façon générale |
| R10 | Code dupliqué dans le contrôleur : 5 lectures JSON identiques et 7 blocs `try/catch` identiques | `DemandeController` | `grep -c` : 5 et 7 | 3 × 1 = 3 | Mineure | non corrigé | méthodes privées `corpsJson()` et `repondre(callable $action)` |
| R11 | Les 8 comptes du jeu d'essai partagent le même hash (donc le même sel) | `migrations/002_seed.sql` | T45 : 1 hash distinct sur 8 | 1 × 2 = 2 | Mineure | **corrigé** | un hash par compte |
| R12 | Le test `tests/api_demandes.php` (TP6) ne passait plus sur le code TP8 : sa base en mémoire n'a pas la table `encadreur` | `tests/api_demandes.php` | `no such table: encadreur` | 3 × 1 = 3 | Mineure | **corrigé** | lancer tous les tests à chaque ajout de table |
| R13 | `403` pour un dossier interdit et `404` pour un dossier inexistant permettent de deviner les numéros existants | `DemandeService::lireDemande` | T07 et T17 | 1 × 1 = 1 | Mineure | accepté (imposé par le TP) | à revoir seulement si les numéros deviennent sensibles |

### Points conformes

- Requêtes préparées partout, aucune concaténation de valeur dans le SQL ; l'injection `' OR '1'='1` et `1 OR 1=1` est inoffensive (T12 à T15).
- Frontend : les données de la base sont insérées avec `textContent`, jamais `innerHTML` (pas de XSS stocké).
- Changement de statut et historique dans **une transaction** avec rollback.
- Mots de passe en bcrypt, `session_regenerate_id(true)` après connexion (T05), cookie `HttpOnly; SameSite=Lax` (T06), même message pour compte inconnu et mot de passe faux (T03).
- Erreurs 500 : message neutre au client, détail dans le journal (T43).

## Partie B : grille pour le dépôt de l'autre groupe

Dépôt relu : ______________________  Groupe : ______________________  Relecteurs : ______________________

| N° | Remarque | Fichier et ligne | Preuve (commande et sortie) | P | I | P × I | Classe | Recommandation |
|---|---|---|---|---|---|---|---|---|
| 1 | | | | | | | | |
| 2 | | | | | | | | |
| 3 | | | | | | | | |
| 4 | | | | | | | | |

### Contrôles rapides à exécuter (avec Git)

| Contrôle | Commande | À regarder |
|---|---|---|
| Secrets versionnés | `git ls-files \| findstr /i ".env"` (Windows) ou `git ls-files \| grep -i "\.env"` | seul `.env.example` doit apparaître |
| Mots de passe en clair | `git grep -n -i -E "password\|mot_de_passe\|mdp" -- "*.php" "*.sql"` | des `password_hash` / `password_verify`, pas de valeur en clair |
| Requêtes préparées | `git grep -n -E "->query\(\|->exec\(" -- "*.php"` | aucune variable utilisateur dans le texte SQL |
| XSS | `git grep -n -E "innerHTML\|insertAdjacentHTML\|document.write" -- "*.js" "*.html"` | aucune donnée de la base insérée avec `innerHTML` |
| Session | `git grep -n -E "session_regenerate_id\|samesite\|httponly" -- "*.php"` | régénération après connexion, cookie protégé |
| Messages d'erreur | chercher `getMessage()` renvoyé dans `json(...)` | pas de message d'exception renvoyé au client |
| Autorisation | connecté en étudiant B, ouvrir le dossier de A | attendu 403 |
| Chaîne `1 OR 1=1` | l'envoyer comme identifiant dans l'URL et dans le corps JSON | refusée, aucune donnée renvoyée |
| Cas limites | demande inexistante, refus sans motif, validation répétée, date invalide | 404, 400, 409, 400 (jamais 500) |
| CSRF | requête `PATCH` avec `Origin: http://evil.test` | attendu 403 |
| Installation | suivre le README sur un poste propre | le projet démarre sans aide |
| Tests | lancer les scripts du dossier `tests/` | tous verts, sorties conservées |
