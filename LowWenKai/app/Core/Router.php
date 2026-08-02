<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use VoteTrust\Election\Exceptions\NotFoundException;

final class Router
{
    /** @var list<array{method:string, pattern:string, handler:callable|array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameterNames = [];
            $regex = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];

                return '([^/]+)';
            }, rtrim($route['pattern'], '/'));

            $normalizedPath = rtrim($request->path(), '/') ?: '/';
            if (!preg_match('#^' . ($regex ?: '/') . '$#', $normalizedPath, $matches)) {
                continue;
            }

            array_shift($matches);
            $parameters = [];
            foreach ($parameterNames as $index => $name) {
                $parameters[$name] = urldecode($matches[$index]);
            }

            $boundRequest = $request->withRouteParameters($parameters);
            $handler = $route['handler'];

            if (is_array($handler) && is_string($handler[0])) {
                $controller = new $handler[0]();

                return $controller->{$handler[1]}($boundRequest);
            }

            return $handler($boundRequest);
        }

        throw new NotFoundException('The requested page was not found.');
    }
}

