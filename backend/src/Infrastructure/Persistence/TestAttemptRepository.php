<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure\Persistence;

use PDO;
use PDOException;
use OpoMatic2000\Domain\AttemptScorer;

final readonly class TestAttemptRepository
{
    public function __construct(private PDO $connection) {}

    public function findBySubmission(string $id): ?array
    {
        $query = $this->connection->prepare('SELECT * FROM TestAttempts WHERE SubmissionId = ?');
        $query->execute([$id]);
        $row = $query->fetch();
        return $row === false ? null : $this->snapshots($row);
    }

    public function findOwned(int $id, string $username): ?array
    {
        $query = $this->connection->prepare('SELECT * FROM TestAttempts WHERE Id = ? AND Username = ?');
        $query->execute([$id, $username]);
        $row = $query->fetch();
        return $row === false ? null : $this->snapshots($row);
    }

    public function listOwned(string $username, int $page, int $pageSize): array
    {
        $count = $this->connection->prepare('SELECT COUNT(*) FROM TestAttempts WHERE Username = ?');
        $count->execute([$username]);
        $total = (int) $count->fetchColumn();
        $query = $this->connection->prepare('SELECT * FROM TestAttempts WHERE Username = ?
            ORDER BY CompletedAtUtc DESC, Id DESC LIMIT ? OFFSET ?');
        $query->bindValue(1, $username);
        $query->bindValue(2, $pageSize, PDO::PARAM_INT);
        $query->bindValue(3, ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        if ($rows !== []) {
            $ids = array_column($rows, 'Id');
            $topics = $this->connection->prepare('SELECT TestAttemptId, OriginalTopicId, TopicNameSnapshot
                FROM TestAttemptTopics WHERE TestAttemptId IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
                ORDER BY OriginalTopicId');
            $topics->execute($ids);
            $byAttempt = [];
            foreach ($topics->fetchAll() as $topic) { $byAttempt[$topic['TestAttemptId']][] = $topic; }
            foreach ($rows as &$row) { $row['Topics'] = $byAttempt[$row['Id']] ?? []; }
            unset($row);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    private function snapshots(array $row): array
    {
        $topics = $this->connection->prepare('SELECT OriginalTopicId, TopicNameSnapshot FROM TestAttemptTopics WHERE TestAttemptId = ? ORDER BY OriginalTopicId');
        $topics->execute([$row['Id']]); $row['Topics'] = $topics->fetchAll();
        $query = $this->connection->prepare('SELECT * FROM TestAttemptQuestions WHERE TestAttemptId = ? ORDER BY DisplayOrder');
        $query->execute([$row['Id']]); $row['Questions'] = $query->fetchAll();
        // Snapshot tables only: never reconstruct a completed attempt from current bank text.
        $options = $this->connection->prepare('SELECT o.* FROM TestAttemptOptions o
            JOIN TestAttemptQuestions q ON q.Id = o.TestAttemptQuestionId
            WHERE q.TestAttemptId = ? ORDER BY q.DisplayOrder, o.DisplayOrder');
        $options->execute([$row['Id']]);
        $byQuestion = [];
        foreach ($options->fetchAll() as $option) { $byQuestion[(int) $option['TestAttemptQuestionId']][] = $option; }
        foreach ($row['Questions'] as &$question) { $question['Options'] = $byQuestion[(int) $question['Id']] ?? []; }
        unset($question);
        return $row;
    }

    public function sources(array $ids): array
    {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $query = $this->connection->prepare('SELECT q.Id, q.TopicId, q.Statement, t.Name AS TopicName
            FROM Questions q JOIN Topics t ON t.Id = q.TopicId WHERE q.Id IN (' . $marks . ')');
        $query->execute($ids); $rows = [];
        foreach ($query->fetchAll() as $row) { $rows[(int) $row['Id']] = $row + ['Options' => []]; }
        $options = $this->connection->prepare('SELECT Id, QuestionId, Text, IsCorrect FROM QuestionOptions WHERE QuestionId IN (' . $marks . ')');
        $options->execute($ids);
        foreach ($options->fetchAll() as $option) { $rows[(int) $option['QuestionId']]['Options'][(int) $option['Id']] = $option; }
        return $rows;
    }

    /** Must run in the caller's transaction together with all snapshots. */
    public function save(string $submissionId, string $username, array $classified, int $correct, int $incorrect, int $score): array
    {
        $total = count($classified);
        $query = $this->connection->prepare('INSERT INTO TestAttempts
            (SubmissionId, CompletedAtUtc, TotalQuestions, CorrectCount, IncorrectCount, UnansweredCount, Score, ScoringRuleVersion, Username)
            VALUES (?, UTC_TIMESTAMP(6), ?, ?, ?, ?, ?, ?, ?)');
        try {
            $query->execute([$submissionId, $total, $correct, $incorrect, $total - $correct - $incorrect, $score, AttemptScorer::RULE_VERSION, $username]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) { throw new DuplicateSubmission('Entrega duplicada.', previous: $error); }
            throw $error;
        }
        $id = (int) $this->connection->lastInsertId();
        $topicInsert = $this->connection->prepare('INSERT INTO TestAttemptTopics (TestAttemptId, OriginalTopicId, TopicNameSnapshot) VALUES (?, ?, ?)');
        $topicIds = [];
        foreach ($classified as $item) {
            $source = $item['source'];
            if (!isset($topicIds[$source['TopicId']])) {
                $topicInsert->execute([$id, $source['TopicId'], $source['TopicName']]); $topicIds[$source['TopicId']] = true;
            }
        }
        $questionInsert = $this->connection->prepare('INSERT INTO TestAttemptQuestions
            (TestAttemptId, OriginalQuestionId, OriginalTopicId, DisplayOrder, StatementSnapshot, TopicNameSnapshot, Result)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $optionInsert = $this->connection->prepare('INSERT INTO TestAttemptOptions
            (TestAttemptQuestionId, OriginalOptionId, TextSnapshot, DisplayOrder, IsCorrect, IsSelected)
            VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($classified as $item) {
            $source = $item['source']; $token = $item['token'];
            $questionInsert->execute([$id, $token['questionId'], $source['TopicId'], $token['displayOrder'],
                $source['Statement'], $source['TopicName'], $item['result']->value]);
            $questionId = (int) $this->connection->lastInsertId();
            foreach ($token['options'] as $option) {
                $data = $source['Options'][$option['optionId']];
                $optionInsert->execute([$questionId, $option['optionId'], $data['Text'], $option['displayOrder'],
                    (int) $data['IsCorrect'], (int) ($item['selected'] === $option['optionId'])]);
            }
        }
        return $this->findOwned($id, $username);
    }
}
