<?php

declare(strict_types=1);

use OpoMatic2000\Domain\AttemptScorer;
use OpoMatic2000\Domain\QuestionResult;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function verifyProgress(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment(); $base = $argv[1] ?? '';
verifyProgress(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases y servidores temporales locales.');
$db = (new PdoConnectionFactory())->connect($config);
foreach (['Users', 'TestAttempts'] as $table) {
    verifyProgress((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === 0, 'Se exige base vacía.');
}
$db->exec('DELETE FROM QuestionOptions'); $db->exec('DELETE FROM Questions'); $db->exec('DELETE FROM Topics');
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
verifyProgress(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 30]);
function progressRequest(string $path, string $method = 'GET', ?array $body = null): array
{
    global $client, $base;
    $headers = ['Content-Type: application/json'];
    foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
        $parts = explode("\t", $cookie);
        if (($parts[5] ?? '') === 'OPOMATIC_CSRF') { $headers[] = 'X-CSRF-Token: ' . $parts[6]; }
    }
    $responseHeaders = [];
    curl_setopt_array($client, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode((object) $body, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$responseHeaders): int {
            $responseHeaders[] = trim($line); return strlen($line);
        }]);
    $raw = curl_exec($client); verifyProgress(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $responseHeaders];
}
function progressLogin(string $name): void
{
    progressRequest('/api/auth/logout', 'POST');
    $password = getenv($name === 'Gogloglo' ? 'OPOMATIC_E2E_GOGLOGLO_PASSWORD' : 'OPOMATIC_E2E_VIRGINIA_PASSWORD');
    verifyProgress(progressRequest('/api/auth/login', 'POST', ['username' => $name, 'password' => $password])[0] === 200, 'Login fallido.');
}
$paths = ['/api/attempts', '/api/statistics/summary', '/api/statistics/topics'];
foreach ($paths as $path) {
    $r = progressRequest($path);
    verifyProgress($r[0] === 401 && $r[1]['status'] === 401, 'Sesión requerida.');
    verifyProgress(in_array('Content-Type: application/problem+json; charset=utf-8', $r[2], true), 'Problem Details anónimo.');
}
progressLogin('Gogloglo');
$empty = progressRequest('/api/attempts');
verifyProgress($empty[0] === 200 && $empty[1] === ['items' => [], 'page' => 1, 'pageSize' => 20, 'totalItems' => 0, 'totalPages' => 0], 'Histórico vacío.');
verifyProgress(progressRequest('/api/statistics/summary')[1] === ['completedTests' => 0, 'answeredQuestions' => 0,
    'correctCount' => 0, 'incorrectCount' => 0, 'unansweredCount' => 0, 'accuracyPercentage' => null], 'Resumen vacío.');
