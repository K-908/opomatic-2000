<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final readonly class LoginAccessRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function record(string $username, string $ipAddress): void
    {
        $query = $this->connection->prepare(
            'INSERT INTO `LoginAccesses` (`Username`, `AccessedAtUtc`, `IpAddress`) VALUES (:username, UTC_TIMESTAMP(6), :ip)',
        );
        $query->execute(['username' => $username, 'ip' => $ipAddress]);
    }

    /** @return list<array{accessedAtUtc: string, ipAddress: string}> */
    public function recentFor(string $username): array
    {
        $query = $this->connection->prepare(
            'SELECT `AccessedAtUtc`, `IpAddress` FROM `LoginAccesses` WHERE `Username` = :username ORDER BY `AccessedAtUtc` DESC, `Id` DESC LIMIT 50',
        );
        $query->execute(['username' => $username]);
        $accesses = [];
        foreach ($query->fetchAll() as $row) {
            $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $row['AccessedAtUtc'], new DateTimeZone('UTC'));
            if ($timestamp === false) {
                throw new RuntimeException('Fecha de acceso inválida en MySQL.');
            }
            $accesses[] = [
                'accessedAtUtc' => $timestamp->format('Y-m-d\TH:i:s\Z'),
                'ipAddress' => $row['IpAddress'],
            ];
        }

        return $accesses;
    }
}
