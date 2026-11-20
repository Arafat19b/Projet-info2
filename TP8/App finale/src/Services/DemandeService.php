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

    public function listerEncadreurs(): array
    {
        return $this->demandes->listerEncadreurs();
    }

    public function listerPourUtilisateur(array $utilisateur): array
    {
        $role = $utilisateur['role'] ?? '';
        if (in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            return $this->demandes->findAll();
        }
        if ($role === 'ENCADREUR' && !empty($utilisateur['encadreur_id'])) {
            return $this->demandes->findByEncadreur((int) $utilisateur['encadreur_id']);
        }
        if ($role === 'ETUDIANT' && !empty($utilisateur['etudiant_id'])) {
            return $this->demandes->findByStudent((int) $utilisateur['etudiant_id']);
        }
        throw new RuntimeException('Accès interdit.', 403);
    }

    /** Lecture d'un dossier : un étudiant ne lit que le sien, un encadreur que ceux qu'il encadre. */
    public function lireDemande(int $id, array $utilisateur): array
    {
        $demande = $this->demandes->findDetailById($id);
        if (!$demande) {
            throw new RuntimeException('Demande introuvable.', 404);
        }

        $role = $utilisateur['role'] ?? '';
        $autorise = false;
        if (in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            $autorise = true;
        } elseif ($role === 'ETUDIANT') {
            $autorise = (int) ($utilisateur['etudiant_id'] ?? 0) === (int) $demande['etudiant_id'];
        } elseif ($role === 'ENCADREUR') {
            $autorise = !empty($demande['encadreur_id'])
                && (int) ($utilisateur['encadreur_id'] ?? 0) === (int) $demande['encadreur_id'];
        }
        if (!$autorise) {
            throw new RuntimeException('Accès interdit sur ce dossier.', 403);
        }
        return $demande;
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
            if (!in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
                throw new RuntimeException('Accès interdit : rôle responsable ou secrétariat requis.', 403);
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

    public function assignerEncadreur(int $id, mixed $encadreurId, array $utilisateur): array
    {
        $role = $utilisateur['role'] ?? '';
        if (!in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            throw new RuntimeException('Accès interdit : rôle responsable requis.', 403);
        }

        $demande = $this->demandes->findById($id);
        if (!$demande) {
            throw new RuntimeException('Demande introuvable.', 404);
        }

        if ($demande['statut'] !== 'VALIDEE') {
            throw new RuntimeException('Impossible d\'assigner un encadreur sur un stage non validé.', 400);
        }

        // Un identifiant est un entier : « 2 OR 1=1 » est refusé au lieu d'être tronqué en 2.
        if (!is_int($encadreurId) && !(is_string($encadreurId) && ctype_digit($encadreurId))) {
            throw new RuntimeException('Identifiant d\'encadreur invalide.', 400);
        }
        $encId = (int) $encadreurId;
        if ($encId <= 0 || !$this->demandes->encadreurExiste($encId)) {
            throw new RuntimeException('Encadreur inexistant.', 400);
        }

        try {
            $this->demandes->assignerEncadreur($id, $encId);
            return ['id' => $id, 'encadreur_id' => $encId];
        } catch (Throwable $e) {
            error_log('[DemandeService::assignerEncadreur] ' . $e->getMessage());
            throw new RuntimeException('Erreur interne du serveur.', 500);
        }
    }

    public function programmerSoutenance(int $id, string $dateHeure, string $salle, array $utilisateur): array
    {
        $role = $utilisateur['role'] ?? '';
        if (!in_array($role, ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            throw new RuntimeException('Accès interdit : rôle responsable ou secrétariat requis.', 403);
        }

        $demande = $this->demandes->findById($id);
        if (!$demande) {
            throw new RuntimeException('Demande introuvable.', 404);
        }

        if ($demande['statut'] !== 'VALIDEE') {
            throw new RuntimeException('La soutenance requiert un stage validé.', 400);
        }

        $sl = trim($salle);
        if (trim($dateHeure) === '' || $sl === '') {
            throw new RuntimeException('Date/heure et salle obligatoires.', 400);
        }
        $dh = self::normaliserDateHeure($dateHeure);
        if ($dh === null) {
            throw new RuntimeException('Date/heure invalide (format attendu : AAAA-MM-JJ HH:MM).', 400);
        }
        if (mb_strlen($sl) > 50) {
            throw new RuntimeException('Salle trop longue (50 caractères maximum).', 400);
        }

        try {
            $soutenanceId = $this->demandes->programmerSoutenance($id, $dh, $sl);
            return ['id' => $soutenanceId, 'demande_id' => $id, 'date_heure' => $dh, 'salle' => $sl];
        } catch (Throwable $e) {
            error_log('[DemandeService::programmerSoutenance] ' . $e->getMessage());
            throw new RuntimeException('Erreur interne du serveur.', 500);
        }
    }

    /**
     * Accepte AAAA-MM-JJ HH:MM[:SS] (avec espace ou « T ») et refuse les dates inexistantes
     * (31 février, 25 h 99) en comparant avec le texte d'origine. Renvoie le format SQL ou null.
     */
    private static function normaliserDateHeure(string $valeur): ?string
    {
        $valeur = trim($valeur);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $valeur);
            if ($date !== false && $date->format($format) === $valeur) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    public function noterSoutenance(int $id, mixed $note, array $utilisateur): array
    {
        $role = $utilisateur['role'] ?? '';
        if (!in_array($role, ['RESPONSABLE', 'ENCADREUR', 'ADMIN'], true)) {
            throw new RuntimeException('Accès interdit.', 403);
        }

        $demande = $this->demandes->findById($id);
        if (!$demande) {
            throw new RuntimeException('Demande introuvable.', 404);
        }

        if ($role === 'ENCADREUR' && (int) ($demande['encadreur_id'] ?? 0) !== (int) ($utilisateur['encadreur_id'] ?? 0)) {
            throw new RuntimeException('Cet encadreur n\'est pas affecté à ce stage.', 403);
        }

        if (!$this->demandes->soutenanceExiste($id)) {
            throw new RuntimeException('Aucune soutenance programmée pour ce dossier.', 404);
        }

        if (!is_numeric($note)) {
            throw new RuntimeException('Note invalide.', 400);
        }

        $valeurNote = (float) $note;
        if ($valeurNote < 0 || $valeurNote > 20) {
            throw new RuntimeException('La note doit être comprise entre 0 et 20.', 400);
        }

        try {
            $this->demandes->noterSoutenance($id, $valeurNote);
            return ['id' => $id, 'note' => $valeurNote];
        } catch (Throwable $e) {
            error_log('[DemandeService::noterSoutenance] ' . $e->getMessage());
            throw new RuntimeException('Erreur interne du serveur.', 500);
        }
    }
}
