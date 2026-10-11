<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\UserRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function verifyBootstrap(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{exitCode: int, output: string} */
function invokeBootstrap(string $input): array
{
    $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-users.php'];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el proceso de alta.');
    }

    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exitCode' => proc_close($process), 'output' => $output];
}

$config = DatabaseConfig::fromEnvironment();
verifyBootstrap(str_starts_with($config->name, 'opomatic_p03_'), 'La prueba exige una base temporal.');
$db = (new PdoConnectionFactory())->connect($config);
$users = new UserRepository($db);
verifyBootstrap(!$users->hasAny(), 'La tabla Users de prueba debe estar vacía.');

$manualHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$users->create('Temporal', $manualHash);
$preexisting = invokeBootstrap('');
verifyBootstrap($preexisting['exitCode'] === 2, 'Debe rechazarse una tabla Users que ya tiene cuentas.');
verifyBootstrap((int) $db->query('SELECT COUNT(*) FROM `Users`')->fetchColumn() === 1, 'El rechazo no debe modificar cuentas.');
$db->exec("DELETE FROM `Users` WHERE `Username` = 'Temporal'");

$invalid = invokeBootstrap("\n" . bin2hex(random_bytes(16)) . "\n");
verifyBootstrap($invalid['exitCode'] === 1 && !$users->hasAny(), 'Una contraseña vacía no debe crear cuentas.');

$db->exec(
    "CREATE TRIGGER `P04_RejectVirginia` BEFORE INSERT ON `Users` FOR EACH ROW
     BEGIN
       IF NEW.`Username` = 'Virginia' THEN
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba';
       END IF;
     END",
);
try {
    $failed = invokeBootstrap(bin2hex(random_bytes(24)) . "\n" . bin2hex(random_bytes(24)) . "\n");
    verifyBootstrap($failed['exitCode'] === 1 && !$users->hasAny(), 'El fallo de la segunda inserción debe revertir la primera.');
} finally {
    $db->exec('DROP TRIGGER `P04_RejectVirginia`');
}

$firstPassword = bin2hex(random_bytes(24));
$secondPassword = bin2hex(random_bytes(24));
$created = invokeBootstrap($firstPassword . "\n" . $secondPassword . "\n");
verifyBootstrap($created['exitCode'] === 0, 'El comando debe crear ambas cuentas.');
verifyBootstrap(!str_contains($created['output'], $firstPassword) && !str_contains($created['output'], $secondPassword), 'La salida no debe revelar contraseñas.');
verifyBootstrap((int) $db->query('SELECT COUNT(*) FROM `Users`')->fetchColumn() === 2, 'Solo deben existir dos cuentas.');

$gogloglo = $users->findByUsername('Gogloglo');
$virginia = $users->findByUsername('Virginia');
verifyBootstrap($gogloglo !== null && $virginia !== null, 'Faltan las cuentas previstas.');
verifyBootstrap(password_verify($firstPassword, $gogloglo['PasswordHash']), 'El hash de Gogloglo no verifica.');
verifyBootstrap(password_verify($secondPassword, $virginia['PasswordHash']), 'El hash de Virginia no verifica.');
verifyBootstrap(!password_verify($secondPassword, $gogloglo['PasswordHash']), 'Las cuentas no deben compartir contraseña.');
verifyBootstrap($gogloglo['PasswordHash'] !== $firstPassword && $virginia['PasswordHash'] !== $secondPassword, 'No deben guardarse contraseñas en claro.');

$repeat = invokeBootstrap(bin2hex(random_bytes(24)) . "\n" . bin2hex(random_bytes(24)) . "\n");
verifyBootstrap($repeat['exitCode'] === 2, 'El comando debe rechazar una segunda ejecución.');
verifyBootstrap($users->findByUsername('Gogloglo')['PasswordHash'] === $gogloglo['PasswordHash'], 'El segundo intento no debe cambiar hashes.');
verifyBootstrap($users->findByUsername('Virginia')['PasswordHash'] === $virginia['PasswordHash'], 'El segundo intento no debe cambiar hashes.');

echo "Alta inicial: dos hashes verificables, entradas inválidas y segunda ejecución rechazadas OK\n";
