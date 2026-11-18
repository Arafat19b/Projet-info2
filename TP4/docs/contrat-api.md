# Contrat d'API : gestion des stages

Écrit avant le code et partagé entre backend et frontend. Format : JSON (UTF-8).
Tous les messages d'erreur sont neutres : le détail technique va dans le journal, jamais dans la réponse.

## Vue d'ensemble

| Méthode | Chemin | Rôle autorisé | Succès | Erreurs |
|---|---|---|---|---|
| GET | `/api/sante` | Public | 200 | 503 |
| GET | `/api/demandes` | Responsable, secrétariat | 200 | 401, 403 |
| POST | `/api/demandes` | Étudiant | 201 | 400, 401 |
| PATCH | `/api/demandes/{id}/statut` | Responsable | 200 | 400, 403, 404, 409 |

## GET /api/sante

Répond 200 si la base est joignable, 503 sinon.

- Corps de réponse : `{"statut": "ok"}` ou `{"statut": "base injoignable"}`

## GET /api/demandes

Liste les demandes (responsable, secrétariat).

- Corps de requête : aucun
- 200 : `[{"id": 1, "etudiant_id": 1, "entreprise_id": 1, "statut": "SOUMISE", "motif_refus": null}]`
- 401 : non authentifié (session absente ou expirée)
- 403 : rôle insuffisant (ex. un étudiant)

## POST /api/demandes

Crée une demande pour l'étudiant connecté.

- Corps : `{"entrepriseId": 3}`
- 201 : `{"id": 12, "statut": "BROUILLON"}`
- 400 : données invalides (`entrepriseId` absent, non entier ou inexistant)
- 401 : non authentifié

## PATCH /api/demandes/{id}/statut

Change le statut d'une demande. Seules les transitions autorisées sont acceptées :

`BROUILLON → SOUMISE`, `SOUMISE → VALIDEE`, `SOUMISE → REFUSEE` (motif obligatoire), `REFUSEE → BROUILLON`.

- Corps : `{"statut": "VALIDEE"}` ou `{"statut": "REFUSEE", "motif": "Convention incomplète"}`
- 200 : `{"id": 7, "statut": "VALIDEE"}`
- 400 : statut inconnu ou motif absent pour un refus
- 403 : rôle insuffisant (ex. un étudiant tente de valider)
- 404 : demande inexistante (ex. `/api/demandes/999/statut`)
- 409 : transition interdite (ex. valider une demande déjà validée), statut inchangé

## Codes HTTP utilisés

| Code | Signification |
|---|---|
| 200 / 201 | Succès / création |
| 400 | Données invalides |
| 401 | Non authentifié |
| 403 | Accès interdit |
| 404 | Ressource absente |
| 409 | Conflit d'état |
| 501 | Route au contrat, pas encore implémentée (TP6) |
| 503 | Base injoignable |
