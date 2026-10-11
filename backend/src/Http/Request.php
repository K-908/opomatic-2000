<?php

declare(strict_types=1);

namespace OpoMatic2000\Http;

use JsonException;
use stdClass;

final readonly class Request
{
    public string $method;
    public string $path;

    /** @var array<string, mixed> */
    public array $query;

    /** @var array<string, string> */
    private array $headers;

    /** @param array<string, string> $headers */
    public function __construct(string $method, string $uri, public string $body = '', array $headers = [])
    {
        $this->method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = is_string($path) && $path !== '' ? $path : '/';

        $queryString = parse_url($uri, PHP_URL_QUERY);
        $query = [];
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }
        $this->query = $query;

        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }
        $this->headers = $normalizedHeaders;
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_') && is_string($value)) {
                $headerName = str_replace('_', '-', substr($name, 5));
                $headers[$headerName] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = '';
        }

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $body,
            $headers,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function jsonObject(): stdClass
    {
        $contentType = strtolower(trim(explode(';', $this->header('Content-Type') ?? '')[0]));
        if ($contentType !== 'application/json' && !str_ends_with($contentType, '+json')) {
            throw new HttpProblem(415, 'Tipo de contenido no admitido', 'Se esperaba application/json.');
        }

        try {
            $decoded = json_decode($this->body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw HttpProblem::invalidJson();
        }

        if (!$decoded instanceof stdClass) {
            throw HttpProblem::invalidJson();
        }

        return $decoded;
    }
}
