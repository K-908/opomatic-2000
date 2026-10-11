<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use Closure;
use InvalidArgumentException;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: Closure}> */
    private array $routes = [];

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function add(string $method, string $pattern, Closure $handler): void
    {
        if ($pattern === '' || $pattern[0] !== '/') {
            throw new InvalidArgumentException('Las rutas deben empezar por /.');
        }

        $segments = $pattern === '/' ? [] : explode('/', trim($pattern, '/'));
        $regexSegments = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z][a-zA-Z0-9_]*)\}$/', $segment, $matches) === 1) {
                $regexSegments[] = '(?P<' . $matches[1] . '>[^/]+)';
            } else {
                $regexSegments[] = preg_quote($segment, '~');
            }
        }

        $regex = '~^/' . implode('/', $regexSegments) . '$~D';
        $this->routes[] = ['method' => strtoupper($method), 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return ($route['handler'])($request, $parameters);
        }

        if ($allowedMethods !== []) {
            $allow = implode(', ', array_unique($allowedMethods));
            throw new HttpProblem(405, 'Método no permitido', 'Esta ruta no admite ese método HTTP.', headers: ['Allow' => $allow]);
        }

        throw new HttpProblem(404, 'Recurso no encontrado', 'No existe la ruta solicitada.');
    }
}
