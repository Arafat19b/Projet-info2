<?php
declare(strict_types=1);

/**
 * TP8 : banc de test HTTP (matrice de tests, cas limites, autorisation, injection, hachage, CSRF).
 *
 * Prérequis : MySQL/MariaDB démarré, migrations 001 et 002 fraîchement importées, serveur lancé :
 *     php -S localhost:8000 -t public public/index.php
 * Lancement :
 *     php tests/securite_http.php http://localhost:8000
 * Option : un 2e serveur démarré avec un mauvais mot de passe SQL (test « base injoignable ») :
 *     DB_PASS=mauvais php -S localhost:8001 -t public public/index.php
 *     php tests/securite_http.php http://localhost:8000 http://localhost:8001
 * Conservez la sortie comme preuve :  php tests/securite_http.php http://localhost:8000 > preuves.txt
 *
 * Le script modifie les données : réimportez 001_schema.sql et 002_seed.sql avant chaque exécution.
 */

require __DIR__ . '/../src/autoload.php';

use App\Database;

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$baseCassee = isset($argv[2]) ? rtrim($argv[2], '/') : null;
$mdp = 'Demo1234!';

$comptes = [
    'arafat' => 'arafat.alidou@univ.test',      // étudiant 1 (dossiers 1 et 4)
    'danik'  => 'danik.ahouantcheme@univ.test', // étudiant 2 (dossier 2)
    'ozias'  => 'ozias.dovonou@univ.test',      // étudiant 3 (dossier 3)
    'resp'   => 'resp.pedago@univ.test',        // responsable
    'secr'   => 'secretariat@univ.test',        // secrétariat
    'lea'    => 'lea.hounkpati@univ.test',      // encadreur 1 (dossier 3)
    'paul'   => 'paul.sossou@univ.test',        // encadreur 2 (aucun dossier)
];

$config = require __DIR__ . '/../config/config.php';
try {
    $pdo = Database::connecter($config['db']);
} catch (Throwable $e) {
    fwrite(STDERR, "Connexion à la base impossible : " . $e->getMessage() . "\n");
    exit(2);
}

/** Première colonne de la première ligne (vérifications en base). */
function val(string $sql): string
{
    global $pdo;
    $v = $pdo->query($sql)->fetchColumn();
    return $v === false || $v === null ? '' : (string) $v;
}

/** Un « navigateur » : son propre cookie de session. */
final class Client
{
    public ?string $sid = null;
    /** @var string[] */
    public array $cookies = [];

    public function __construct(private string $base) {}

    /** @return array{0:int,1:mixed,2:string} code HTTP, JSON décodé, texte brut */
    public function appel(string $methode, string $chemin, ?array $corps = null, ?string $brut = null, array $entetes = []): array
    {
        $h = ['Accept: application/json'];
        $contenu = '';
        if ($corps !== null) {
            $contenu = json_encode($corps);
            $h[] = 'Content-Type: application/json';
        }
        if ($brut !== null) {
            $contenu = $brut;
        }
        if ($this->sid !== null) {
            $h[] = 'Cookie: PHPSESSID=' . $this->sid;
        }
        foreach ($entetes as $nom => $valeur) {
            $h[] = "$nom: $valeur";
        }
        $ctx = stream_context_create(['http' => [
            'method' => $methode, 'header' => implode("\r\n", $h), 'content' => $contenu,
            'ignore_errors' => true, 'timeout' => 15, 'follow_location' => 0,
        ]]);
        $texte = @file_get_contents($this->base . $chemin, false, $ctx);
        $entetesReponse = $http_response_header ?? [];
        $statut = 0;
        $this->cookies = [];
        foreach ($entetesReponse as $ligne) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $ligne, $m)) {
                $statut = (int) $m[1];
            } elseif (stripos($ligne, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($ligne, 11));
                $this->cookies[] = $cookie;
                if (str_starts_with($cookie, 'PHPSESSID=')) {
                    $this->sid = explode(';', substr($cookie, 10), 2)[0];
                }
            }
        }
        $json = json_decode((string) $texte, true);
        return [$statut, is_array($json) ? $json : null, (string) $texte];
    }

    public function connexion(string $email, string $motDePasse): array
    {
        return $this->appel('POST', '/api/connexion', ['email' => $email, 'motDePasse' => $motDePasse]);
    }
}

