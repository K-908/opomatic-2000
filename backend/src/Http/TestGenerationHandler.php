<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use OpoMatic2000\Application\TestGenerationService;
use OpoMatic2000\Domain\InsufficientQuestions;
use OpoMatic2000\Domain\TestGenerationInput;
use OpoMatic2000\Domain\TestGenerationValidation;
use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TestGenerationRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\TestTokenProtector;
use OutOfBoundsException;

final readonly class TestGenerationHandler
{
    public function __construct(private AuthSession $session) {}

    public function handle(Request $request): Response
    {
        $username = $this->session->username() ?? throw new HttpProblem(401, 'Sesión requerida', 'Inicia sesión.');
        try {
            $input = TestGenerationInput::fromObject($request->jsonObject());
            $tokens = TestTokenProtector::fromEnvironment();
            $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
            $service = new TestGenerationService(new TestGenerationRepository($connection), new TransactionManager($connection), $tokens);
            return Response::json($service->generate($input, $username), headers: ['Cache-Control' => 'no-store']);
        } catch (TestGenerationValidation $error) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', $error->getMessage(), errors: $error->errors);
        } catch (InsufficientQuestions $error) {
            throw new HttpProblem(409, 'Preguntas insuficientes', $error->getMessage(),
                extensions: ['requestedCount' => $error->requestedCount, 'availableCount' => $error->availableCount]);
        } catch (OutOfBoundsException $error) {
            throw new HttpProblem(404, 'Recurso no encontrado', $error->getMessage());
        }
    }
}
