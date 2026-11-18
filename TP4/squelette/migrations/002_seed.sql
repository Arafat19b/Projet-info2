-- Jeu d'essai fictif : 3 étudiants, 2 entreprises, 4 demandes, 1 soutenance
INSERT INTO etudiant (nom, prenom, email) VALUES
 ('ADJOVI', 'Koffi', 'koffi.adjovi@univ.test'),
 ('BELLO', 'Aicha', 'aicha.bello@univ.test'),
 ('DOSSOU', 'Marc', 'marc.dossou@univ.test');

INSERT INTO entreprise (nom, ville) VALUES
 ('Atelier Numerique', 'Porto-Novo'),
 ('Reseau Plus', 'Cotonou');

INSERT INTO demande_stage (etudiant_id, entreprise_id, statut, motif_refus) VALUES
 (1, 1, 'SOUMISE', NULL),
 (2, 1, 'VALIDEE', NULL),
 (3, 2, 'BROUILLON', NULL),
 (1, 2, 'REFUSEE', 'Convention incomplete');

INSERT INTO soutenance (demande_id, date_heure, salle) VALUES
 (2, '2026-12-10 09:00:00', 'B12');
