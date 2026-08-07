<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Core;

use VoteTrust\UserAdmin\Exceptions\NotFoundException;

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

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $names = [];
            $pattern = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $matches) use (&$names): string {
                $names[] = $matches[1];

                return '([^/]+)';
            }, rtrim($route['pattern'], '/')) ?: '/';
            $path = rtrim($request->path(), '/') ?: '/';
            if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $parameters = [];
            foreach ($names as $index => $name) {
                $parameters[$name] = urldecode($matches[$index]);
            }

            $handler = $route['handler'];
            $request = $request->withRouteParameters($parameters);
            if (is_array($handler) && is_string($handler[0])) {
                $controller = new $handler[0]();

                return $controller->{$handler[1]}($request);
            }

            return $handler($request);
        }

        throw new NotFoundException('The requested page was not found.');
    }
}
