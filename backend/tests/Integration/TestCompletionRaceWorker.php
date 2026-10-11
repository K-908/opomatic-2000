<?php

declare(strict_types=1);

use OpoMatic2000\Application\TestCompletionService;
use OpoMatic2000\Domain\TestCompletionInput;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\TestTokenProtector;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$config = DatabaseConfig::fromEnvironment();
if (!str_starts_with($config->name, 'opomatic_p03_')) { throw new RuntimeException('Solo bases temporales.'); }
$input = json_decode(stream_get_contents(STDIN), false, 32, JSON_THROW_ON_ERROR);
$directory = $argv[1] ?? '';
$slot = $argv[2] ?? '';
if (!in_array($slot, ['1', '2'], true) || !str_starts_with(basename($directory), 'opomatic-p10-race-')) {
    throw new RuntimeException('Barrera temporal inválida.');
}
$db = (new PdoConnectionFactory())->connect($config);
$service = new TestCompletionService(new TestAttemptRepository($db), new TransactionManager($db), TestTokenProtector::fromEnvironment());
file_put_contents($directory . '/ready-' . $slot, 'ready');
$deadline = microtime(true) + 10;
while (!is_file($directory . '/start')) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Barrera caducada.'); }
    usleep(10000);
}
$result = $service->finish(TestCompletionInput::fromObject($input), 'Gogloglo');
echo json_encode($result, JSON_THROW_ON_ERROR);
