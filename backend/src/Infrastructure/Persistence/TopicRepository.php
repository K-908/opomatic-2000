<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;

final readonly class TopicRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array{Id: int, Name: string, IsActive: int, CreatedAtUtc: string, UpdatedAtUtc: string}|null */
    public function findById(int $id): ?array
    {
        $query = $this->connection->prepare(
            'SELECT `Id`, `Name`, `IsActive`, `CreatedAtUtc`, `UpdatedAtUtc` FROM `Topics` WHERE `Id` = :id',
        );
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $row = $query->fetch();

        return $row === false ? null : $row;
    }
}
