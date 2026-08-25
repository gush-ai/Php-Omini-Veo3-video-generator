<?php
declare(strict_types=1);

namespace GVid;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            Response::error('Route not found.', 404, 'not_found');
        }

        try {
            call_user_func($handler);
        } catch (\Throwable $e) {
            error_log('[GVid] ' . $e->getMessage());
            Response::error('Internal server error.', 500, 'internal_error');
        }
    }
}
