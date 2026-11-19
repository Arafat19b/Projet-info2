# Preuves TP4

## Tests du service de santé (`php tests/sante.php`, sans MySQL)
```
OK    base joignable -> ok = true
OK    base en panne -> ok = false
OK    le message client ne contient aucun détail interne
```

## Route de santé avec base injoignable (serveur lancé, MySQL arrêté)
```
GET /api/sante -> 503 {"statut":"base injoignable"}
```

## À compléter sur ton poste (MySQL/MariaDB démarré)
Sortie de `php tests/connexion.php` :OK  connexion PDO établie (serveur : 10.4.32-MariaDB)

Le résultat de `GET /api/sante` : {"statut":"ok"}

## Justification
La route répond 503 et non une page blanche quand la base tombe : le détail de l'erreur PDO va au journal, jamais au client (chap. 6.5).
