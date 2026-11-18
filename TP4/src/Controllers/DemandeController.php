<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Routes du contrat (docs/contrat-api.md). Le contrat est figé au TP4 ;
 * l'implémentation arrive au TP6 : d'ici là, 501 Not Implemented.
 */
final class DemandeController
{
    public function lister(): void
    {
        $this->nonImplemente();
    }

    public function creer(): void
    {
        $this->nonImplemente();
    }

    public function changerStatut(int $id): void
    {
        $this->nonImplemente();
    }

    private function nonImplemente(): void
    {
        SanteController::json(501, ['message' => 'Route définie au contrat, implémentée au TP6.']);
    }
}
