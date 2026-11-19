<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Seule couche qui parle SQL pour les demandes de stage, entreprises et historique. */
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
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville
             FROM demande_stage d
             JOIN etudiant e ON e.id = d.etudiant_id
             JOIN utilisateur u ON u.id = e.utilisateur_id
             JOIN entreprise ent ON ent.id = d.entreprise_id
             ORDER BY d.id DESC'
        );
        return $stmt->fetchAll();
    }

    public function findByStudent(int $etudiantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.etudiant_id, d.entreprise_id, d.encadreur_id, d.statut, d.motif_refus,
                    d.date_depot, d.date_decision,
                    ent.nom AS entreprise_nom, ent.ville AS entreprise_ville
             FROM demande_stage d
             JOIN entreprise ent ON ent.id = d.entreprise_id
             WHERE d.etudiant_id = :etudiant_id
             ORDER BY d.id DESC'
        );
        $stmt->execute(['etudiant_id' => $etudiantId]);
        return $stmt->fetchAll();
    }

    public function listerEntreprises(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom, ville FROM entreprise ORDER BY nom ASC');
        return $stmt->fetchAll();
    }

    public function entrepriseExiste(int $entrepriseId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM entreprise WHERE id = :id');
        $stmt->execute(['id' => $entrepriseId]);
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
