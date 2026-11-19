<?php
declare(strict_types=1);

// Tests complets de l'API TP6 (transitions, authentification, transaction/rollback, 6 codes HTTP)
// Exécution : php tests/api_demandes.php
require __DIR__ . '/../src/autoload.php';

use App\Repositories\DemandeRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\AuthService;
use App\Services\DemandeService;

$echecs = 0;
function verifier(string $nom, bool $condition): void
{
    global $echecs;
    echo ($condition ? '[OK]   ' : '[ECHEC]') . " $nom\n";
    if (!$condition) {
        $echecs++;
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE utilisateur (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        mot_de_passe_hash TEXT NOT NULL,
        role TEXT NOT NULL,
        nom TEXT NOT NULL,
        prenom TEXT NOT NULL,
        actif INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE etudiant (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        utilisateur_id INTEGER NOT NULL UNIQUE,
        numero_etudiant TEXT NOT NULL,
        filiere TEXT NOT NULL
    );
    CREATE TABLE entreprise (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        ville TEXT NOT NULL
    );
    CREATE TABLE demande_stage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        etudiant_id INTEGER NOT NULL,
        entreprise_id INTEGER NOT NULL,
        encadreur_id INTEGER NULL,
        statut TEXT NOT NULL DEFAULT 'BROUILLON',
        motif_refus TEXT NULL,
        date_depot DATETIME NULL,
        date_decision DATETIME NULL
    );
    CREATE TABLE historique (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        demande_id INTEGER NOT NULL,
        ancien_statut TEXT NULL,
        nouveau_statut TEXT NOT NULL,
        auteur_id INTEGER NOT NULL,
        motif TEXT NULL,
        date_evenement DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

$hash = password_hash('Demo1234!', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO utilisateur (id, email, mot_de_passe_hash, role, nom, prenom) VALUES (1, 'danik@univ.test', ?, 'ETUDIANT', 'AHOUANTCHEME', 'Danik')")->execute([$hash]);
$pdo->prepare("INSERT INTO utilisateur (id, email, mot_de_passe_hash, role, nom, prenom) VALUES (2, 'resp@univ.test', ?, 'RESPONSABLE', 'TOSSOU', 'Bertin')")->execute([$hash]);
$pdo->exec("INSERT INTO etudiant (id, utilisateur_id, numero_etudiant, filiere) VALUES (1, 1, 'ETU-2026-002', 'SIL')");
$pdo->exec("INSERT INTO entreprise (id, nom, ville) VALUES (1, 'Atelier Numerique', 'Porto-Novo')");

$userRepo = new UtilisateurRepository($pdo);
$authService = new AuthService($userRepo);
$demandeRepo = new DemandeRepository($pdo);
$demandeService = new DemandeService($demandeRepo, $pdo);

echo "=== 1. Tests d'authentification ===\n";
$etu = $authService->authentifier('danik@univ.test', 'Demo1234!');
verifier('Connexion étudiant valide (mot de passe haché)', $etu !== null && $etu['role'] === 'ETUDIANT' && !isset($etu['mot_de_passe_hash']));
verifier('Connexion refusée si mot de passe faux', $authService->authentifier('danik@univ.test', 'MauvaisMdp') === null);
verifier('Connexion refusée si email inconnu', $authService->authentifier('inconnu@univ.test', 'Demo1234!') === null);

$resp = $authService->authentifier('resp@univ.test', 'Demo1234!');
verifier('Connexion responsable valide', $resp !== null && $resp['role'] === 'RESPONSABLE');

echo "\n=== 2. Tests des transitions autorisées ===\n";
verifier('BROUILLON -> SOUMISE autorisé', DemandeService::peutPasser('BROUILLON', 'SOUMISE') === true);
verifier('SOUMISE -> VALIDEE autorisé', DemandeService::peutPasser('SOUMISE', 'VALIDEE') === true);
verifier('SOUMISE -> REFUSEE autorisé', DemandeService::peutPasser('SOUMISE', 'REFUSEE') === true);
verifier('REFUSEE -> BROUILLON autorisé', DemandeService::peutPasser('REFUSEE', 'BROUILLON') === true);
verifier('BROUILLON -> VALIDEE interdit', DemandeService::peutPasser('BROUILLON', 'VALIDEE') === false);
verifier('VALIDEE -> REFUSEE interdit', DemandeService::peutPasser('VALIDEE', 'REFUSEE') === false);

echo "\n=== 3. Tests des scénarios métier et codes HTTP ===\n";
$cree = $demandeService->creerDemande($etu, 1);
verifier('201 : Création demande -> statut BROUILLON', $cree['id'] === 1 && $cree['statut'] === 'BROUILLON');

try {
    $demandeService->creerDemande($etu, 999);
    verifier('400 : Entreprise inexistante rejetée', false);
} catch (RuntimeException $e) {
    verifier('400 : Entreprise inexistante rejetée (code 400)', $e->getCode() === 400);
}

$soumise = $demandeService->changerStatut(1, 'SOUMISE', null, $etu);
verifier('200 : Étudiant soumet sa demande (BROUILLON -> SOUMISE)', $soumise['statut'] === 'SOUMISE');

try {
    $demandeService->changerStatut(1, 'VALIDEE', null, $etu);
    verifier('403 : Étudiant ne peut pas valider', false);
} catch (RuntimeException $e) {
    verifier('403 : Étudiant bloqué sur validation (code 403)', $e->getCode() === 403);
}

try {
    $demandeService->changerStatut(1, 'REFUSEE', '', $resp);
    verifier('400 : Refus sans motif rejeté', false);
} catch (RuntimeException $e) {
    verifier('400 : Refus sans motif rejeté (code 400)', $e->getCode() === 400);
}

try {
    $demandeService->changerStatut(999, 'VALIDEE', null, $resp);
    verifier('404 : Demande inexistante rejetée', false);
} catch (RuntimeException $e) {
    verifier('404 : Demande 999 introuvable (code 404)', $e->getCode() === 404);
}

$validee = $demandeService->changerStatut(1, 'VALIDEE', null, $resp);
$nbHist = (int) $pdo->query("SELECT COUNT(*) FROM historique WHERE demande_id = 1")->fetchColumn();
verifier('200 : Responsable valide la demande (SOUMISE -> VALIDEE)', $validee['statut'] === 'VALIDEE');
verifier('Transaction : 3 lignes enregistrées dans historique (BROUILLON, SOUMISE, VALIDEE)', $nbHist === 3);

try {
    $demandeService->changerStatut(1, 'VALIDEE', null, $resp);
    verifier('409 : Validation répétée rejetée', false);
} catch (RuntimeException $e) {
    verifier('409 : Validation répétée bloquée (code 409)', $e->getCode() === 409);
}

echo "\n=== 4. Test de rollback transactionnel en cas d'erreur SQL ===\n";
$pdo->exec("INSERT INTO demande_stage (id, etudiant_id, entreprise_id, statut) VALUES (2, 1, 1, 'SOUMISE')");
$pdo->exec("DROP TABLE historique");
$prevLog = ini_set('error_log', '/dev/null');
try {
    $demandeService->changerStatut(2, 'VALIDEE', null, $resp);
    verifier('Rollback : exception levée sur échec historique', false);
} catch (RuntimeException $e) {
    $statutApresRollback = $pdo->query("SELECT statut FROM demande_stage WHERE id = 2")->fetchColumn();
    verifier('Rollback : code 500 et statut demande_stage inchangé (SOUMISE)', $e->getCode() === 500 && $statutApresRollback === 'SOUMISE');
}
ini_set('error_log', $prevLog === false ? '' : $prevLog);

echo "\nRésultat global : " . ($echecs === 0 ? "TOUS LES TESTS SONT PASSÉS" : "$echecs échec(s)") . "\n";
exit($echecs === 0 ? 0 : 1);
