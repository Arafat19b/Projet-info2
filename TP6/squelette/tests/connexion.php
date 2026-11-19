<?php
declare(strict_types=1);

// Test de connexion PDO : php tests/connexion.php
require __DIR__ . '/../src/autoload.php';

$config = require __DIR__ . '/../config/config.php';

try {
    $pdo = App\Database::connecter($config['db']);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "OK  connexion PDO établie (serveur : $version)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ECHEC connexion PDO : " . $e->getMessage() . "\n");
    exit(1);
}
