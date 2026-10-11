<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

final class SecureRandomizer
{
    /** @template T @param list<T> $items @return list<T> */
    public static function shuffle(array $items): array
    {
        for ($index = count($items) - 1; $index > 0; --$index) {
            $other = random_int(0, $index);
            [$items[$index], $items[$other]] = [$items[$other], $items[$index]];
        }
        return $items;
    }
}
