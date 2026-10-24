<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router sederhana tanpa framework.
 * Mendukung parameter dinamis, contoh: /arsip/{id}.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:mixed}> */
    private array $routes = [];

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, mixed $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . rtrim($regex, '/') . '/?$#';

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => $regex,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            $params = array_filter(
                $matches,
                static fn ($key) => !is_int($key),
                ARRAY_FILTER_USE_KEY
            );

            $this->invoke($route['handler'], array_values($params));
            return;
        }

        $this->abort(404);
    }

    private function invoke(mixed $handler, array $params): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $controller = new $class();
            call_user_func_array([$controller, $action], $params);
            return;
        }

        throw new \RuntimeException('Handler route tidak valid.');
    }

    private function abort(int $code): void
    {
        http_response_code($code);
        $file = APP_PATH . '/views/errors/' . $code . '.php';

        if (is_file($file)) {
            require $file;
        } else {
            echo "Error {$code}";
        }
    }
}
