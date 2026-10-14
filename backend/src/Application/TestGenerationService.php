<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use OpoMatic2000\Domain\InsufficientQuestions;
use OpoMatic2000\Domain\TestGenerationInput;
use OpoMatic2000\Infrastructure\Persistence\TestGenerationRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\SecureRandomizer;
use OpoMatic2000\Infrastructure\TestTokenProtector;
use OutOfBoundsException;
use RuntimeException;

final readonly class TestGenerationService
{
    public function __construct(private TestGenerationRepository $repository,
        private TransactionManager $transactions, private TestTokenProtector $tokens) {}

    public function generate(TestGenerationInput $input, string $username): array
    {
        // Count, selected questions and options share one consistent database snapshot.
        return $this->transactions->run(function () use ($input, $username): array {
            $topics = [];
            foreach ($this->repository->activeTopics($input->topicIds) as $row) {
                $topics[(int) $row['Id']] = ['id' => (int) $row['Id'], 'name' => $row['Name']];
            }
            foreach ($input->topicIds as $id) {
                if (!isset($topics[$id])) { throw new OutOfBoundsException('No existen o no están activos los temas solicitados.'); }
            }
            $eligible = $this->repository->eligibleIds($input->topicIds);
            if (count($eligible) < $input->numberOfQuestions) {
                throw new InsufficientQuestions($input->numberOfQuestions, count($eligible));
            }
            $ids = array_slice(SecureRandomizer::shuffle($eligible), 0, $input->numberOfQuestions);
            $sources = $this->repository->selected($ids);
            $questions = [];
            $tokenQuestions = [];
            foreach ($ids as $index => $id) {
                $row = $sources[$id];
                if (!in_array(count($row['Options']), [3, 4], true)) { throw new RuntimeException('La pregunta persistida tiene opciones inconsistentes.'); }
                $options = [];
                $tokenOptions = [];
                foreach (SecureRandomizer::shuffle($row['Options']) as $position => $option) {
                    $options[] = ['id' => (int) $option['Id'], 'displayOrder' => $position + 1, 'text' => $option['Text']];
                    $tokenOptions[] = ['optionId' => (int) $option['Id'], 'displayOrder' => $position + 1];
                }
                $questions[] = ['id' => $id, 'displayOrder' => $index + 1, 'topicId' => (int) $row['TopicId'],
                    'topicName' => $row['TopicName'], 'statement' => $row['Statement'], 'options' => $options];
                $tokenQuestions[] = ['questionId' => $id, 'displayOrder' => $index + 1, 'options' => $tokenOptions];
            }
            return $this->tokens->issue($username, $input->topicIds, $tokenQuestions)
                + ['topics' => array_map(static fn (int $id): array => $topics[$id], $input->topicIds), 'questions' => $questions];
        });
    }
}
