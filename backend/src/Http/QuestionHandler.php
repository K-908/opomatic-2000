<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use DomainException;
use OutOfBoundsException;
use OpoMatic2000\Application\QuestionService;
use OpoMatic2000\Domain\QuestionInput;
use OpoMatic2000\Domain\QuestionValidation;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\QuestionRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;

final class QuestionHandler
{
    private ?QuestionService $service = null;

    public function handle(Request $request, ?string $rawId = null): Response
    {
        $id = null;
        if ($rawId !== null) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id === false) { throw new HttpProblem(404, 'Recurso no encontrado', 'No existe la pregunta solicitada.'); }
        }
        try {
            if ($request->method === 'GET' && $id === null) {
                $page = self::integer($request->query['page'] ?? '1', 'page', 2147483647);
                $size = self::integer($request->query['pageSize'] ?? '20', 'pageSize', 100);
                $topic = isset($request->query['topicId']) ? self::integer($request->query['topicId'], 'topicId', 2147483647) : null;
                $inactive = $request->query['includeInactive'] ?? 'false';
                if (!is_string($inactive) || !in_array(strtolower($inactive), ['true', 'false'], true)) { throw new QuestionValidation(['includeInactive' => ['Indica true o false.']]); }
                $search = $request->query['search'] ?? '';
                if (!is_string($search) || preg_match('//u', $search) !== 1 || str_contains($search, "\0")) { throw new QuestionValidation(['search' => ['Indica un texto válido.']]); }
                $search = preg_replace('/^\s+|\s+$/u', '', $search);
                return Response::json($this->service()->list($topic, strtolower($inactive) === 'true', $search, $page, $size));
            }
            if ($request->method === 'GET') { return Response::json($this->service()->get($id)); }
            $body = $request->jsonObject();
            if ($request->method === 'POST' || $request->method === 'PUT') {
                $input = QuestionInput::fromObject($body);
                if ($request->method === 'PUT') { return Response::json($this->service()->update($id, $input)); }
                $question = $this->service()->create($input);
                return Response::json($question, 201, ['Location' => '/api/questions/' . $question['id']]);
            }
            if (!isset($body->isActive) || !is_bool($body->isActive)) { throw new QuestionValidation(['isActive' => ['Indica true o false.']]); }
            $this->service()->setStatus($id, $body->isActive);
            return Response::noContent();
        } catch (QuestionValidation $error) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', $error->getMessage(), errors: $error->errors);
        } catch (OutOfBoundsException $error) {
            throw new HttpProblem(404, 'Recurso no encontrado', $error->getMessage());
        } catch (DomainException $error) {
            throw new HttpProblem(409, 'Conflicto', $error->getMessage());
        }
    }

    private static function integer(mixed $value, string $key, int $max): int
    {
        $parsed = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]) : false;
        if ($parsed === false) { throw new QuestionValidation([$key => ['Indica un entero entre 1 y ' . $max . '.']]); }
        return $parsed;
    }

    private function service(): QuestionService
    {
        if ($this->service === null) {
            $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
            $this->service = new QuestionService(new QuestionRepository($connection), new TransactionManager($connection));
        }
        return $this->service;
    }
}
