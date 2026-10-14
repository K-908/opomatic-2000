<?php

declare(strict_types=1);

use OpoMatic2000\Domain\QuestionImportDocument;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkImport(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment(); $base = $argv[1] ?? '';
checkImport(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases temporales locales.');
$db = (new PdoConnectionFactory())->connect($config);
foreach (['QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM `' . $table . '`'); }
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
checkImport(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init(); curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 60]);
function iRequest(string $path, string $method = 'POST', array|string|null $body = null, bool $csrf = true, string $contentType = 'application/json'): array
{
    global $client, $base;
    $headers = ['Content-Type: ' . $contentType, 'Expect:'];
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
    $raw = curl_exec($client); checkImport(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $responseHeaders];
}
function importQuestion(string $statement = 'Pregunta importada', int $correct = 2): array
{
    return ['statement' => $statement, 'options' => ['Primera', 'Segunda', 'Tercera', 'Cuarta'], 'correctOption' => $correct];
}
function importTopic(string $name, ?array $questions = null): array { return ['name' => $name, 'questions' => $questions ?? [importQuestion()]]; }
function importCounts(): array
{
    global $db;
    return array_map(static fn ($table) => (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(), ['Topics', 'Questions', 'QuestionOptions']);
}
$path = '/api/questions/import';
checkImport(iRequest($path, body: ['topics' => []])[0] === 401, 'Importar requiere sesión.');
checkImport(iRequest('/api/auth/login', body: ['username' => 'Gogloglo', 'password' => getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD')])[0] === 200, 'Login fallido.');
$existingId = iRequest('/api/topics', body: ['name' => 'Tema P08 existente'])[1]['id'];
$inactiveId = iRequest('/api/topics', body: ['name' => 'Tema P08 inactivo'])[1]['id'];
iRequest('/api/topics/' . $inactiveId . '/status', 'PATCH', ['isActive' => false]);
$baselineTopics = $db->query('SELECT * FROM `Topics` ORDER BY `Id`')->fetchAll();
$baseline = importCounts();
$valid = ['topics' => [importTopic('Tema nuevo P08'), importTopic(' TEMA P08 EXISTENTE ')]];
checkImport(iRequest($path, body: $valid, csrf: false)[0] === 403, 'CSRF obligatorio.');
checkImport(iRequest($path, body: '{invalid')[0] === 400, 'JSON inválido.');
checkImport(iRequest($path, body: '[]')[0] === 400, 'Objeto JSON obligatorio.');
checkImport(iRequest($path, body: $valid, contentType: 'text/plain')[0] === 415, 'Content-Type JSON.');
$invalidDocuments = [[], ['topics' => []], ['topics' => (object) []], ['topics' => [null]], ['topics' => [importTopic('Sin preguntas', [])]]];
$invalid = $valid; $invalid['topics'][1]['name'] = str_repeat('á', 301); $invalidDocuments[] = $invalid;
$invalid = $valid; $invalid['topics'][1]['questions'][0]['statement'] = ' '; $invalidDocuments[] = $invalid;
$invalid = $valid; $invalid['topics'][1]['questions'][0]['statement'] = str_repeat('á', 10001); $invalidDocuments[] = $invalid;
$invalid = $valid; $invalid['topics'][1]['questions'][0]['options'] = ['Uno']; $invalidDocuments[] = $invalid;
$invalid = $valid; $invalid['topics'][1]['questions'][0]['options'][0] = null; $invalidDocuments[] = $invalid;
$invalid = $valid; $invalid['topics'][1]['questions'][0]['options'][3] = str_repeat('á', 1001); $invalidDocuments[] = $invalid;
foreach ([0, 5, '2', true, 2.5] as $correct) { $invalid = $valid; $invalid['topics'][1]['questions'][0]['correctOption'] = $correct; $invalidDocuments[] = $invalid; }
foreach ($invalidDocuments as $invalid) {
    $response = iRequest($path, body: $invalid);
    checkImport($response[0] === 400 && isset($response[1]['errors']) && importCounts() === $baseline, 'Documento inválido sin datos parciales.');
}
$invalid = $valid; $invalid['topics'][1]['questions'][0]['options'][3] = ' ';
$response = iRequest($path, body: $invalid);
checkImport(isset($response[1]['errors']['topics[1].questions[0].options[3]']), 'Ruta precisa de opción (índice cero).');
$invalid = $valid; $invalid['topics'][0]['questions'][0]['statement'] = ''; $invalid['topics'][1]['questions'][0]['correctOption'] = 0;
$response = iRequest($path, body: $invalid);
checkImport(isset($response[1]['errors']['topics[0].questions[0].statement'], $response[1]['errors']['topics[1].questions[0].correctOption']), 'Validación del documento completo.');
foreach ([['Duplicado', ' DUPLICADO '], ['Álgebra P08', 'ALGEBRA P08']] as $names) {
    $response = iRequest($path, body: ['topics' => [importTopic($names[0]), importTopic($names[1])]]);
    checkImport($response[0] === 400 && isset($response[1]['errors']['topics[1].name']) && importCounts() === $baseline, 'Duplicados según colación antes de escribir.');
}
$response = iRequest($path, body: ['topics' => [importTopic('Debe revertir tema nuevo'), importTopic(' TEMA P08 INACTIVO ')]]);
checkImport($response[0] === 409 && isset($response[1]['errors']['topics[1].name']) && importCounts() === $baseline && $db->query('SELECT * FROM `Topics` ORDER BY `Id`')->fetchAll() === $baselineTopics, 'Tema inactivo revierte todo sin reactivarlo.');
$tooMany = ['topics' => [importTopic('Demasiadas', array_fill(0, 2001, importQuestion()))]];
checkImport(iRequest($path, body: $tooMany)[0] === 400 && importCounts() === $baseline, 'Máximo de 2.000 preguntas.');
$valid['topics'][0]['questions'][] = importQuestion('Pregunta importada', 4);
$response = iRequest($path, body: $valid);
checkImport($response[0] === 201 && $response[1] === ['createdTopics' => 1, 'reusedTopics' => 1, 'importedQuestions' => 3] && importCounts() === [3, 3, 12], 'Resumen y filas de alta/reutilización.');
checkImport($db->query('SELECT COUNT(*) FROM `QuestionOptions` WHERE `IsCorrect` = 1')->fetchColumn() == 3, 'Una correcta por pregunta.');
checkImport($db->query('SELECT * FROM `Topics` WHERE `Id` = ' . $existingId)->fetch() === $baselineTopics[0], 'Reutilización conserva nombre, estado y fechas.');
$response = iRequest($path, body: $valid);
checkImport($response[0] === 201 && $response[1] === ['createdTopics' => 0, 'reusedTopics' => 2, 'importedQuestions' => 3] && importCounts() === [3, 6, 24], 'Repetir importa enunciados sin deduplicarlos.');
// Fail after a previous question and its options have been inserted.
$db->exec("CREATE TRIGGER p08_fail_option BEFORE INSERT ON `QuestionOptions` FOR EACH ROW BEGIN IF NEW.Text = 'Fallo P08' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'temporary import failure'; END IF; END");
$before = importCounts(); $beforeTopics = $db->query('SELECT * FROM `Topics` ORDER BY `Id`')->fetchAll();
$failedTopic = importTopic('Todo debe revertirse', [importQuestion('Primera transaccional'), importQuestion('Segunda transaccional')]);
$failedTopic['questions'][1]['options'][2] = 'Fallo P08';
$response = iRequest($path, body: ['topics' => [importTopic('Tema P08 existente'), $failedTopic]]);
checkImport($response[0] === 500 && isset($response[1]['traceId']) && !str_contains(json_encode($response[1]), 'temporary import failure') && importCounts() === $before && $db->query('SELECT * FROM `Topics` ORDER BY `Id`')->fetchAll() === $beforeTopics, 'Rollback real de temas, preguntas y opciones.');
$db->exec('DROP TRIGGER p08_fail_option');
$small = json_encode(['topics' => [importTopic('Tema P08 existente')]], JSON_THROW_ON_ERROR);
$exact = $small . str_repeat(' ', QuestionImportDocument::MAXIMUM_BYTES - strlen($small));
checkImport(iRequest($path, body: $exact)[0] === 201, 'Exactamente 5 MB admitidos.');
$before = importCounts();
checkImport(iRequest($path, body: $exact . ' ')[0] === 413 && importCounts() === $before, '5 MB + 1 byte rechazados sin escribir.');
unset($exact);
$response = iRequest($path, body: ['topics' => [importTopic('Tema P08 existente', array_fill(0, 2000, importQuestion('Límite de preguntas')))]]);
checkImport($response[0] === 201 && $response[1]['importedQuestions'] === 2000, 'Exactamente 2.000 preguntas admitidas.');
$manyTopics = array_map(static fn (int $index): array => importTopic('Tema masivo P08 ' . $index), range(1, 2000));
$response = iRequest($path, body: ['topics' => $manyTopics]);
checkImport($response[0] === 201 && $response[1] === ['createdTopics' => 2000, 'reusedTopics' => 0, 'importedQuestions' => 2000], '2.000 preguntas distribuidas entre 2.000 temas.');
$longName = str_repeat('á', 300);
$longImport = iRequest($path, body: ['topics' => [importTopic($longName)]]);
$storedLength = $db->prepare('SELECT CHAR_LENGTH(`Name`) FROM `Topics` WHERE `Name` = :name');
$storedLength->execute(['name' => $longName]);
checkImport($longImport[0] === 201 && (int) $storedLength->fetchColumn() === 300, 'Importación con nombre Unicode de 300 caracteres.');
$threeOptionQuestion = importQuestion('Pregunta con tres opciones', 3);
$threeOptionQuestion['options'] = ['Primera', 'Segunda', 'Tercera'];
$threeImport = iRequest($path, body: ['topics' => [importTopic('Tema P08 existente', [$threeOptionQuestion])]]);
$optionCount = $db->query("SELECT COUNT(*) FROM `QuestionOptions` o JOIN `Questions` q ON q.`Id` = o.`QuestionId` WHERE q.`Statement` = 'Pregunta con tres opciones'")->fetchColumn();
checkImport($threeImport[0] === 201 && $threeImport[1]['importedQuestions'] === 1 && (int) $optionCount === 3, 'La importación admite tres opciones.');
// Restore the two initial topics for the browser; all data belongs to this disposable DB.
$db->exec('DELETE FROM `QuestionOptions`'); $db->exec('DELETE FROM `Questions`');
$query = $db->prepare('DELETE FROM `Topics` WHERE `Id` NOT IN (:existing, :inactive)'); $query->execute(['existing' => $existingId, 'inactive' => $inactiveId]);
curl_close($client);
echo "Import HTTP: rutas de error, temas reutilizados/inactivos, duplicados, 5 MB, 2.000 preguntas y rollback completo OK\n";
