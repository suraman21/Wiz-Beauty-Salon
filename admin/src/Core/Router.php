<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Pattern-based router.
 *
 * Routes are declared as "METHOD /path/{param}" with an optional list of
 * middleware names. Dispatch walks the table in registration order, so specific
 * routes can be declared before catch-alls.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: mixed, middleware: list<string>, name: ?string}> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<string> $middleware
     */
    public function get(string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('GET', $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<string> $middleware
     */
    public function post(string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('POST', $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<string> $middleware
     */
    public function add(string $method, string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $normalised = Request::normalisePath($pattern);
        $regex = $this->compile($normalised);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalised,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $name,
        ];
    }

    /**
     * @return array{handler: mixed, params: array<string, string>, middleware: list<string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = Request::normalisePath($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return [
                'handler' => $route['handler'],
                'params' => $params,
                'middleware' => $route['middleware'],
            ];
        }

        return null;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] !== $name) {
                continue;
            }

            $url = $route['pattern'];

            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
            }

            return $url;
        }

        throw new \RuntimeException('Unknown route name: ' . $name);
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(string $path): array
    {
        $path = Request::normalisePath($path);
        $methods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path) === 1) {
                $methods[] = $route['method'];
            }
        }

        return array_values(array_unique($methods));
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $pattern,
        );

        $regex = $regex ?? $pattern;

        return '#^' . $regex . '$#';
    }
}
