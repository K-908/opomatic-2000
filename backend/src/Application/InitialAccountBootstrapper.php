<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use InvalidArgumentException;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\Persistence\UserRepository;
use PDO;

final readonly class InitialAccountBootstrapper
{
    public function __construct(private TransactionManager $transactions)
    {
    }

    public function create(string $goglogloPassword, string $virginiaPassword): void
    {
        self::validatePassword($goglogloPassword);
        self::validatePassword($virginiaPassword);

        $goglogloHash = password_hash($goglogloPassword, PASSWORD_DEFAULT);
        $virginiaHash = password_hash($virginiaPassword, PASSWORD_DEFAULT);

        $this->transactions->run(static function (PDO $connection) use ($goglogloHash, $virginiaHash): void {
            $users = new UserRepository($connection);
            if ($users->hasAny()) {
                throw new InitialAccountsAlreadyExist('La tabla Users ya contiene cuentas; no se ha modificado.');
            }

            $users->create('Gogloglo', $goglogloHash);
            $users->create('Virginia', $virginiaHash);
        });
    }

    private static function validatePassword(string $password): void
    {
        // PASSWORD_DEFAULT usa bcrypt en PHP 8.2, que trunca a 72 bytes.
        if ($password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Cada contraseña debe tener entre 1 y 72 bytes y no contener NUL.');
        }
    }
}
