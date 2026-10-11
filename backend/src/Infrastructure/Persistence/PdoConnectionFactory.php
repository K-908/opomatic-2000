<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;
use PDOException;

final class PdoConnectionFactory
{
    public function connect(DatabaseConfig $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->host, $config->port, $config->name);

        try {
            return new PDO($dsn, $config->user, $config->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException) {
            throw new DatabaseConnectionException('No se pudo conectar a la base de datos.');
        }
    }
}
