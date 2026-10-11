<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use stdClass;

final readonly class QuestionInput
{
    /** @param list<array{position: int, text: string, isCorrect: bool}> $options */
    private function __construct(public int $topicId, public string $statement, public array $options) {}

    public static function fromObject(stdClass $body): self
    {
        $errors = [];
        $topicId = $body->topicId ?? null;
        if (!is_int($topicId) || $topicId < 1 || $topicId > 2147483647) {
            $errors['topicId'] = ['Selecciona un tema válido.'];
        }
        $statement = self::text($body->statement ?? null, 10000);
        if ($statement === null) {
            $errors['statement'] = ['El enunciado es obligatorio y no puede superar los 10.000 caracteres.'];
        }
        $rawOptions = $body->options ?? null;
        $options = [];
        $positions = [];
        $correct = 0;
        if (!is_array($rawOptions) || count($rawOptions) !== 4) {
            $errors['options'] = ['La pregunta debe contener exactamente cuatro opciones.'];
        } else {
            foreach ($rawOptions as $raw) {
                if (!$raw instanceof stdClass) {
                    $errors['options'] = ['Cada opción debe ser un objeto válido.'];
                    continue;
                }
                $position = $raw->position ?? null;
                if (!is_int($position) || $position < 1 || $position > 4 || in_array($position, $positions, true)) {
                    $errors['options'] = ['Las posiciones deben ser 1, 2, 3 y 4 sin repetirse.'];
                } else {
                    $positions[] = $position;
                }
                $text = self::text($raw->text ?? null, 1000);
                if ($text === null) {
                    $key = is_int($position) && $position >= 1 && $position <= 4 ? 'options[' . $position . '].text' : 'options';
                    $errors[$key] = ['El texto de cada opción es obligatorio y no puede superar los 1.000 caracteres.'];
                }
                if (!isset($raw->isCorrect) || !is_bool($raw->isCorrect)) {
                    $errors['correctOption'] = ['isCorrect debe ser booleano en todas las opciones.'];
                } elseif ($raw->isCorrect) {
                    $correct++;
                }
                $options[] = ['position' => $position, 'text' => $text, 'isCorrect' => $raw->isCorrect ?? null];
            }
            if ($correct !== 1) {
                $errors['correctOption'] = ['Debe marcarse exactamente una respuesta correcta.'];
            }
        }
        if ($errors !== []) {
            throw new QuestionValidation($errors);
        }
        usort($options, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        return new self($topicId, $statement, $options);
    }

    public function withTopic(int $topicId): self
    {
        return new self($topicId, $this->statement, $this->options);
    }

    private static function text(mixed $value, int $limit): ?string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) {
            return null;
        }
        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);
        $length = preg_match_all('/./us', $normalized);
        return $length >= 1 && $length <= $limit ? $normalized : null;
    }
}
