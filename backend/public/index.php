<?php

declare(strict_types=1);

use OpoMatic2000\Http\HealthEndpoint;
use OpoMatic2000\Http\AuthHandler;
use OpoMatic2000\Http\Kernel;
use OpoMatic2000\Http\Request;
use OpoMatic2000\Http\Router;
use OpoMatic2000\Infrastructure\RuntimeConfig;
use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\LoginRateLimiter;
use OpoMatic2000\Infrastructure\PrivateStorage;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = RuntimeConfig::fromEnvironment();

if ($config->isProduction()) {
    ini_set('display_errors', '0');
}

$router = new Router();
$router->add('GET', '/health', static fn (): \OpoMatic2000\Http\Response => (new HealthEndpoint())->respond());

$storage = PrivateStorage::fromEnvironment();
$auth = new AuthHandler(
    new AuthSession($storage, $config->isProduction() || RuntimeConfig::requestIsHttps()),
    new LoginRateLimiter($storage),
    $config->isProduction(),
);
$router->add('POST', '/api/auth/login', static fn (Request $request): \OpoMatic2000\Http\Response => $auth->login($request));
$router->add('GET', '/api/auth/session', static fn (): \OpoMatic2000\Http\Response => $auth->session());
$router->add('POST', '/api/auth/logout', static fn (): \OpoMatic2000\Http\Response => $auth->logout());
$router->add('GET', '/api/auth/accesses', static fn (): \OpoMatic2000\Http\Response => $auth->accesses());

$kernel = new Kernel($router, beforeDispatch: static fn (Request $request) => $auth->guard($request));
$kernel->handle(Request::fromGlobals())->send();
