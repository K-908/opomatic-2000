<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\TestTokenProtector;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkCompletion(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment(); $base = $argv[1] ?? '';
checkCompletion(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases y servidores temporales locales.');
$db = (new PdoConnectionFactory())->connect($config);
foreach (['TestAttempts', 'QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM ' . $table); }
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
checkCompletion(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 30]);
function completionRequest(string $path, string $method = 'POST', mixed $body = null, bool $csrf = true, string $contentType = 'application/json'): array
{
    global $client, $base;
    $headers = ['Content-Type: ' . $contentType];
    if ($csrf) {
        foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'OPOMATIC_CSRF') { $headers[] = 'X-CSRF-Token: ' . $parts[6]; }
        }
    }
    $responseHeaders = [];
    curl_setopt_array($client, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => is_string($body) ? $body : ($body === null ? null : json_encode((object) $body, JSON_THROW_ON_ERROR)),
        CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$responseHeaders): int {
            $responseHeaders[] = trim($line); return strlen($line);
        }]);
    $raw = curl_exec($client); checkCompletion(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $raw, $responseHeaders];
}
function completionUuid(): string
{
    return sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
}
function completionGenerate(array $topics, int $count): array
{
    $r = completionRequest('/api/tests/generate', body: ['topicIds' => $topics, 'numberOfQuestions' => $count]);
    checkCompletion($r[0] === 200, 'Generación fallida: ' . $r[2]); return $r[1];
}
function completionAnswers(array $test, array $results): array
{
    global $db;
    $answers = [];
    foreach ($test['questions'] as $index => $question) {
        $q = $db->prepare('SELECT Id FROM QuestionOptions WHERE QuestionId = ? AND IsCorrect = ? ORDER BY Id LIMIT 1');
        $q->execute([$question['id'], ($results[$index] ?? 'blank') === 'correct' ? 1 : 0]);
        $selected = ($results[$index] ?? 'blank') === 'blank' ? null : (int) $q->fetchColumn();
        $answers[] = ['questionId' => $question['id'], 'selectedOptionId' => $selected];
    }
    return $answers;
}
function completionBody(array $test, array $results): array
{
    return ['submissionId' => completionUuid(), 'testToken' => $test['testToken'], 'answers' => completionAnswers($test, $results)];
}
function completionCounts(): array
{
    global $db;
    $counts = [];
    foreach (['TestAttempts', 'TestAttemptTopics', 'TestAttemptQuestions', 'TestAttemptOptions'] as $table) {
        $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $counts;
}
$finish = '/api/tests/finish';
checkCompletion(completionRequest($finish, body: [])[0] === 401, 'Entrega exige sesión.');
checkCompletion(completionRequest('/api/attempts/1', 'GET')[0] === 401, 'Detalle exige sesión.');
checkCompletion(completionRequest('/api/auth/login', body: ['username' => 'Gogloglo', 'password' => getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD')])[0] === 200, 'Login fallido.');
$topicInsert = $db->prepare('INSERT INTO Topics (Name, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
$questionInsert = $db->prepare('INSERT INTO Questions (TopicId, Statement, IsActive, CreatedAtUtc, UpdatedAtUtc) VALUES (?, ?, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
$optionInsert = $db->prepare('INSERT INTO QuestionOptions (QuestionId, Text, Position, IsCorrect) VALUES (?, ?, ?, ?)');
$topicIds = [];
foreach (['Constitución P10', 'Administrativo P10'] as $name) {
    $topicInsert->execute([$name]); $topic = (int) $db->lastInsertId(); $topicIds[] = $topic;
    for ($i = 1; $i <= 100; ++$i) {
        $questionInsert->execute([$topic, "Pregunta P10 $topic/$i"]); $question = (int) $db->lastInsertId();
        foreach ([1, 2, 3, 4] as $position) { $optionInsert->execute([$question, "Opción $position de $question", $position, (int) ($position === 1)]); }
    }
}
$generated = completionGenerate($topicIds, 3);
$body = completionBody($generated, ['correct', 'incorrect', 'blank']);
checkCompletion(completionRequest($finish, body: $body, csrf: false)[0] === 403, 'CSRF entrega.');
checkCompletion(completionRequest($finish, body: '{')[0] === 400 && completionRequest($finish, body: '[]')[0] === 400, 'JSON inválido.');
checkCompletion(completionRequest($finish, body: $body, contentType: 'text/plain')[0] === 415, 'Content-Type.');
$before = completionCounts();
foreach ([['submissionId' => null], ['submissionId' => 123], ['submissionId' => 'bad'], ['submissionId' => '00000000-0000-0000-0000-000000000000'],
    ['testToken' => null], ['testToken' => true], ['testToken' => ''], ['testToken' => str_repeat('a', 200001)],
    ['answers' => null], ['answers' => (object) []], ['answers' => []], ['answers' => array_fill(0, 201, $body['answers'][0])],
    ['answers' => [['questionId' => '1', 'selectedOptionId' => null]]],
    ['answers' => [['questionId' => 1, 'selectedOptionId' => 1.5]]],
    ['answers' => [['questionId' => 1, 'selectedOptionId' => false]]],
    ['answers' => [['questionId' => 1]]], ['answers' => [null]],
    ['answers' => [$body['answers'][0], $body['answers'][0], $body['answers'][2]]],
    ['answers' => array_slice($body['answers'], 0, 2)],
    ['answers' => [...$body['answers'], ['questionId' => 2147483647, 'selectedOptionId' => null]]]] as $override) {
    $r = completionRequest($finish, body: $override + $body);
    checkCompletion($r[0] === 400 && isset($r[1]['errors']), 'Validación 400 fallida.');
}
$wrong = $body; $wrong['answers'][0]['selectedOptionId'] = $body['answers'][1]['selectedOptionId'];
checkCompletion(completionRequest($finish, body: $wrong)[0] === 400, 'Opción de otra pregunta.');
$wrong = $body; $wrong['answers'][0]['questionId'] = 2147483647;
checkCompletion(completionRequest($finish, body: $wrong)[0] === 400, 'Pregunta ajena.');
$badToken = $body; $badToken['testToken'][10] = $badToken['testToken'][10] === 'A' ? 'B' : 'A';
checkCompletion(completionRequest($finish, body: $badToken)[0] === 400, 'Token alterado.');
$protector = TestTokenProtector::fromEnvironment(); $claims = $protector->verify($body['testToken'], 'Gogloglo');
$expired = new TestTokenProtector(getenv('APP_KEY'), static fn (): int => time() - 43200);
$old = $body;
$old['testToken'] = $expired->issue('Gogloglo', $claims['topicIds'], $claims['questions'])['testToken'];
checkCompletion(completionRequest($finish, body: $old)[0] === 410, 'Caducidad HTTP.');
$missingClaims = $claims['questions']; $missingClaims[0]['questionId'] = 2147483647;
$missing = $body;
$missing['testToken'] = $protector->issue('Gogloglo', $claims['topicIds'], $missingClaims)['testToken'];
$missing['answers'][0]['questionId'] = 2147483647;
checkCompletion(completionRequest($finish, body: $missing)[0] === 400, 'Pregunta inexistente en base.');
$alteredClaims = $claims['questions']; $alteredClaims[0]['options'][0]['optionId'] = 2147483647;
$altered = $body;
$altered['testToken'] = $protector->issue('Gogloglo', $claims['topicIds'], $alteredClaims)['testToken'];
$altered['answers'][0]['selectedOptionId'] = null;
checkCompletion(completionRequest($finish, body: $altered)[0] === 400, 'Opciones de token/base diferentes.');
checkCompletion(completionCounts() === $before, 'Rechazos no guardan datos.');
$created = completionRequest($finish, body: $body);
checkCompletion($created[0] === 201 && $created[1]['score'] === 2.5
    && $created[1]['correctCount'] === 1 && $created[1]['incorrectCount'] === 1 && $created[1]['unansweredCount'] === 1, 'Entrega mixta.');
$id = $created[1]['attemptId'];
checkCompletion(in_array('Location: /api/attempts/' . $id, $created[3], true), 'Location del intento.');
checkCompletion(in_array('Cache-Control: no-store', $created[3], true), 'No almacenar resultado en caché.');
checkCompletion(completionCounts()['TestAttempts'] === $before['TestAttempts'] + 1
    && completionCounts()['TestAttemptQuestions'] === 3 && completionCounts()['TestAttemptOptions'] === 12, 'Snapshots completos.');
$detail = completionRequest('/api/attempts/' . $id, 'GET')[1];
checkCompletion($detail['id'] === $id && $detail['score'] === 2.5
    && array_column($detail['questions'], 'result') === ['correct', 'incorrect', 'unanswered'], 'Detalle real desde snapshots.');
foreach ($detail['questions'] as $index => $question) {
    $original = $generated['questions'][$index];
    checkCompletion($question['displayOrder'] === $index + 1 && $question['originalQuestionId'] === $original['id']
        && $question['statement'] === $original['statement'] && array_column($question['options'], 'text') === array_column($original['options'], 'text')
        && count(array_filter($question['options'], static fn (array $o): bool => $o['isCorrect'])) === 1
        && count(array_filter($question['options'], static fn (array $o): bool => $o['isSelected'])) === ($index === 2 ? 0 : 1), 'Texto/orden/selección snapshot.');
}
$counts = completionCounts();
$repeat = completionRequest($finish, body: $body);
checkCompletion($repeat[0] === 200 && $repeat[1] === $created[1] && completionCounts() === $counts, 'Idempotencia exacta.');
$reordered = $body; $reordered['answers'] = array_reverse($body['answers']); $reordered['submissionId'] = strtoupper($body['submissionId']);
checkCompletion(completionRequest($finish, body: $reordered)[1] === $created[1], 'Orden respuestas/UUID mayúsculas equivalentes.');
$conflict = $body; $conflict['answers'][0]['selectedOptionId'] = null;
checkCompletion(completionRequest($finish, body: $conflict)[0] === 409 && completionCounts() === $counts, 'UUID con respuestas distintas.');
$another = completionBody(completionGenerate($topicIds, 1), ['blank']); $another['submissionId'] = $body['submissionId'];
checkCompletion(completionRequest($finish, body: $another)[0] === 409, 'UUID de otro test.');
// Later bank edits and logical deactivation cannot change the result or replay.
foreach ($topicIds as $topic) { $db->prepare('UPDATE Topics SET Name = CONCAT(Name, ?), IsActive = 0 WHERE Id = ?')->execute([' revisado', $topic]); }
foreach ($generated['questions'] as $question) {
    $db->prepare('UPDATE Questions SET Statement = ?, IsActive = 0 WHERE Id = ?')->execute(['Texto modificado después', $question['id']]);
    $db->prepare('UPDATE QuestionOptions SET Text = ?, IsCorrect = (Position = 2) WHERE QuestionId = ?')->execute(['Opción modificada después', $question['id']]);
}
checkCompletion(completionRequest('/api/attempts/' . $id, 'GET')[1] === $detail, 'Detalle inmutable después de editar.');
checkCompletion(completionRequest($finish, body: $body)[1] === $created[1], 'Replay sin volver a corregir.');
// A previously generated valid test can be completed after logical deactivation.
$inactiveBody = $body; $inactiveBody['submissionId'] = completionUuid();
checkCompletion(completionRequest($finish, body: $inactiveBody)[0] === 201, 'Entrega de test vigente tras desactivar.');
foreach ($topicIds as $topic) { $db->prepare('UPDATE Topics SET IsActive = 1 WHERE Id = ?')->execute([$topic]); }
$db->exec('UPDATE Questions SET IsActive = 1'); $db->exec('UPDATE QuestionOptions SET IsCorrect = (Position = 1)');
foreach (['0', '-1', 'abc', '9223372036854775808', '9223372036854775807'] as $invalidId) {
    checkCompletion(completionRequest('/api/attempts/' . $invalidId, 'GET')[0] === 404, 'Detalle ausente/inválido.');
}
// Single-question extremes and 200-question limit; Score is stored as an integer.
foreach ([[1, ['correct'], 10], [1, ['incorrect'], -2.5], [1, ['blank'], 0], [200, array_fill(0, 200, 'correct'), 10]] as [$n, $results, $score]) {
    $delivery = completionRequest($finish, body: completionBody(completionGenerate($topicIds, $n), $results));
    checkCompletion($delivery[0] === 201 && (float) $delivery[1]['score'] === (float) $score, 'Extremos y 200 preguntas.');
    $query = $db->prepare('SELECT Score, ScoringRuleVersion FROM TestAttempts WHERE Id = ?'); $query->execute([$delivery[1]['attemptId']]); $saved = $query->fetch();
    checkCompletion((int) $saved['Score'] === (int) ($score * 1000000) && (int) $saved['ScoringRuleVersion'] === 1, 'Millonésimas y regla persistidas.');
}
// The second snapshot question fails: the attempt, topics and first snapshots must all roll back.
$rollbackBody = completionBody(completionGenerate($topicIds, 3), ['correct', 'incorrect', 'blank']);
$counts = completionCounts();
$db->exec("CREATE TRIGGER p10_snapshot_failure BEFORE INSERT ON TestAttemptQuestions FOR EACH ROW BEGIN
    IF NEW.DisplayOrder = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'p10-private-rollback'; END IF; END");
try {
    $failed = completionRequest($finish, body: $rollbackBody);
    checkCompletion($failed[0] === 500 && isset($failed[1]['traceId']) && !str_contains($failed[2], 'p10-private'), 'Error privado genérico.');
    checkCompletion(completionCounts() === $counts, 'Rollback completo tras guardar la primera pregunta.');
} finally { $db->exec('DROP TRIGGER IF EXISTS p10_snapshot_failure'); }
checkCompletion(completionRequest($finish, body: $rollbackBody)[0] === 201, 'Reintento tras rollback.');
// Two processes race against the same MariaDB database (HTTP development server is single-threaded).
$raceBody = completionBody(completionGenerate($topicIds, 2), ['correct', 'blank']);
$raceRoot = sys_get_temp_dir() . '/opomatic-p10-race-' . bin2hex(random_bytes(8)); mkdir($raceRoot);
$workers = [];
$counts = completionCounts();
$db->exec('CREATE TRIGGER p10_race_delay BEFORE INSERT ON TestAttempts FOR EACH ROW DO SLEEP(0.5)');
try {
    foreach (['1', '2'] as $slot) {
        $worker = proc_open([PHP_BINARY, __DIR__ . '/TestCompletionRaceWorker.php', $raceRoot, $slot],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $workerPipes);
        checkCompletion(is_resource($worker), 'Worker no iniciado.');
        fwrite($workerPipes[0], json_encode((object) $raceBody, JSON_THROW_ON_ERROR)); fclose($workerPipes[0]);
        $workers[] = [$worker, $workerPipes];
    }
    $deadline = microtime(true) + 10;
    while (!is_file($raceRoot . '/ready-1') || !is_file($raceRoot . '/ready-2')) {
        checkCompletion(microtime(true) < $deadline, 'Workers no llegaron a la barrera.'); usleep(10000);
    }
    file_put_contents($raceRoot . '/start', 'start');
    $outcomes = [];
    foreach ($workers as [$worker, $workerPipes]) {
        $output = stream_get_contents($workerPipes[1]); $error = stream_get_contents($workerPipes[2]);
        fclose($workerPipes[1]); fclose($workerPipes[2]);
        checkCompletion(proc_close($worker) === 0, 'Worker fallido: ' . $error);
        $outcomes[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }
    checkCompletion($outcomes[0]['attempt'] === $outcomes[1]['attempt'] && $outcomes[0]['created'] !== $outcomes[1]['created']
        && completionCounts()['TestAttempts'] === $counts['TestAttempts'] + 1
        && completionCounts()['TestAttemptQuestions'] === $counts['TestAttemptQuestions'] + 2
        && completionCounts()['TestAttemptOptions'] === $counts['TestAttemptOptions'] + 8, 'Carrera: un 201, un 200, un intento completo.');
} finally {
    $db->exec('DROP TRIGGER IF EXISTS p10_race_delay');
    foreach (['ready-1', 'ready-2', 'start'] as $file) { if (is_file($raceRoot . '/' . $file)) { unlink($raceRoot . '/' . $file); } }
    rmdir($raceRoot);
}
checkCompletion(completionRequest('/api/auth/logout')[0] === 204, 'Logout.');
checkCompletion(completionRequest('/api/auth/login', body: ['username' => 'Virginia', 'password' => getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD')])[0] === 200, 'Login Virginia.');
$counts = completionCounts();
checkCompletion(completionRequest($finish, body: $body)[0] === 403, 'Token cruzado por HTTP.');
checkCompletion(completionRequest('/api/attempts/' . $id, 'GET')[0] === 404, 'Detalle ajeno oculto.');
$own = completionBody(completionGenerate($topicIds, 1), ['correct']);
$own['submissionId'] = $body['submissionId'];
checkCompletion(completionRequest($finish, body: $own)[0] === 409 && completionCounts() === $counts, 'UUID global no permite apropiarse del intento.');
$own['submissionId'] = completionUuid();
$ownResult = completionRequest($finish, body: $own);
checkCompletion($ownResult[0] === 201 && completionRequest('/api/attempts/' . $ownResult[1]['attemptId'], 'GET')[0] === 200, 'Intento propio Virginia.');
// Reset disposable content for the browser; keep accounts.
foreach (['TestAttempts', 'QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM ' . $table); }
$topicInsert->execute(['Tema P10 navegador']); $topic = (int) $db->lastInsertId();
foreach ([1, 2, 3] as $index) {
    $questionInsert->execute([$topic, "Pregunta P10 navegador $index"]); $id = (int) $db->lastInsertId();
    foreach ([1, 2, 3, 4] as $position) { $optionInsert->execute([$id, "Opción $position pregunta $index", $position, (int) ($position === 1)]); }
}
curl_close($client);
echo "Entrega HTTP: validación/400/401/403/409/410/415, corrección, snapshots inmutables, idempotencia, carrera real, rollback completo, 200 preguntas y aislamiento por cuenta OK\n";
