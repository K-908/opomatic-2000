<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;

final readonly class QuestionImportRepository
{
    public function __construct(private PDO $connection) {}

    /** Compare with precisely the collation used by Topics.Name, including Unicode. */
    public function duplicateNames(array $topics): array
    {
        $parts = [];
        $parameters = [];
        foreach ($topics as $index => $topic) {
            $parts[] = 'SELECT CAST(:name' . $index . ' AS CHAR CHARACTER SET utf8mb4) AS `Name`, ' . $index . ' AS `Position`';
            $parameters['name' . $index] = $topic['name'];
        }
        $query = $this->connection->prepare('SELECT MIN(`Position`) AS `FirstIndex`, MAX(`Position`) AS `LastIndex` FROM (' . implode(' UNION ALL ', $parts) . ') incoming GROUP BY `Name` COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1');
        $query->execute($parameters);
        return $query->fetchAll();
    }

    /** Atomically create or reuse the unique name; existing status/name/dates stay intact. */
    public function resolveTopic(string $name): array
    {
        $query = $this->connection->prepare('INSERT INTO `Topics` (`Name`, `IsActive`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:name, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE `Id` = LAST_INSERT_ID(`Id`)');
        $query->execute(['name' => $name]);
        $created = $query->rowCount() === 1;
        $id = (int) $this->connection->lastInsertId();
        $query = $this->connection->prepare('SELECT `Id`, `IsActive` FROM `Topics` WHERE `Id` = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        return ['topic' => $query->fetch(), 'created' => $created];
    }
}
