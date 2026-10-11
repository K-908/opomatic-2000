<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use DomainException;
use OutOfBoundsException;
use OpoMatic2000\Domain\TopicName;
use OpoMatic2000\Infrastructure\Persistence\TopicRepository;
use PDOException;

final readonly class TopicService
{
    public function __construct(private TopicRepository $topics) {}

    public function list(bool $includeInactive): array
    {
        return array_map(self::contract(...), $this->topics->list($includeInactive));
    }

    public function get(int $id): array
    {
        $row = $this->topics->findWithCount($id);
        if ($row === null) {
            throw new OutOfBoundsException('No existe el tema solicitado.');
        }
        return self::contract($row);
    }

    public function create(mixed $name): array
    {
        $normalized = TopicName::normalize($name);
        try {
            $id = $this->topics->create($normalized);
        } catch (PDOException $error) {
            self::translateConflict($error);
        }
        return $this->get($id);
    }

    public function update(int $id, mixed $name): array
    {
        $normalized = TopicName::normalize($name);
        $this->get($id);
        try {
            $this->topics->rename($id, $normalized);
        } catch (PDOException $error) {
            self::translateConflict($error);
        }
        return $this->get($id);
    }

    public function setStatus(int $id, bool $active): void
    {
        $this->get($id);
        $this->topics->setStatus($id, $active);
    }

    private static function translateConflict(PDOException $error): never
    {
        if (($error->errorInfo[1] ?? null) === 1062) {
            throw new DomainException('Ya existe un tema con ese nombre.');
        }
        throw $error;
    }

    private static function contract(array $row): array
    {
        return ['id' => (int) $row['Id'], 'name' => $row['Name'],
            'isActive' => (bool) $row['IsActive'], 'activeQuestionCount' => (int) $row['ActiveQuestionCount']];
    }
}
