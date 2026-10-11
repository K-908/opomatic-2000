<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use OpoMatic2000\Application\AttemptDetailsService;
use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TestAttemptRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OutOfBoundsException;

final readonly class AttemptDetailsHandler
{
    public function __construct(private AuthSession $session) {}

    public function list(Request $request): Response
    {
        $username = $this->session->username() ?? throw new HttpProblem(401, 'Sesión requerida', 'Inicia sesión.');
        $values = [];
        $errors = [];
        foreach (['page' => [1, 2147483647], 'pageSize' => [20, 100]] as $key => [$default, $max]) {
            $raw = $request->query[$key] ?? (string) $default;
            $value = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => $max]]) : false;
            if ($value === false) { $errors[$key] = ['Indica un entero entre 1 y ' . $max . '.']; }
            else { $values[$key] = $value; }
        }
        if ($errors !== []) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', 'Corrige los campos indicados.', errors: $errors);
        }
        $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
        $service = new AttemptDetailsService(new TestAttemptRepository($connection), new TransactionManager($connection));
        return Response::json($service->list($username, $values['page'], $values['pageSize']), headers: ['Cache-Control' => 'no-store']);
    }

    public function handle(string $rawId): Response
    {
        $username = $this->session->username() ?? throw new HttpProblem(401, 'Sesión requerida', 'Inicia sesión.');
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new HttpProblem(404, 'Recurso no encontrado', 'No existe el intento solicitado.'); }
        $connection = (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
        $service = new AttemptDetailsService(new TestAttemptRepository($connection), new TransactionManager($connection));
        try { return Response::json($service->get($id, $username), headers: ['Cache-Control' => 'no-store']); }
        catch (OutOfBoundsException $error) { throw new HttpProblem(404, 'Recurso no encontrado', $error->getMessage()); }
    }
}
