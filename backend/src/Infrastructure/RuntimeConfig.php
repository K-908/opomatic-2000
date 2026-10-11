<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

use InvalidArgumentException;

final readonly class RuntimeConfig
{
    private function __construct(public string $environment)
    {
    }

    public static function fromEnvironment(): self
    {
        $environment = getenv('APP_ENV');

        if ($environment === false || $environment === '') {
            $environment = 'development';
        }

        if (!in_array($environment, ['development', 'test', 'production'], true)) {
            throw new InvalidArgumentException('APP_ENV debe ser development, test o production.');
        }

        return new self($environment);
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