verifyProgress(progressRequest('/api/statistics/topics')[1] === [], 'Temas vacíos.');
foreach (['page=0', 'page=-1', 'page=1.5', 'page=2147483648', 'page=no', 'page%5B%5D=1', 'pageSize=0', 'pageSize=101', 'pageSize=1.5', 'pageSize%5B%5D=2'] as $query) {
    $r = progressRequest('/api/attempts?' . $query);
    verifyProgress($r[0] === 400 && isset($r[1]['errors']), 'Paginación inválida: ' . $query);
    verifyProgress(in_array('Content-Type: application/problem+json; charset=utf-8', $r[2], true), 'Problem Details validación.');
}
foreach ($paths as $path) {
    $r = progressRequest($path, 'PUT', []);
    verifyProgress($r[0] === 405 && in_array('Allow: GET', $r[2], true), 'Método no permitido.');
}
$topicInsert = $db->prepare('INSERT INTO Topics (Name, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
$questionInsert = $db->prepare('INSERT INTO Questions (TopicId, Statement, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, ?, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
$optionInsert = $db->prepare('INSERT INTO QuestionOptions (QuestionId, Text, Position, IsCorrect) VALUES (?, ?, ?, ?)');
$repo = new TestAttemptRepository($db); $transactions = new TransactionManager($db);
$fixtures = [];
foreach (['Alfa P12', 'Beta P12', 'Gamma P12', 'Solo blancos P12', 'Preciso P12', 'Cero P12', 'Histórico P11'] as $name) {
    $topicInsert->execute([$name]); $topicId = (int) $db->lastInsertId(); $ids = [];
    for ($i = 1; $i <= 4; ++$i) {
        $questionInsert->execute([$topicId, 'Enunciado histórico ' . $name . '/' . $i]); $id = (int) $db->lastInsertId(); $ids[] = $id;
        foreach ([1, 2, 3, 4] as $position) { $optionInsert->execute([$id, 'Opción histórica ' . $position, $position, (int) ($position === 1)]); }
    }
    $fixtures[$name] = ['topic' => $topicId, 'sources' => array_values($repo->sources($ids))];
}
function progressSeed(string $name, array $results, string $owner = 'Gogloglo'): array
{
    global $repo, $transactions, $fixtures;
    $classified = [];
    foreach ($results as $i => $result) {
        $source = $fixtures[$name]['sources'][$i]; $options = [];
        foreach (array_keys($source['Options']) as $position => $id) { $options[] = ['optionId' => $id, 'displayOrder' => $position + 1]; }
        $selected = $result === 0 ? null : $options[$result === 1 ? 0 : 1]['optionId'];
        $classified[] = ['source' => $source, 'token' => ['questionId' => (int) $source['Id'], 'displayOrder' => $i + 1, 'options' => $options],
            'result' => QuestionResult::from($result), 'selected' => $selected];
    }
    $correct = count(array_filter($results, static fn ($r): bool => $r === 1));
    $incorrect = count(array_filter($results, static fn ($r): bool => $r === 2));
    $uuid = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    return $transactions->run(fn (): array => $repo->save($uuid, $owner, $classified, $correct, $incorrect, AttemptScorer::millionths(count($results), $correct, $incorrect)));
}
// A finalized blank-only test is activity but has no accuracy or ranking.
$blank = progressSeed('Solo blancos P12', [0, 0]);
$r = progressRequest('/api/statistics/summary')[1];
verifyProgress($r['completedTests'] === 1 && $r['answeredQuestions'] === 0 && $r['unansweredCount'] === 2 && $r['accuracyPercentage'] === null, 'Solo blancos resumen.');
$r = progressRequest('/api/statistics/topics')[1][0];
verifyProgress($r['rankingPosition'] === null && $r['accuracyPercentage'] === null, 'Solo blancos tema.');
foreach (['Alfa P12' => [1, 2], 'Beta P12' => [1, 1, 2, 2], 'Gamma P12' => [1, 2], 'Preciso P12' => [1, 1, 2], 'Cero P12' => [2]] as $name => $results) { progressSeed($name, $results); }
for ($i = 0; $i < 21; ++$i) { $latest = progressSeed('Histórico P11', [0]); }
$virginia = progressSeed('Alfa P12', [1, 1, 1], 'Virginia');
// Identical timestamps exercise the ID tie breaker and page boundaries.
$db->exec("UPDATE TestAttempts SET CompletedAtUtc = '2026-01-01 12:00:00.000000'");
$detail = progressRequest('/api/attempts/' . $latest['Id'])[1];
$db->exec("UPDATE Topics SET Name = CONCAT('Renombrado ', Id), IsActive = 0");
$db->exec("UPDATE Questions SET Statement = 'Texto posterior', IsActive = 0");
$db->exec("UPDATE QuestionOptions SET Text = 'Opción posterior'");
$r = progressRequest('/api/attempts'); $first = $r[1];
verifyProgress($r[0] === 200 && $first['pageSize'] === 20 && $first['totalItems'] === 27 && $first['totalPages'] === 2 && count($first['items']) === 20, 'Página predeterminada.');
verifyProgress($first['items'][0]['id'] === (int) $latest['Id'] && !isset($first['items'][0]['questions']) && !isset($first['items'][0]['Username']), 'Resumen privado y ligero.');
verifyProgress($first['items'][0]['topics'][0]['name'] === 'Histórico P11' && str_ends_with($first['items'][0]['completedAtUtc'], 'Z'), 'Snapshots y UTC.');
$second = progressRequest('/api/attempts?page=2')[1];
verifyProgress(count($second['items']) === 7 && count(array_unique(array_column([...$first['items'], ...$second['items']], 'id'))) === 27, 'Páginas sin duplicados ni pérdidas.');
$all = progressRequest('/api/attempts?pageSize=100')[1];
$ids = array_column($all['items'], 'id'); $sorted = $ids; rsort($sorted);
verifyProgress($ids === $sorted, 'Orden estable por ID en empate.');
$oldestId = $ids[count($ids) - 1];
$db->exec('UPDATE TestAttempts SET CompletedAtUtc = UTC_TIMESTAMP(6) WHERE Id = ' . $oldestId);
verifyProgress(progressRequest('/api/attempts?pageSize=1')[1]['items'][0]['id'] === $oldestId, 'Fecha antes que ID.');
foreach ([3, 2147483647] as $page) { $r = progressRequest('/api/attempts?page=' . $page)[1]; verifyProgress($r['items'] === [] && $r['totalItems'] === 27, 'Página fuera de rango.'); }
verifyProgress(progressRequest('/api/attempts/' . $latest['Id'])[1] === $detail, 'Revisión inmutable tras cambios del banco.');
verifyProgress(progressRequest('/api/attempts/' . $virginia['Id'])[0] === 404, 'Detalle ajeno.');
$summary = progressRequest('/api/statistics/summary')[1];
verifyProgress($summary === ['completedTests' => 27, 'answeredQuestions' => 12, 'correctCount' => 6, 'incorrectCount' => 6,
    'unansweredCount' => 23, 'accuracyPercentage' => 50], 'Totales y precisión sin blancos.');
$ranking = progressRequest('/api/statistics/topics')[1];
verifyProgress(array_column($ranking, 'topicName') === ['Preciso P12', 'Beta P12', 'Alfa P12', 'Gamma P12', 'Cero P12', 'Histórico P11', 'Solo blancos P12'], 'Ranking: precisión, tamaño, nombre y sin datos al final.');
verifyProgress(array_column($ranking, 'rankingPosition') === [1, 2, 3, 4, 5, null, null] && $ranking[0]['accuracyPercentage'] === 66.67, 'Puestos y redondeo.');
foreach ($paths as $path) { verifyProgress(in_array('Cache-Control: no-store', progressRequest($path)[2], true), 'No cachear datos privados.'); }
progressLogin('Virginia');
verifyProgress(progressRequest('/api/attempts')[1]['totalItems'] === 1 && progressRequest('/api/attempts/' . $latest['Id'])[0] === 404, 'Histórico aislado Virginia.');
verifyProgress(progressRequest('/api/statistics/summary')[1]['correctCount'] === 3 && count(progressRequest('/api/statistics/topics')[1]) === 1, 'Estadísticas aisladas Virginia.');
// The name must come from Virginia\'s own latest snapshot, never another account or current Topics.
verifyProgress(progressRequest('/api/statistics/topics')[1][0]['topicName'] === 'Alfa P12', 'Nombre aislado por cuenta.');
// A newer snapshot from Gogloglo must not rename Virginia's topic.
$fixtures['Alfa P12']['sources'][0]['TopicName'] = 'Alfa snapshot nuevo';
$renamed = progressSeed('Alfa P12', [1]);
$db->exec("UPDATE TestAttempts SET CompletedAtUtc = '2026-10-11 12:00:00.000000' WHERE Id = " . $renamed['Id']);
verifyProgress(progressRequest('/api/statistics/topics')[1][0]['topicName'] === 'Alfa P12', 'Nombre de otra cuenta no filtrado.');
progressLogin('Gogloglo');
$ownedTopics = progressRequest('/api/statistics/topics')[1];
$ownedName = array_values(array_filter($ownedTopics, static fn ($t): bool => $t['topicId'] === $fixtures['Alfa P12']['topic']))[0]['topicName'];
verifyProgress($ownedName === 'Alfa snapshot nuevo', 'Nombre de la última instantánea propia.');
// Ratios 1/199 and 1/200 round to the same display percentage. Rank the exact ratio first.
foreach (['Precisión 199' => 199, 'Precisión 200' => 200] as $name => $count) {
    $topicInsert->execute([$name]); $topicId = (int) $db->lastInsertId(); $ids = [];
    for ($i = 1; $i <= $count; ++$i) {
        $questionInsert->execute([$topicId, 'Pregunta precisión ' . $i]); $id = (int) $db->lastInsertId(); $ids[] = $id;
        foreach ([1, 2, 3, 4] as $position) { $optionInsert->execute([$id, 'Respuesta ' . $position, $position, (int) ($position === 1)]); }
    }
    $fixtures[$name] = ['topic' => $topicId, 'sources' => array_values($repo->sources($ids))];
    progressSeed($name, [1, ...array_fill(0, $count - 1, 2)]);
}
$precise = array_values(array_filter(progressRequest('/api/statistics/topics')[1], static fn ($t): bool => str_starts_with($t['topicName'], 'Precisión ')));
verifyProgress(array_column($precise, 'topicName') === ['Precisión 199', 'Precisión 200'] && $precise[0]['accuracyPercentage'] === $precise[1]['accuracyPercentage'], 'Ranking antes de redondear.');
curl_close($client);
// Leave just the two accounts for browser tests, which create and complete their own content.
$db->exec('DELETE FROM TestAttempts'); $db->exec('DELETE FROM QuestionOptions'); $db->exec('DELETE FROM Questions'); $db->exec('DELETE FROM Topics');
echo "P11/P12 HTTP: vacíos, validación, paginación, orden, snapshots, totales, ranking, blancos y aislamiento OK\n";
