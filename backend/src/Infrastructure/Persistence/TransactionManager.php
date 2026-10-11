<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use LogicException;
use PDO;
use Throwable;

final readonly class TransactionManager
{
    public function __construct(private PDO $connection)
    {
    }

    /** @template T @param callable(PDO): T $operation @return T */
    public function run(callable $operation): mixed
    {
        if ($this->connection->inTransaction()) {
            throw new LogicException('Ya hay una transacción activa.');
        }

        $this->connection->beginTransaction();
        try {
            $result = $operation($this->connection);
            $this->connection->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
    }
}
