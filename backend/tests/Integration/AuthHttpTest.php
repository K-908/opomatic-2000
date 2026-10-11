<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function assertAuth(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{status: int, body: string, headers: list<string>} */
function sendAuthRequest(CurlHandle $client, string $url, string $method = 'GET', ?array $body = null, ?string $csrf = null): array
{
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    $responseHeaders = [];
    curl_setopt_array($client, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$responseHeaders): int {
            $responseHeaders[] = trim($line);
            return strlen($line);
        },
    ]);
    $result = curl_exec($client);
    if (!is_string($result)) {
        throw new RuntimeException('Falló una petición HTTP de prueba.');
    }

    return ['status' => (int) curl_getinfo($client, CURLINFO_RESPONSE_CODE), 'body' => $result, 'headers' => $responseHeaders];
}

function client(): CurlHandle
{
    $client = curl_init();
    curl_setopt_array($client, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_TIMEOUT => 5,
    ]);
    return $client;
}

function cookie(CurlHandle $client, string $name): ?string
{
    foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $line) {
        $parts = explode("\t", $line);
        if (($parts[5] ?? '') === $name) {
            return $parts[6] ?? null;
        }
    }
    return null;
}

/** @return array{exitCode: int, output: string} */
function seedAccounts(string $firstPassword, string $secondPassword): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el alta de prueba.');
    }
    fwrite($pipes[0], $firstPassword . "\n" . $secondPassword . "\n");
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exitCode' => proc_close($process), 'output' => $output];
}

$baseUrl = $argv[1] ?? '';
assertAuth(str_starts_with($baseUrl, 'http://127.0.0.1:'), 'La prueba exige un servidor local.');
$config = DatabaseConfig::fromEnvironment();
assertAuth(str_starts_with($config->name, 'opomatic_p03_'), 'La prueba exige una base temporal.');
$firstPassword = bin2hex(random_bytes(24));
$secondPassword = bin2hex(random_bytes(24));
assertAuth(seedAccounts($firstPassword, $secondPassword)['exitCode'] === 0, 'No se pudieron crear las cuentas de prueba.');

$anonymous = client();
$gogloglo = client();
$virginia = client();
assertAuth(sendAuthRequest($anonymous, $baseUrl . '/health')['status'] === 200, 'GET /health debe ser público.');
assertAuth(sendAuthRequest($anonymous, $baseUrl . '/api/auth/session')['status'] === 401, 'La sesión anónima debe devolver 401.');

$firstLogin = sendAuthRequest($gogloglo, $baseUrl . '/api/auth/login', 'POST', ['username' => 'Gogloglo', 'password' => $firstPassword]);
assertAuth($firstLogin['status'] === 200 && json_decode($firstLogin['body'], true)['username'] === 'Gogloglo', 'El login de Gogloglo debe funcionar.');
assertAuth(cookie($gogloglo, 'OPOMATICSESSID') !== null && cookie($gogloglo, 'OPOMATIC_CSRF') !== null, 'Faltan las cookies de sesión y CSRF.');
assertAuth(count(array_filter($firstLogin['headers'], static fn (string $header): bool => str_starts_with(strtolower($header), 'set-cookie: opomaticsessid=') && str_contains(strtolower($header), 'httponly') && str_contains(strtolower($header), 'samesite=lax'))) === 1, 'La cookie de sesión debe ser HttpOnly y SameSite=Lax.');
assertAuth(sendAuthRequest($gogloglo, $baseUrl . '/api/auth/session')['status'] === 200, 'La sesión de Gogloglo debe recuperarse.');

$secondLogin = sendAuthRequest($virginia, $baseUrl . '/api/auth/login', 'POST', ['username' => 'Virginia', 'password' => $secondPassword]);
assertAuth($secondLogin['status'] === 200, 'El login de Virginia debe funcionar.');
$firstAccesses = sendAuthRequest($gogloglo, $baseUrl . '/api/auth/accesses');
$secondAccesses = sendAuthRequest($virginia, $baseUrl . '/api/auth/accesses');
$firstList = json_decode($firstAccesses['body'], true);
$secondList = json_decode($secondAccesses['body'], true);
assertAuth($firstAccesses['status'] === 200 && count($firstList) === 1 && $firstList[0]['ipAddress'] === '127.0.0.1', 'Gogloglo solo debe ver su acceso.');
assertAuth($secondAccesses['status'] === 200 && count($secondList) === 1 && $secondList[0]['ipAddress'] === '127.0.0.1', 'Virginia solo debe ver su acceso.');
assertAuth(str_ends_with($firstList[0]['accessedAtUtc'], 'Z'), 'Las fechas de acceso deben estar en UTC ISO 8601.');

