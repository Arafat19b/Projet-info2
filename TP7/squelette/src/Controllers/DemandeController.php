<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DemandeService;
use RuntimeException;

final class DemandeController
{
    /** @param callable():DemandeService $fabrique */
    public function __construct(private $fabrique) {}

    public function listerEntreprises(): void
    {
        if (!$this->exigerConnexion()) {
            return;
        }

        try {
            $entreprises = ($this->fabrique)()->listerEntreprises();
            SanteController::json(200, $entreprises);
        } catch (\Throwable $e) {
            error_log('[DemandeController::listerEntreprises] ' . $e->getMessage());
            SanteController::json(500, ['message' => 'Erreur interne du serveur.']);
        }
    }

    public function lister(): void
    {
        $utilisateur = $this->exigerConnexion();
        if (!$utilisateur) {
            return;
        }

        try {
            $liste = ($this->fabrique)()->listerPourUtilisateur($utilisateur);
            SanteController::json(200, $liste);
        } catch (RuntimeException $e) {
            $code = in_array($e->getCode(), [400, 403, 404, 409], true) ? $e->getCode() : 500;
            SanteController::json($code, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[DemandeController::lister] ' . $e->getMessage());
            SanteController::json(500, ['message' => 'Erreur interne du serveur.']);
        }
    }

    public function creer(): void
    {
        $utilisateur = $this->exigerConnexion();
        if (!$utilisateur) {
            return;
        }

        $corps = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($corps)) {
            SanteController::json(400, ['message' => 'Corps JSON invalide.']);
            return;
        }

        try {
            $soumettre = !empty($corps['soumettre']);
            $resultat = ($this->fabrique)()->creerDemande($utilisateur, $corps['entrepriseId'] ?? null, $soumettre);
            SanteController::json(201, $resultat);
        } catch (RuntimeException $e) {
            $code = in_array($e->getCode(), [400, 403, 404, 409], true) ? $e->getCode() : 500;
            SanteController::json($code, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[DemandeController::creer] ' . $e->getMessage());
            SanteController::json(500, ['message' => 'Erreur interne du serveur.']);
        }
    }

    public function changerStatut(int $id): void
    {
        $utilisateur = $this->exigerConnexion();
        if (!$utilisateur) {
            return;
        }

        $corps = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($corps)) {
            SanteController::json(400, ['message' => 'Corps JSON invalide.']);
            return;
        }

        $nouveauStatut = (string) ($corps['statut'] ?? '');
        $motif = isset($corps['motif']) ? (string) $corps['motif'] : null;

        try {
            $resultat = ($this->fabrique)()->changerStatut($id, $nouveauStatut, $motif, $utilisateur);
            SanteController::json(200, $resultat);
        } catch (RuntimeException $e) {
            $code = in_array($e->getCode(), [400, 403, 404, 409], true) ? $e->getCode() : 500;
            SanteController::json($code, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[DemandeController::changerStatut] ' . $e->getMessage());
            SanteController::json(500, ['message' => 'Erreur interne du serveur.']);
        }
    }

    private function exigerConnexion(): ?array
    {
        if (empty($_SESSION['utilisateur']) || !is_array($_SESSION['utilisateur'])) {
            SanteController::json(401, ['message' => 'Non authentifié.']);
            return null;
        }
        return $_SESSION['utilisateur'];
    }
}
