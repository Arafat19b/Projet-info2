<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UtilisateurRepository;

/** Gestion de l'authentification (hachage password_verify et sessions sécurisées). */
final class AuthService
{
    public function __construct(private UtilisateurRepository $utilisateurs) {}

    public function authentifier(string $email, string $motDePasse): ?array
    {
        $emailNettoye = trim(mb_strtolower($email));
        if ($emailNettoye === '' || $motDePasse === '') {
            return null;
        }

        $utilisateur = $this->utilisateurs->trouverParEmail($emailNettoye);
        if (!$utilisateur || (int) $utilisateur['actif'] !== 1) {
            return null;
        }

        if (!password_verify($motDePasse, $utilisateur['mot_de_passe_hash'])) {
            return null;
        }

        unset($utilisateur['mot_de_passe_hash']);
        return $utilisateur;
    }
}
