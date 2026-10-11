<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use OpoMatic2000\Domain\QuestionInput;
use PDO;

final readonly class QuestionRepository
{
    public function __construct(private PDO $connection) {}

    public function list(?int $topicId, bool $includeInactive, string $search, int $page, int $pageSize): array
    {
        $conditions = [];
        $parameters = [];
        if ($topicId !== null) { $conditions[] = 'q.`TopicId` = :topic'; $parameters['topic'] = $topicId; }
        if (!$includeInactive) { $conditions[] = 'q.`IsActive` = 1'; }
        // LOCATE searches literally: percent and underscore are not SQL wildcards.
        if ($search !== '') { $conditions[] = 'LOCATE(:search, q.`Statement`) > 0'; $parameters['search'] = $search; }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $from = ' FROM `Questions` q INNER JOIN `Topics` t ON t.`Id` = q.`TopicId`';
        $count = $this->connection->prepare('SELECT COUNT(*)' . $from . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $query = $this->connection->prepare('SELECT q.`Id`, q.`TopicId`, t.`Name` AS `TopicName`, q.`Statement`, q.`IsActive`, q.`UpdatedAtUtc`' . $from . $where . ' ORDER BY q.`UpdatedAtUtc` DESC, q.`Id` LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) { $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $query->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();
        return ['rows' => $query->fetchAll(), 'total' => $total];
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $query = $this->connection->prepare('SELECT `Id`, `TopicId`, `Statement`, `IsActive` FROM `Questions` WHERE `Id` = :id' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute(['id' => $id]);
        $row = $query->fetch();
        if ($row === false) { return null; }
        $options = $this->connection->prepare('SELECT `Id`, `Position`, `Text`, `IsCorrect` FROM `QuestionOptions` WHERE `QuestionId` = :id ORDER BY `Position`');
        $options->execute(['id' => $id]);
        $row['Options'] = $options->fetchAll();
        return $row;
    }

    public function topic(int $id): ?array
    {
        $query = $this->connection->prepare('SELECT `Id`, `IsActive` FROM `Topics` WHERE `Id` = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    public function create(QuestionInput $input): int
    {
        $query = $this->connection->prepare('INSERT INTO `Questions` (`TopicId`, `Statement`, `IsActive`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:topic, :statement, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
        $query->execute(['topic' => $input->topicId, 'statement' => $input->statement]);
        $id = (int) $this->connection->lastInsertId();
        $optionQuery = $this->connection->prepare('INSERT INTO `QuestionOptions` (`QuestionId`, `Position`, `Text`, `IsCorrect`) VALUES (:id, :position, :text, :correct)');
        foreach ($input->options as $option) {
            $optionQuery->execute(['id' => $id, 'position' => $option['position'], 'text' => $option['text'], 'correct' => (int) $option['isCorrect']]);
        }
        return $id;
    }

    public function update(int $id, QuestionInput $input): void
    {
        $query = $this->connection->prepare('UPDATE `Questions` SET `TopicId` = :topic, `Statement` = :statement, `UpdatedAtUtc` = UTC_TIMESTAMP(6) WHERE `Id` = :id');
        $query->execute(['id' => $id, 'topic' => $input->topicId, 'statement' => $input->statement]);
        $optionQuery = $this->connection->prepare('UPDATE `QuestionOptions` SET `Text` = :text, `IsCorrect` = :correct WHERE `QuestionId` = :id AND `Position` = :position');
        foreach ($input->options as $option) {
            $optionQuery->execute(['id' => $id, 'position' => $option['position'], 'text' => $option['text'], 'correct' => (int) $option['isCorrect']]);
        }
    }

    public function setStatus(int $id, bool $active): void
    {
        $query = $this->connection->prepare('UPDATE `Questions` SET `IsActive` = :active, `UpdatedAtUtc` = UTC_TIMESTAMP(6) WHERE `Id` = :id');
        $query->execute(['active' => (int) $active, 'id' => $id]);
    }
}
