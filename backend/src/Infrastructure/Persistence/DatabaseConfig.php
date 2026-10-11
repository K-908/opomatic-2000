<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use InvalidArgumentException;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
    ) {
        foreach (['DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user] as $key => $value) {
            if ($value === '' || strpbrk($value, ";\0\r\n") !== false) {
                throw new InvalidArgumentException("{$key} no es válido.");
            }
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('DB_PORT debe estar entre 1 y 65535.');
        }
    }

    public static function fromEnvironment(): self
    {
        $port = self::required('DB_PORT');
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new InvalidArgumentException('DB_PORT debe estar entre 1 y 65535.');
        }

        $password = getenv('DB_PASSWORD');
        if ($password === false) {
            throw new InvalidArgumentException('Falta DB_PASSWORD.');
        }

        return new self(
            self::required('DB_HOST'),
            (int) $port,
            self::required('DB_NAME'),
            self::required('DB_USER'),
            $password,
        );
    }

    private static function required(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new InvalidArgumentException("Falta {$key}.");
        }

        return $value;
    }
}
