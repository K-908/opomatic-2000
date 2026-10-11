<?php
declare(strict_types=1);

use OpoMatic2000\Domain\TestTokenFailure;
use OpoMatic2000\Infrastructure\TestTokenProtector;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkToken(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function rejectsToken(callable $action, string $reason): void
{
    try { $action(); } catch (TestTokenFailure $error) {
        checkToken($error->reason === $reason, 'Motivo de rechazo incorrecto.'); return;
    }
    throw new RuntimeException('Se aceptó un token inválido.');
}
$key = random_bytes(32); $now = 1700000000;
$clock = static function () use (&$now): int { return $now; };
$protector = new TestTokenProtector($key, $clock);
$questions = [['questionId' => 21, 'displayOrder' => 1, 'options' => [
    ['optionId' => 104, 'displayOrder' => 1], ['optionId' => 102, 'displayOrder' => 2],
    ['optionId' => 101, 'displayOrder' => 3], ['optionId' => 103, 'displayOrder' => 4]]]];
$issued = $protector->issue('Gogloglo', [9], $questions);
$payload = $protector->verify($issued['testToken'], 'Gogloglo');
checkToken($payload['questions'] === $questions && $payload['topicIds'] === [9]
    && $payload['username'] === 'Gogloglo' && $payload['expiresAt'] === $now + 43200, 'Claims y orden conservados.');
checkToken($issued['expiresAtUtc'] === gmdate('Y-m-d\TH:i:s\Z', $now + 43200), 'Expiración UTC exacta.');
checkToken((new TestTokenProtector($key, $clock))->verify($issued['testToken'], 'Gogloglo') === $payload, 'Verificación independiente del proceso.');
checkToken($protector->issue('Gogloglo', [9], $questions)['testToken'] !== $issued['testToken'], 'Nonce aleatorio por emisión.');
$raw = base64_decode(strtr(substr($issued['testToken'], 3), '-_', '+/'), true);
checkToken(!str_contains($raw, 'Gogloglo') && !str_contains($raw, 'questionId') && !str_contains(json_encode($payload), 'isCorrect'), 'Token opaco y sin respuestas.');
foreach ([0, 12, 28, strlen($raw) - 1] as $offset) {
    $changed = $raw; $changed[$offset] = chr(ord($changed[$offset]) ^ 1);
    $tampered = 'v1.' . rtrim(strtr(base64_encode($changed), '+/', '-_'), '=');
    rejectsToken(static fn () => $protector->verify($tampered, 'Gogloglo'), 'invalid');
}
foreach (['', 'v2.' . substr($issued['testToken'], 3), 'v1.abc!', 'v1.A', 'v1.' . str_repeat('A', 200000),
    $issued['testToken'] . '=', substr($issued['testToken'], 0, -10)] as $invalid) {
    rejectsToken(static fn () => $protector->verify($invalid, 'Gogloglo'), 'invalid');
}
rejectsToken(static fn () => (new TestTokenProtector(random_bytes(32), $clock))->verify($issued['testToken'], 'Gogloglo'), 'invalid');
rejectsToken(static fn () => $protector->verify($issued['testToken'], 'Virginia'), 'forbidden');
$virginia = $protector->issue('Virginia', [9], $questions);
checkToken($protector->verify($virginia['testToken'], 'Virginia')['username'] === 'Virginia', 'Segunda cuenta válida.');
rejectsToken(static fn () => $protector->verify($virginia['testToken'], 'Gogloglo'), 'forbidden');
$now = $payload['expiresAt'] - 1;
checkToken($protector->verify($issued['testToken'], 'Gogloglo') === $payload, 'Válido hasta un segundo antes.');
$now++;
rejectsToken(static fn () => $protector->verify($issued['testToken'], 'Gogloglo'), 'expired');
$now = $payload['issuedAt'] - 1;
rejectsToken(static fn () => $protector->verify($issued['testToken'], 'Gogloglo'), 'invalid');
$now = $payload['issuedAt'];
// Authentication alone does not replace payload validation.
foreach (['version', 'expiresAt', 'topicIds', 'questions'] as $field) {
    $bad = $payload;
    $bad[$field] = match ($field) { 'version' => 2, 'expiresAt' => $now + 43201, 'topicIds' => [9, 9], default => [] };
    $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt(json_encode($bad), 'aes-256-gcm',
        hash_hkdf('sha256', $key, 32, 'OpoMatic2000.TestToken.v1'), OPENSSL_RAW_DATA, $iv, $tag, 'OpoMatic2000.TestToken.v1', 16);
    $token = 'v1.' . rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    rejectsToken(static fn () => $protector->verify($token, 'Gogloglo'), 'invalid');
}
foreach (['', str_repeat('a', 31)] as $short) {
    $rejected = false;
    try { new TestTokenProtector($short); } catch (RuntimeException) { $rejected = true; }
    checkToken($rejected, 'Clave ausente/corta debe impedir emitir tokens.');
}
echo "Tokens: AES-256-GCM opaco, nonce, clave privada, orden, usuario, alteración, claims inválidos y caducidad exacta OK\n";
