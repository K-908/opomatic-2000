<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use OpoMatic2000\Application\QuestionImportService;
use OpoMatic2000\Domain\QuestionImportConflict;
use OpoMatic2000\Domain\QuestionImportDocument;
use OpoMatic2000\Domain\QuestionValidation;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\QuestionImportRepository;
use OpoMatic2000\Infrastructure\Persistence\QuestionRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;

final class QuestionImportHandler
{
    public function handle(Request $request): Response
    {
        if (strlen($request->body) > QuestionImportDocument::MAXIMUM_BYTES) {
            throw new HttpProblem(413, 'Archivo demasiado grande', 'El archivo JSON no puede superar los 5 MB.');
        }
        try {
            $document = QuestionImportDocument::fromObject($request->jsonObject());
            $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
            $service = new QuestionImportService(new QuestionImportRepository($connection), new QuestionRepository($connection), new TransactionManager($connection));
            return Response::json($service->import($document), 201);
        } catch (QuestionValidation $error) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', 'Revisa el documento de importación.', errors: $error->errors);
        } catch (QuestionImportConflict $error) {
            throw new HttpProblem(409, 'Conflicto', $error->getMessage(), errors: $error->errors);
        }
    }
}
