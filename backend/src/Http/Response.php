<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    public static function noContent(array $headers = []): self
    {
        return new self(204, $headers, '');
    }

    public static function problem(HttpProblem $problem, string $instance, ?string $traceId = null): self
    {
        $payload = [
            'type' => $problem->type,
            'title' => $problem->title,
            'status' => $problem->status,
            'detail' => $problem->getMessage(),
            'instance' => $instance,
        ];
        if ($traceId !== null) {
            $payload['traceId'] = $traceId;
        }
        if ($problem->errors !== []) {
            $payload['errors'] = $problem->errors;
        }

        return new self(
            $problem->status,
            ['Content-Type' => 'application/problem+json; charset=utf-8', ...$problem->headers],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
        );
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
