<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SanteRepository;
use Throwable;

/** Règle métier : l'application est « en bonne santé » si la base répond. */
final class SanteService
{
    public function __construct(private SanteRepository $repo) {}

    public function verifier(): array
    {
        try {
            $ok = $this->repo->baseJoignable();
        } catch (Throwable $e) {
            error_log('[sante] ' . $e->getMessage()); // détail dans le journal, jamais au client
            $ok = false;
        }
        return ['ok' => $ok, 'statut' => $ok ? 'ok' : 'base injoignable'];
    }
}
