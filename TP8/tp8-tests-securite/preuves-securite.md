# TP8 : preuves des tests et de l'audit de sécurité

## 1. Environnement et méthode

- PHP 8.3.6, MariaDB 10.11.14 (`sql_mode` strict : `STRICT_TRANS_TABLES`), serveur `php -S`.
- Connexion applicative avec l'utilisateur dédié `stages_app` (SELECT, INSERT, UPDATE, DELETE), jamais root.
- Avant **chaque** exécution : réimport de `001_schema.sql` et `002_seed.sql`, pour partir du même état.
- Deux versions testées avec exactement les mêmes scénarios : le **code livré** (archive TP8) et le **code corrigé** (même arborescence, correctifs listés dans `correctifs.patch`).
- Un second serveur est lancé avec un mauvais mot de passe SQL pour tester le cas « base injoignable » (T43).
- Le scénario est rejouable sur votre poste : `php tests/securite_http.php http://localhost:8000` (voir l'en-tête du fichier). Mesures ci-dessous obtenues sous Linux : **rejouez-les sur votre poste (MySQL) et conservez votre propre sortie.**
- Interface web vérifiée dans un vrai navigateur (Chromium sans affichage) : connexion, vue responsable, requête modifiante same-origin (200) et lecture d'un dossier (200). Seule erreur console : le `401` attendu de `/api/moi` avant la connexion.

## 2. Bilan

| | Tests | OK | Écarts |
|---|---|---|---|
| Code livré | 48 | 35 | 13 |
| Code corrigé | 48 | 46 | 2 |

Écarts constatés sur le code livré, cause et correctif :

| Test | Constat | Cause | Correctif |
|---|---|---|---|
| T07, T08 | `GET /api/demandes/1` répond 404 pour tout le monde | la route de lecture d'un dossier n'existe pas : l'autorisation par objet ne peut pas être testée | route `GET /api/demandes/{id}` + `DemandeService::lireDemande` (propriétaire, encadreur affecté, responsable/secrétariat/admin) |
| T16 | `encadreurId = "2 OR 1=1"` accepté (200), encadreur 2 affecté | `(int) "2 OR 1=1"` vaut 2 : la valeur est tronquée au lieu d'être refusée | refus (400) de tout identifiant qui n'est pas un entier |
| T22, T23, T25 | date invalide ou salle de 51 caractères : **500** | aucune validation : la valeur arrive jusqu'à MySQL (SQLSTATE 22007 / 22001, visible dans le journal serveur) | validation stricte de la date (format + existence réelle) et de la longueur de la salle : 400 |
| T34 | noter un dossier sans soutenance répond 200 | `UPDATE soutenance` ne touche aucune ligne, l'API répond quand même « succès » | 404 « Aucune soutenance programmée » |
| T38, T39 | requête venant d'un autre site (`Origin: http://evil.test`) acceptée, la note passe à 1.00 | aucun contrôle CSRF côté serveur (seul le cookie SameSite protège) | contrôle de l'en-tête `Origin` dans `public/index.php` : 403 |
| T45 | les 8 comptes ont le même hash (1 hash distinct) | un seul hash recopié dans le jeu d'essai : même sel partout | 8 hash distincts dans `002_seed.sql` (même mot de passe de démonstration) |
| T46 | modifier une soutenance renvoie `id = 1` alors que la ligne a l'id 2 | `lastInsertId()` vaut 0 lors d'un `ON DUPLICATE KEY UPDATE`, le code retombe sur `1` | lecture de l'identifiant réel après l'écriture |
| T47 | 40 échecs de connexion, puis le bon mot de passe : 200 | aucune limitation des tentatives | **non corrigé** (voir fiche de revue, R1) |
| T48 | compte désactivé (`actif = 0`) : la session ouverte reste valide | la session stocke un instantané de l'utilisateur et ne le revérifie jamais | **non corrigé** (voir fiche de revue, R4) |

## 3. Les quatre cas limites imposés

#### T17 : Demande inexistante

- Données : resp : PATCH /api/demandes/999/statut VALIDEE
- Attendu : **404**
- Code livré : **404** `{"message":"Demande introuvable."}`
- Code corrigé : **404** `{"message":"Demande introuvable."}`

#### T18 : Refus sans motif (champ absent)

- Données : resp : PATCH /api/demandes/2/statut {statut:REFUSEE}
- Attendu : **400**
- Code livré : **400** `{"message":"Le motif de refus est obligatoire."}`
- Code corrigé : **400** `{"message":"Le motif de refus est obligatoire."}`

#### T19 : Refus avec motif vide (espaces)

- Données : resp : {statut:REFUSEE, motif:'   '}
- Attendu : **400**
- Code livré : **400** `{"message":"Le motif de refus est obligatoire."}`
- Code corrigé : **400** `{"message":"Le motif de refus est obligatoire."}`

#### T20 : Validation (première fois) SOUMISE -> VALIDEE

- Données : resp : PATCH /api/demandes/2/statut VALIDEE
- Attendu : **200**
- Code livré : **200** `{"id":2,"statut":"VALIDEE"}`
- Code corrigé : **200** `{"id":2,"statut":"VALIDEE"}`

#### T21 : Validation répétée

- Données : resp : PATCH /api/demandes/2/statut VALIDEE (2e fois)
- Attendu : **409**
- Code livré : **409** `{"message":"Transition interdite de VALIDEE vers VALIDEE."}`  (statut resté VALIDEE, historique inchangé (2 lignes))
- Code corrigé : **409** `{"message":"Transition interdite de VALIDEE vers VALIDEE."}`  (statut resté VALIDEE, historique inchangé (2 lignes))

#### T22 : Date de soutenance invalide (31/02/2026 25:99)

- Données : resp : POST /api/demandes/3/soutenance {dateHeure:'31/02/2026 25:99'}
- Attendu : **400**
- Code livré : **500** `{"message":"Erreur interne du serveur."}`
- Code corrigé : **400** `{"message":"Date\/heure invalide (format attendu : AAAA-MM-JJ HH:MM)."}`

#### T23 : Date inexistante au bon format (2026-02-31 10:00)

- Données : resp : {dateHeure:'2026-02-31 10:00'}
- Attendu : **400**
- Code livré : **500** `{"message":"Erreur interne du serveur."}`
- Code corrigé : **400** `{"message":"Date\/heure invalide (format attendu : AAAA-MM-JJ HH:MM)."}`

#### T25 : Salle trop longue (51 caractères)

- Données : resp : salle = 'X' * 51
- Attendu : **400**
- Code livré : **500** `{"message":"Erreur interne du serveur."}`
- Code corrigé : **400** `{"message":"Salle trop longue (50 caractères maximum)."}`

Journal serveur du code livré pour T22, T23 et T25 : le détail technique reste dans le journal, jamais dans la réponse au client.

```
[DemandeService::programmerSoutenance] SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect datetime value: '31/02/2026 25:99' for column `stages`.`soutenance`.`date_heure` at row 1
[DemandeService::programmerSoutenance] SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect datetime value: '2026-02-31 10:00' for column `stages`.`soutenance`.`date_heure` at row 1
[DemandeService::programmerSoutenance] SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'salle' at row 1
```

## 4. Autorisation

Règle appliquée côté serveur : l'identifiant de l'étudiant (`etudiant_id`) stocké en session est comparé à celui du dossier. Un étudiant ne lit et ne modifie que ses dossiers ; un encadreur ne note que les dossiers qui lui sont affectés ; seuls responsable et secrétariat décident, affectent et programment.

#### T07 : Étudiant B (Danik) lit le dossier de l'étudiant A (Arafat) : GET /api/demandes/1

- Données : danik -> dossier 1 (appartient à arafat)
- Attendu : **403**
- Code livré : **404** `{"message":"Route inconnue."}`
- Code corrigé : **403** `{"message":"Accès interdit sur ce dossier."}`

#### T08 : Le propriétaire lit son propre dossier : GET /api/demandes/1

- Données : arafat -> dossier 1
- Attendu : **200**
- Code livré : **404** `{"message":"Route inconnue."}`
- Code corrigé : **200** `{"id":1,"etudiant_id":1,"entreprise_id":1,"encadreur_id":null,"statut":"BROUILLON","motif_refus":null,"date_depot":null,"date_decision":null,"etudiant_nom":"ALI`

#### T09 : La liste d'un étudiant ne contient que ses dossiers

- Données : danik : GET /api/demandes
- Attendu : **200**
- Code livré : **200** `[{"id":2,"etudiant_id":2,"entreprise_id":1,"encadreur_id":null,"statut":"SOUMISE","motif_refus":null,"date_depot":"2026-11-02 10:00:00","date_decision":null,"en`  (ids reçus = [2] (attendu [2]))
- Code corrigé : **200** `[{"id":2,"etudiant_id":2,"entreprise_id":1,"encadreur_id":null,"statut":"SOUMISE","motif_refus":null,"date_depot":"2026-11-02 10:00:00","date_decision":null,"en`  (ids reçus = [2] (attendu [2]))

#### T10 : L'étudiant B modifie le dossier de l'étudiant A (soumission)

- Données : danik : PATCH /api/demandes/1/statut SOUMISE
- Attendu : **403**
- Code livré : **403** `{"message":"Accès interdit sur ce dossier."}`
- Code corrigé : **403** `{"message":"Accès interdit sur ce dossier."}`

#### T11 : Un étudiant tente de valider (rôle insuffisant)

- Données : danik : PATCH /api/demandes/2/statut VALIDEE
- Attendu : **403**
- Code livré : **403** `{"message":"Accès interdit : rôle responsable ou secrétariat requis."}`
- Code corrigé : **403** `{"message":"Accès interdit : rôle responsable ou secrétariat requis."}`

#### T26 : Un étudiant tente d'affecter un encadreur

- Données : arafat : PATCH /api/demandes/3/encadreur
- Attendu : **403**
- Code livré : **403** `{"message":"Accès interdit : rôle responsable requis."}`
- Code corrigé : **403** `{"message":"Accès interdit : rôle responsable requis."}`

#### T31 : Encadreur non affecté au dossier (Paul)

- Données : paul : PATCH /api/demandes/3/note
- Attendu : **403**
- Code livré : **403** `{"message":"Cet encadreur n'est pas affecté à ce stage."}`
- Code corrigé : **403** `{"message":"Cet encadreur n'est pas affecté à ce stage."}`

#### T32 : Étudiant tente de se noter

- Données : ozias : PATCH /api/demandes/3/note
- Attendu : **403**
- Code livré : **403** `{"message":"Accès interdit."}`
- Code corrigé : **403** `{"message":"Accès interdit."}`

#### T34 : Noter un dossier validé SANS soutenance programmée (dossier 2)

- Données : resp : PATCH /api/demandes/2/note {note:10}
- Attendu : **404/409**
- Code livré : **200** `{"id":2,"note":10}`
- Code corrigé : **404** `{"message":"Aucune soutenance programmée pour ce dossier."}`

Compromis connu : « dossier existant mais interdit » répond 403 alors que « dossier inexistant » répond 404, ce qui permet de deviner quels numéros existent. Le TP impose le 403 ; l'impact reste faible car les numéros sont de simples compteurs.

## 5. Injection SQL et chaîne « 1 OR 1=1 »

Protections en place : requêtes préparées partout (`PDO::ATTR_EMULATE_PREPARES => false`, aucune concaténation de valeur dans le SQL), identifiants d'URL limités à `\d+` par le routeur, validation de type côté service.

#### T12 : « 1 OR 1=1 » comme identifiant dans l'URL (GET)

- Données : GET /api/demandes/1%20OR%201=1 (danik)
- Attendu : **400/404**
- Code livré : **404** `{"message":"Route inconnue."}`  (aucune donnée de dossier renvoyée)
- Code corrigé : **404** `{"message":"Route inconnue."}`  (aucune donnée de dossier renvoyée)

#### T13 : « 1 OR 1=1 » comme identifiant dans l'URL (PATCH)

- Données : PATCH /api/demandes/1%20OR%201=1/statut (resp)
- Attendu : **400/404**
- Code livré : **404** `{"message":"Route inconnue."}`
- Code corrigé : **404** `{"message":"Route inconnue."}`

#### T14 : Injection SQL dans l'e-mail de connexion

- Données : email = ' OR '1'='1' -- 
- Attendu : **401**
- Code livré : **401** `{"message":"Identifiants invalides."}`
- Code corrigé : **401** `{"message":"Identifiants invalides."}`

#### T15 : « 1 OR 1=1 » comme entrepriseId (corps JSON)

- Données : arafat : POST /api/demandes {entrepriseId:'1 OR 1=1'}
- Attendu : **400**
- Code livré : **400** `{"message":"Veuillez sélectionner une entreprise valide."}`
- Code corrigé : **400** `{"message":"Veuillez sélectionner une entreprise valide."}`

#### T16 : « 2 OR 1=1 » comme encadreurId (corps JSON)

- Données : resp : PATCH /api/demandes/3/encadreur {encadreurId:'2 OR 1=1'}
- Attendu : **400**
- Code livré : **200** `{"id":3,"encadreur_id":2}`  (encadreur_id en base après l'appel = 2 (attendu 1 : valeur inchangée))
- Code corrigé : **400** `{"message":"Identifiant d'encadreur invalide."}`  (encadreur_id en base après l'appel = 1 (attendu 1 : valeur inchangée))

Conclusion : aucune injection SQL possible (T12 à T15 refusées, T14 : l'e-mail `' OR '1'='1' --` est traité comme du texte). T16 montrait une validation trop permissive (pas une injection : la valeur était convertie en entier avant d'atteindre la base), corrigée.

## 6. Mots de passe hachés en base

Requête exécutée : `SELECT email, LEFT(mot_de_passe_hash,7) AS algo_cout, LENGTH(mot_de_passe_hash) AS longueur, LEFT(mot_de_passe_hash,29) AS debut_hash FROM utilisateur;`

**Code livré** :
```
email	algo_cout	longueur	debut_hash
arafat.alidou@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
danik.ahouantcheme@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
ozias.dovonou@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
secretariat@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
resp.pedago@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
lea.hounkpati@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
paul.sossou@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
admin@univ.test	$2y$10$	60	$2y$10$w8vj0PJ5HJkVug46z0gCge
```
```
comptes	hash_distincts	bcrypt	en_clair
8	1	8	0
```

**Code corrigé** :
```
email	algo_cout	longueur	debut_hash
arafat.alidou@univ.test	$2y$10$	60	$2y$10$a/k1DdfSixn19w1w/NUHG.
danik.ahouantcheme@univ.test	$2y$10$	60	$2y$10$xXFFf65O5uPUd8v498WIyO
ozias.dovonou@univ.test	$2y$10$	60	$2y$10$pInU5W/oaEVuJslHh7f18O
secretariat@univ.test	$2y$10$	60	$2y$10$JHtWnizNalWpkwC3pRy5LO
resp.pedago@univ.test	$2y$10$	60	$2y$10$j6ySdr5uF.q2cmAiuJexOu
lea.hounkpati@univ.test	$2y$10$	60	$2y$10$nfTzfbinGYbl2UXHeS9CPe
paul.sossou@univ.test	$2y$10$	60	$2y$10$O5.2P6WB8jbHcDZ1hDhki.
admin@univ.test	$2y$10$	60	$2y$10$msP5lyloGhZg.aOIr6V6X.
```
```
comptes	hash_distincts	bcrypt	en_clair
8	8	8	0
```

- Tous les hash sont du **bcrypt** (`$2y$`, coût 10, 60 caractères) : aucun mot de passe en clair en base (`en_clair = 0`).
- Vérification avec `password_verify` dans `AuthService::authentifier` ; le hash n'est jamais renvoyé au client (T01) ; le mot de passe n'est écrit dans aucun `error_log`.
- Avant correctif, les 8 comptes partageaient **le même hash** donc le même sel (T45). Ce n'est pas exploitable ici (un seul mot de passe de démonstration) mais ce serait une faute en production : deux utilisateurs ayant le même mot de passe doivent avoir des hash différents.
- Réserve : la page de connexion affiche le mot de passe de démonstration (`public/app.html`, bloc « Comptes de test »). Acceptable en démonstration, à retirer avant tout déploiement réel (voir R6 de la fiche de revue).

## 7. Protection CSRF retenue

**Menace.** Une page d'un autre site pousse le navigateur d'un utilisateur connecté à envoyer, avec son cookie de session, une requête qui modifie des données (valider un dossier, noter une soutenance).

**Protection retenue : trois couches.**

1. **Cookie de session `HttpOnly; SameSite=Lax`** (T06) : les navigateurs n'envoient pas ce cookie sur une requête `POST`/`PATCH` déclenchée depuis un autre site, et JavaScript ne peut pas le lire.
2. **Contrôle d'origine côté serveur** (`public/index.php`) : toute requête `POST`, `PUT`, `PATCH` ou `DELETE` vers `/api/` qui porte un en-tête `Origin` différent du site est refusée en **403** (T38, T39). Les requêtes du site lui-même passent (T40), ainsi que les outils sans en-tête `Origin` comme Postman ou curl (T41), qui ne sont pas exposés au CSRF (il n'y a pas de navigateur de victime).
3. **Aucune action sensible en `GET`** : toutes les routes qui modifient des données sont des `POST` ou `PATCH`.

**Limites assumées.** (a) Le contrôle d'origine suppose que `Host` est fiable, ce qui demande une configuration correcte derrière un proxy. (b) `SameSite=Lax` ne protège pas contre un site « frère » du même domaine. (c) Une évolution possible, indépendante des en-têtes : un **jeton synchronisé** (jeton aléatoire en session, renvoyé dans un en-tête `X-CSRF-Token` et vérifié avec `hash_equals`). Il n'a pas été retenu pour ne pas modifier le client.

**Précision sur T38.** Le banc de test envoie lui-même le cookie, ce qui simule un navigateur qui l'aurait transmis. Sur le code livré, la requête forgée **modifie réellement la base** (la note passe à 1.00) : seul le cookie `SameSite` protégeait. Sur le code corrigé, le serveur refuse lui-même.

#### T06 : Cookie de session : HttpOnly et SameSite

- Données : en-tête Set-Cookie du login
- Attendu : **200**
- Code livré : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`
- Code corrigé : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`

#### T38 : Requête modifiante envoyée depuis un autre site (Origin: http://evil.test)

- Données : lea : PATCH note avec Origin étranger
- Attendu : **403**
- Code livré : **200** `{"id":3,"note":1}`  (note en base = 1.00 (14.50 attendu : non modifiée))
- Code corrigé : **403** `{"message":"Origine non autorisée."}`  (note en base = 14.50 (14.50 attendu : non modifiée))

#### T39 : Corps JSON posté en text/plain (formulaire forgé) avec Origin étranger

- Données : lea : Content-Type text/plain + Origin étranger
- Attendu : **403**
- Code livré : **200** `{"id":3,"note":2}`
- Code corrigé : **403** `{"message":"Origine non autorisée."}`

#### T40 : Requête légitime depuis la même origine (non-régression)

- Données : lea : PATCH note avec Origin = site
- Attendu : **200**
- Code livré : **200** `{"id":3,"note":15}`
- Code corrigé : **200** `{"id":3,"note":15}`

#### T41 : Client sans en-tête Origin (Postman, curl)

- Données : lea : PATCH note sans Origin
- Attendu : **200**
- Code livré : **200** `{"id":3,"note":15}`
- Code corrigé : **200** `{"id":3,"note":15}`

Le cookie renvoyé au login : `PHPSESSID=9g5l5k5oitudj1k2f5a538kvrd; path=/; HttpOnly; SameSite=Lax`

## 8. Autres contrôles

#### T01 : Connexion valide (responsable)

- Données : resp.pedago@univ.test / Demo1234!
- Attendu : **200**
- Code livré : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`  (le hash n'est pas renvoyé au client)
- Code corrigé : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`  (le hash n'est pas renvoyé au client)

#### T02 : Mauvais mot de passe

- Données : arafat.alidou@univ.test / faux
- Attendu : **401**
- Code livré : **401** `{"message":"Identifiants invalides."}`
- Code corrigé : **401** `{"message":"Identifiants invalides."}`

#### T03 : Compte inconnu : même message que T02

- Données : inconnu@univ.test / faux
- Attendu : **401**
- Code livré : **401** `{"message":"Identifiants invalides."}`  (message identique à T02)
- Code corrigé : **401** `{"message":"Identifiants invalides."}`  (message identique à T02)

#### T04 : Liste sans session

- Données : aucun cookie
- Attendu : **401**
- Code livré : **401** `{"message":"Non authentifié."}`
- Code corrigé : **401** `{"message":"Non authentifié."}`

#### T05 : Identifiant de session régénéré après connexion

- Données : cookie avant/après connexion
- Attendu : **200**
- Code livré : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`  (avant=hebsup3n… après=m95kt94r…)
- Code corrigé : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`  (avant=sk624u1c… après=9mvqj3cp…)

#### T35 : Corps JSON invalide

- Données : corps = '{pas du json'
- Attendu : **400**
- Code livré : **400** `{"message":"Corps JSON invalide."}`
- Code corrigé : **400** `{"message":"Corps JSON invalide."}`

#### T36 : Statut inconnu (PERDU)

- Données : resp : {statut:'PERDU'}
- Attendu : **400**
- Code livré : **400** `{"message":"Statut cible invalide."}`
- Code corrigé : **400** `{"message":"Statut cible invalide."}`

#### T37 : Route inconnue

- Données : GET /api/n-importe-quoi
- Attendu : **404**
- Code livré : **404** `{"message":"Route inconnue."}`
- Code corrigé : **404** `{"message":"Route inconnue."}`

#### T42 : Déconnexion puis accès refusé

- Données : POST /api/deconnexion puis GET /api/moi
- Attendu : **401**
- Code livré : **401** `{"message":"Non authentifié."}`
- Code corrigé : **401** `{"message":"Non authentifié."}`

#### T43 : Base injoignable : message neutre, aucun détail SQL

- Données : serveur lancé avec un mauvais DB_PASS : POST /api/connexion
- Attendu : **500**
- Code livré : **500** `{"message":"Erreur interne du serveur."}`  (aucun SQLSTATE / PDO dans la réponse)
- Code corrigé : **500** `{"message":"Erreur interne du serveur."}`  (aucun SQLSTATE / PDO dans la réponse)

#### T46 : Identifiant renvoyé quand on modifie une soutenance existante (dossier 2)

- Données : resp : POST soutenance dossier 2 puis POST à nouveau
- Attendu : **200**
- Code livré : **200** `{"id":1,"demande_id":2,"date_heure":"2026-12-02 11:00:00","salle":"B2"}`  (id renvoyé = 1, id en base = 2)
- Code corrigé : **200** `{"id":2,"demande_id":2,"date_heure":"2026-12-02 11:00:00","salle":"B2"}`  (id renvoyé = 2, id en base = 2)

#### T47 : 40 échecs de connexion d'affilée, puis le bon mot de passe

- Données : 40 × mauvais mot de passe sur resp.pedago, puis Demo1234!
- Attendu : **423/429**
- Code livré : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`
- Code corrigé : **200** `{"message":"Connexion réussie.","utilisateur":{"id":5,"email":"resp.pedago@univ.test","role":"RESPONSABLE","nom":"TOSSOU","prenom":"Bertin","actif":1,"etudiant_`

#### T48 : Compte désactivé pendant une session ouverte (actif = 0)

- Données : paul connecté, puis UPDATE utilisateur SET actif=0, puis GET /api/demandes
- Attendu : **401**
- Code livré : **200** `[]`
- Code corrigé : **200** `[]`

T47 : les 40 échecs répondent tous 401, en 2,4 secondes, puis le bon mot de passe est accepté : aucune limitation. T48 : la session reste valide après `actif = 0`.

## 9. Sorties brutes complètes

### Code livré
```
== A. Authentification et session
[OK   ] T01  Connexion valide (responsable)  -> attendu 200, obtenu 200  (le hash n'est pas renvoyé au client)
[OK   ] T02  Mauvais mot de passe  -> attendu 401, obtenu 401
[OK   ] T03  Compte inconnu : même message que T02  -> attendu 401, obtenu 401  (message identique à T02)
[OK   ] T04  Liste sans session  -> attendu 401, obtenu 401
[OK   ] T05  Identifiant de session régénéré après connexion  -> attendu 200, obtenu 200  (avant=hebsup3n… après=m95kt94r…)
[OK   ] T06  Cookie de session : HttpOnly et SameSite  -> attendu 200, obtenu 200
        cookie : PHPSESSID=ipg8eib2v0o4kktehkvdifs6fl; path=/; HttpOnly; SameSite=Lax
== B. Autorisation (isolation entre étudiants)
[ÉCART] T07  Étudiant B (Danik) lit le dossier de l'étudiant A (Arafat) : GET /api/demandes/1  -> attendu 403, obtenu 404
[ÉCART] T08  Le propriétaire lit son propre dossier : GET /api/demandes/1  -> attendu 200, obtenu 404
[OK   ] T09  La liste d'un étudiant ne contient que ses dossiers  -> attendu 200, obtenu 200  (ids reçus = [2] (attendu [2]))
[OK   ] T10  L'étudiant B modifie le dossier de l'étudiant A (soumission)  -> attendu 403, obtenu 403
[OK   ] T11  Un étudiant tente de valider (rôle insuffisant)  -> attendu 403, obtenu 403
== C. Injection SQL : « 1 OR 1=1 »
[OK   ] T12  « 1 OR 1=1 » comme identifiant dans l'URL (GET)  -> attendu 400/404, obtenu 404  (aucune donnée de dossier renvoyée)
[OK   ] T13  « 1 OR 1=1 » comme identifiant dans l'URL (PATCH)  -> attendu 400/404, obtenu 404
[OK   ] T14  Injection SQL dans l'e-mail de connexion  -> attendu 401, obtenu 401
[OK   ] T15  « 1 OR 1=1 » comme entrepriseId (corps JSON)  -> attendu 400, obtenu 400
[ÉCART] T16  « 2 OR 1=1 » comme encadreurId (corps JSON)  -> attendu 400, obtenu 200  (encadreur_id en base après l'appel = 2 (attendu 1 : valeur inchangée))
== D. Quatre cas limites imposés
[OK   ] T17  Demande inexistante  -> attendu 404, obtenu 404
[OK   ] T18  Refus sans motif (champ absent)  -> attendu 400, obtenu 400
[OK   ] T19  Refus avec motif vide (espaces)  -> attendu 400, obtenu 400
[OK   ] T20  Validation (première fois) SOUMISE -> VALIDEE  -> attendu 200, obtenu 200
[OK   ] T21  Validation répétée  -> attendu 409, obtenu 409  (statut resté VALIDEE, historique inchangé (2 lignes))
[ÉCART] T22  Date de soutenance invalide (31/02/2026 25:99)  -> attendu 400, obtenu 500
[ÉCART] T23  Date inexistante au bon format (2026-02-31 10:00)  -> attendu 400, obtenu 500
[OK   ] T24  Soutenance valide  -> attendu 200, obtenu 200
[ÉCART] T25  Salle trop longue (51 caractères)  -> attendu 400, obtenu 500
== E. Encadrement et notation
[OK   ] T26  Un étudiant tente d'affecter un encadreur  -> attendu 403, obtenu 403
[OK   ] T27  Affectation sur un dossier non validé (BROUILLON)  -> attendu 400, obtenu 400
[OK   ] T28  Encadreur inexistant  -> attendu 400, obtenu 400
[OK   ] T29  Note hors bornes (25)  -> attendu 400, obtenu 400
[OK   ] T30  Note non numérique  -> attendu 400, obtenu 400
[OK   ] T31  Encadreur non affecté au dossier (Paul)  -> attendu 403, obtenu 403
[OK   ] T32  Étudiant tente de se noter  -> attendu 403, obtenu 403
[OK   ] T33  Note valide par l'encadreur affecté  -> attendu 200, obtenu 200  (note en base = 14.50)
[ÉCART] T34  Noter un dossier validé SANS soutenance programmée (dossier 2)  -> attendu 404/409, obtenu 200
== F. Robustesse des entrées
[OK   ] T35  Corps JSON invalide  -> attendu 400, obtenu 400
[OK   ] T36  Statut inconnu (PERDU)  -> attendu 400, obtenu 400
[OK   ] T37  Route inconnue  -> attendu 404, obtenu 404
== G. CSRF
[ÉCART] T38  Requête modifiante envoyée depuis un autre site (Origin: http://evil.test)  -> attendu 403, obtenu 200  (note en base = 1.00 (14.50 attendu : non modifiée))
[ÉCART] T39  Corps JSON posté en text/plain (formulaire forgé) avec Origin étranger  -> attendu 403, obtenu 200
[OK   ] T40  Requête légitime depuis la même origine (non-régression)  -> attendu 200, obtenu 200
[OK   ] T41  Client sans en-tête Origin (Postman, curl)  -> attendu 200, obtenu 200
== H. Déconnexion et erreurs serveur
[OK   ] T42  Déconnexion puis accès refusé  -> attendu 401, obtenu 401
[OK   ] T43  Base injoignable : message neutre, aucun détail SQL  -> attendu 500, obtenu 500  (aucun SQLSTATE / PDO dans la réponse)
== I. Mots de passe hachés en base
[OK   ] T44  hash bcrypt : 8/8, aucun en clair : True
[ÉCART] T45  hash distincts : 1/8
== J. Autres contrôles relevés pendant la revue
[ÉCART] T46  Identifiant renvoyé quand on modifie une soutenance existante (dossier 2)  -> attendu 200, obtenu 200  (id renvoyé = 1, id en base = 2)
[ÉCART] T47  40 échecs de connexion d'affilée, puis le bon mot de passe  -> attendu 423/429, obtenu 200
[ÉCART] T48  Compte désactivé pendant une session ouverte (actif = 0)  -> attendu 401, obtenu 200

BILAN : 35 OK, 13 écart(s), 48 tests
```

### Code corrigé
```
== A. Authentification et session
[OK   ] T01  Connexion valide (responsable)  -> attendu 200, obtenu 200  (le hash n'est pas renvoyé au client)
[OK   ] T02  Mauvais mot de passe  -> attendu 401, obtenu 401
[OK   ] T03  Compte inconnu : même message que T02  -> attendu 401, obtenu 401  (message identique à T02)
[OK   ] T04  Liste sans session  -> attendu 401, obtenu 401
[OK   ] T05  Identifiant de session régénéré après connexion  -> attendu 200, obtenu 200  (avant=sk624u1c… après=9mvqj3cp…)
[OK   ] T06  Cookie de session : HttpOnly et SameSite  -> attendu 200, obtenu 200
        cookie : PHPSESSID=9g5l5k5oitudj1k2f5a538kvrd; path=/; HttpOnly; SameSite=Lax
== B. Autorisation (isolation entre étudiants)
[OK   ] T07  Étudiant B (Danik) lit le dossier de l'étudiant A (Arafat) : GET /api/demandes/1  -> attendu 403, obtenu 403
[OK   ] T08  Le propriétaire lit son propre dossier : GET /api/demandes/1  -> attendu 200, obtenu 200
[OK   ] T09  La liste d'un étudiant ne contient que ses dossiers  -> attendu 200, obtenu 200  (ids reçus = [2] (attendu [2]))
[OK   ] T10  L'étudiant B modifie le dossier de l'étudiant A (soumission)  -> attendu 403, obtenu 403
[OK   ] T11  Un étudiant tente de valider (rôle insuffisant)  -> attendu 403, obtenu 403
== C. Injection SQL : « 1 OR 1=1 »
[OK   ] T12  « 1 OR 1=1 » comme identifiant dans l'URL (GET)  -> attendu 400/404, obtenu 404  (aucune donnée de dossier renvoyée)
[OK   ] T13  « 1 OR 1=1 » comme identifiant dans l'URL (PATCH)  -> attendu 400/404, obtenu 404
[OK   ] T14  Injection SQL dans l'e-mail de connexion  -> attendu 401, obtenu 401
[OK   ] T15  « 1 OR 1=1 » comme entrepriseId (corps JSON)  -> attendu 400, obtenu 400
[OK   ] T16  « 2 OR 1=1 » comme encadreurId (corps JSON)  -> attendu 400, obtenu 400  (encadreur_id en base après l'appel = 1 (attendu 1 : valeur inchangée))
== D. Quatre cas limites imposés
[OK   ] T17  Demande inexistante  -> attendu 404, obtenu 404
[OK   ] T18  Refus sans motif (champ absent)  -> attendu 400, obtenu 400
[OK   ] T19  Refus avec motif vide (espaces)  -> attendu 400, obtenu 400
[OK   ] T20  Validation (première fois) SOUMISE -> VALIDEE  -> attendu 200, obtenu 200
[OK   ] T21  Validation répétée  -> attendu 409, obtenu 409  (statut resté VALIDEE, historique inchangé (2 lignes))
[OK   ] T22  Date de soutenance invalide (31/02/2026 25:99)  -> attendu 400, obtenu 400
[OK   ] T23  Date inexistante au bon format (2026-02-31 10:00)  -> attendu 400, obtenu 400
[OK   ] T24  Soutenance valide  -> attendu 200, obtenu 200
[OK   ] T25  Salle trop longue (51 caractères)  -> attendu 400, obtenu 400
== E. Encadrement et notation
[OK   ] T26  Un étudiant tente d'affecter un encadreur  -> attendu 403, obtenu 403
[OK   ] T27  Affectation sur un dossier non validé (BROUILLON)  -> attendu 400, obtenu 400
[OK   ] T28  Encadreur inexistant  -> attendu 400, obtenu 400
[OK   ] T29  Note hors bornes (25)  -> attendu 400, obtenu 400
[OK   ] T30  Note non numérique  -> attendu 400, obtenu 400
[OK   ] T31  Encadreur non affecté au dossier (Paul)  -> attendu 403, obtenu 403
[OK   ] T32  Étudiant tente de se noter  -> attendu 403, obtenu 403
[OK   ] T33  Note valide par l'encadreur affecté  -> attendu 200, obtenu 200  (note en base = 14.50)
[OK   ] T34  Noter un dossier validé SANS soutenance programmée (dossier 2)  -> attendu 404/409, obtenu 404
== F. Robustesse des entrées
[OK   ] T35  Corps JSON invalide  -> attendu 400, obtenu 400
[OK   ] T36  Statut inconnu (PERDU)  -> attendu 400, obtenu 400
[OK   ] T37  Route inconnue  -> attendu 404, obtenu 404
== G. CSRF
[OK   ] T38  Requête modifiante envoyée depuis un autre site (Origin: http://evil.test)  -> attendu 403, obtenu 403  (note en base = 14.50 (14.50 attendu : non modifiée))
[OK   ] T39  Corps JSON posté en text/plain (formulaire forgé) avec Origin étranger  -> attendu 403, obtenu 403
[OK   ] T40  Requête légitime depuis la même origine (non-régression)  -> attendu 200, obtenu 200
[OK   ] T41  Client sans en-tête Origin (Postman, curl)  -> attendu 200, obtenu 200
== H. Déconnexion et erreurs serveur
[OK   ] T42  Déconnexion puis accès refusé  -> attendu 401, obtenu 401
[OK   ] T43  Base injoignable : message neutre, aucun détail SQL  -> attendu 500, obtenu 500  (aucun SQLSTATE / PDO dans la réponse)
== I. Mots de passe hachés en base
[OK   ] T44  hash bcrypt : 8/8, aucun en clair : True
[OK   ] T45  hash distincts : 8/8
== J. Autres contrôles relevés pendant la revue
[OK   ] T46  Identifiant renvoyé quand on modifie une soutenance existante (dossier 2)  -> attendu 200, obtenu 200  (id renvoyé = 2, id en base = 2)
[ÉCART] T47  40 échecs de connexion d'affilée, puis le bon mot de passe  -> attendu 423/429, obtenu 200
[ÉCART] T48  Compte désactivé pendant une session ouverte (actif = 0)  -> attendu 401, obtenu 200

BILAN : 46 OK, 2 écart(s), 48 tests
```

### Test du groupe `tests/api_demandes.php` (TP6)

Sur le code TP8 il échouait (`no such table: encadreur`) parce que son schéma SQLite en mémoire ne créait pas la table `encadreur`, ajoutée au TP8. Une fois la table ajoutée au schéma de test, il passe sur le code livré **et** sur le code corrigé (`Résultat global : TOUS LES TESTS SONT PASSÉS`).

