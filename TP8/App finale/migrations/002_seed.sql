-- Projet informatique 2 — L3 SIL · Application de gestion des stages et soutenances
-- Groupe : ALIDOU Arafat – AHOUANTCHEME Danik – DOVONOU Ozias
-- 002_seed.sql — jeu d'essai fictif incluant les soutenances
-- Attendu : 8 comptes, 3 étudiants, 2 encadreurs, 2 entreprises,
--           4 demandes, 1 soutenance programmée, 5 lignes d'historique.
-- Mot de passe commun des comptes de démonstration : Demo1234! (chaque compte a son propre hash, donc son propre sel)

SET NAMES utf8mb4;

INSERT INTO utilisateur (id, email, mot_de_passe_hash, role, nom, prenom, actif) VALUES
  (1, 'arafat.alidou@univ.test',      '$2y$10$a/k1DdfSixn19w1w/NUHG.7c/48bRtPhBxjBLvq0CJhakrT3ORXgK', 'ETUDIANT',    'ALIDOU',       'Arafat', 1),
  (2, 'danik.ahouantcheme@univ.test', '$2y$10$xXFFf65O5uPUd8v498WIyOO20HLkeH2TfSDW/IO2v97wVO3WaqpWq', 'ETUDIANT',    'AHOUANTCHEME', 'Danik',  1),
  (3, 'ozias.dovonou@univ.test',      '$2y$10$pInU5W/oaEVuJslHh7f18OU1VZNv0PLuKHEJCrbe8Fm9eThQULo76', 'ETUDIANT',    'DOVONOU',      'Ozias',  1),
  (4, 'secretariat@univ.test',        '$2y$10$JHtWnizNalWpkwC3pRy5LO2LT2zrKpwJ.QxU42Vk5p.AIQKAXOTpS', 'SECRETARIAT', 'AGOSSOU',      'Rita',   1),
  (5, 'resp.pedago@univ.test',        '$2y$10$j6ySdr5uF.q2cmAiuJexOuHqyYr7jV2nOPtciRXfZza1.tSXwsbfO', 'RESPONSABLE', 'TOSSOU',       'Bertin', 1),
  (6, 'lea.hounkpati@univ.test',      '$2y$10$nfTzfbinGYbl2UXHeS9CPeAmhpmQVJDRG4sVau9kb7FnH5lyIpziu', 'ENCADREUR',   'HOUNKPATI',    'Léa',    1),
  (7, 'paul.sossou@univ.test',        '$2y$10$O5.2P6WB8jbHcDZ1hDhki.9H4gjGJXfK2zuixLdOC4EtgJuTHGoY6', 'ENCADREUR',   'SOSSOU',       'Paul',   1),
  (8, 'admin@univ.test',              '$2y$10$msP5lyloGhZg.aOIr6V6X.b4B.8tkck4qfacQ6bMHUJiHd6xVoYpS', 'ADMIN',       'GBAGUIDI',     'Noël',   1);

INSERT INTO etudiant (id, utilisateur_id, numero_etudiant, filiere) VALUES
  (1, 1, 'ETU-2026-001', 'SIL'),
  (2, 2, 'ETU-2026-002', 'SIL'),
  (3, 3, 'ETU-2026-003', 'SIL');

INSERT INTO encadreur (id, utilisateur_id, grade) VALUES
  (1, 6, 'Maître de conférences'),
  (2, 7, 'Assistant');

INSERT INTO entreprise (id, nom, ville, contact) VALUES
  (1, 'Atelier Numerique', 'Porto-Novo', 'contact@atelier-numerique.test'),
  (2, 'Reseau Plus',       'Cotonou',    'rh@reseau-plus.test');

INSERT INTO demande_stage (id, etudiant_id, entreprise_id, encadreur_id, statut, motif_refus, date_depot, date_decision) VALUES
  (1, 1, 1, NULL, 'BROUILLON', NULL, NULL, NULL),
  (2, 2, 1, NULL, 'SOUMISE',   NULL, '2026-11-02 10:00:00', NULL),
  (3, 3, 2, 1,    'VALIDEE',   NULL, '2026-10-20 09:15:00', '2026-10-27 16:40:00'),
  (4, 1, 2, NULL, 'REFUSEE',   'Convention incomplète : signature de l''entreprise absente',
                                  '2026-10-05 11:00:00', '2026-10-09 14:20:00');

-- Soutenance programmée pour la demande validée (id=3)
INSERT INTO soutenance (id, demande_id, date_heure, salle, note) VALUES
  (1, 3, '2026-11-25 09:00:00', 'Amphi B', NULL);

INSERT INTO historique (demande_id, ancien_statut, nouveau_statut, auteur_id, motif, date_evenement) VALUES
  (2, NULL,      'SOUMISE', 2, NULL, '2026-11-02 10:00:00'),
  (3, NULL,      'SOUMISE', 3, NULL, '2026-10-20 09:15:00'),
  (3, 'SOUMISE', 'VALIDEE', 5, NULL, '2026-10-27 16:40:00'),
  (4, NULL,      'SOUMISE', 1, NULL, '2026-10-05 11:00:00'),
  (4, 'SOUMISE', 'REFUSEE', 5, 'Convention incomplète', '2026-10-09 14:20:00');
