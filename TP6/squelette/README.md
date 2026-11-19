# TP6 — Backend et API des demandes (L3 SIL)

Implémentation complète de l'API REST en PHP 8 / PDO conformément à l'architecture en couches définie au TP4 et au schéma relationnel du TP3.

## Exécution rapide

```bash
cp .env.example .env
mariadb -u root -e "CREATE DATABASE IF NOT EXISTS stages CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -u root stages < migrations/001_schema.sql
mariadb -u root stages < migrations/002_seed.sql

php tests/api_demandes.php
php -S localhost:8000 -t public public/index.php
```
