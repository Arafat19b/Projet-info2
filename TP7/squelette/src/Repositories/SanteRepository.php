<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Seule couche qui écrit du SQL. */
final class SanteRepository
{
    public function __construct(private PDO $pdo) {}

    public function baseJoignable(): bool
    {
        $stmt = $this->pdo->query('SELECT 1');
        return (int) $stmt->fetchColumn() === 1;
    }
}
