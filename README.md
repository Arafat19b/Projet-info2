# Projet informatique 2 — Gestion des stages et soutenances

Application web de gestion des stages et des soutenances : dépôt des dossiers par les étudiants, validation ou refus motivé, affectation d'un encadreur et programmation des soutenances.

| | |
|---|---|
| **Cours** | Projet informatique 2 — L3 SIL (10 chapitres, 25 h, 10 TP) |
| **Groupe** | ALIDOU Arafat, AHOUANTCHEME Danik, DOVONOU Ozias (groupe de 3) |
| **Environnement** | PHP 8, MySQL / MariaDB, Git — données fictives uniquement |

## Organisation du dépôt

| Dossier / Fichier | Contenu |
|---|---|
| `TP1/` | Fiche de cadrage du projet (Problème, indicateurs, parties prenantes, périmètre MVP) |
| `TP2/` | Exigences fonctionnelles et non fonctionnelles (MOSCOW), user stories et matrice de traçabilité (`T-01` à `T-11`) |
| `TP3/` | Modèle UML (Cas d'utilisation et Classes avec `Soutenance`), scripts SQL (`001_schema.sql`, `002_seed.sql`, `003_verification.sql`) et rapport de modélisation |

## Branches

`main` reste toujours exécutable. Le travail se fait sur des branches courtes fusionnées après relecture du diff.

| Branche | Contenu |
|---|---|
| `main` | Squelette du dépôt et documentation globale |
| `tp1` | Fiche de cadrage initiale |
| `tp2` | Matrice de traçabilité et exigences |
| `tp3` | Modélisation UML complète et scripts SQL v2 (avec table `soutenance`) |

## Périmètre retenu (v2)

**Inclus** : authentification et rôles (`EF-01`), dépôt d'une demande de stage (`EF-02`), validation ou refus motivé (`EF-03`), affectation d'un encadreur (`EF-04`), programmation de la soutenance (`EF-05`), consultation de l'état des demandes (`EF-06`), recherche de dossiers (`EF-07`), journal des décisions et gestion de la table `Soutenance`.

**Exclus** (reportés en v3) : paiement en ligne, notifications SMS, portail entreprise, convention signée électroniquement, messagerie interne.

## État d'avancement

| TP | Livrable | Statut |
|---|---|---|
| TP1 | Fiche de cadrage (2 pages) | Fait |
| TP2 | Exigences (EF, ENF) et matrice de traçabilité | Fait |
| TP3 | Diagrammes UML (avec Soutenance), schéma SQL, jeu d'essai | Fait (v2) |
| TP4 à TP10 | Implémentation et tests unitaires | À venir |
