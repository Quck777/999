<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function add(string $method, string $pattern, callable|string $handler, array $middleware = []): self
    {
        $pattern = rtrim($this->prefix, '/') . '/' . ltrim($pattern, '/');
        $pattern = '/' . ltrim($pattern, '/');
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'regex'      => $this->buildRegex($pattern),
        ];
        return $this;
    }

    public function group(array $middleware, callable $callback): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|string $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $pattern, $handler, $middleware);
    }

    private function buildRegex(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            fn($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );
        return '~^' . $regex . '$~';
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $uri = '/' . ltrim(parse_url($uri, PHP_URL_PATH), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['regex'], $uri, $matches)) {
                // Filter only named groups
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run middleware
                foreach ($route['middleware'] as $mw) {
                    $middlewareClass = "App\\Middleware\\{$mw}";
                    if (class_exists($middlewareClass)) {
                        $middleware = new $middlewareClass();
                        $result = $middleware->handle($params);
                        if ($result !== null) {
                            return $result; // Middleware returned a response
                        }
                    }
                }

                // Resolve handler
                if (is_string($route['handler']) && str_contains($route['handler'], '@')) {
                    [$controller, $action] = explode('@', $route['handler'], 2);
                    $controllerClass = "App\\Controllers\\{$controller}";
                    if (!class_exists($controllerClass)) {
                        throw new RuntimeException("Controller {$controllerClass} not found");
                    }
                    $instance = new $controllerClass();
                    if (!method_exists($instance, $action)) {
                        throw new RuntimeException("Method {$action} not found in {$controllerClass}");
                    }
                    return $instance->$action($params);
                }

                if (is_callable($route['handler'])) {
                    return call_user_func_array($route['handler'], [$params]);
                }

                throw new RuntimeException('Invalid route handler');
            }
        }

        // 404
        http_response_code(404);
        return jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
    }

    public function loadRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->add($route[0], $route[1], $route[2], $route[3] ?? []);
        }
    }
}

// Helper function for JSON responses
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

if (!function_exists('getInput')) {
    function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $str): string
    {
        return htmlspecialchars(trim($str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('validateRequired')) {
    function validateRequired(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[$field] = "{$label} обязательно для заполнения";
            }
        }
        return $errors;
    }
}
