<?php
declare(strict_types=1);

// Tests de la route de santé, sans MySQL requis : php tests/sante.php
// Le service est testé avec SQLite en mémoire (base joignable) et avec une connexion qui échoue.
require __DIR__ . '/../src/autoload.php';

use App\Repositories\SanteRepository;
use App\Services\SanteService;

$echecs = 0;
function verifier(string $nom, bool $condition): void
{
    global $echecs;
    echo ($condition ? 'OK   ' : 'ECHEC') . " $nom\n";
    if (!$condition) {
        $echecs++;
    }
}

// 1. Base joignable -> ok
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$r = (new SanteService(new SanteRepository($pdo)))->verifier();
verifier('base joignable -> ok = true', $r['ok'] === true && $r['statut'] === 'ok');

// 2. Base en panne -> ok = false, sans exception ni détail interne
$casse = new class extends PDO {
    public function __construct() {}
    public function query(string $q, ?int $mode = null, mixed ...$args): PDOStatement|false
    {
        throw new PDOException("SQLSTATE[HY000] [2002] détail interne secret");
    }
};
$prev = ini_set('error_log', '/dev/null');
$r = (new SanteService(new SanteRepository($casse)))->verifier();
ini_set('error_log', $prev === false ? '' : $prev);
verifier('base en panne -> ok = false', $r['ok'] === false);
verifier('le message client ne contient aucun détail interne', !str_contains($r['statut'], 'secret'));

exit($echecs === 0 ? 0 : 1);
