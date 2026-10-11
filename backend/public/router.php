<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (is_string($path)) {
    $requestedFile = realpath(__DIR__ . $path);
    if ($requestedFile !== false && str_starts_with($requestedFile, __DIR__ . DIRECTORY_SEPARATOR) && is_file($requestedFile)) {
        return false;
    }
}

require __DIR__ . '/index.php';
