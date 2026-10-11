<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use DomainException;
use OpoMatic2000\Application\TestCompletionService;
use OpoMatic2000\Domain\TestCompletionInput;
use OpoMatic2000\Domain\TestCompletionValidation;
use OpoMatic2000\Domain\TestTokenFailure;
use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\TestTokenProtector;

final readonly class TestCompletionHandler
{
    public function __construct(private AuthSession $session) {}

    public function handle(Request $request): Response
    {
        $username = $this->session->username() ?? throw new HttpProblem(401, 'Sesión requerida', 'Inicia sesión.');
        try {
            $input = TestCompletionInput::fromObject($request->jsonObject());
            $tokens = TestTokenProtector::fromEnvironment();
            $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
            $service = new TestCompletionService(new TestAttemptRepository($connection), new TransactionManager($connection), $tokens);
            $result = $service->finish($input, $username);
            $headers = ['Cache-Control' => 'no-store'];
            if ($result['created']) { $headers['Location'] = '/api/attempts/' . $result['attempt']['attemptId']; }
            return Response::json($result['attempt'], $result['created'] ? 201 : 200, $headers);
        } catch (TestCompletionValidation $error) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', $error->getMessage(), errors: $error->errors);
        } catch (TestTokenFailure $error) {
            $status = match ($error->reason) { 'expired' => 410, 'forbidden' => 403, default => 400 };
            throw new HttpProblem($status, $status === 410 ? 'Test caducado' : 'Token del test no válido', $error->getMessage(),
                errors: ['testToken' => [$error->getMessage()]]);
        } catch (DomainException $error) {
            throw new HttpProblem(409, 'Conflicto de entrega', $error->getMessage());
        }
    }
}