$bilanOk = 0;
$bilanEcarts = 0;

/**
 * @param int|int[] $attendu
 * @param callable():array $appel   renvoie [statut, json, texte]
 * @param null|callable(int,mixed,string):array $verif   renvoie [bool, note]
 */
function test(string $id, string $titre, int|array $attendu, callable $appel, ?callable $verif = null): void
{
    global $bilanOk, $bilanEcarts;
    $attendus = is_array($attendu) ? $attendu : [$attendu];
    try {
        [$statut, $json, $texte] = $appel();
    } catch (Throwable $e) {
        [$statut, $json, $texte] = [0, null, 'EXCEPTION ' . $e->getMessage()];
    }
    $note = '';
    $okVerif = true;
    if ($verif !== null) {
        [$okVerif, $note] = $verif($statut, $json, $texte);
    }
    $ok = in_array($statut, $attendus, true) && $okVerif;
    $ok ? $bilanOk++ : $bilanEcarts++;
    printf("[%s] %-4s %s  -> attendu %s, obtenu %d%s\n", $ok ? 'OK   ' : 'ÉCART', $id, $titre,
        implode('/', $attendus), $statut, $note !== '' ? "  ($note)" : '');
}

function ids(mixed $json): ?array
{
    if (!is_array($json)) {
        return null;
    }
    $ids = array_map(static fn($d) => $d['id'] ?? null, $json);
    sort($ids);
    return $ids;
}

// --- Garde-fou : le jeu d'essai doit être intact -------------------------------------------------
$etatInitial = val("SELECT GROUP_CONCAT(statut ORDER BY id) FROM demande_stage")
    . '|' . val('SELECT COUNT(*) FROM soutenance') . '|' . val('SELECT COUNT(*) FROM historique');
if ($etatInitial !== 'BROUILLON,SOUMISE,VALIDEE,REFUSEE|1|5') {
    fwrite(STDERR, "Jeu d'essai modifié ($etatInitial). Réimportez migrations/001_schema.sql puis 002_seed.sql, puis relancez.\n");
    exit(2);
}

$cl = [];
foreach (array_keys($comptes) as $k) {
    $cl[$k] = new Client($base);
}
$anonyme = new Client($base);
$conn = static fn(string $k): array => $cl[$k]->connexion($comptes[$k], $mdp);

echo "== A. Authentification et session\n";
test('T01', 'Connexion valide (responsable)', 200, fn() => $conn('resp'),
    fn($s, $j, $t) => [!str_contains($t, 'mot_de_passe_hash'), "le hash n'est pas renvoyé au client"]);
test('T02', 'Mauvais mot de passe', 401, fn() => (new Client($base))->connexion($comptes['arafat'], 'faux'));
test('T03', 'Compte inconnu : même message que T02', 401, fn() => (new Client($base))->connexion('inconnu@univ.test', 'faux'),
    fn($s, $j, $t) => [($j['message'] ?? '') === 'Identifiants invalides.', 'message identique à T02']);
test('T04', 'Liste sans session', 401, fn() => $anonyme->appel('GET', '/api/demandes'));
foreach (['arafat', 'danik', 'ozias', 'lea', 'paul', 'secr'] as $k) {
    $conn($k);
}

$cSession = new Client($base);
$cSession->appel('GET', '/api/moi');
$sidAvant = $cSession->sid;
test('T05', 'Identifiant de session régénéré après connexion', 200, fn() => $cSession->connexion($comptes['resp'], $mdp),
    fn($s, $j, $t) => [$sidAvant !== null && $cSession->sid !== $sidAvant, 'avant=' . substr((string) $sidAvant, 0, 8) . '… après=' . substr((string) $cSession->sid, 0, 8) . '…']);
