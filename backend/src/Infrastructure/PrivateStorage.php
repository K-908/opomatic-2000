<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

use RuntimeException;

final readonly class PrivateStorage
{
    public function __construct(private string $root)
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new RuntimeException('AUTH_STORAGE_DIR no es válido.');
        }
    }

    public static function fromEnvironment(): self
    {
        $configured = getenv('AUTH_STORAGE_DIR');
        return new self($configured !== false && $configured !== '' ? $configured : dirname(__DIR__, 2) . '/var/auth');
    }

    public function directory(string $name): string
    {
        if (!in_array($name, ['sessions', 'login-limits'], true)) {
            throw new RuntimeException('Directorio privado no válido.');
        }

        $directory = $this->root . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('El almacenamiento privado no permite escritura.');
        }

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $publicRoot = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
        $resolvedDirectory = realpath($directory);
        if ($publicRoot !== false && $resolvedDirectory !== false
            && ($resolvedDirectory === $publicRoot || str_starts_with($resolvedDirectory, $publicRoot . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('El almacenamiento privado no puede estar dentro del directorio público.');
        }

        if (DIRECTORY_SEPARATOR === '/' && (fileperms($directory) & 0077) !== 0) {
            if (!chmod($directory, 0700)) {
                throw new RuntimeException('El almacenamiento privado no tiene permisos seguros.');
            }
        }

        return $directory;
    }
}
