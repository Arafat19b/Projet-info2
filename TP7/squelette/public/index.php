<?php
declare(strict_types=1);

// Point d'entrée unique : php -S localhost:8000 -t public public/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/../src/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\DemandeController;
use App\Controllers\SanteController;
use App\Database;
use App\Repositories\DemandeRepository;
use App\Repositories\SanteRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DemandeService;
use App\Services\SanteService;

$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $chemin !== '/' && is_file(__DIR__ . $chemin)) {
    return false;
}

$config = require __DIR__ . '/../config/config.php';
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$creerPdo = static fn(): PDO => Database::connecter($config['db']);

$sante = new SanteController(
    static fn(): SanteService => new SanteService(new SanteRepository($creerPdo()))
);

$auth = new AuthController(
    static fn(): AuthService => new AuthService(new UtilisateurRepository($creerPdo()))
);

$demandes = new DemandeController(
    static function () use ($creerPdo): DemandeService {
        $pdo = $creerPdo();
        return new DemandeService(new DemandeRepository($pdo), $pdo);
    }
);

if ($chemin === '/api/sante' && $methode === 'GET') {
    $sante->index();
} elseif ($chemin === '/api/connexion' && $methode === 'POST') {
    $auth->login();
} elseif ($chemin === '/api/deconnexion' && $methode === 'POST') {
    $auth->logout();
} elseif ($chemin === '/api/moi' && $methode === 'GET') {
    $auth->moi();
} elseif ($chemin === '/api/entreprises' && $methode === 'GET') {
    $demandes->listerEntreprises();
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
    readfile(__DIR__ . '/app.html');
}
