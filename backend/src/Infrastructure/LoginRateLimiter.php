<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

use RuntimeException;

final readonly class LoginRateLimiter
{
    public function __construct(private PrivateStorage $storage)
    {
    }

    public function allow(string $ipAddress): bool
    {
        $key = hash('sha256', $ipAddress);
        $path = $this->storage->directory('login-limits') . DIRECTORY_SEPARATOR . substr($key, 0, 2) . '.json';
        $file = fopen($path, 'c+b');
        if ($file === false) {
            throw new RuntimeException('No se pudo abrir el límite de accesos.');
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el límite de accesos.');
            }
            $raw = stream_get_contents($file);
            $decoded = $raw === '' ? [] : json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('El límite de accesos no se pudo leer.');
            }
            $now = time();
            $active = [];
            foreach ($decoded as $savedKey => $timestamps) {
                if (!is_string($savedKey) || !is_array($timestamps)) {
                    throw new RuntimeException('El límite de accesos contiene datos no válidos.');
                }
                $recent = [];
                foreach ($timestamps as $timestamp) {
                    if (is_int($timestamp) && $timestamp > $now - 60 && $timestamp <= $now) {
                        $recent[] = $timestamp;
                    }
                }
                if ($recent !== []) {
                    $active[$savedKey] = $recent;
                }
            }

            $attempts = $active[$key] ?? [];
            if (count($attempts) >= 5) {
                return false;
            }

            $attempts[] = $now;
            $active[$key] = $attempts;
            $encoded = json_encode($active, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
                throw new RuntimeException('No se pudo guardar el límite de accesos.');
            }
            return true;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
