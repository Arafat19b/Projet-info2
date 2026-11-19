# TP6 — API des demandes et preuves de tests

**Groupe :** ALIDOU Arafat – AHOUANTCHEME Danik – DOVONOU Ozias (L3 SIL)

## 1. Tableau des six cas HTTP testés

| Cas | Méthode et Route | Contexte / Données | Code attendu | Code observé | Preuve |
|---|---|---|---|---|---|
| **Succès** | `POST /api/demandes` & `PATCH /api/demandes/1/statut` | Création (`{"entrepriseId": 1}`) puis validation (`{"statut": "VALIDEE"}`) par le responsable | `201` / `200` | `201` / `200` | `php tests/api_demandes.php` + ligne ajoutée dans `historique` |
| **Données invalides** | `PATCH /api/demandes/1/statut` | Refus sans motif : `{"statut": "REFUSEE", "motif": ""}` | `400` | `400` | `{"message":"Le motif de refus est obligatoire."}` |
| **Non authentifié** | `GET /api/demandes` | Requête sans session active | `401` | `401` | `{"message":"Non authentifié."}` |
| **Accès interdit** | `PATCH /api/demandes/1/statut` | Étudiant connecté tentant `{"statut": "VALIDEE"}` | `403` | `403` | `{"message":"Accès interdit : rôle responsable requis."}` |
| **Introuvable** | `PATCH /api/demandes/999/statut` | Demande `999` inexistante | `404` | `404` | `{"message":"Demande introuvable."}` |
| **Conflit d'état** | `PATCH /api/demandes/1/statut` | Validation répétée sur une demande déjà `VALIDEE` | `409` | `409` | `{"message":"Transition interdite de VALIDEE vers VALIDEE."}` |
