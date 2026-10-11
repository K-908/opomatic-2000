<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use RuntimeException;

final class HttpProblem extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        string $detail,
        public readonly string $type = 'about:blank',
        public readonly array $headers = [],
        public readonly array $errors = [],
        public readonly array $extensions = [],
    ) {
        parent::__construct($detail);
    }

    public static function invalidJson(): self
    {
        return new self(
            400,
            'Los datos enviados no son válidos',
            'El cuerpo debe contener un objeto JSON válido.',
            'https://opomatic-2000/errors/validation',
            errors: ['body' => ['Envía un objeto JSON válido.']],
        );
    }
}
