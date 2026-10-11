<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use OpoMatic2000\Infrastructure\Persistence\StatisticsRepository;

final readonly class StatisticsService
{
    public function __construct(private StatisticsRepository $statistics) {}

    private static function counts(array $row): array
    {
        $correct = (int) $row['CorrectCount'];
        $incorrect = (int) $row['IncorrectCount'];
        $answered = $correct + $incorrect;
        return ['answeredQuestions' => $answered, 'correctCount' => $correct,
            'incorrectCount' => $incorrect, 'unansweredCount' => (int) $row['UnansweredCount'],
            'accuracyPercentage' => $answered === 0 ? null : round($correct * 100 / $answered, 2)];
    }

    public function summary(string $username): array
    {
        $row = $this->statistics->summary($username);
        return ['completedTests' => (int) $row['CompletedTests']] + self::counts($row);
    }

    public function topics(string $username): array
    {
        $result = [];
        $position = 0;
        foreach ($this->statistics->topics($username) as $row) {
            $counts = self::counts($row);
            $result[] = ['topicId' => (int) $row['TopicId'], 'topicName' => $row['TopicName']] + $counts +
                ['rankingPosition' => $counts['answeredQuestions'] === 0 ? null : ++$position];
        }
        return $result;
    }
}
