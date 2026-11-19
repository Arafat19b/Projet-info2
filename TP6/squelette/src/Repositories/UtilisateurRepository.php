<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Seule couche qui parle SQL pour les comptes utilisateurs et profils liés. */
final class UtilisateurRepository
{
    public function __construct(private PDO $pdo) {}

    public function trouverParEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.mot_de_passe_hash, u.role, u.nom, u.prenom, u.actif, e.id AS etudiant_id
             FROM utilisateur u
             LEFT JOIN etudiant e ON e.utilisateur_id = u.id
             WHERE u.email = :email'
        );
        $stmt->execute(['email' => $email]);
        $ligne = $stmt->fetch();
        return $ligne ?: null;
    }

    public function trouverParId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.role, u.nom, u.prenom, u.actif, e.id AS etudiant_id
             FROM utilisateur u
             LEFT JOIN etudiant e ON e.utilisateur_id = u.id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $ligne = $stmt->fetch();
        return $ligne ?: null;
    }
}
