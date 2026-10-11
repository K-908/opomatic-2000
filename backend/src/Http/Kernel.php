<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use Closure;
use Throwable;

final readonly class Kernel
{
    /** @param null|Closure(string): void $logger */
    public function __construct(private Router $router, private ?Closure $logger = null)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpProblem $problem) {
            return Response::problem($problem, $request->path);
        } catch (Throwable $error) {
            $traceId = bin2hex(random_bytes(8));
            $message = sprintf('[%s] Error no controlado: %s', $traceId, $error::class);
            if ($this->logger !== null) {
                ($this->logger)($message);
            } else {
                error_log($message);
            }

            return Response::problem(
                new HttpProblem(500, 'Error interno del servidor', 'No se ha podido completar la solicitud.'),
                $request->path,
                $traceId,
            );
        }
    }
}
