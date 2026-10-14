<?php

declare(strict_types=1);

use OpoMatic2000\Domain\QuestionImportDocument;
use OpoMatic2000\Domain\QuestionInput;
use OpoMatic2000\Domain\QuestionValidation;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function checkOptions(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function rejectsOptions(callable $operation, string $field): void
{
    try { $operation(); } catch (QuestionValidation $error) {
        checkOptions(isset($error->errors[$field]), 'Falta el error de ' . $field);
        return;
    }
    throw new RuntimeException('Se aceptaron opciones inválidas.');
}

foreach ([3, 4] as $count) {
    $options = array_map(static fn (int $position): string => 'Opción ' . $position, range(1, $count));
    $document = QuestionImportDocument::fromObject((object) ['topics' => [(object) [
        'name' => 'Tema de prueba', 'questions' => [(object) [
            'statement' => 'Pregunta de prueba', 'options' => $options, 'correctOption' => $count,
        ]],
    ]]]);
    checkOptions(count($document->topics[0]['questions'][0]->options) === $count, 'La importación debe conservar las opciones.');
    checkOptions($document->topics[0]['questions'][0]->options[$count - 1]['isCorrect'], 'La última opción debe ser correcta.');
}

$raw = (object) ['topicId' => 1, 'statement' => 'Pregunta', 'options' => [
    (object) ['position' => 1, 'text' => 'Uno', 'isCorrect' => true],
    (object) ['position' => 2, 'text' => 'Dos', 'isCorrect' => false],
    (object) ['position' => 4, 'text' => 'Cuatro', 'isCorrect' => false],
]];
rejectsOptions(static fn () => QuestionInput::fromObject($raw), 'options');
foreach ([2, 5] as $count) {
    $raw->options = array_map(static fn (int $position): object => (object) [
        'position' => $position, 'text' => 'Opción ' . $position, 'isCorrect' => $position === 1,
    ], range(1, $count));
    rejectsOptions(static fn () => QuestionInput::fromObject($raw), 'options');
}

$invalidCorrect = (object) ['topics' => [(object) ['name' => 'Tema', 'questions' => [(object) [
    'statement' => 'Pregunta', 'options' => ['Uno', 'Dos', 'Tres'], 'correctOption' => 4,
]]]]];
rejectsOptions(static fn () => QuestionImportDocument::fromObject($invalidCorrect), 'topics[0].questions[0].correctOption');

echo "Opciones: importación de tres o cuatro, posiciones consecutivas y respuesta correcta válida OK\n";
