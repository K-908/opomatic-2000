<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

enum QuestionResult: int
{
    case Unanswered = 0;
    case Correct = 1;
    case Incorrect = 2;

    public static function classify(?int $selected, int $correct): self
    {
        return $selected === null ? self::Unanswered : ($selected === $correct ? self::Correct : self::Incorrect);
    }

    public function label(): string
    {
        return match ($this) { self::Unanswered => 'unanswered', self::Correct => 'correct', self::Incorrect => 'incorrect' };
    }
}
