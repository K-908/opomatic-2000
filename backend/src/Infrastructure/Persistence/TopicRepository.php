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

    public function list(bool $includeInactive): array
    {
        $sql = $this->withCountSql() . ($includeInactive ? '' : ' WHERE t.`IsActive` = 1') . ' ORDER BY t.`Name`, t.`Id`';
        return $this->connection->query($sql)->fetchAll();
    }

    public function findWithCount(int $id): ?array
    {
        $query = $this->connection->prepare($this->withCountSql() . ' WHERE t.`Id` = :id');
        $query->execute(['id' => $id]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $name): int
    {
        $query = $this->connection->prepare('INSERT INTO `Topics` (`Name`, `IsActive`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:name, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        $query->execute(['name' => $name]);
        return (int) $this->connection->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $query = $this->connection->prepare('UPDATE `Topics` SET `Name` = :name, `UpdatedAtUtc` = UTC_TIMESTAMP(6) WHERE `Id` = :id');
        $query->execute(['id' => $id, 'name' => $name]);
    }

    public function setStatus(int $id, bool $active): void
    {
        $query = $this->connection->prepare('UPDATE `Topics` SET `IsActive` = :active, `UpdatedAtUtc` = UTC_TIMESTAMP(6) WHERE `Id` = :id');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':active', $active, PDO::PARAM_BOOL);
        $query->execute();
    }

    private function withCountSql(): string
    {
        return 'SELECT t.`Id`, t.`Name`, t.`IsActive`, (SELECT COUNT(*) FROM `Questions` q WHERE q.`TopicId` = t.`Id` AND q.`IsActive` = 1) AS `ActiveQuestionCount` FROM `Topics` t';
    }
}
