<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

/** Gestion des routes de connexion / déconnexion / profil courant. */
final class AuthController
{
    /** @param callable():AuthService $fabrique */
    public function __construct(private $fabrique) {}

    public function login(): void
    {
        $corps = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($corps)) {
            SanteController::json(400, ['message' => 'Corps JSON invalide.']);
            return;
        }

        $email = (string) ($corps['email'] ?? '');
        $motDePasse = (string) ($corps['motDePasse'] ?? $corps['password'] ?? '');

        if (trim($email) === '' || $motDePasse === '') {
            SanteController::json(400, ['message' => 'Email et mot de passe requis.']);
            return;
        }

        try {
            $utilisateur = ($this->fabrique)()->authentifier($email, $motDePasse);
        } catch (\Throwable $e) {
            error_log('[AuthController::login] ' . $e->getMessage());
            SanteController::json(500, ['message' => 'Erreur interne du serveur.']);
            return;
        }

        if (!$utilisateur) {
            SanteController::json(401, ['message' => 'Identifiants invalides.']);
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['utilisateur'] = $utilisateur;

        SanteController::json(200, [
            'message' => 'Connexion réussie.',
            'utilisateur' => $utilisateur,
        ]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        SanteController::json(200, ['message' => 'Déconnexion réussie.']);
    }

    public function moi(): void
    {
        if (empty($_SESSION['utilisateur'])) {
            SanteController::json(401, ['message' => 'Non authentifié.']);
            return;
        }
        SanteController::json(200, ['utilisateur' => $_SESSION['utilisateur']]);
    }
}
