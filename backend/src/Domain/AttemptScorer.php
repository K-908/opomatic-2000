<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use InvalidArgumentException;

final class AttemptScorer
{
    public const RULE_VERSION = 1;
    public const SCALE = 1000000;

    /** Exact rational arithmetic; round only the final score to millionths, halves away from zero. */
    public static function millionths(int $total, int $correct, int $incorrect): int
    {
        if ($total < 1 || $total > 200 || $correct < 0 || $incorrect < 0
            || $correct > $total || $incorrect > $total - $correct) {
            throw new InvalidArgumentException('Los contadores del intento no son válidos.');
        }
        $numerator = (4 * $correct - $incorrect) * 2500000;
        $absolute = abs($numerator);
        $rounded = intdiv($absolute, $total) + (($absolute % $total) * 2 >= $total ? 1 : 0);
        return $numerator < 0 ? -$rounded : $rounded;
    }

    public static function points(int $millionths): float
    {
        return $millionths / self::SCALE;
    }
}
