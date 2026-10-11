<?php

declare(strict_types=1);

use OpoMatic2000\Http\HealthEndpoint;
use OpoMatic2000\Http\Kernel;
use OpoMatic2000\Http\Request;
use OpoMatic2000\Http\Router;
use OpoMatic2000\Infrastructure\RuntimeConfig;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = RuntimeConfig::fromEnvironment();

if ($config->isProduction()) {
    ini_set('display_errors', '0');
}

$router = new Router();
$router->add('GET', '/health', static fn (): \OpoMatic2000\Http\Response => (new HealthEndpoint())->respond());

$kernel = new Kernel($router);
$kernel->handle(Request::fromGlobals())->send();
