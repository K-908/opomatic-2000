<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use RuntimeException;

final class TestTokenFailure extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'expired' => 'El test ha caducado.',
            'forbidden' => 'El test pertenece a otra cuenta.',
            default => 'El token del test no es válido.',
        });
    }
}
