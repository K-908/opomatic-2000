<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use OpoMatic2000\Infrastructure\AuthSession;
use OpoMatic2000\Infrastructure\LoginRateLimiter;
use OpoMatic2000\Infrastructure\RuntimeConfig;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\LoginAccessRepository;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\UserRepository;
use PDO;

final class AuthHandler
{
    private ?PDO $connection = null;

    public function __construct(private AuthSession $session, private LoginRateLimiter $limiter, private bool $requireHttps)
    {
    }

    public function guard(Request $request): void
    {
        if ($request->path === '/health' || ($request->path === '/api/auth/login' && $request->method === 'POST')) {
            return;
        }

        $this->requireUser();
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->session->requireCsrf($request->header('X-CSRF-Token'));
        }
    }

    public function login(Request $request): Response
    {
        if ($this->requireHttps && !RuntimeConfig::requestIsHttps()) {
            throw new HttpProblem(403, 'HTTPS obligatorio', 'El acceso requiere HTTPS.');
        }

        $ip = filter_var($request->remoteIp, FILTER_VALIDATE_IP) ? $request->remoteIp : '0.0.0.0';
        if (!$this->limiter->allow($ip)) {
            throw new HttpProblem(429, 'Demasiadas solicitudes', 'Espera antes de volver a intentar el acceso.', headers: ['Retry-After' => '60']);
        }

        $body = $request->jsonObject();
        if (!isset($body->username, $body->password) || !is_string($body->username) || !is_string($body->password)
            || $body->username === '' || $body->password === '' || strlen($body->username) > 100 || strlen($body->password) > 4096) {
            throw new HttpProblem(400, 'Los datos enviados no son válidos', 'Indica usuario y contraseña válidos.');
        }

        $user = in_array($body->username, ['Gogloglo', 'Virginia'], true)
            ? (new UserRepository($this->database()))->findByUsername($body->username)
            : null;
        if ($user === null || !password_verify($body->password, $user['PasswordHash'])) {
            throw new HttpProblem(401, 'Acceso no autorizado', 'Usuario o contraseña incorrectos.');
        }

        (new LoginAccessRepository($this->database()))->record($user['Username'], $ip);
        $this->session->establish($user['Username']);

        return Response::json(['username' => $user['Username']], headers: ['Cache-Control' => 'no-store']);
    }

    public function session(): Response
    {
        $username = $this->requireUser();
        $this->session->refreshCsrfCookie();
        return Response::json(['username' => $username], headers: ['Cache-Control' => 'no-store']);
    }

    public function logout(): Response
    {
        $this->session->destroy();
        return Response::noContent(['Cache-Control' => 'no-store']);
    }

    public function accesses(): Response
    {
        $username = $this->requireUser();
        return Response::json((new LoginAccessRepository($this->database()))->recentFor($username), headers: ['Cache-Control' => 'no-store']);
    }

    private function requireUser(): string
    {
        $username = $this->session->username();
        if ($username === null) {
            throw new HttpProblem(401, 'Acceso no autorizado', 'Inicia sesión para continuar.');
        }
        return $username;
    }

    private function database(): PDO
    {
        return $this->connection ??= (new PdoConnectionFactory())->connect(DatabaseConfig::fromEnvironment());
    }

}
