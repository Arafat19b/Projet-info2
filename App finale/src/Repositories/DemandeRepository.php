<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DemandeRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, etudiant_id, entreprise_id, encadreur_id, statut, motif_refus, date_depot, date_decision
             FROM demande_stage
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch();
        return $ligne ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.statut, d.motif_refus,
                    d.date_depot, d.date_decision,
                    u.nom AS etudiant_nom, u.prenom AS etudiant_prenom,
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville,
                    en_u.nom AS encadreur_nom, en_u.prenom AS encadreur_prenom,
                    s.id AS soutenance_id, s.date_heure AS soutenance_date, s.salle AS soutenance_salle, s.note AS soutenance_note
             FROM demande_stage d
             JOIN etudiant e ON e.id = d.etudiant_id
             JOIN utilisateur u ON u.id = e.utilisateur_id
             JOIN entreprise ent ON ent.id = d.entreprise_id
             LEFT JOIN encadreur en ON en.id = d.encadreur_id
             LEFT JOIN utilisateur en_u ON en_u.id = en.utilisateur_id
             LEFT JOIN soutenance s ON s.demande_id = d.id
             ORDER BY d.id DESC'
        );
        return $stmt->fetchAll();
    }

    public function findByStudent(int $etudiantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.statut, d.motif_refus,
                    d.date_depot, d.date_decision,
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville,
                    en_u.nom AS encadreur_nom, en_u.prenom AS encadreur_prenom,
                    s.id AS soutenance_id, s.date_heure AS soutenance_date, s.salle AS soutenance_salle, s.note AS soutenance_note
             FROM demande_stage d
             JOIN entreprise ent ON ent.id = d.entreprise_id
             LEFT JOIN encadreur en ON en.id = d.encadreur_id
             LEFT JOIN utilisateur en_u ON en_u.id = en.utilisateur_id
             LEFT JOIN soutenance s ON s.demande_id = d.id
             WHERE d.etudiant_id = :etudiant_id
             ORDER BY d.id DESC'
        );
        $stmt->execute(['etudiant_id' => $etudiantId]);
        return $stmt->fetchAll();
    }

    public function findByEncadreur(int $encadreurId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.statut, d.motif_refus,
                    d.date_depot, d.date_decision,
                    u.nom AS etudiant_nom, u.prenom AS etudiant_prenom,
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville,
                    s.id AS soutenance_id, s.date_heure AS soutenance_date, s.salle AS soutenance_salle, s.note AS soutenance_note
             FROM demande_stage d
             JOIN etudiant e ON e.id = d.etudiant_id
             JOIN utilisateur u ON u.id = e.utilisateur_id
             JOIN entreprise ent ON ent.id = d.entreprise_id
             LEFT JOIN soutenance s ON s.demande_id = d.id
             WHERE d.encadreur_id = :encadreur_id
             ORDER BY d.id DESC'
        );
        $stmt->execute(['encadreur_id' => $encadreurId]);
        return $stmt->fetchAll();
    }

    /** Un dossier avec les libellés (étudiant, entreprise, encadreur, soutenance). */
    public function findDetailById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.statut, d.motif_refus,
                    d.date_depot, d.date_decision,
                    u.nom AS etudiant_nom, u.prenom AS etudiant_prenom,
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville,
                    en_u.nom AS encadreur_nom, en_u.prenom AS encadreur_prenom,
                    s.id AS soutenance_id, s.date_heure AS soutenance_date, s.salle AS soutenance_salle, s.note AS soutenance_note
             FROM demande_stage d
             JOIN etudiant e ON e.id = d.etudiant_id
             JOIN utilisateur u ON u.id = e.utilisateur_id
             JOIN entreprise ent ON ent.id = d.entreprise_id
             LEFT JOIN encadreur en ON en.id = d.encadreur_id
             LEFT JOIN utilisateur en_u ON en_u.id = en.utilisateur_id
             LEFT JOIN soutenance s ON s.demande_id = d.id
             WHERE d.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch();
        return $ligne ?: null;
    }

    public function soutenanceExiste(int $demandeId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM soutenance WHERE demande_id = :id');
        $stmt->execute(['id' => $demandeId]);
        return (bool) $stmt->fetchColumn();
    }

    public function listerEntreprises(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom, ville FROM entreprise ORDER BY nom ASC');
        return $stmt->fetchAll();
    }

    public function listerEncadreurs(): array
    {
        $stmt = $this->pdo->query(
            'SELECT en.id, u.nom, u.prenom, en.grade
             FROM encadreur en
             JOIN utilisateur u ON u.id = en.utilisateur_id
             ORDER BY u.nom ASC'
        );
        return $stmt->fetchAll();
    }

    public function entrepriseExiste(int $entrepriseId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM entreprise WHERE id = :id');
        $stmt->execute(['id' => $entrepriseId]);
        return (bool) $stmt->fetchColumn();
    }

    public function encadreurExiste(int $encadreurId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM encadreur WHERE id = :id');
        $stmt->execute(['id' => $encadreurId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(int $etudiantId, int $entrepriseId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demande_stage (etudiant_id, entreprise_id, statut)
             VALUES (:etudiant_id, :entreprise_id, :statut)'
        );
        $stmt->execute([
            'etudiant_id' => $etudiantId,
            'entreprise_id' => $entrepriseId,
            'statut' => 'BROUILLON',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $statut, ?string $motifRefus = null): bool
    {
        $dateDepotSql = $statut === 'SOUMISE' ? ', date_depot = CURRENT_TIMESTAMP' : '';
        $dateDecisionSql = in_array($statut, ['VALIDEE', 'REFUSEE'], true)
            ? ', date_decision = CURRENT_TIMESTAMP'
            : ($statut === 'BROUILLON' ? ', date_decision = NULL' : '');

        $sql = "UPDATE demande_stage
                SET statut = :statut,
                    motif_refus = :motif
                    {$dateDepotSql}
                    {$dateDecisionSql}
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'statut' => $statut,
            'motif' => $motifRefus,
        ]);
    }

    public function assignerEncadreur(int $demandeId, int $encadreurId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE demande_stage SET encadreur_id = :encadreur_id WHERE id = :id');
        return $stmt->execute(['encadreur_id' => $encadreurId, 'id' => $demandeId]);
    }

    public function programmerSoutenance(int $demandeId, string $dateHeure, string $salle): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO soutenance (demande_id, date_heure, salle)
             VALUES (:demande_id, :date_heure, :salle)
             ON DUPLICATE KEY UPDATE date_heure = :date_heure2, salle = :salle2'
        );
        // SQLite compatibility or standard MySQL
        try {
            $stmt->execute([
                'demande_id' => $demandeId,
                'date_heure' => $dateHeure,
                'salle' => $salle,
            ]);
            return $this->idSoutenance($demandeId);
        } catch (\Throwable) {
            // Fallback for strict SQLite without ON DUPLICATE KEY
            $stmtUp = $this->pdo->prepare('UPDATE soutenance SET date_heure = :dh, salle = :s WHERE demande_id = :did');
            $stmtUp->execute(['dh' => $dateHeure, 's' => $salle, 'did' => $demandeId]);
            if ($stmtUp->rowCount() === 0) {
                $stmtIns = $this->pdo->prepare('INSERT INTO soutenance (demande_id, date_heure, salle) VALUES (:did, :dh, :s)');
                $stmtIns->execute(['did' => $demandeId, 'dh' => $dateHeure, 's' => $salle]);
            }
            return $this->idSoutenance($demandeId);
        }
    }

    /** Identifiant réel de la soutenance d'un dossier (lastInsertId() vaut 0 lors d'une modification). */
    private function idSoutenance(int $demandeId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM soutenance WHERE demande_id = :id');
        $stmt->execute(['id' => $demandeId]);
        return (int) $stmt->fetchColumn();
    }

    public function noterSoutenance(int $demandeId, float $note): bool
    {
        $stmt = $this->pdo->prepare('UPDATE soutenance SET note = :note WHERE demande_id = :demande_id');
        return $stmt->execute(['note' => $note, 'demande_id' => $demandeId]);
    }

    public function ajouterHistorique(
        int $demandeId,
        ?string $ancienStatut,
        string $nouveauStatut,
        int $auteurId,
        ?string $motif = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO historique (demande_id, ancien_statut, nouveau_statut, auteur_id, motif)
             VALUES (:demande_id, :ancien_statut, :nouveau_statut, :auteur_id, :motif)'
        );
        $stmt->execute([
            'demande_id' => $demandeId,
            'ancien_statut' => $ancienStatut,
            'nouveau_statut' => $nouveauStatut,
            'auteur_id' => $auteurId,
            'motif' => $motif,
        ]);
    }
}