$cCookie = new Client($base);
test('T06', 'Cookie de session : HttpOnly et SameSite', 200, fn() => $cCookie->connexion($comptes['resp'], $mdp),
    function () use ($cCookie) {
        $attrs = implode(' | ', $cCookie->cookies);
        return [stripos($attrs, 'httponly') !== false && stripos($attrs, 'samesite=lax') !== false, $attrs];
    });

echo "== B. Autorisation (isolation entre étudiants)\n";
test('T07', "Étudiant B (Danik) lit le dossier de l'étudiant A (Arafat) : GET /api/demandes/1", 403,
    fn() => $cl['danik']->appel('GET', '/api/demandes/1'));
test('T08', 'Le propriétaire lit son propre dossier : GET /api/demandes/1', 200,
    fn() => $cl['arafat']->appel('GET', '/api/demandes/1'));
test('T09', "La liste d'un étudiant ne contient que ses dossiers", 200,
    fn() => $cl['danik']->appel('GET', '/api/demandes'),
    fn($s, $j, $t) => [ids($j) === [2], 'ids reçus = ' . json_encode(ids($j)) . ' (attendu [2])']);
test('T10', "L'étudiant B modifie le dossier de l'étudiant A (soumission)", 403,
    fn() => $cl['danik']->appel('PATCH', '/api/demandes/1/statut', ['statut' => 'SOUMISE']));
test('T11', 'Un étudiant tente de valider (rôle insuffisant)', 403,
    fn() => $cl['danik']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'VALIDEE']));

echo "== C. Injection SQL : « 1 OR 1=1 »\n";
test('T12', "« 1 OR 1=1 » comme identifiant dans l'URL (GET)", [400, 404],
    fn() => $cl['danik']->appel('GET', '/api/demandes/1%20OR%201=1'),
    fn($s, $j, $t) => [!str_contains($t, 'etudiant_id'), 'aucune donnée de dossier renvoyée']);
test('T13', "« 1 OR 1=1 » comme identifiant dans l'URL (PATCH)", [400, 404],
    fn() => $cl['resp']->appel('PATCH', '/api/demandes/1%20OR%201=1/statut', ['statut' => 'SOUMISE']));
test('T14', "Injection SQL dans l'e-mail de connexion", 401,
    fn() => (new Client($base))->connexion("' OR '1'='1' -- ", 'x'));
test('T15', "« 1 OR 1=1 » comme entrepriseId (corps JSON)", 400,
    fn() => $cl['arafat']->appel('POST', '/api/demandes', ['entrepriseId' => '1 OR 1=1']));
$pdo->exec('UPDATE demande_stage SET encadreur_id = 1 WHERE id = 3');
test('T16', "« 2 OR 1=1 » comme encadreurId (corps JSON)", 400,
    fn() => $cl['resp']->appel('PATCH', '/api/demandes/3/encadreur', ['encadreurId' => '2 OR 1=1']),
    fn($s, $j, $t) => [val('SELECT encadreur_id FROM demande_stage WHERE id=3') === '1',
        'encadreur_id en base après appel = ' . val('SELECT encadreur_id FROM demande_stage WHERE id=3') . ' (attendu 1 : inchangé)']);
$pdo->exec('UPDATE demande_stage SET encadreur_id = 1 WHERE id = 3');

echo "== D. Quatre cas limites imposés\n";
test('T17', 'Demande inexistante', 404, fn() => $cl['resp']->appel('PATCH', '/api/demandes/999/statut', ['statut' => 'VALIDEE']));
test('T18', 'Refus sans motif (champ absent)', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'REFUSEE']));
test('T19', 'Refus avec motif vide (espaces)', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'REFUSEE', 'motif' => '   ']));
test('T20', 'Validation (première fois) SOUMISE -> VALIDEE', 200, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'VALIDEE']));
$nbHist = val('SELECT COUNT(*) FROM historique WHERE demande_id=2');
test('T21', 'Validation répétée', 409, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'VALIDEE']),
    fn($s, $j, $t) => [val('SELECT statut FROM demande_stage WHERE id=2') === 'VALIDEE' && val('SELECT COUNT(*) FROM historique WHERE demande_id=2') === $nbHist,
        "statut resté VALIDEE, historique inchangé ($nbHist lignes)"]);
