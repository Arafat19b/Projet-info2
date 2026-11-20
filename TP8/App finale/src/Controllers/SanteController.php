<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SanteService;

/** Lit la requête, écrit la réponse. Aucune règle métier ici. */
final class SanteController
{
    /** @param callable():SanteService $fabrique construit le service (connexion paresseuse) */
    public function __construct(private $fabrique) {}

    public function index(): void
    {
        try {
            $service = ($this->fabrique)();
            $resultat = $service->verifier();
        } catch (\Throwable $e) {
            error_log('[sante] ' . $e->getMessage());
            $resultat = ['ok' => false, 'statut' => 'base injoignable'];
        }
        self::json($resultat['ok'] ? 200 : 503, ['statut' => $resultat['statut']]);
    }

    public static function json(int $code, array $corps): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($corps, JSON_UNESCAPED_UNICODE);
    }
}
