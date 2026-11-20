# TP8 : plan de tests et audit de sécurité

| Livrable du TP | Fichier |
|---|---|
| Matrice de tests renseignée (48 lignes) | [matrice-de-tests.md](matrice-de-tests.md) |
| Preuves : cas limites, autorisation, « 1 OR 1=1 », hash, CSRF, sorties brutes | [preuves-securite.md](preuves-securite.md) |
| Fiche de revue de code (auto-revue + grille pour l'autre groupe) | [fiche-revue-code.md](fiche-revue-code.md) |
| Correctifs appliqués au code livré | [correctifs.patch](correctifs.patch) |

## Rejouer les tests sur votre poste

```bat
mysql -u root -p stages < migrations\001_schema.sql
mysql -u root -p stages < migrations\002_seed.sql
php -S localhost:8000 -t public public/index.php
```

Dans une seconde fenêtre :

```bat
php tests\securite_http.php http://localhost:8000 > preuves-poste.txt
php tests\api_demandes.php
```

Réimportez les deux migrations avant chaque relance : le banc modifie les données. Il refuse de démarrer si le jeu d'essai n'est plus intact.

Pour le test « base injoignable » (T43), lancez un second serveur avec un mauvais mot de passe SQL et passez son adresse en second argument :

```bat
set DB_PASS=mauvais
php -S localhost:8001 -t public public/index.php
```

```bat
php tests\securite_http.php http://localhost:8000 http://localhost:8001
```

## Correctifs appliqués (résumé)

| Fichier | Changement |
|---|---|
| `public/index.php` | contrôle d'origine CSRF ; route `GET /api/demandes/{id}` |
| `src/Controllers/DemandeController.php` | méthode `lire(int $id)` |
| `src/Services/DemandeService.php` | `lireDemande`, validation stricte de `encadreurId`, de la date et de la salle, 404 si pas de soutenance à noter |
| `src/Repositories/DemandeRepository.php` | `findDetailById`, `soutenanceExiste`, identifiant réel de soutenance |
| `migrations/002_seed.sql` | un hash bcrypt distinct par compte (mot de passe inchangé : `Demo1234!`) |
| `tests/api_demandes.php` | table `encadreur` ajoutée au schéma de test |
| `tests/securite_http.php` | nouveau : banc de test HTTP (48 tests) |

Non corrigés, laissés en recommandation : limitation des tentatives de connexion (R1), revalidation et expiration de la session (R4), comptes de démonstration affichés (R6), verrou lors du changement de statut (R8), duplication du contrôleur (R10).
