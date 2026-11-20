# TP8 — Encadrement et soutenances (Preuves et fonctionnalités)

**Groupe :** ALIDOU Arafat – AHOUANTCHEME Danik – DOVONOU Ozias (L3 SIL)

## 1. Fonctionnalités clés ajoutées au TP8

1. **Affectation d'un encadreur (`PATCH /api/demandes/{id}/encadreur`) :**
   - Réservé au rôle `RESPONSABLE` ou `SECRETARIAT`.
   - Applicable uniquement sur un stage au statut `VALIDEE`.
2. **Programmation de la soutenance (`POST /api/demandes/{id}/soutenance`) :**
   - Enregistrement de la date/heure et de la salle dans la table `soutenance`.
3. **Notation de la soutenance (`PATCH /api/demandes/{id}/note`) :**
   - Accessible à l'encadreur affecté au dossier ou au responsable.
   - Contrôle strict de la note (comprise entre 0 et 20).

## 2. Test rapide
```bash
cp .env.example .env
mariadb -u root stages < migrations/001_schema.sql
mariadb -u root stages < migrations/002_seed.sql
php -S localhost:8000 -t public public/index.php
```
