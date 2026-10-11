<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

use OpoMatic2000\Http\HttpProblem;
use RuntimeException;

final readonly class AuthSession
{
    public const LIFETIME_SECONDS = 43200;
    private const SESSION_COOKIE = 'OPOMATICSESSID';
    private const CSRF_COOKIE = 'OPOMATIC_CSRF';

    public function __construct(private PrivateStorage $storage, private bool $secureCookies)
    {
    }

    public function username(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::SESSION_COOKIE])) {
            return null;
        }
        $this->open();

        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth) || !isset($auth['username'], $auth['expiresAt'], $auth['csrf'])
            || !is_string($auth['username']) || !is_int($auth['expiresAt']) || !is_string($auth['csrf'])
            || $auth['expiresAt'] <= time()) {
            $this->destroy();
            return null;
        }

        return $auth['username'];
    }

    public function establish(string $username): void
    {
        $this->open();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('No se pudo renovar la sesión.');
        }

        $expiresAt = time() + self::LIFETIME_SECONDS;
        $csrf = bin2hex(random_bytes(32));
        $_SESSION = ['auth' => ['username' => $username, 'expiresAt' => $expiresAt, 'csrf' => $csrf]];
        $this->setCsrfCookie($csrf, $expiresAt);
    }

    public function requireCsrf(?string $submittedToken): void
    {
        $stored = $_SESSION['auth']['csrf'] ?? null;
        if (!is_string($stored) || !is_string($submittedToken) || !hash_equals($stored, $submittedToken)) {
            throw new HttpProblem(403, 'Solicitud prohibida', 'Token CSRF ausente o no válido.');
        }
    }

    public function refreshCsrfCookie(): void
    {
        $auth = $_SESSION['auth'] ?? null;
        if (is_array($auth) && isset($auth['csrf'], $auth['expiresAt'])) {
            $this->setCsrfCookie($auth['csrf'], $auth['expiresAt']);
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
        $this->cookie(self::SESSION_COOKIE, '', time() - 3600, true);
        $this->cookie(self::CSRF_COOKIE, '', time() - 3600, false);
    }

    private function open(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== self::SESSION_COOKIE) {
                throw new RuntimeException('PHP inició una sesión incompatible antes de la aplicación.');
            }
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException('No se puede iniciar la sesión después de enviar la respuesta.');
        }

        ini_set('session.save_path', $this->storage->directory('sessions'));
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME_SECONDS);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name(self::SESSION_COOKIE);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME_SECONDS,
            'path' => '/',
            'secure' => $this->secureCookies,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new RuntimeException('No se pudo iniciar la sesión.');
        }
    }

    private function setCsrfCookie(string $token, int $expiresAt): void
    {
        $this->cookie(self::CSRF_COOKIE, $token, $expiresAt, false);
    }

    private function cookie(string $name, string $value, int $expiresAt, bool $httpOnly): void
    {
        if (!setcookie($name, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->secureCookies,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ])) {
            throw new RuntimeException('No se pudo enviar una cookie de sesión.');
        }
    }
}
