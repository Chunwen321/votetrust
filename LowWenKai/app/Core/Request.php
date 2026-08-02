<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

final class Request
{
    /** @param array<string, string> $routeParameters */
    public function __construct(
        private readonly array $routeParameters = []
    ) {
    }

    public static function capture(): self
    {
        return new self();
    }

    public function withRouteParameters(array $parameters): self
    {
        return new self($parameters);
    }

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return '/' . trim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $_SERVER[$serverKey] ?? $default;
    }

    public function expectsJson(): bool
    {
        return str_starts_with($this->path(), '/api/')
            || str_contains((string) $this->header('Accept', ''), 'application/json');
    }
}

