<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DemandeRepository;
use PDO;
use RuntimeException;
use Throwable;

final class DemandeService
{
    private const TRANSITIONS = [
        'BROUILLON' => ['SOUMISE'],
        'SOUMISE'   => ['VALIDEE', 'REFUSEE'],
        'REFUSEE'   => ['BROUILLON'],
        'VALIDEE'   => [],
    ];

    public function __construct(
        private DemandeRepository $demandes,
        private PDO $pdo
    ) {}

    public static function peutPasser(string $de, string $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }

    public function listerEntreprises(): array
    {
        return $this->demandes->listerEntreprises();
    }

    public function listerPourUtilisateur(array $utilisateur): array
    {
        $role = $utilisateur['role'] ?? '';
        if (in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            return $this->demandes->findAll();
        }
        if ($role === 'ETUDIANT' && !empty($utilisateur['etudiant_id'])) {
            return $this->demandes->findByStudent((int) $utilisateur['etudiant_id']);
        }
        throw new RuntimeException('Accès interdit.', 403);
    }

    public function creerDemande(array $utilisateur, mixed $entrepriseId, bool $soumettreDirectement = false): array
    {
        if (($utilisateur['role'] ?? '') !== 'ETUDIANT' || empty($utilisateur['etudiant_id'])) {
            throw new RuntimeException('Seul un étudiant peut déposer une demande.', 403);
        }

        if (!is_int($entrepriseId) && !(is_string($entrepriseId) && ctype_digit($entrepriseId))) {
            throw new RuntimeException('Veuillez sélectionner une entreprise valide.', 400);
        }

        $entId = (int) $entrepriseId;
        if ($entId <= 0 || !$this->demandes->entrepriseExiste($entId)) {
            throw new RuntimeException('Entreprise inexistante.', 400);
        }

        $etudiantId = (int) $utilisateur['etudiant_id'];
        $userId = (int) $utilisateur['id'];

        $this->pdo->beginTransaction();
        try {
            $id = $this->demandes->create($etudiantId, $entId);
            $this->demandes->ajouterHistorique($id, null, 'BROUILLON', $userId, null);

            $statutFinal = 'BROUILLON';
            if ($soumettreDirectement) {
                $this->demandes->updateStatus($id, 'SOUMISE', null);
                $this->demandes->ajouterHistorique($id, 'BROUILLON', 'SOUMISE', $userId, null);
                $statutFinal = 'SOUMISE';
            }

            $this->pdo->commit();
            return ['id' => $id, 'statut' => $statutFinal];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[DemandeService::creerDemande] ' . $e->getMessage());
            throw new RuntimeException('Erreur technique lors de la création.', 500);
        }
    }

    public function changerStatut(int $id, string $nouveauStatut, ?string $motif, array $utilisateur): array
    {
        $statutsValides = ['BROUILLON', 'SOUMISE', 'VALIDEE', 'REFUSEE'];
        if (!in_array($nouveauStatut, $statutsValides, true)) {
            throw new RuntimeException('Statut cible invalide.', 400);
        }

        $demande = $this->demandes->findById($id);
        if (!$demande) {
            throw new RuntimeException('Demande introuvable.', 404);
        }

        $role = $utilisateur['role'] ?? '';
        $userId = (int) $utilisateur['id'];

        if (in_array($nouveauStatut, ['VALIDEE', 'REFUSEE'], true)) {
            if ($role !== 'RESPONSABLE') {
                throw new RuntimeException('Accès interdit : rôle responsable requis.', 403);
            }
        } elseif (in_array($nouveauStatut, ['SOUMISE', 'BROUILLON'], true)) {
            if ($role !== 'ETUDIANT' || (int) ($utilisateur['etudiant_id'] ?? 0) !== (int) $demande['etudiant_id']) {
                throw new RuntimeException('Accès interdit sur ce dossier.', 403);
            }
        }

        $ancienStatut = (string) $demande['statut'];
        if (!self::peutPasser($ancienStatut, $nouveauStatut)) {
            throw new RuntimeException("Transition interdite de {$ancienStatut} vers {$nouveauStatut}.", 409);
        }

        $motifNettoye = $motif !== null ? trim($motif) : null;
        if ($nouveauStatut === 'REFUSEE' && ($motifNettoye === null || $motifNettoye === '')) {
            throw new RuntimeException('Le motif de refus est obligatoire.', 400);
        }
        if ($nouveauStatut !== 'REFUSEE') {
            $motifNettoye = null;
        }

        $this->pdo->beginTransaction();
        try {
            $this->demandes->updateStatus($id, $nouveauStatut, $motifNettoye);
            $this->demandes->ajouterHistorique($id, $ancienStatut, $nouveauStatut, $userId, $motifNettoye);
            $this->pdo->commit();
            return ['id' => $id, 'statut' => $nouveauStatut];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[DemandeService::changerStatut] ' . $e->getMessage());
            throw new RuntimeException('Erreur interne du serveur.', 500);
        }
    }
}
