<?php

declare(strict_types=1);

use OpoMatic2000\Application\InitialAccountBootstrapper;
use OpoMatic2000\Application\InitialAccountsAlreadyExist;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\Persistence\UserRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
    if ((new UserRepository($connection))->hasAny()) {
        throw new InitialAccountsAlreadyExist('La tabla Users ya contiene cuentas; no se ha modificado.');
    }

    if (stream_isatty(STDIN)) {
        throw new RuntimeException('Envía las dos contraseñas por entrada estándar desde un prompt oculto.');
    }

    $first = fgets(STDIN, 4096);
    $second = fgets(STDIN, 4096);
    if ($first === false || $second === false || fgets(STDIN, 2) !== false) {
        throw new RuntimeException('Envía exactamente dos líneas de contraseña.');
    }

    (new InitialAccountBootstrapper(new TransactionManager($connection)))->create(
        rtrim($first, "\r\n"),
        rtrim($second, "\r\n"),
    );

    fwrite(STDOUT, "Cuentas Gogloglo y Virginia creadas.\n");
} catch (InitialAccountsAlreadyExist $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
} catch (Throwable) {
    fwrite(STDERR, "No se pudieron crear las cuentas. Revisa la configuración, el esquema y el formato de entrada.\n");
    exit(1);
}
