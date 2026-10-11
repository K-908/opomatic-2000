<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use OpoMatic2000\Application\TopicService;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TopicRepository;

final class TopicHandler
{
    private ?TopicService $service = null;

    public function handle(Request $request, ?string $rawId = null): Response
    {
        $id = null;
        if ($rawId !== null) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id === false) {
                throw new HttpProblem(404, 'Recurso no encontrado', 'No existe el tema solicitado.');
            }
        }
        try {
            if ($request->method === 'GET' && $id === null) {
                $includeInactive = $request->query['includeInactive'] ?? 'false';
                if (!is_string($includeInactive) || !in_array(strtolower($includeInactive), ['true', 'false'], true)) {
                    throw new HttpProblem(400, 'Los datos enviados no son válidos', 'includeInactive debe ser true o false.', errors: ['includeInactive' => ['Indica true o false.']]);
                }
                return Response::json($this->service()->list(strtolower($includeInactive) === 'true'));
            }
            if ($request->method === 'GET') {
                return Response::json($this->service()->get($id));
            }
            $body = $request->jsonObject();
            if ($request->method === 'POST') {
                $topic = $this->service()->create($body->name ?? null);
                return Response::json($topic, 201, ['Location' => '/api/topics/' . $topic['id']]);
            }
            if ($request->method === 'PUT') {
                return Response::json($this->service()->update($id, $body->name ?? null));
            }
            if (!isset($body->isActive) || !is_bool($body->isActive)) {
                throw new HttpProblem(400, 'Los datos enviados no son válidos', 'isActive debe ser booleano.', errors: ['isActive' => ['Indica true o false.']]);
            }
            $this->service()->setStatus($id, $body->isActive);
            return Response::noContent();
        } catch (InvalidArgumentException $error) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', $error->getMessage(), errors: ['name' => [$error->getMessage()]]);
        } catch (OutOfBoundsException $error) {
            throw new HttpProblem(404, 'Recurso no encontrado', $error->getMessage());
        } catch (DomainException $error) {
            throw new HttpProblem(409, 'Conflicto', $error->getMessage());
        }
    }

    private function service(): TopicService
    {
        return $this->service ??= new TopicService(new TopicRepository((new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment())));
    }
}
