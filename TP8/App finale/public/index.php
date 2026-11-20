<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
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

// Protection CSRF (en plus du cookie SameSite=Lax) : une requête qui modifie des données
// doit venir de notre propre origine. Les clients sans en-tête Origin (curl, Postman) passent.
if (in_array($methode, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && str_starts_with($chemin, '/api/')) {
    $origine = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origine !== null) {
        $p = parse_url($origine);
        $hoteOrigine = ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        if (strcasecmp($hoteOrigine, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
            SanteController::json(403, ['message' => 'Origine non autorisée.']);
            return;
        }
    }
}

$sante = new SanteController(static fn(): SanteService => new SanteService(new SanteRepository($creerPdo())));
$auth = new AuthController(static fn(): AuthService => new AuthService(new UtilisateurRepository($creerPdo())));
$demandes = new DemandeController(static function () use ($creerPdo): DemandeService {
    $pdo = $creerPdo();
    return new DemandeService(new DemandeRepository($pdo), $pdo);
});

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
} elseif ($chemin === '/api/encadreurs' && $methode === 'GET') {
    $demandes->listerEncadreurs();
} elseif ($chemin === '/api/demandes' && $methode === 'GET') {
    $demandes->lister();
} elseif ($chemin === '/api/demandes' && $methode === 'POST') {
    $demandes->creer();
} elseif (preg_match('#^/api/demandes/(\d+)$#', $chemin, $m) && $methode === 'GET') {
    $demandes->lire((int) $m[1]);
} elseif (preg_match('#^/api/demandes/(\d+)/statut$#', $chemin, $m) && $methode === 'PATCH') {
    $demandes->changerStatut((int) $m[1]);
} elseif (preg_match('#^/api/demandes/(\d+)/encadreur$#', $chemin, $m) && $methode === 'PATCH') {
    $demandes->assignerEncadreur((int) $m[1]);
} elseif (preg_match('#^/api/demandes/(\d+)/soutenance$#', $chemin, $m) && $methode === 'POST') {
    $demandes->programmerSoutenance((int) $m[1]);
} elseif (preg_match('#^/api/demandes/(\d+)/note$#', $chemin, $m) && $methode === 'PATCH') {
    $demandes->noterSoutenance((int) $m[1]);
} elseif (str_starts_with($chemin, '/api/')) {
    SanteController::json(404, ['message' => 'Route inconnue.']);
} else {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/app.html');
}
