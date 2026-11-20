# Projet informatique 2 — Gestion des stages et soutenances

Application web de gestion des stages et des soutenances : dépôt des dossiers par les étudiants, validation ou refus motivé, affectation d'un encadreur et programmation des soutenances.

| | |
|---|---|
| **Cours** | Projet informatique 2 — L3 SIL (10 chapitres, 25 h, 10 TP) |
| **Groupe** | ALIDOU Arafat, AHOUANTCHEME Danik, DOVONOU Ozias (groupe de 3) |
| **Environnement** | PHP 8.2+, MySQL / MariaDB 10.11+, Git — données fictives uniquement |

## Organisation du dépôt

| Dossier / Fichier | Contenu |
|---|---|
| `TP1/` | Fiche de cadrage du projet (Problème, indicateurs, parties prenantes, périmètre MVP) |
| `TP2/` | Exigences fonctionnelles et non fonctionnelles (MOSCOW), user stories et matrice de traçabilité (`T-01` à `T-11`) |
| `TP3/` | Modèle UML (Cas d'utilisation et Classes avec `Soutenance`), scripts SQL (`001_schema.sql`, `002_seed.sql`, `003_verification.sql`) et rapport de modélisation |
| `TP4/` à `TP7/` | Architecture en couches (`Controller`, `Service`, `Repository`), API REST PHP, interface client (`app.html`) et tests d'intégration (`api_demandes.php`) |
| `TP8/` | Plan de tests et audit de sécurité : code corrigé, scripts automatisés (`tests/securite_http.php`, `tests/tests_securite.sh`), matrice de tests, fiche de revue de code et dossier de preuves |

## Branches

`main` reste toujours exécutable. Le travail se fait sur des branches courtes fusionnées après relecture du diff.

| Branche | Contenu |
|---|---|
| `main` | Squelette du dépôt, application fonctionnelle et documentation globale |
| `tp1` | Fiche de cadrage initiale |
| `tp2` | Matrice de traçabilité et exigences |
| `tp3` | Modélisation UML complète et scripts SQL v2 (avec table `soutenance`) |
| `tp4` – `tp7` | Implémentation de l'API REST, interface web et premiers tests fonctionnels |
| `tp8` | Durcissement sécurité (bcrypt, CSRF `Origin` + `SameSite=Lax`, validation stricte) et campagne d'audit |

## Périmètre retenu (v2)

**Inclus** : authentification et rôles (`EF-01`), dépôt d'une demande de stage (`EF-02`), validation ou refus motivé (`EF-03`), affectation d'un encadreur (`EF-04`), programmation et notation de la soutenance (`EF-05`), consultation de l'état des demandes (`EF-06`), recherche de dossiers (`EF-07`), journal des décisions, gestion de la table `Soutenance` et exigences de sécurité (`ENF-01`, `ENF-02`).

**Exclus** (reportés en v3) : paiement en ligne, notifications SMS, portail entreprise, convention signée électroniquement, messagerie interne.

## Démarrage rapide et tests (TP8)

1. **Initialiser la base de données (`stages`) :**
   ```bash
   mysql -u root < TP8/migrations/001_schema.sql
   mysql -u root < TP8/migrations/002_seed.sql
   ```
2. **Configurer l'environnement et démarrer le serveur PHP :**
   ```bash
   cp TP8/.env.example TP8/.env
   php -S 127.0.0.1:8000 -t TP8/public TP8/public/index.php
   ```
3. **Exécuter la campagne de tests de sécurité (46/48 conformes après correctifs) :**
   ```bash
   php TP8/tests/securite_http.php http://127.0.0.1:8000
   ```
   *(Ou via le script automatisé `cd TP8/tests && ./tests_securite.sh` qui génère les preuves dans `TP8/tests/preuves/`.)*

## État d'avancement

| TP | Livrable | Statut |
|---|---|---|
| TP1 | Fiche de cadrage (2 pages) | Fait |
| TP2 | Exigences (EF, ENF) et matrice de traçabilité | Fait |
| TP3 | Diagrammes UML (avec Soutenance), schéma SQL, jeu d'essai | Fait (v2) |
| TP4 à TP7 | Implémentation API REST PHP, frontend et tests d'intégration | Fait |
| TP8 | Plan de tests, revue de code, correctifs sécurité et dossier de preuves | Fait |
| TP9 à TP10 | Finalisation, déploiement et soutenance du projet | À venir |