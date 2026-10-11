<?php

declare(strict_types=1);

use OpoMatic2000\Domain\AttemptScorer;
use OpoMatic2000\Domain\QuestionResult;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
function checkScore(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
foreach ([[20, 14, 4, 6500000], [1, 1, 0, 10000000], [1, 0, 1, -2500000], [1, 0, 0, 0],
    [3, 1, 1, 2500000], [3, 1, 0, 3333333], [3, 0, 1, -833333],
    [64, 1, 1, 117188], [64, 0, 1, -39063], [200, 199, 1, 9937500]] as [$n, $a, $f, $expected]) {
    checkScore(AttemptScorer::millionths($n, $a, $f) === $expected, 'Nota/precisión incorrecta.');
}
for ($n = 1; $n <= 200; ++$n) {
    checkScore(AttemptScorer::millionths($n, $n, 0) === 10000000, 'Máximo exacto.');
    checkScore(AttemptScorer::millionths($n, 0, $n) === -2500000, 'Mínimo exacto.');
    checkScore(AttemptScorer::millionths($n, 0, 0) === 0, 'Blancos exactos.');
    for ($a = 0; $a <= $n; ++$a) {
        for ($f = 0; $f <= $n - $a; ++$f) {
            $micro = AttemptScorer::millionths($n, $a, $f);
            $numerator = (4 * $a - $f) * 2500000;
            checkScore(abs($micro * $n - $numerator) * 2 <= $n, 'Error mayor de media millonésima.');
            checkScore($micro >= -2500000 && $micro <= 10000000, 'Nota fuera de rango.');
        }
    }
}
foreach ([[0, 0, 0], [201, 0, 0], [10, -1, 0], [10, 0, -1], [10, 8, 3]] as [$n, $a, $f]) {
    $rejected = false;
    try { AttemptScorer::millionths($n, $a, $f); } catch (InvalidArgumentException) { $rejected = true; }
    checkScore($rejected, 'Contadores inconsistentes aceptados.');
}
checkScore(AttemptScorer::points(6500000) === 6.5 && AttemptScorer::RULE_VERSION === 1, 'Contrato de nota y versión.');
checkScore(QuestionResult::classify(null, 10) === QuestionResult::Unanswered
    && QuestionResult::classify(10, 10) === QuestionResult::Correct
    && QuestionResult::classify(11, 10) === QuestionResult::Incorrect, 'Clasificación.');
echo "Puntuación: fórmula exacta, 1–200, todos los contadores válidos, extremos/negativos/blancos, millonésimas y clasificación OK\n";
