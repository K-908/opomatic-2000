<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use stdClass;

final readonly class TestGenerationInput
{
    /** @param list<int> $topicIds */
    private function __construct(public array $topicIds, public int $numberOfQuestions) {}

    public static function fromObject(stdClass $body): self
    {
        $errors = [];
        $ids = $body->topicIds ?? null;
        if (!is_array($ids) || $ids === []) {
            $errors['topicIds'] = ['Selecciona al menos un tema.'];
        } else {
            foreach ($ids as $id) {
                if (!is_int($id) || $id < 1 || $id > 2147483647) {
                    $errors['topicIds'] = ['Todos los temas deben tener un identificador entero válido.'];
                    break;
                }
            }
            if (!isset($errors['topicIds']) && count(array_unique($ids)) !== count($ids)) {
                $errors['topicIds'] = ['No se permiten temas repetidos.'];
            }
        }
        $count = $body->numberOfQuestions ?? null;
        if (!is_int($count) || $count < 1 || $count > 200) {
            $errors['numberOfQuestions'] = ['El número de preguntas debe estar entre 1 y 200.'];
        }
        if ($errors !== []) { throw new TestGenerationValidation($errors); }
        return new self($ids, $count);
    }
}
