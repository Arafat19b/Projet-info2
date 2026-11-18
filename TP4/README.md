# Gestion des stages et des soutenances

Application web de gestion des stages universitaires (projet fil rouge, Projet informatique 2, L3 SIL).
Données fictives uniquement. **TP4 : squelette technique et contrat d'API.**

## Prérequis

- PHP 8.1 ou plus, avec l'extension `pdo_mysql`
- MySQL ou MariaDB
- Git

Vérification rapide : `php -v` et `php -m | findstr pdo_mysql` (Windows) ou `php -m | grep pdo_mysql` (Linux/macOS).

## Installation

```bash
git clone <adresse-du-depot> projet-stages
cd projet-stages
copy .env.example .env        # Linux/macOS : cp .env.example .env
```

Créez la base et un utilisateur dédié (jamais root), puis éditez `.env` avec ses identifiants :

```sql
CREATE DATABASE stages CHARACTER SET utf8mb4;
CREATE USER 'stages_app'@'localhost' IDENTIFIED BY 'changeme';
GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'localhost';
```

Importez le schéma et le jeu d'essai (en tant qu'administrateur, car `CREATE TABLE` n'est pas accordé à `stages_app`) :

```bash
mysql -u root -p stages < migrations/001_schema.sql
mysql -u root -p stages < migrations/002_seed.sql
```

## Lancer et vérifier

```bash
php -S localhost:8000 -t public public/index.php
```

Puis ouvrez <http://localhost:8000/api/sante> : réponse 200 `{"statut":"ok"}` si la base est joignable, 503 sinon.

## Tests

```bash
php tests/connexion.php   # connexion PDO réelle (nécessite la base)
php tests/sante.php       # service de santé : base OK / base en panne (sans MySQL)
```

## Structure

```
public/        index.php (point d'entrée et routage)
src/Controllers/   lit la requête, écrit la réponse
src/Services/      règles métier
src/Repositories/  seule couche qui écrit du SQL (PDO préparé)
config/        config.php (lit .env)
migrations/    001_schema.sql, 002_seed.sql
tests/         scripts de test
docs/          contrat-api.md, adr/ADR-001-...
```

## Routes (voir `docs/contrat-api.md`)

| Méthode | Chemin | État |
|---|---|---|
| GET | `/api/sante` | implémentée (TP4) |
| GET | `/api/demandes` | contrat figé, 501 jusqu'au TP6 |
| POST | `/api/demandes` | contrat figé, 501 jusqu'au TP6 |
| PATCH | `/api/demandes/{id}/statut` | contrat figé, 501 jusqu'au TP6 |

## Sécurité de la configuration

Le fichier `.env` n'est jamais versionné (`.gitignore`). Seul `.env.example`, avec des valeurs factices, est dans le dépôt.

## Décisions

- [ADR-001 : monolithe modulaire PHP 8 avec PDO](docs/adr/ADR-001-monolithe-modulaire-php.md)
