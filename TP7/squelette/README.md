# TP7 — Frontend accessible et intégration complète (L3 SIL)

Application Web complète reliant l'interface HTML/CSS/JS accessible (`public/app.html`, `public/style.css`, `public/app.js`) à l'API REST PHP 8 / PDO du TP6.

## Lancement

```bash
cp .env.example .env
mariadb -u root -e "CREATE DATABASE IF NOT EXISTS stages CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -u root stages < migrations/001_schema.sql
mariadb -u root stages < migrations/002_seed.sql

php -S localhost:8000 -t public public/index.php
```

Ouvrir ensuite **http://localhost:8000** dans le navigateur.
