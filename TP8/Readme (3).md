# Projet Informatique 2 (L3 SIL) — TP8 : Plan de tests et audit de sécurité

**Groupe :** ALIDOU Arafat, AHOUANTCHEME Danik, DOVONOU Ozias (L3 SIL)  
**Application :** Gestion des demandes de conventions de stage, affectation des encadreurs et suivi des soutenances (API REST PHP 8 & MariaDB).

---

## 1. Structure du dépôt

```text
TP8/
├── migrations/
│   ├── 001_schema.sql               # Schéma relationnel (tables, clés étrangères, contraintes)
│   └── 002_seed.sql                 # Jeu d'essai initial (8 comptes avec hash bcrypt distincts)
├── public/
│   ├── index.php                    # Routeur principal, sessions sécurisées et contrôle CSRF (Origin)
│   └── app.html                     # Interface client (DOM sécurisé via textContent)
├── src/
│   ├── Controller/                  # Contrôleurs HTTP (AuthController, DemandeController)
│   ├── Service/                     # Logique métier et contrôles d'accès (AuthService, DemandeService)
│   └── Repository/                  # Accès base de données via requêtes préparées PDO
├── tests/
│   ├── api_demandes.php             # Tests unitaires/intégration des endpoints (SQLite en mémoire)
│   ├── securite_http.php            # Campagne complète de 48 tests HTTP & sécurité
│   └── tests_securite.sh            # Script Bash automatisé (recharge SQL + serveur + preuves TSV)
├── docs/                            # Livrables du TP8 (Matrice de tests, Fiche de revue, Preuves)
└── .env.example                     # Modèle de configuration (base de données, environnement)
```

---

## 2. Prérequis et installation

### Prérequis techniques
* **PHP** $\ge 8.2$ (avec extensions `pdo_mysql`, `pdo_sqlite`, `json`, `session`)
* **MariaDB** $\ge 10.11$ ou **MySQL** $\ge 8.0$ (mode strict `STRICT_TRANS_TABLES` activé)
* **Outils CLI :** `curl`, `mysql`, `php`, et **Git Bash** (sous Windows/XAMPP) pour exécuter `tests_securite.sh`

### Configuration de l'environnement
1. Copier le fichier d'exemple de configuration :
   ```bash
   cp .env.example .env
   ```
2. Renseigner les paramètres de connexion à MariaDB dans `.env` (`DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=stages`, `DB_USER=stages_app`, `DB_PASSWORD=...`).

### Initialisation de la base de données
Importer le schéma puis le jeu d'essai :
```powershell
C:\xampp\mysql\bin\mysql.exe -u root < migrations\001_schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < migrations\002_seed.sql
```

### Démarrage du serveur local
```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```
L'interface est accessible sur `http://127.0.0.1:8000/app.html` et l'API sur `http://127.0.0.1:8000/api/...`.

---

## 3. Comptes de démonstration

Tous les comptes du jeu d'essai partagent le mot de passe de démonstration `Demo1234!`, stocké uniquement sous forme d'empreinte **bcrypt** (`$2y$10$`, 60 caractères, sel unique par compte) :

| Rôle | Nom complet | E-mail | ID associé |
| :--- | :--- | :--- | :--- |
| `ETUDIANT` | Arafat ALIDOU | `arafat.alidou@univ.test` | `etudiant_id = 1` |
| `ETUDIANT` | Danik AHOUANTCHEME | `danik.ahouantcheme@univ.test` | `etudiant_id = 2` |
| `ETUDIANT` | Ozias DOVONOU | `ozias.dovonou@univ.test` | `etudiant_id = 3` |
| `SECRETARIAT` | Secrétariat Pédagogique | `secretariat@univ.test` | — |
| `RESPONSABLE` | Bertin TOSSOU | `resp.pedago@univ.test` | — |
| `ENCADREUR` | Léa HOUNKPATI | `lea.hounkpati@univ.test` | `encadreur_id = 1` |
| `ENCADREUR` | Paul SOSSOU | `paul.sossou@univ.test` | `encadreur_id = 2` |
| `ADMIN` | Administrateur | `admin@univ.test` | — |

