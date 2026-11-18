<?php
declare(strict_types=1);

// Point d'entrée unique : php -S localhost:8000 -t public public/index.php
require __DIR__ . '/../src/autoload.php';

use App\Controllers\DemandeController;
use App\Controllers\SanteController;
use App\Database;
use App\Repositories\SanteRepository;
use App\Services\SanteService;

// Le serveur interne PHP sert directement les fichiers statiques existants.
$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $chemin !== '/' && is_file(__DIR__ . $chemin)) {
    return false;
}

$config = require __DIR__ . '/../config/config.php';
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$sante = new SanteController(
    fn() => new SanteService(new SanteRepository(Database::connecter($config['db'])))
);
$demandes = new DemandeController();

if ($chemin === '/api/sante' && $methode === 'GET') {
    $sante->index();
} elseif ($chemin === '/api/demandes' && $methode === 'GET') {
    $demandes->lister();
} elseif ($chemin === '/api/demandes' && $methode === 'POST') {
    $demandes->creer();
} elseif (preg_match('#^/api/demandes/(\d+)/statut$#', $chemin, $m) && $methode === 'PATCH') {
    $demandes->changerStatut((int) $m[1]);
} elseif (str_starts_with($chemin, '/api/')) {
    SanteController::json(404, ['message' => 'Route inconnue.']);
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Gestion des stages</title>'
       . '<h1>Gestion des stages</h1><p>API : <a href="/api/sante">/api/sante</a></p></html>';
}
