<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkQuestion(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment();
$base = $argv[1] ?? '';
checkQuestion(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases temporales locales.');
$db = (new PdoConnectionFactory())->connect($config);
foreach (['QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM `' . $table . '`'); }
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
checkQuestion(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 5]);
function qRequest(string $path, string $method = 'GET', array|string|null $body = null, bool $csrf = true, string $contentType = 'application/json'): array
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
        CURLOPT_POSTFIELDS => is_array($body) ? json_encode((object) $body, JSON_THROW_ON_ERROR) : $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$responseHeaders): int { $responseHeaders[] = trim($line); return strlen($line); }]);
    $raw = curl_exec($client);
    checkQuestion(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $responseHeaders];
}
function questionBody(int $topic, string $statement = '¿Cuál es la capital?'): array
{
    return ['topicId' => $topic, 'statement' => $statement, 'options' => array_map(static fn (int $position): array => ['position' => $position, 'text' => 'Respuesta ' . $position, 'isCorrect' => $position === 2], [1, 2, 3, 4])];
}
function databaseCounts(): array
{
    global $db;
    return [(int) $db->query('SELECT COUNT(*) FROM `Questions`')->fetchColumn(), (int) $db->query('SELECT COUNT(*) FROM `QuestionOptions`')->fetchColumn()];
}
checkQuestion(qRequest('/api/questions')[0] === 401, 'Preguntas requiere sesión.');
checkQuestion(qRequest('/api/auth/login', 'POST', ['username' => 'Gogloglo', 'password' => getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD')])[0] === 200, 'Login fallido.');
$topic = qRequest('/api/topics', 'POST', ['name' => 'Tema P07'])[1]['id'];
$otherTopic = qRequest('/api/topics', 'POST', ['name' => 'Otro tema P07'])[1]['id'];
$empty = qRequest('/api/questions')[1];
checkQuestion($empty === ['items' => [], 'page' => 1, 'pageSize' => 20, 'totalItems' => 0, 'totalPages' => 0], 'Página inicial vacía.');
$valid = questionBody($topic);
checkQuestion(qRequest('/api/questions', 'POST', $valid, false)[0] === 403, 'Escrituras exigen CSRF.');
$invalidBodies = [[]];
foreach (['topicId' => '1', 'statement' => ' ', 'options' => []] as $key => $value) { $invalid = $valid; $invalid[$key] = $value; $invalidBodies[] = $invalid; }
$invalid = $valid; $invalid['options'][3]['position'] = 1; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][3]['position'] = '4'; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][0]['isCorrect'] = true; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][1]['isCorrect'] = false; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][1]['isCorrect'] = 'true'; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][2]['text'] = " \t "; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][2] = null; $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['statement'] = str_repeat('á', 10001); $invalidBodies[] = $invalid;
$invalid = $valid; $invalid['options'][0]['text'] = str_repeat('á', 1001); $invalidBodies[] = $invalid;
foreach ($invalidBodies as $invalid) {
    $response = qRequest('/api/questions', 'POST', $invalid);
    checkQuestion($response[0] === 400 && isset($response[1]['errors']) && databaseCounts() === [0, 0], 'Entradas inválidas: 400 sin filas parciales.');
}
checkQuestion(qRequest('/api/questions', 'POST', '{invalid')[0] === 400, 'JSON mal formado.');
checkQuestion(qRequest('/api/questions', 'POST', $valid, contentType: 'text/plain')[0] === 415, 'Tipo de contenido.');
checkQuestion(qRequest('/api/questions', 'POST', questionBody(2147483647))[0] === 404, 'Tema inexistente.');
$created = qRequest('/api/questions', 'POST', questionBody($topic, " \t¿Cuál es la capital?\n "));
checkQuestion($created[0] === 201 && $created[1]['statement'] === '¿Cuál es la capital?' && $created[1]['isActive'] === true && count($created[1]['options']) === 4, 'Alta y normalización.');
$id = $created[1]['id']; $original = $created[1];
checkQuestion(in_array('Location: /api/questions/' . $id, $created[2], true), 'Location de alta.');
$originalIds = array_column($original['options'], 'id');
checkQuestion(databaseCounts() === [1, 4] && count(array_filter($original['options'], static fn ($option) => $option['isCorrect'])) === 1, 'Cuatro opciones y una correcta.');
// Snapshot persisted through the real FKs to prove edits preserve historical content.
$db->exec("INSERT INTO `TestAttempts` (`SubmissionId`,`CompletedAtUtc`,`TotalQuestions`,`CorrectCount`,`IncorrectCount`,`UnansweredCount`,`Score`,`Username`) VALUES ('00000000-0000-4000-8000-000000000007',UTC_TIMESTAMP(6),1,1,0,0,10000000,'Gogloglo')");
$attemptId = (int) $db->lastInsertId();
$query = $db->prepare('INSERT INTO `TestAttemptTopics` (`TestAttemptId`,`OriginalTopicId`,`TopicNameSnapshot`) VALUES (:attempt,:topic,:name)');
$query->execute(['attempt' => $attemptId, 'topic' => $topic, 'name' => 'Tema P07']);
$query = $db->prepare('INSERT INTO `TestAttemptQuestions` (`TestAttemptId`,`OriginalQuestionId`,`OriginalTopicId`,`DisplayOrder`,`StatementSnapshot`,`TopicNameSnapshot`,`Result`) VALUES (:attempt,:question,:topic,1,:statement,:name,0)');
$query->execute(['attempt' => $attemptId, 'question' => $id, 'topic' => $topic, 'statement' => $original['statement'], 'name' => 'Tema P07']);
$snapshotId = (int) $db->lastInsertId();
$query = $db->prepare('INSERT INTO `TestAttemptOptions` (`TestAttemptQuestionId`,`OriginalOptionId`,`TextSnapshot`,`DisplayOrder`,`IsCorrect`,`IsSelected`) VALUES (:snapshot,:original,:text,:position,:correct,:selected)');
foreach ($original['options'] as $option) { $query->execute(['snapshot' => $snapshotId, 'original' => $option['id'], 'text' => $option['text'], 'position' => $option['position'], 'correct' => (int) $option['isCorrect'], 'selected' => (int) $option['isCorrect']]); }
$historicalQuestions = $db->query('SELECT * FROM `TestAttemptQuestions`')->fetchAll();
$historicalOptions = $db->query('SELECT * FROM `TestAttemptOptions`')->fetchAll();
$edit = questionBody($otherTopic, 'Enunciado editado');
$edit['options'][1]['isCorrect'] = false; $edit['options'][3]['isCorrect'] = true;
$edit['options'][0]['text'] = 'Primera opción editada';
$edit['options'] = array_reverse($edit['options']);
$updated = qRequest('/api/questions/' . $id, 'PUT', $edit);
checkQuestion($updated[0] === 200 && $updated[1]['topicId'] === $otherTopic && array_column($updated[1]['options'], 'id') === $originalIds && $updated[1]['options'][3]['isCorrect'] === true, 'Edición por posición conserva IDs.');
$invalidEdit = $edit; $invalidEdit['options'] = [];
checkQuestion(qRequest('/api/questions/' . $id, 'PUT', $invalidEdit)[0] === 400 && qRequest('/api/questions/' . $id)[1] === $updated[1], 'Edición inválida conserva datos.');
// Force a DB failure after earlier options were saved, on create and update.
$db->exec("CREATE TRIGGER p07_fail_insert BEFORE INSERT ON `QuestionOptions` FOR EACH ROW BEGIN IF NEW.Position = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'temporary test failure'; END IF; END");
$before = databaseCounts();
$failed = qRequest('/api/questions', 'POST', $valid);
checkQuestion($failed[0] === 500 && isset($failed[1]['traceId']) && databaseCounts() === $before && !str_contains(json_encode($failed[1]), 'temporary test failure'), 'Rollback de alta y error genérico.');
$db->exec('DROP TRIGGER p07_fail_insert');
$db->exec("CREATE TRIGGER p07_fail_update BEFORE UPDATE ON `QuestionOptions` FOR EACH ROW BEGIN IF NEW.Position = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'temporary test failure'; END IF; END");
checkQuestion(qRequest('/api/questions/' . $id, 'PUT', questionBody($topic, 'Debe revertirse'))[0] === 500 && qRequest('/api/questions/' . $id)[1] === $updated[1], 'Rollback de enunciado, tema y opciones.');
$db->exec('DROP TRIGGER p07_fail_update');
$extra = qRequest('/api/questions', 'POST', questionBody($topic, 'IVA 21% y_caso'))[1]['id'];
$page = qRequest('/api/questions?pageSize=1')[1];
checkQuestion(count($page['items']) === 1 && $page['totalItems'] === 2 && $page['totalPages'] === 2, 'Paginación.');
checkQuestion(qRequest('/api/questions?page=2&pageSize=1')[1]['items'][0]['id'] !== $page['items'][0]['id'], 'Páginas sin repetidos.');
checkQuestion(qRequest('/api/questions?page=99')[1]['items'] === [], 'Página fuera del rango.');
checkQuestion(qRequest('/api/questions?topicId=' . $topic)[1]['totalItems'] === 1, 'Filtro por tema.');
checkQuestion(qRequest('/api/questions?search=%25')[1]['totalItems'] === 1 && qRequest('/api/questions?search=_')[1]['totalItems'] === 1, 'Búsqueda literal de comodines.');
checkQuestion(qRequest('/api/questions?search=ENUNCIADO%20EDITADO')[1]['totalItems'] === 1, 'Búsqueda por texto sin mayúsculas.');
checkQuestion(str_ends_with($page['items'][0]['updatedAtUtc'], 'Z') && !isset($page['items'][0]['options']), 'Lista UTC sin opciones correctas.');
foreach (['page=0', 'pageSize=101', 'topicId=-1', 'page[]=1', 'search[]=x', 'includeInactive=1', 'page=abc'] as $filter) {
    checkQuestion(qRequest('/api/questions?' . $filter)[0] === 400, 'Filtro inválido: ' . $filter);
}
foreach (['0', 'abc', '2147483647'] as $missing) { checkQuestion(qRequest('/api/questions/' . $missing)[0] === 404, 'Detalle inexistente.'); }
checkQuestion(qRequest('/api/questions/2147483647', 'PUT', $valid)[0] === 404, 'Edición inexistente.');
checkQuestion(qRequest('/api/questions/' . $id . '/status', 'PATCH', ['isActive' => 'false'])[0] === 400, 'Estado booleano.');
checkQuestion(qRequest('/api/questions/' . $id . '/status', 'PATCH', ['isActive' => false])[0] === 204, 'Desactivación lógica.');
checkQuestion(qRequest('/api/questions')[1]['totalItems'] === 1 && qRequest('/api/questions?includeInactive=true')[1]['totalItems'] === 2, 'Filtro de inactivas.');
checkQuestion(qRequest('/api/topics/' . $otherTopic . '/status', 'PATCH', ['isActive' => false])[0] === 204, 'Tema inactivo.');
checkQuestion(qRequest('/api/questions/' . $id . '/status', 'PATCH', ['isActive' => true])[0] === 409, 'No reactivar en tema inactivo.');
checkQuestion(qRequest('/api/questions', 'POST', questionBody($otherTopic))[0] === 409, 'No crear en tema inactivo.');
checkQuestion(qRequest('/api/questions/' . $id, 'PUT', questionBody($otherTopic))[0] === 409, 'No editar hacia tema inactivo.');
qRequest('/api/topics/' . $otherTopic . '/status', 'PATCH', ['isActive' => true]);
checkQuestion(qRequest('/api/questions/' . $id . '/status', 'PATCH', ['isActive' => true])[0] === 204, 'Reactivación.');
checkQuestion(qRequest('/api/questions/' . $id, 'DELETE')[0] === 405, 'Sin borrado físico.');
checkQuestion($db->query('SELECT * FROM `TestAttemptQuestions`')->fetchAll() === $historicalQuestions && $db->query('SELECT * FROM `TestAttemptOptions`')->fetchAll() === $historicalOptions, 'Snapshots históricos conservados.');
$limitBody = questionBody($topic, str_repeat('á', 10000)); $limitBody['options'][0]['text'] = str_repeat('á', 1000);
checkQuestion(qRequest('/api/questions', 'POST', $limitBody)[0] === 201, 'Límites Unicode inclusivos.');
$firstClient = $client; $client = curl_init(); curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 5]);
checkQuestion(qRequest('/api/auth/login', 'POST', ['username' => 'Virginia', 'password' => getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD')])[0] === 200 && qRequest('/api/questions/' . $id)[0] === 200, 'Banco compartido entre cuentas.');
curl_close($firstClient); curl_close($client);
// Leave only two active topics for the browser journey.
$db->exec('DELETE FROM `TestAttemptOptions`'); $db->exec('DELETE FROM `TestAttemptQuestions`'); $db->exec('DELETE FROM `TestAttemptTopics`'); $db->exec('DELETE FROM `TestAttempts`');
$db->exec('DELETE FROM `QuestionOptions`'); $db->exec('DELETE FROM `Questions`');
echo "Questions HTTP: validación, filtros, paginación, cuatro opciones, IDs, rollback, snapshots y estados OK\n";
