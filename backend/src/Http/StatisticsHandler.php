<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use OpoMatic2000\Application\StatisticsService;
use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\StatisticsRepository;

final readonly class StatisticsHandler
{
    public function __construct(private AuthSession $session) {}

    public function summary(): Response { return $this->respond(false); }
    public function topics(): Response { return $this->respond(true); }

    private function respond(bool $topics): Response
    {
        $username = $this->session->username() ?? throw new HttpProblem(401, 'Sesión requerida', 'Inicia sesión.');
        $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
        $service = new StatisticsService(new StatisticsRepository($connection));
        return Response::json($topics ? $service->topics($username) : $service->summary($username),
            headers: ['Cache-Control' => 'no-store']);
    }
}
