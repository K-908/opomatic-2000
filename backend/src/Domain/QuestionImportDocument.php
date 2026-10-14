<?php

declare(strict_types=1);

namespace OpoMatic2000\Domain;

use InvalidArgumentException;
use stdClass;

final readonly class QuestionImportDocument
{
    public const MAXIMUM_BYTES = 5 * 1024 * 1024;
    public const MAXIMUM_QUESTIONS = 2000;

    /** @param list<array{name: string, questions: list<QuestionInput>}> $topics */
    private function __construct(public array $topics) {}

    public static function fromObject(stdClass $body): self
    {
        $rawTopics = $body->topics ?? null;
        if (!is_array($rawTopics) || $rawTopics === [] || count($rawTopics) > self::MAXIMUM_QUESTIONS) {
            throw new QuestionValidation(['topics' => ['Debe incluir entre 1 y 2.000 temas, cada uno con preguntas.']]);
        }
        $count = 0;
        foreach ($rawTopics as $rawTopic) {
            if ($rawTopic instanceof stdClass && is_array($rawTopic->questions ?? null)) { $count += count($rawTopic->questions); }
        }
        if ($count > self::MAXIMUM_QUESTIONS) {
            throw new QuestionValidation(['topics' => ['El archivo no puede contener más de 2.000 preguntas.']]);
        }
        $errors = [];
        $topics = [];
        foreach ($rawTopics as $topicIndex => $rawTopic) {
            $topicPath = 'topics[' . $topicIndex . ']';
            if (!$rawTopic instanceof stdClass) {
                $errors[$topicPath] = ['El tema debe ser un objeto válido.'];
                continue;
            }
            $name = '';
            try { $name = TopicName::normalize($rawTopic->name ?? null); }
            catch (InvalidArgumentException $error) { $errors[$topicPath . '.name'] = [$error->getMessage()]; }
            if (!is_array($rawTopic->questions ?? null) || $rawTopic->questions === []) {
                $errors[$topicPath . '.questions'] = ['Debe incluir al menos una pregunta.'];
                continue;
            }
            $questions = [];
            foreach ($rawTopic->questions as $questionIndex => $rawQuestion) {
                $path = $topicPath . '.questions[' . $questionIndex . ']';
                if (!$rawQuestion instanceof stdClass) {
                    $errors[$path] = ['La pregunta debe ser un objeto válido.'];
                    continue;
                }
                $rawOptions = $rawQuestion->options ?? null;
                $correct = $rawQuestion->correctOption ?? null;
                $validCorrect = is_int($correct) && $correct >= 1 && $correct <= 4
                    && is_array($rawOptions) && $correct <= count($rawOptions);
                if (!$validCorrect) { $errors[$path . '.correctOption'] = ['La respuesta correcta debe ser una posición de las opciones disponibles (1 a 3 o 4).']; }
                $options = is_array($rawOptions) && in_array(count($rawOptions), [3, 4], true) ? array_map(static fn (mixed $text, int $index): stdClass => (object) [
                    'position' => $index + 1, 'text' => $text, 'isCorrect' => $index + 1 === ($validCorrect ? $correct : 1),
                ], $rawOptions, array_keys($rawOptions)) : null;
                try {
                    $questions[] = QuestionInput::fromObject((object) ['topicId' => 1, 'statement' => $rawQuestion->statement ?? null, 'options' => $options]);
                } catch (QuestionValidation $error) {
                    foreach ($error->errors as $field => $messages) {
                        if (preg_match('/^options\[([1-4])\]\.text$/', $field, $matches) === 1) {
                            $field = 'options[' . ((int) $matches[1] - 1) . ']';
                        }
                        $errors[$path . '.' . $field] = $messages;
                    }
                }
            }
            $topics[] = ['name' => $name, 'questions' => $questions];
        }
        if ($errors !== []) { throw new QuestionValidation($errors); }
        return new self($topics);
    }
}
