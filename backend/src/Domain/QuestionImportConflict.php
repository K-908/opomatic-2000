<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use RuntimeException;

final class QuestionImportConflict extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('La importación contiene temas inactivos.');
    }
}
