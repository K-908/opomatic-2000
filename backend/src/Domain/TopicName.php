<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use InvalidArgumentException;

final class TopicName
{
    public static function normalize(mixed $value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Indica un nombre de entre 1 y 150 caracteres.');
        }
        $name = preg_replace('/^\s+|\s+$/u', '', $value);
        $length = preg_match_all('/./us', $name);
        if ($length < 1 || $length > 150 || str_contains($name, "\0")) {
            throw new InvalidArgumentException('Indica un nombre de entre 1 y 150 caracteres.');
        }
        return $name;
    }
}
