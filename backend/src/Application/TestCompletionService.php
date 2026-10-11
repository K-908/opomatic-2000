<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use DomainException;
use OpoMatic2000\Domain\AttemptScorer;
use OpoMatic2000\Domain\QuestionResult;
use OpoMatic2000\Domain\TestCompletionInput;
use OpoMatic2000\Domain\TestCompletionValidation;
use OpoMatic2000\Infrastructure\Persistence\DuplicateSubmission;
use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\TestTokenProtector;

final readonly class TestCompletionService
{
    public function __construct(private TestAttemptRepository $attempts, private TransactionManager $transactions,
        private TestTokenProtector $tokens) {}

    public function finish(TestCompletionInput $input, string $username): array
    {
        $payload = $this->tokens->verify($input->testToken, $username);
        $input->validateAgainst($payload);
        try {
            return $this->transactions->run(function () use ($input, $username, $payload): array {
                $existing = $this->attempts->findBySubmission($input->submissionId);
                if ($existing !== null) { return $this->repeated($existing, $payload, $input, $username); }
                $sources = $this->attempts->sources(array_column($payload['questions'], 'questionId'));
                if (count($sources) !== count($payload['questions'])) {
                    throw new TestCompletionValidation(['testToken' => ['El contenido del test ya no es válido.']]);
                }
                $classified = []; $correct = 0; $incorrect = 0;
                foreach ($payload['questions'] as $question) {
                    $source = $sources[$question['questionId']];
                    $actual = array_keys($source['Options']); $expected = array_column($question['options'], 'optionId');
                    sort($actual); sort($expected);
                    $correctIds = [];
                    foreach ($source['Options'] as $id => $option) { if ((bool) $option['IsCorrect']) { $correctIds[] = $id; } }
                    if ($actual !== $expected || count($correctIds) !== 1) {
                        throw new TestCompletionValidation(['testToken' => ['Las opciones del test ya no son válidas.']]);
                    }
                    $selected = $input->answers[$question['questionId']];
                    $result = QuestionResult::classify($selected, $correctIds[0]);
                    if ($result === QuestionResult::Correct) { ++$correct; }
                    if ($result === QuestionResult::Incorrect) { ++$incorrect; }
                    $classified[] = ['source' => $source, 'token' => $question, 'selected' => $selected, 'result' => $result];
                }
                $score = AttemptScorer::millionths(count($classified), $correct, $incorrect);
                $saved = $this->attempts->save($input->submissionId, $username, $classified, $correct, $incorrect, $score);
                return ['created' => true, 'attempt' => AttemptContract::finished($saved)];
            });
        } catch (DuplicateSubmission) {
            // The competing insert committed first. The rollback ended our old read snapshot.
            $existing = $this->attempts->findBySubmission($input->submissionId);
            if ($existing === null) { throw new DomainException('No se pudo confirmar la entrega. Vuelve a intentarlo.'); }
            return $this->repeated($existing, $payload, $input, $username);
        }
    }

    private function repeated(array $existing, array $payload, TestCompletionInput $input, string $username): array
    {
        $compatible = $existing['Username'] === $username && (int) $existing['TotalQuestions'] === count($payload['questions'])
            && count($existing['Questions']) === count($payload['questions']);
        if ($compatible) {
            foreach ($payload['questions'] as $index => $question) {
                $saved = $existing['Questions'][$index];
                $selected = null;
                foreach ($saved['Options'] as $option) { if ((bool) $option['IsSelected']) { $selected = (int) $option['OriginalOptionId']; } }
                if ((int) $saved['OriginalQuestionId'] !== $question['questionId'] || (int) $saved['DisplayOrder'] !== $question['displayOrder']
                    || array_map('intval', array_column($saved['Options'], 'OriginalOptionId')) !== array_column($question['options'], 'optionId')
                    || $selected !== $input->answers[$question['questionId']]) { $compatible = false; break; }
            }
        }
        if (!$compatible) { throw new DomainException('El identificador de entrega ya se utilizó con datos diferentes.'); }
        return ['created' => false, 'attempt' => AttemptContract::finished($existing)];
    }
}