test('T22', 'Date de soutenance invalide (31/02/2026 25:99)', 400,
    fn() => $cl['resp']->appel('POST', '/api/demandes/3/soutenance', ['dateHeure' => '31/02/2026 25:99', 'salle' => 'B12']));
test('T23', 'Date inexistante au bon format (2026-02-31 10:00)', 400,
    fn() => $cl['resp']->appel('POST', '/api/demandes/3/soutenance', ['dateHeure' => '2026-02-31 10:00', 'salle' => 'B12']));
test('T24', 'Soutenance valide', 200,
    fn() => $cl['resp']->appel('POST', '/api/demandes/3/soutenance', ['dateHeure' => '2026-12-15 09:00', 'salle' => 'Amphi A']));
test('T25', 'Salle trop longue (51 caractères)', 400,
    fn() => $cl['resp']->appel('POST', '/api/demandes/3/soutenance', ['dateHeure' => '2026-12-15 09:00', 'salle' => str_repeat('X', 51)]));

echo "== E. Encadrement et notation\n";
test('T26', "Un étudiant tente d'affecter un encadreur", 403, fn() => $cl['arafat']->appel('PATCH', '/api/demandes/3/encadreur', ['encadreurId' => 2]));
test('T27', 'Affectation sur un dossier non validé (BROUILLON)', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/1/encadreur', ['encadreurId' => 1]));
test('T28', 'Encadreur inexistant', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/3/encadreur', ['encadreurId' => 99]));
test('T29', 'Note hors bornes (25)', 400, fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 25]));
test('T30', 'Note non numérique', 400, fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 'abc']));
test('T31', 'Encadreur non affecté au dossier (Paul)', 403, fn() => $cl['paul']->appel('PATCH', '/api/demandes/3/note', ['note' => 12]));
test('T32', 'Étudiant tente de se noter', 403, fn() => $cl['ozias']->appel('PATCH', '/api/demandes/3/note', ['note' => 20]));
test('T33', 'Note valide par l\'encadreur affecté', 200, fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 14.5]),
    fn($s, $j, $t) => [in_array(val('SELECT note FROM soutenance WHERE demande_id=3'), ['14.50', '14.5'], true), 'note en base = ' . val('SELECT note FROM soutenance WHERE demande_id=3')]);
test('T34', 'Noter un dossier validé SANS soutenance programmée (dossier 2)', [404, 409], fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/note', ['note' => 10]));

echo "== F. Robustesse des entrées\n";
test('T35', 'Corps JSON invalide', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', null, '{pas du json', ['Content-Type' => 'application/json']));
test('T36', 'Statut inconnu (PERDU)', 400, fn() => $cl['resp']->appel('PATCH', '/api/demandes/2/statut', ['statut' => 'PERDU']));
test('T37', 'Route inconnue', 404, fn() => $anonyme->appel('GET', '/api/n-importe-quoi'));

echo "== G. CSRF\n";
$noteEstQuatorze = static fn(): bool => in_array(val('SELECT note FROM soutenance WHERE demande_id=3'), ['14.50', '14.5'], true);
test('T38', 'Requête modifiante envoyée depuis un autre site (Origin: http://evil.test)', 403,
    fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 1], null, ['Origin' => 'http://evil.test']),
    fn($s, $j, $t) => [$noteEstQuatorze(), 'note en base = ' . val('SELECT note FROM soutenance WHERE demande_id=3') . ' (14.50 attendu : non modifiée)']);
test('T39', 'Corps JSON posté en text/plain (formulaire forgé) avec Origin étranger', 403,
    fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', null, '{"note": 2}', ['Content-Type' => 'text/plain', 'Origin' => 'http://evil.test']));
$origineSite = (preg_match('#^(https?://[^/]+)#', $base, $m) ? $m[1] : $base);
test('T40', 'Requête légitime depuis la même origine (non-régression)', 200,
    fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 15], null, ['Origin' => $origineSite]));
