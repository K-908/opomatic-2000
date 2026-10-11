<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;

final readonly class TestGenerationRepository
{
    public function __construct(private PDO $connection) {}

    public function activeTopics(array $ids): array
    {
        $query = $this->connection->prepare('SELECT Id, Name FROM Topics WHERE IsActive = 1 AND Id IN (' . self::placeholders($ids) . ')');
        $query->execute($ids);
        return $query->fetchAll();
    }

    public function eligibleIds(array $topicIds): array
    {
        $query = $this->connection->prepare('SELECT q.Id FROM Questions q JOIN Topics t ON t.Id = q.TopicId
            WHERE q.IsActive = 1 AND t.IsActive = 1 AND q.TopicId IN (' . self::placeholders($topicIds) . ') ORDER BY q.Id');
        $query->execute($topicIds);
        return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Read selected questions without loading correctness into the generation path. */
    public function selected(array $ids): array
    {
        $query = $this->connection->prepare('SELECT q.Id, q.TopicId, t.Name AS TopicName, q.Statement
            FROM Questions q JOIN Topics t ON t.Id = q.TopicId WHERE q.Id IN (' . self::placeholders($ids) . ')');
        $query->execute($ids);
        $questions = [];
        foreach ($query->fetchAll() as $row) { $questions[(int) $row['Id']] = $row + ['Options' => []]; }
        $options = $this->connection->prepare('SELECT Id, QuestionId, Text FROM QuestionOptions
            WHERE QuestionId IN (' . self::placeholders($ids) . ') ORDER BY Id');
        $options->execute($ids);
        foreach ($options->fetchAll() as $row) { $questions[(int) $row['QuestionId']]['Options'][] = $row; }
        return $questions;
    }

    private static function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
