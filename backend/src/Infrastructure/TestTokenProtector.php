<?php

declare(strict_types=1);

namespace OpoMatic2000\Infrastructure;

use Closure;
use JsonException;
use OpoMatic2000\Domain\TestTokenFailure;
use RuntimeException;

final readonly class TestTokenProtector
{
    public const LIFETIME_SECONDS = 43200;
    private const PURPOSE = 'OpoMatic2000.TestToken.v1';
    private string $key;
    private Closure $clock;

    public function __construct(string $secret, ?Closure $clock = null)
    {
        if (strlen($secret) < 32) { throw new RuntimeException('APP_KEY debe contener al menos 32 bytes aleatorios.'); }
        $this->key = hash_hkdf('sha256', $secret, 32, self::PURPOSE);
        $this->clock = $clock ?? static fn (): int => time();
    }

    public static function fromEnvironment(): self
    {
        return new self(getenv('APP_KEY') ?: '');
    }

    /** Only identifiers and generated order enter the token; no answers or browser progress. */
    public function issue(string $username, array $topicIds, array $questions): array
    {
        $now = ($this->clock)();
        $payload = ['version' => 1, 'username' => $username, 'issuedAt' => $now,
            'expiresAt' => $now + self::LIFETIME_SECONDS, 'topicIds' => $topicIds, 'questions' => $questions];
        if (!self::validPayload($payload)) { throw new RuntimeException('Contenido del token inconsistente.'); }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm',
            $this->key, OPENSSL_RAW_DATA, $nonce, $tag, self::PURPOSE, 16);
        if ($ciphertext === false) { throw new RuntimeException('No se pudo proteger el test.'); }
        $token = 'v1.' . rtrim(strtr(base64_encode($nonce . $tag . $ciphertext), '+/', '-_'), '=');
        return ['testToken' => $token, 'expiresAtUtc' => gmdate('Y-m-d\TH:i:s\Z', $payload['expiresAt'])];
    }

    public function verify(string $token, string $username): array
    {
        if (strlen($token) > 200000 || preg_match('/^v1\.([A-Za-z0-9_-]+)$/D', $token, $matches) !== 1) {
            throw new TestTokenFailure('invalid');
        }
        $raw = base64_decode(strtr($matches[1], '-_', '+/'), true);
        if ($raw === false || strlen($raw) <= 28
            || rtrim(strtr(base64_encode($raw), '+/', '-_'), '=') !== $matches[1]) {
            throw new TestTokenFailure('invalid');
        }
        $json = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), self::PURPOSE);
        if ($json === false) { throw new TestTokenFailure('invalid'); }
        try { $payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new TestTokenFailure('invalid'); }
        if (!is_array($payload) || !self::validPayload($payload) || $payload['issuedAt'] > ($this->clock)()) {
            throw new TestTokenFailure('invalid');
        }
        if (!hash_equals($payload['username'], $username)) { throw new TestTokenFailure('forbidden'); }
        if ($payload['expiresAt'] <= ($this->clock)()) { throw new TestTokenFailure('expired'); }
        return $payload;
    }

    private static function validPayload(array $payload): bool
    {
        if (($payload['version'] ?? null) !== 1 || !is_string($payload['username'] ?? null)
            || !in_array($payload['username'], ['Gogloglo', 'Virginia'], true)
            || !is_int($payload['issuedAt'] ?? null) || !is_int($payload['expiresAt'] ?? null)
            || $payload['expiresAt'] - $payload['issuedAt'] !== self::LIFETIME_SECONDS
            || !self::ids($payload['topicIds'] ?? null) || !is_array($payload['questions'] ?? null)
            || !array_is_list($payload['questions']) || count($payload['questions']) < 1 || count($payload['questions']) > 200) {
            return false;
        }
        $questionIds = [];
        $optionIds = [];
        foreach ($payload['questions'] as $index => $question) {
            if (!is_array($question) || !self::id($question['questionId'] ?? null)
                || ($question['displayOrder'] ?? null) !== $index + 1 || !is_array($question['options'] ?? null)
                || !array_is_list($question['options']) || count($question['options']) !== 4) { return false; }
            $questionIds[] = $question['questionId'];
            foreach ($question['options'] as $position => $option) {
                if (!is_array($option) || !self::id($option['optionId'] ?? null)
                    || ($option['displayOrder'] ?? null) !== $position + 1) { return false; }
                $optionIds[] = $option['optionId'];
            }
        }
        return self::ids($questionIds) && self::ids($optionIds);
    }

    private static function ids(mixed $values): bool
    {
        return is_array($values) && array_is_list($values) && $values !== []
            && count(array_filter($values, self::id(...))) === count($values)
            && count(array_unique($values)) === count($values);
    }

    private static function id(mixed $value): bool
    {
        return is_int($value) && $value > 0 && $value <= 2147483647;
    }
}
