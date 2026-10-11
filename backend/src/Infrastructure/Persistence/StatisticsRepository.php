<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;

final readonly class StatisticsRepository
{
    public function __construct(private PDO $connection) {}

    public function summary(string $username): array
    {
        $query = $this->connection->prepare('SELECT COUNT(*) AS CompletedTests,
            COALESCE(SUM(CorrectCount), 0) AS CorrectCount,
            COALESCE(SUM(IncorrectCount), 0) AS IncorrectCount,
            COALESCE(SUM(UnansweredCount), 0) AS UnansweredCount
            FROM TestAttempts WHERE Username = ?');
        $query->execute([$username]);
        return $query->fetch();
    }

    public function topics(string $username): array
    {
        // Use the latest owned snapshot name, even if the current topic has been renamed or disabled.
        $query = $this->connection->prepare('SELECT q.OriginalTopicId AS TopicId,
            (SELECT latest.TopicNameSnapshot FROM TestAttemptQuestions latest
             JOIN TestAttempts a ON a.Id = latest.TestAttemptId
             WHERE latest.OriginalTopicId = q.OriginalTopicId AND a.Username = ?
             ORDER BY a.CompletedAtUtc DESC, a.Id DESC, latest.DisplayOrder LIMIT 1) AS TopicName,
            SUM(q.Result = 1) AS CorrectCount, SUM(q.Result = 2) AS IncorrectCount,
            SUM(q.Result = 0) AS UnansweredCount
            FROM TestAttemptQuestions q JOIN TestAttempts a ON a.Id = q.TestAttemptId
            WHERE a.Username = ? GROUP BY q.OriginalTopicId
            ORDER BY (CAST(SUM(q.Result = 1) AS DECIMAL(40, 20)) / NULLIF(SUM(q.Result <> 0), 0)) DESC,
                SUM(q.Result <> 0) DESC, TopicName ASC, q.OriginalTopicId ASC');
        $query->execute([$username, $username]);
        return $query->fetchAll();
    }
}
