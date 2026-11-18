<?php
declare(strict_types=1);

// Chargeur minimal : App\Controllers\X -> src/Controllers/X.php
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'App\\';
    if (strncmp($classe, $prefixe, strlen($prefixe)) !== 0) {
        return;
    }
    $fichier = __DIR__ . '/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});
