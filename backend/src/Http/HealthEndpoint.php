<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

final class HealthEndpoint
{
    public function respond(): Response
    {
        return Response::json(['status' => 'ok', 'application' => 'OpoMatic-2000']);
    }
}
