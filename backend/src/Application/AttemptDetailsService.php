<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OutOfBoundsException;

final readonly class AttemptDetailsService
{
    public function __construct(private TestAttemptRepository $attempts, private TransactionManager $transactions) {}

    public function list(string $username, int $page, int $pageSize): array
    {
        return $this->transactions->run(function () use ($username, $page, $pageSize): array {
            $result = $this->attempts->listOwned($username, $page, $pageSize);
            return ['items' => array_map(AttemptContract::summary(...), $result['rows']),
                'page' => $page, 'pageSize' => $pageSize, 'totalItems' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $pageSize)];
        });
    }

    public function get(int $id, string $username): array
    {
        return $this->transactions->run(fn (): array => AttemptContract::detail(
            $this->attempts->findOwned($id, $username) ?? throw new OutOfBoundsException('No existe el intento solicitado.')));
    }
}
