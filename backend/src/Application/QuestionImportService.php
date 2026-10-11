<?php

declare(strict_types=1);

namespace OpoMatic2000\Application;

use OpoMatic2000\Domain\QuestionImportConflict;
use OpoMatic2000\Domain\QuestionImportDocument;
use OpoMatic2000\Domain\QuestionValidation;
use OpoMatic2000\Infrastructure\Persistence\QuestionImportRepository;
use OpoMatic2000\Infrastructure\Persistence\QuestionRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;

final readonly class QuestionImportService
{
    public function __construct(private QuestionImportRepository $imports, private QuestionRepository $questions, private TransactionManager $transactions) {}

    public function import(QuestionImportDocument $document): array
    {
        return $this->transactions->run(function () use ($document): array {
            $errors = [];
            foreach ($this->imports->duplicateNames($document->topics) as $duplicate) {
                $errors['topics[' . $duplicate['LastIndex'] . '].name'] = ['El tema está repetido; ya aparece en topics[' . $duplicate['FirstIndex'] . '].name.'];
            }
            if ($errors !== []) { throw new QuestionValidation($errors); }
            $resolved = [];
            $created = 0;
            foreach ($document->topics as $index => $topic) {
                $result = $this->imports->resolveTopic($topic['name']);
                if (!(bool) $result['topic']['IsActive']) {
                    $errors['topics[' . $index . '].name'] = ['El tema existe, pero está inactivo.'];
                }
                $resolved[] = (int) $result['topic']['Id'];
                if ($result['created']) { $created++; }
            }
            if ($errors !== []) { throw new QuestionImportConflict($errors); }
            $count = 0;
            foreach ($document->topics as $index => $topic) {
                foreach ($topic['questions'] as $question) {
                    $this->questions->create($question->withTopic($resolved[$index]));
                    $count++;
                }
            }
            return ['createdTopics' => $created, 'reusedTopics' => count($resolved) - $created, 'importedQuestions' => $count];
        });
    }
}
