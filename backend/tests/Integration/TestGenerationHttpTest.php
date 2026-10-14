<?php
declare(strict_types=1);

use OpoMatic2000\Domain\TestTokenFailure;
use OpoMatic2000\Infrastructure\TestTokenProtector;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkGeneration(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment(); $base = $argv[1] ?? '';
checkGeneration(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases y servidores temporales locales.');
$db = (new PdoConnectionFactory())->connect($config);
foreach (['QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM ' . $table); }
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
checkGeneration(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
function generationRequest(string $path, string $method = 'POST', ?array $body = null, bool $csrf = true): array
{
    global $client, $base;
    $headers = ['Content-Type: application/json'];
    if ($csrf) {
        foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'OPOMATIC_CSRF') { $headers[] = 'X-CSRF-Token: ' . $parts[6]; }
        }
    }
    curl_setopt_array($client, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode((object) $body, JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER => $headers]);
    $raw = curl_exec($client); checkGeneration(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $raw];
}
function createGenerationTopic(string $name, bool $active = true): int
{
    global $db;
    $q = $db->prepare('INSERT INTO Topics (Name, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
    $q->execute([$name, (int) $active]); return (int) $db->lastInsertId();
}
function seedGenerationQuestions(int $topic, int $count, bool $active = true): array
{
    global $db;
    $q = $db->prepare('INSERT INTO Questions (TopicId, Statement, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
    $o = $db->prepare('INSERT INTO QuestionOptions (QuestionId, Text, Position, IsCorrect) VALUES (?, ?, ?, ?)');
    $ids = [];
    for ($i = 1; $i <= $count; ++$i) {
        $q->execute([$topic, "Pregunta P09 $topic/$i", (int) $active]);
        $id = (int) $db->lastInsertId(); $ids[] = $id;
        for ($p = 1; $p <= 4; ++$p) { $o->execute([$id, "Opción $p de $id", $p, (int) ($p === 1)]); }
    }
    return $ids;
}
$path = '/api/tests/generate';
checkGeneration(generationRequest($path, body: ['topicIds' => [1], 'numberOfQuestions' => 1])[0] === 401, 'Exige sesión.');
checkGeneration(generationRequest('/api/auth/login', body: ['username' => 'Gogloglo', 'password' => getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD')])[0] === 200, 'Login.');
checkGeneration(generationRequest($path, body: ['topicIds' => [1], 'numberOfQuestions' => 1], csrf: false)[0] === 403, 'Exige CSRF.');
$a = createGenerationTopic('Constitución P09'); $b = createGenerationTopic('Administrativo P09');
$inactive = createGenerationTopic('Inactivo P09', false); $outside = createGenerationTopic('No seleccionado P09'); $empty = createGenerationTopic('Vacío P09');
$aIds = seedGenerationQuestions($a, 130); $bIds = seedGenerationQuestions($b, 75);
seedGenerationQuestions($a, 3, false); seedGenerationQuestions($inactive, 5); seedGenerationQuestions($outside, 5);
foreach ([[], ['topicIds' => null], ['topicIds' => (object) ['id' => $a]], ['topicIds' => []],
    ['topicIds' => [$a, $a]], ['topicIds' => ['1']], ['topicIds' => [1.5]], ['topicIds' => [true]],
    ['topicIds' => [0]], ['topicIds' => [-1]], ['topicIds' => [2147483648]],
    ['numberOfQuestions' => 0], ['numberOfQuestions' => 201], ['numberOfQuestions' => '2'],
    ['numberOfQuestions' => 1.5], ['numberOfQuestions' => true], ['numberOfQuestions' => null]] as $invalid) {
    $body = $invalid + ['topicIds' => [$a], 'numberOfQuestions' => 1];
    if ($invalid === []) { $body = []; }
    $r = generationRequest($path, body: $body);
    checkGeneration($r[0] === 400 && isset($r[1]['errors']), 'Entrada inválida 400: ' . json_encode($invalid));
}
foreach ([[$inactive], [2147483647], [$a, $inactive], [$a, 2147483647]] as $ids) {
    checkGeneration(generationRequest($path, body: ['topicIds' => $ids, 'numberOfQuestions' => 1])[0] === 404, 'Tema ausente/inactivo 404.');
}
foreach ([[$empty, 1, 0], [$a, 131, 130]] as [$topic, $requested, $available]) {
    $r = generationRequest($path, body: ['topicIds' => [$topic], 'numberOfQuestions' => $requested]);
    checkGeneration($r[0] === 409 && $r[1]['requestedCount'] === $requested && $r[1]['availableCount'] === $available, 'Conteos de insuficiencia reales.');
}
$before = (int) $db->query('SELECT COUNT(*) FROM TestAttempts')->fetchColumn();
$protector = TestTokenProtector::fromEnvironment(); $orders = []; $optionOrders = []; $tokens = [];
foreach ([1, 200, 130, 200, 200, 200] as $requested) {
    $topicIds = $requested === 130 ? [$a] : [$b, $a];
    $r = generationRequest($path, body: ['topicIds' => $topicIds, 'numberOfQuestions' => $requested]);
    checkGeneration($r[0] === 200, 'Generación 200: ' . $r[2]);
    $test = $r[1]; $questions = $test['questions'];
    checkGeneration(count($questions) === $requested && count(array_unique(array_column($questions, 'id'))) === $requested, 'Cantidad sin repetición.');
    checkGeneration(array_column($test['topics'], 'id') === $topicIds, 'Temas seleccionados en orden.');
    checkGeneration(!str_contains($r[2], 'isCorrect') && !str_contains($r[2], 'position'), 'Sin indicadores de corrección.');
    $payload = $protector->verify($test['testToken'], 'Gogloglo');
    checkGeneration($payload['topicIds'] === $topicIds && $payload['expiresAt'] - $payload['issuedAt'] === 43200
        && $test['expiresAtUtc'] === gmdate('Y-m-d\TH:i:s\Z', $payload['expiresAt']), 'Token emitido por HTTP válido.');
    foreach ($questions as $index => $question) {
        checkGeneration($question['displayOrder'] === $index + 1 && in_array($question['id'], [...$aIds, ...$bIds], true)
            && in_array($question['topicId'], $topicIds, true), 'Pregunta activa de tema elegido.');
        checkGeneration($question['topicName'] === ($question['topicId'] === $a ? 'Constitución P09' : 'Administrativo P09'), 'Nombre de tema.');
        checkGeneration(count($question['options']) === 4 && array_column($question['options'], 'displayOrder') === [1, 2, 3, 4], 'Cuatro opciones ordenadas.');
        $query = $db->prepare('SELECT Id FROM QuestionOptions WHERE QuestionId = ? ORDER BY Id');
        $query->execute([$question['id']]); $expected = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        $actual = array_column($question['options'], 'id'); $sorted = $actual; sort($sorted);
        checkGeneration($sorted === $expected, 'Todas las opciones pertenecen a la pregunta.');
        $claim = $payload['questions'][$index];
        checkGeneration($claim['questionId'] === $question['id'] && $claim['displayOrder'] === $question['displayOrder']
            && array_column($claim['options'], 'optionId') === $actual && array_column($claim['options'], 'displayOrder') === [1, 2, 3, 4], 'Token conserva exactamente orden y opciones.');
        $optionOrders[] = $actual !== $expected;
    }
    $orders[] = array_column($questions, 'id'); $tokens[] = $test['testToken'];
}
checkGeneration(count(array_unique(array_map('json_encode', $orders))) === count($orders) && in_array(true, $optionOrders, true), 'Preguntas y opciones se barajan.');
checkGeneration(count(array_unique($tokens)) === count($tokens), 'Tokens distintos.');
checkGeneration((int) $db->query('SELECT COUNT(*) FROM TestAttempts')->fetchColumn() === $before, 'Generar no guarda un intento.');
$threeTopic = createGenerationTopic('Tres opciones P09');
$db->prepare('INSERT INTO Questions (TopicId, Statement, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, ?, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')->execute([$threeTopic, 'Pregunta de tres opciones']);
$threeQuestionId = (int) $db->lastInsertId();
$threeOptionInsert = $db->prepare('INSERT INTO QuestionOptions (QuestionId, Text, Position, IsCorrect) VALUES (?, ?, ?, ?)');
foreach ([1, 2, 3] as $position) { $threeOptionInsert->execute([$threeQuestionId, 'Opción ' . $position, $position, (int) ($position === 2)]); }
$threeTest = generationRequest($path, body: ['topicIds' => [$threeTopic], 'numberOfQuestions' => 1]);
checkGeneration($threeTest[0] === 200 && count($threeTest[1]['questions'][0]['options']) === 3
    && count($protector->verify($threeTest[1]['testToken'], 'Gogloglo')['questions'][0]['options']) === 3, 'Generación y token con tres opciones.');
checkGeneration(generationRequest('/api/auth/logout')[0] === 204, 'Logout.');
checkGeneration(generationRequest('/api/auth/login', body: ['username' => 'Virginia', 'password' => getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD')])[0] === 200, 'Login segunda cuenta.');
$second = generationRequest($path, body: ['topicIds' => [$a], 'numberOfQuestions' => 1])[1];
checkGeneration($protector->verify($second['testToken'], 'Virginia')['username'] === 'Virginia', 'Token ligado a segunda sesión.');
try { $protector->verify($second['testToken'], 'Gogloglo'); throw new RuntimeException('Token cruzado aceptado.'); }
catch (TestTokenFailure $error) { checkGeneration($error->reason === 'forbidden', 'Uso cruzado prohibido.'); }
// Leave a small content bank for the real browser test.
foreach (['QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM ' . $table); }
$browser = createGenerationTopic('Tema P09 navegador'); seedGenerationQuestions($browser, 2);
$hidden = createGenerationTopic('Tema P09 oculto', false); seedGenerationQuestions($hidden, 2);
curl_close($client);
echo "Generación HTTP: 1–200, tipos, sesión/CSRF, temas activos, exclusión de preguntas inactivas, 404/409 con conteos, barajado, token y cero intentos persistidos OK\n";