$db = (new PdoConnectionFactory())->connect($config);
$extraAccess = $db->prepare(
    'INSERT INTO `LoginAccesses` (`Username`, `AccessedAtUtc`, `IpAddress`) VALUES (:username, UTC_TIMESTAMP(6), :ip)',
);
for ($index = 0; $index < 55; $index++) {
    $extraAccess->execute(['username' => 'Gogloglo', 'ip' => '127.0.0.1']);
}
assertAuth(count(json_decode(sendAuthRequest($gogloglo, $baseUrl . '/api/auth/accesses')['body'], true)) === 50, 'El historial debe limitarse a 50 accesos.');
assertAuth(count(json_decode(sendAuthRequest($virginia, $baseUrl . '/api/auth/accesses')['body'], true)) === 1, 'Una cuenta no debe ver accesos de otra.');

$withoutCsrf = sendAuthRequest($virginia, $baseUrl . '/api/auth/logout', 'POST');
assertAuth($withoutCsrf['status'] === 403, 'Logout sin CSRF debe devolver 403.');
$wrongCsrf = sendAuthRequest($virginia, $baseUrl . '/api/auth/logout', 'POST', csrf: 'incorrecto');
assertAuth($wrongCsrf['status'] === 403, 'Logout con CSRF incorrecto debe devolver 403.');
assertAuth(sendAuthRequest($virginia, $baseUrl . '/api/auth/session')['status'] === 200, 'CSRF fallido no debe cerrar sesión.');
$logout = sendAuthRequest($virginia, $baseUrl . '/api/auth/logout', 'POST', csrf: cookie($virginia, 'OPOMATIC_CSRF'));
assertAuth($logout['status'] === 204, 'Logout con CSRF correcto debe devolver 204.');
assertAuth(sendAuthRequest($virginia, $baseUrl . '/api/auth/session')['status'] === 401, 'La sesión cerrada debe devolver 401.');

$sessionId = cookie($gogloglo, 'OPOMATICSESSID');
assertAuth(is_string($sessionId), 'Falta el identificador de sesión.');
ini_set('session.save_path', getenv('AUTH_STORAGE_DIR') . '/sessions');
session_name('OPOMATICSESSID');
session_id($sessionId);
session_start();
assertAuth(($_SESSION['auth']['expiresAt'] ?? 0) - time() <= 43200, 'La sesión no debe superar doce horas.');
$_SESSION['auth']['expiresAt'] = time() - 1;
session_write_close();
assertAuth(sendAuthRequest($gogloglo, $baseUrl . '/api/auth/session')['status'] === 401, 'La sesión caducada debe devolver 401.');

$invalidUser = sendAuthRequest($anonymous, $baseUrl . '/api/auth/login', 'POST', ['username' => 'NoExiste', 'password' => bin2hex(random_bytes(8))]);
$invalidPassword = sendAuthRequest($anonymous, $baseUrl . '/api/auth/login', 'POST', ['username' => 'Gogloglo', 'password' => bin2hex(random_bytes(8))]);
assertAuth($invalidUser['status'] === 401 && $invalidPassword['status'] === 401 && $invalidUser['body'] === $invalidPassword['body'], 'Las credenciales incorrectas deben tener respuesta genérica.');
assertAuth(sendAuthRequest($anonymous, $baseUrl . '/api/auth/login', 'POST', ['username' => 'NoExiste', 'password' => 'x'])['status'] === 401, 'El quinto intento debe evaluarse.');
$limited = sendAuthRequest($anonymous, $baseUrl . '/api/auth/login', 'POST', ['username' => 'Gogloglo', 'password' => $firstPassword]);
assertAuth($limited['status'] === 429, 'El sexto intento por IP debe limitarse.');
assertAuth(in_array('Retry-After: 60', $limited['headers'], true), '429 debe incluir Retry-After.');

assertAuth((int) $db->query('SELECT COUNT(*) FROM `LoginAccesses`')->fetchColumn() === 57, 'Solo los dos logins correctos y los accesos de prueba deben registrarse.');
curl_close($anonymous);
curl_close($gogloglo);
curl_close($virginia);
echo "Auth HTTP: dos cuentas aisladas, sesiones, 12 h, logout, CSRF, 429 y accesos UTC OK\n";
