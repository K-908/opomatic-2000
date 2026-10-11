<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkTopic(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$config = DatabaseConfig::fromEnvironment();
$base = $argv[1] ?? '';
checkTopic(str_starts_with($config->name, 'opomatic_p03_') && str_starts_with($base, 'http://127.0.0.1:'), 'Solo bases y servidores temporales locales.');
// DatabaseTest leaves its topic/question fixtures in this disposable database.
$db = (new PdoConnectionFactory())->connect($config);
foreach (['QuestionOptions', 'Questions', 'Topics'] as $table) { $db->exec('DELETE FROM `' . $table . '`'); }
$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD') . "\n" . getenv('OPOMATIC_E2E_VIRGINIA_PASSWORD') . "\n");
fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
checkTopic(proc_close($process) === 0, 'Alta temporal fallida.');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 5]);
function topicRequest(string $path, string $method = 'GET', ?array $body = null, bool $csrf = true): array
{
    global $client, $base;
    $headers = ['Content-Type: application/json'];
    if ($csrf) {
        foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'OPOMATIC_CSRF') { $headers[] = 'X-CSRF-Token: ' . $parts[6]; }
        }
    }
    $responseHeaders = [];
    curl_setopt_array($client, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode((object) $body, JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$responseHeaders): int { $responseHeaders[] = trim($line); return strlen($line); }]);
    $raw = curl_exec($client);
    checkTopic(is_string($raw), 'HTTP fallido.');
    return [(int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), $raw === '' ? null : json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $responseHeaders];
}
checkTopic(topicRequest('/api/topics')[0] === 401, 'Temas exige sesión.');
checkTopic(topicRequest('/api/auth/login', 'POST', ['username' => 'Gogloglo', 'password' => getenv('OPOMATIC_E2E_GOGLOGLO_PASSWORD')])[0] === 200, 'Login fallido.');
checkTopic(topicRequest('/api/topics')[1] === [], 'Lista inicial vacía.');
checkTopic(topicRequest('/api/topics', 'POST', ['name' => 'Sin CSRF'], false)[0] === 403, 'Escrituras exigen CSRF.');
foreach ([[], ['name' => null], ['name' => 123], ['name' => " \t\n"], ['name' => str_repeat('á', 151)]] as $body) {
    $invalid = topicRequest('/api/topics', 'POST', $body);
    checkTopic($invalid[0] === 400 && isset($invalid[1]['errors']['name']), 'Validación name 400.');
}
$created = topicRequest('/api/topics', 'POST', ['name' => " \tConstitución\n "]);
checkTopic($created[0] === 201 && $created[1]['name'] === 'Constitución' && $created[1]['isActive'] === true && $created[1]['activeQuestionCount'] === 0, 'Alta normalizada con tipos JSON.');
$id = $created[1]['id'];
checkTopic(in_array('Location: /api/topics/' . $id, $created[2], true), 'Location de alta.');
checkTopic(topicRequest('/api/topics', 'POST', ['name' => ' CONSTITUCIÓN '])[0] === 409, 'Duplicado por mayúsculas.');
checkTopic(topicRequest('/api/topics', 'POST', ['name' => 'Constitucion'])[0] === 409, 'Colación ignora acentos.');
$other = topicRequest('/api/topics', 'POST', ['name' => 'Administrativo'])[1]['id'];
checkTopic(topicRequest('/api/topics/' . $other, 'PUT', ['name' => 'Constitución'])[0] === 409, 'Edición duplicada.');
checkTopic(topicRequest('/api/topics/' . $id, 'PUT', ['name' => '  Constitución revisada  '])[1]['name'] === 'Constitución revisada', 'Edición normalizada.');
checkTopic(topicRequest('/api/topics/' . $id, 'PUT', ['name' => 'Constitución revisada'])[0] === 200, 'Edición sin cambios.');
foreach (['0', '-1', 'abc', '2147483648', '2147483647'] as $missing) {
    checkTopic(topicRequest('/api/topics/' . $missing)[0] === 404, 'Identificador inválido o ausente.');
}
checkTopic(topicRequest('/api/topics/2147483647', 'PUT', ['name' => 'Ausente'])[0] === 404, 'Edición ausente.');
checkTopic(topicRequest('/api/topics/2147483647/status', 'PATCH', ['isActive' => false])[0] === 404, 'Estado ausente.');
checkTopic(topicRequest('/api/topics?includeInactive=invalid')[0] === 400, 'Filtro inválido.');
checkTopic(topicRequest('/api/topics?includeInactive[]=true')[0] === 400, 'Filtro no escalar.');
checkTopic(topicRequest('/api/topics/' . $id . '/status', 'PATCH', ['isActive' => 'false'])[0] === 400, 'Estado exige booleano.');
$db = (new PdoConnectionFactory())->connect($config);
$insert = $db->prepare('INSERT INTO `Questions` (`TopicId`, `Statement`, `IsActive`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:id, :text, :active, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
foreach ([1, 0] as $active) { $insert->execute(['id' => $id, 'text' => 'Pregunta temporal', 'active' => $active]); }
checkTopic(topicRequest('/api/topics/' . $id)[1]['activeQuestionCount'] === 1, 'Cuenta solo preguntas activas.');
checkTopic(topicRequest('/api/topics/' . $id . '/status', 'PATCH', ['isActive' => false])[0] === 204, 'Desactivar 204.');
checkTopic(count(topicRequest('/api/topics')[1]) === 1 && count(topicRequest('/api/topics?includeInactive=true')[1]) === 2, 'Filtrado de inactivos.');
checkTopic(topicRequest('/api/topics/' . $id)[1]['isActive'] === false, 'Detalle inactivo accesible.');
checkTopic(topicRequest('/api/topics', 'POST', ['name' => 'Constitución revisada'])[0] === 409, 'Inactivos conservan unicidad.');
checkTopic((int) $db->query('SELECT COUNT(*) FROM `Questions` WHERE `IsActive` = 1')->fetchColumn() === 1, 'Desactivar no cambia preguntas.');
checkTopic(topicRequest('/api/topics/' . $id . '/status', 'PATCH', ['isActive' => true])[0] === 204, 'Reactivar 204.');
checkTopic(topicRequest('/api/topics/' . $id . '/status', 'PATCH', ['isActive' => true])[0] === 204, 'Estado idempotente.');
checkTopic(topicRequest('/api/topics/' . $id, 'DELETE')[0] === 405, 'Sin borrado físico.');
$limit = topicRequest('/api/topics', 'POST', ['name' => str_repeat('á', 150)]);
checkTopic($limit[0] === 201, 'Límite Unicode de 150 caracteres.');
$db->exec('DELETE FROM `Questions`');
$db->exec('DELETE FROM `Topics`');
curl_close($client);
echo "Topics HTTP: CRUD lógico, validación, Unicode, unicidad, 400/404/409, CSRF y preguntas conservadas OK\n";
