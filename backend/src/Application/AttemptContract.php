<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use OpoMatic2000\Domain\AttemptScorer;
use OpoMatic2000\Domain\QuestionResult;

final class AttemptContract
{
    public static function finished(array $row): array
    {
        return ['attemptId' => (int) $row['Id'], 'completedAtUtc' => str_replace(' ', 'T', $row['CompletedAtUtc']) . 'Z',
            'totalQuestions' => (int) $row['TotalQuestions'], 'correctCount' => (int) $row['CorrectCount'],
            'incorrectCount' => (int) $row['IncorrectCount'], 'unansweredCount' => (int) $row['UnansweredCount'],
            'score' => AttemptScorer::points((int) $row['Score'])];
    }

    public static function summary(array $row): array
    {
        $summary = self::finished($row); unset($summary['attemptId']);
        return ['id' => (int) $row['Id']] + $summary + [
            'topics' => array_map(static fn (array $topic): array => ['id' => (int) $topic['OriginalTopicId'], 'name' => $topic['TopicNameSnapshot']], $row['Topics']),
        ];
    }

    public static function detail(array $row): array
    {
        return self::summary($row) + [
            'questions' => array_map(static fn (array $question): array => [
                'displayOrder' => (int) $question['DisplayOrder'], 'originalQuestionId' => (int) $question['OriginalQuestionId'],
                'topicName' => $question['TopicNameSnapshot'], 'statement' => $question['StatementSnapshot'],
                'result' => QuestionResult::from((int) $question['Result'])->label(),
                'options' => array_map(static fn (array $option): array => [
                    'displayOrder' => (int) $option['DisplayOrder'], 'text' => $option['TextSnapshot'],
                    'isCorrect' => (bool) $option['IsCorrect'], 'isSelected' => (bool) $option['IsSelected'],
                ], $question['Options']),
            ], $row['Questions']),
        ];
    }
}