---

## 4. Mesures de sécurité implémentées

* **Authentification et sessions :**
  * Hachage des mots de passe avec `password_hash(..., PASSWORD_BCRYPT)` (`$2y$`, coût `10`) et vérification via `password_verify()`.
  * Régénération de l'identifiant de session à la connexion (`session_regenerate_id(true)`).
  * Cookie de session configuré avec `HttpOnly` et `SameSite=Lax`.
  * Messages d'erreur d'authentification neutres et identiques (`401 Identifiants invalides.`) pour éviter l'énumération des comptes.
* **Contrôle d'accès (RBAC & isolation par objet) :**
  * Vérification systématique du rôle et de la propriété côté serveur (`etudiant_id` ou `encadreur_id` en session comparé à l'enregistrement ciblé).
  * Un étudiant ne peut consulter ou modifier que ses propres dossiers (`403 Forbidden` sur les dossiers d'un tiers).
* **Protection contre les injections SQL et XSS :**
  * Utilisation exclusive de requêtes préparées PDO (`PDO::ATTR_EMULATE_PREPARES => false`).
  * Validation stricte des identifiants entiers, des longueurs de chaînes et des dates calendaires avant toute requête.
  * Insertion côté frontend uniquement via `textContent` (aucun `innerHTML`).
* **Protection CSRF :**
  * Combinaison du cookie `SameSite=Lax` et d'une vérification côté serveur de l'en-tête `Origin` sur toutes les méthodes modifiant l'état (`POST`, `PUT`, `PATCH`, `DELETE`), renvoyant `403 Forbidden` en cas d'origine externe non autorisée.

---

## 5. Exécution des tests et génération des preuves

### 1. Campagne complète HTTP & Sécurité (PHP — 48 tests)
```powershell
php tests/securite_http.php http://127.0.0.1:8000
```
*Bilan sur le code corrigé : **46/48 tests conformes** (les 2 écarts résiduels T47 sur la limitation de débit et T48 sur l'invalidation immédiate de session après désactivation sont documentés dans la fiche de revue de code).*

### 2. Script automatisé avec génération des preuves TSV (Git Bash / Linux)
```bash
cd tests
chmod +x tests_securite.sh
./tests_securite.sh
```
Ce script réinitialise automatiquement la base, lance un serveur isolé sur le port `8086`, exécute les scénarios critiques et génère :
* `tests/preuves/sorties.tsv` : détail ligne par ligne des requêtes, statuts attendus/obtenus et réponses.
* `tests/preuves/hachages.tsv` : audit des empreintes bcrypt en base.
* `tests/preuves/resume.txt` : synthèse globale de conformité.

### 3. Tests d'intégration TP6 (SQLite en mémoire)
```powershell
php tests/api_demandes.php
```

### 4. Audit SQL manuel des empreintes bcrypt
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "USE stages; SELECT email, LEFT(mot_de_passe_hash, 7) AS algo_cout, CHAR_LENGTH(mot_de_passe_hash) AS longueur, LEFT(mot_de_passe_hash, 29) AS debut_hash FROM utilisateur;"
```

---

## 6. Livrables documentaires du TP8

* **`TP8_Matrice_de_Tests.pdf`** : Inventaire complet des 48 cas de tests (cas limites, autorisations, injections, CSRF, robustesse).
* **`TP8_Fiche_Revue_de_Code.pdf`** : Audit interne classé par criticité ($P \times I$), points conformes et grille de relecture croisée.
* **`TP8_Preuves_Tests_Audit_Securite.pdf`** : Comparatif avant/après correctifs, journaux serveur, requêtes SQL et captures d'exécution.