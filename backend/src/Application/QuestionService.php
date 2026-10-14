<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use DomainException;
use OutOfBoundsException;
use OpoMatic2000\Domain\QuestionInput;
use OpoMatic2000\Domain\QuestionValidation;
use OpoMatic2000\Infrastructure\Persistence\QuestionRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use RuntimeException;

final readonly class QuestionService
{
    public function __construct(private QuestionRepository $questions, private TransactionManager $transactions) {}

    public function list(?int $topic, bool $inactive, string $search, int $page, int $pageSize): array
    {
        $result = $this->questions->list($topic, $inactive, $search, $page, $pageSize);
        $items = array_map(static fn (array $row): array => [
            'id' => (int) $row['Id'], 'topicId' => (int) $row['TopicId'], 'topicName' => $row['TopicName'],
            'statement' => $row['Statement'], 'isActive' => (bool) $row['IsActive'],
            'updatedAtUtc' => str_replace(' ', 'T', $row['UpdatedAtUtc']) . 'Z',
        ], $result['rows']);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize,
            'totalItems' => $result['total'], 'totalPages' => (int) ceil($result['total'] / $pageSize)];
    }

    public function get(int $id): array { return self::contract($this->requireQuestion($id)); }

    public function create(QuestionInput $input): array
    {
        return $this->transactions->run(function () use ($input): array {
            $this->requireActiveTopic($input->topicId);
            return $this->get($this->questions->create($input));
        });
    }

    public function update(int $id, QuestionInput $input): array
    {
        return $this->transactions->run(function () use ($id, $input): array {
            $existing = $this->requireQuestion($id, true);
            if (!in_array(count($existing['Options']), [3, 4], true)) { throw new RuntimeException('La pregunta persistida tiene opciones inconsistentes.'); }
            if (count($existing['Options']) !== count($input->options)) {
                throw new QuestionValidation(['options' => ['Al editar, conserva el número de opciones de la pregunta.']]);
            }
            $this->requireActiveTopic($input->topicId);
            $this->questions->update($id, $input);
            return $this->get($id);
        });
    }

    public function setStatus(int $id, bool $active): void
    {
        $this->transactions->run(function () use ($id, $active): void {
            $existing = $this->requireQuestion($id, true);
            if ($active) { $this->requireActiveTopic((int) $existing['TopicId']); }
            if ((bool) $existing['IsActive'] !== $active) { $this->questions->setStatus($id, $active); }
        });
    }

    private function requireQuestion(int $id, bool $lock = false): array
    {
        return $this->questions->find($id, $lock) ?? throw new OutOfBoundsException('No existe la pregunta solicitada.');
    }

    private function requireActiveTopic(int $id): void
    {
        $topic = $this->questions->topic($id) ?? throw new OutOfBoundsException('No existe el tema solicitado.');
        if (!(bool) $topic['IsActive']) { throw new DomainException('La pregunta debe pertenecer a un tema activo.'); }
    }

    private static function contract(array $row): array
    {
        return ['id' => (int) $row['Id'], 'topicId' => (int) $row['TopicId'], 'statement' => $row['Statement'],
            'isActive' => (bool) $row['IsActive'], 'options' => array_map(static fn (array $option): array => [
                'id' => (int) $option['Id'], 'position' => (int) $option['Position'],
                'text' => $option['Text'], 'isCorrect' => (bool) $option['IsCorrect'],
            ], $row['Options'])];
    }
}
