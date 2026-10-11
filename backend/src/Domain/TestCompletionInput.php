<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use stdClass;

final readonly class TestCompletionInput
{
    /** @param array<int, ?int> $answers */
    private function __construct(public string $submissionId, public string $testToken, public array $answers) {}

    public static function fromObject(stdClass $body): self
    {
        $errors = [];
        $id = $body->submissionId ?? null;
        if (!is_string($id) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id) !== 1
            || strtolower($id) === '00000000-0000-0000-0000-000000000000') {
            $errors['submissionId'] = ['Debe ser un UUID no vacío.'];
        }
        $token = $body->testToken ?? null;
        if (!is_string($token) || $token === '' || strlen($token) > 200000) {
            $errors['testToken'] = ['El token del test es obligatorio y debe ser válido.'];
        }
        $answers = $body->answers ?? null;
        $byQuestion = [];
        if (!is_array($answers) || count($answers) < 1 || count($answers) > 200) {
            $errors['answers'] = ['Envía una respuesta por pregunta, entre 1 y 200.'];
        } else {
            foreach ($answers as $index => $answer) {
                if (!$answer instanceof stdClass || !self::id($answer->questionId ?? null)
                    || !property_exists($answer, 'selectedOptionId')
                    || ($answer->selectedOptionId !== null && !self::id($answer->selectedOptionId))) {
                    $errors["answers[$index]"] = ['Indica questionId entero positivo y selectedOptionId entero positivo o null.'];
                    continue;
                }
                if (array_key_exists($answer->questionId, $byQuestion)) {
                    $errors['answers'] = ['No se permiten preguntas repetidas.'];
                }
                $byQuestion[$answer->questionId] = $answer->selectedOptionId;
            }
        }
        if ($errors !== []) { throw new TestCompletionValidation($errors); }
        return new self(strtolower($id), $token, $byQuestion);
    }

    public function validateAgainst(array $payload): void
    {
        $expected = array_column($payload['questions'], 'questionId');
        $actual = array_keys($this->answers);
        sort($expected); sort($actual);
        if ($expected !== $actual) {
            throw new TestCompletionValidation(['answers' => ['Hay respuestas ausentes o ajenas al test.']]);
        }
        foreach ($payload['questions'] as $question) {
            $selected = $this->answers[$question['questionId']];
            if ($selected !== null && !in_array($selected, array_column($question['options'], 'optionId'), true)) {
                throw new TestCompletionValidation(['answers' => ['Una opción seleccionada no pertenece a su pregunta.']]);
            }
        }
    }

    private static function id(mixed $value): bool
    {
        return is_int($value) && $value > 0 && $value <= 2147483647;
    }
}
