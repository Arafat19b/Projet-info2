# ADR-001 : Monolithe modulaire PHP 8 avec PDO, sans framework

- **Statut** : accepté
- **Date** : 2026-11-18

## Contexte

Équipe de trois ou quatre étudiants, budget de 25 heures, déploiement sur des postes Windows (PHP 8 et MySQL/MariaDB en local).
Il faut démarrer vite, rester lisible et pouvoir tout expliquer à l'oral.

## Décision

Une seule application PHP 8 organisée en couches (`public/` → `Controllers` → `Services` → `Repositories` → base), avec PDO et requêtes préparées, sans framework.

## Conséquences

- Positif : démarrage rapide, très peu de dépendances, installation reproductible avec `php -S`.
- Positif : le SQL est confiné aux dépôts ; les règles métier se testent sans base.
- Négatif : le routage et la validation sont écrits à la main et doivent être testés.
- Négatif : couplage plus fort entre modules qu'avec des services séparés.
