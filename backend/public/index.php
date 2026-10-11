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
$session = new AuthSession($storage, $config->isProduction() || RuntimeConfig::requestIsHttps());
$auth = new AuthHandler(
    $session,
    new LoginRateLimiter($storage),
    $config->isProduction(),
);
$router->add('POST', '/api/auth/login', static fn (Request $request): \OpoMatic2000\Http\Response => $auth->login($request));
$router->add('GET', '/api/auth/session', static fn (): \OpoMatic2000\Http\Response => $auth->session());
$router->add('POST', '/api/auth/logout', static fn (): \OpoMatic2000\Http\Response => $auth->logout());
$router->add('GET', '/api/auth/accesses', static fn (): \OpoMatic2000\Http\Response => $auth->accesses());

$topics = new \OpoMatic2000\Http\TopicHandler();
$router->add('GET', '/api/topics', static fn (Request $request): \OpoMatic2000\Http\Response => $topics->handle($request));
$router->add('POST', '/api/topics', static fn (Request $request): \OpoMatic2000\Http\Response => $topics->handle($request));
$router->add('GET', '/api/topics/{id}', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $topics->handle($request, $parameters['id']));
$router->add('PUT', '/api/topics/{id}', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $topics->handle($request, $parameters['id']));
$router->add('PATCH', '/api/topics/{id}/status', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $topics->handle($request, $parameters['id']));
$import = new \OpoMatic2000\Http\QuestionImportHandler();
$router->add('POST', '/api/questions/import', static fn (Request $request): \OpoMatic2000\Http\Response => $import->handle($request));
$questions = new \OpoMatic2000\Http\QuestionHandler();
$router->add('GET', '/api/questions', static fn (Request $request): \OpoMatic2000\Http\Response => $questions->handle($request));
$router->add('POST', '/api/questions', static fn (Request $request): \OpoMatic2000\Http\Response => $questions->handle($request));
$router->add('GET', '/api/questions/{id}', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $questions->handle($request, $parameters['id']));
$router->add('PUT', '/api/questions/{id}', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $questions->handle($request, $parameters['id']));
$router->add('PATCH', '/api/questions/{id}/status', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $questions->handle($request, $parameters['id']));
$generation = new \OpoMatic2000\Http\TestGenerationHandler($session);
$router->add('POST', '/api/tests/generate', static fn (Request $request): \OpoMatic2000\Http\Response => $generation->handle($request));
$completion = new \OpoMatic2000\Http\TestCompletionHandler($session);
$router->add('POST', '/api/tests/finish', static fn (Request $request): \OpoMatic2000\Http\Response => $completion->handle($request));
$attemptDetails = new \OpoMatic2000\Http\AttemptDetailsHandler($session);
$router->add('GET', '/api/attempts/{id}', static fn (Request $request, array $parameters): \OpoMatic2000\Http\Response => $attemptDetails->handle($parameters['id']));
$router->add('GET', '/api/attempts', static fn (Request $request): \OpoMatic2000\Http\Response => $attemptDetails->list($request));
$statistics = new \OpoMatic2000\Http\StatisticsHandler($session);
$router->add('GET', '/api/statistics/summary', static fn (): \OpoMatic2000\Http\Response => $statistics->summary());
$router->add('GET', '/api/statistics/topics', static fn (): \OpoMatic2000\Http\Response => $statistics->topics());
$kernel = new Kernel($router, beforeDispatch: static fn (Request $request) => $auth->guard($request));
$kernel->handle(Request::fromGlobals())->send();