test('T41', 'Client sans en-tête Origin (Postman, curl)', 200, fn() => $cl['lea']->appel('PATCH', '/api/demandes/3/note', ['note' => 15]));

echo "== H. Déconnexion et erreurs serveur\n";
test('T42', 'Déconnexion puis accès refusé', 401, function () use ($cl) {
    $cl['secr']->appel('POST', '/api/deconnexion', []);
    return $cl['secr']->appel('GET', '/api/moi');
});
if ($baseCassee !== null) {
    test('T43', 'Base injoignable : message neutre, aucun détail SQL', 500,
        fn() => (new Client($baseCassee))->connexion($comptes['resp'], $mdp),
        fn($s, $j, $t) => [!str_contains($t, 'SQLSTATE') && !str_contains($t, 'PDO') && !str_contains($t, 'Access denied'), 'aucun SQLSTATE / PDO dans la réponse']);
}

echo "== I. Mots de passe hachés en base\n";
$hashes = $pdo->query('SELECT email, mot_de_passe_hash FROM utilisateur ORDER BY id')->fetchAll(PDO::FETCH_NUM);
$nbBcrypt = count(array_filter($hashes, static fn($h) => str_starts_with($h[1], '$2y$') && strlen($h[1]) === 60));
$aucunClair = count(array_filter($hashes, static fn($h) => $h[1] === $mdp)) === 0;
$ok44 = $nbBcrypt === count($hashes) && $aucunClair;
$ok44 ? $bilanOk++ : $bilanEcarts++;
printf("[%s] T44  hash bcrypt : %d/%d, aucun en clair : %s\n", $ok44 ? 'OK   ' : 'ÉCART', $nbBcrypt, count($hashes), $aucunClair ? 'oui' : 'NON');
$distincts = count(array_unique(array_column($hashes, 1)));
$ok45 = $distincts === count($hashes);
$ok45 ? $bilanOk++ : $bilanEcarts++;
printf("[%s] T45  hash distincts : %d/%d\n", $ok45 ? 'OK   ' : 'ÉCART', $distincts, count($hashes));

echo "== J. Autres contrôles relevés pendant la revue\n";
test('T46', 'Identifiant renvoyé quand on modifie une soutenance existante (dossier 2)', 200,
    function () use ($cl) {
        $cl['resp']->appel('POST', '/api/demandes/2/soutenance', ['dateHeure' => '2026-12-01 10:00:00', 'salle' => 'B1']);
        return $cl['resp']->appel('POST', '/api/demandes/2/soutenance', ['dateHeure' => '2026-12-02 11:00:00', 'salle' => 'B2']);
    },
    fn($s, $j, $t) => [isset($j['id']) && (string) $j['id'] === val('SELECT id FROM soutenance WHERE demande_id=2'),
        'id renvoyé = ' . ($j['id'] ?? 'aucun') . ', id en base = ' . val('SELECT id FROM soutenance WHERE demande_id=2')]);
test('T47', '40 échecs de connexion d\'affilée, puis le bon mot de passe', [423, 429], function () use ($base, $comptes, $mdp) {
    for ($i = 0; $i < 40; $i++) {
        (new Client($base))->connexion($comptes['resp'], "essai$i");
    }
    return (new Client($base))->connexion($comptes['resp'], $mdp);
});
test('T48', 'Compte désactivé pendant une session ouverte (actif = 0)', 401, function () use ($base, $comptes, $mdp, $pdo) {
    $c = new Client($base);
    $c->connexion($comptes['paul'], $mdp);
    $pdo->exec("UPDATE utilisateur SET actif = 0 WHERE email = " . $pdo->quote($comptes['paul']));
    try {
        return $c->appel('GET', '/api/demandes');
    } finally {
        $pdo->exec("UPDATE utilisateur SET actif = 1 WHERE email = " . $pdo->quote($comptes['paul']));
    }
});

printf("\nBILAN : %d OK, %d écart(s), %d tests\n", $bilanOk, $bilanEcarts, $bilanOk + $bilanEcarts);
exit($bilanEcarts === 0 ? 0 : 1);
