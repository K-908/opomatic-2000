<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use RuntimeException;

final class InsufficientQuestions extends RuntimeException
{
    public function __construct(public readonly int $requestedCount, public readonly int $availableCount)
    {
        parent::__construct("Has pedido $requestedCount preguntas, pero solo hay $availableCount disponibles.");
    }
}
