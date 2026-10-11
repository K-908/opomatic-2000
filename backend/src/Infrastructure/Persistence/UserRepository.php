<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;

final readonly class UserRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function hasAny(): bool
    {
        $query = $this->connection->prepare('SELECT 1 FROM `Users` LIMIT 1');
        $query->execute();

        return $query->fetchColumn() !== false;
    }

    /** @return array{Username: string, PasswordHash: string}|null */
    public function findByUsername(string $username): ?array
    {
        $query = $this->connection->prepare('SELECT `Username`, `PasswordHash` FROM `Users` WHERE `Username` = :username');
        $query->execute(['username' => $username]);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $username, string $passwordHash): void
    {
        $query = $this->connection->prepare('INSERT INTO `Users` (`Username`, `PasswordHash`) VALUES (:username, :passwordHash)');
        $query->execute(['username' => $username, 'passwordHash' => $passwordHash]);
    }
}
