<?php
declare(strict_types=1);

/**
 * Lit le fichier .env (non versionné) et renvoie la configuration.
 * Les variables d'environnement déjà définies ont priorité sur le fichier.
 */
function charger_env(string $chemin): array
{
    $valeurs = [];
    if (is_readable($chemin)) {
        foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
                continue;
            }
            [$cle, $valeur] = explode('=', $ligne, 2);
            $valeurs[trim($cle)] = trim($valeur, " \t\"'");
        }
    }
    return $valeurs;
}

$env = charger_env(__DIR__ . '/../.env');
$lire = static fn(string $cle, string $defaut): string => getenv($cle) ?: ($env[$cle] ?? $defaut);

return [
    'db' => [
        'host' => $lire('DB_HOST', '127.0.0.1'),
        'port' => $lire('DB_PORT', '3306'),
        'name' => $lire('DB_NAME', 'stages'),
        'user' => $lire('DB_USER', 'stages_app'),
        'pass' => $lire('DB_PASS', ''),
    ],
];
