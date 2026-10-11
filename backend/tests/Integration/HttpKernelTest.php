<?php

declare(strict_types=1);

use OpoMatic2000\Http\HealthEndpoint;
use OpoMatic2000\Http\Kernel;
use OpoMatic2000\Http\Request;
use OpoMatic2000\Http\Response;
use OpoMatic2000\Http\Router;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function payload(Response $response): array
{
    $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    verify(is_array($decoded), 'La respuesta no contiene un objeto JSON.');
    return $decoded;
}

$router = new Router();
$router->add('GET', '/health', static fn (): Response => (new HealthEndpoint())->respond());
$router->add('GET', '/api/items/{id}', static fn (Request $request, array $parameters): Response => Response::json([
    'id' => $parameters['id'],
    'search' => $request->query['search'] ?? null,
]));
$router->add('POST', '/api/echo', static function (Request $request): Response {
    $document = $request->jsonObject();
    return Response::json(['name' => $document->name ?? null], 201);
});
$router->add('DELETE', '/api/items/{id}', static fn (): Response => Response::noContent());
$router->add('GET', '/api/crash', static function (): never {
    throw new RuntimeException('detalle-privado-que-no-debe-salir');
});

$logs = [];
$kernel = new Kernel($router, static function (string $message) use (&$logs): void {
    $logs[] = $message;
});

$health = $kernel->handle(new Request('GET', '/health'));
verify($health->status === 200, 'GET /health debe devolver 200.');
verify(payload($health) === ['status' => 'ok', 'application' => 'OpoMatic-2000'], 'Salud: cuerpo incorrecto.');
verify(str_starts_with($health->headers['Content-Type'], 'application/json'), 'Salud: Content-Type incorrecto.');

$item = $kernel->handle(new Request('GET', '/api/items/42?search=tema'));
verify(payload($item) === ['id' => '42', 'search' => 'tema'], 'Parámetros de ruta y consulta incorrectos.');

$created = $kernel->handle(new Request('POST', '/api/echo', '{"name":"Ana"}', ['Content-Type' => 'application/json; charset=utf-8']));
verify($created->status === 201 && payload($created)['name'] === 'Ana', 'POST JSON válido debe devolver 201.');

$empty = $kernel->handle(new Request('DELETE', '/api/items/42'));
verify($empty->status === 204 && $empty->body === '', '204 no debe contener cuerpo.');

$invalid = $kernel->handle(new Request('POST', '/api/echo', '{', ['Content-Type' => 'application/json']));
verify($invalid->status === 400, 'JSON inválido debe devolver 400.');
verify(str_starts_with($invalid->headers['Content-Type'], 'application/problem+json'), '400 debe ser Problem Details.');
verify(payload($invalid)['errors']['body'][0] === 'Envía un objeto JSON válido.', '400 debe indicar error en body.');
verify(payload($invalid)['instance'] === '/api/echo', 'Problem Details debe indicar la ruta.');

$arrayBody = $kernel->handle(new Request('POST', '/api/echo', '[]', ['Content-Type' => 'application/json']));
verify($arrayBody->status === 400, 'Un array JSON no es un objeto de petición.');

$unsupported = $kernel->handle(new Request('POST', '/api/echo', '{"name":"Ana"}', ['Content-Type' => 'text/plain']));
verify($unsupported->status === 415, 'Content-Type no admitido debe devolver 415.');

$missing = $kernel->handle(new Request('GET', '/api/missing'));
verify($missing->status === 404 && payload($missing)['status'] === 404, 'Ruta desconocida debe devolver 404.');

$wrongMethod = $kernel->handle(new Request('POST', '/health'));
verify($wrongMethod->status === 405, 'Método no admitido debe devolver 405.');
verify($wrongMethod->headers['Allow'] === 'GET', '405 debe informar el método permitido.');

$crash = $kernel->handle(new Request('GET', '/api/crash'));
verify($crash->status === 500, 'Excepción inesperada debe devolver 500.');
verify(!str_contains($crash->body, 'detalle-privado'), '500 no debe filtrar detalles internos.');
verify(isset(payload($crash)['traceId']) && count($logs) === 1, '500 debe generar referencia y log privado.');

echo "HTTP kernel: 200, 201, 204, 400, 404, 405, 415 y 500 OK\n";
