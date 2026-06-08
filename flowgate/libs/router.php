<?php

declare(strict_types=1);

/*
 * Router da Flowgate — mesma implementação do Automax.
 * Mantido em cópia local para que os dois projetos sejam
 * deployados de forma independente (Virtual Hosts separados).
 */

class RouteException extends \RuntimeException {}

class Route
{
    private const VALID_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    public readonly string $path;
    public readonly string $method;
    private readonly mixed $callback;
    private array $paramNames = [];
    private string $regex;

    public function __construct(string $path, string $method, callable $callback)
    {
        $method = strtoupper($method);
        if (!in_array($method, self::VALID_METHODS, true)) {
            throw new RouteException("Método HTTP inválido '$method'.");
        }
        $this->path     = $path;
        $this->method   = $method;
        $this->callback = $callback;
        $this->regex    = $this->build_regex($path);
    }

    private function build_regex(string $path): string
    {
        $pattern = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($m) {
            $this->paramNames[] = $m[1];
            return '([^/]+)';
        }, $path);
        return '#^' . $pattern . '$#';
    }

    public function matches(string $path): bool
    {
        return (bool) preg_match($this->regex, $path);
    }

    public function extract_params(string $path): array
    {
        preg_match($this->regex, $path, $matches);
        array_shift($matches);
        return array_combine($this->paramNames, $matches) ?: [];
    }

    public function run(array $params = []): void
    {
        call_user_func($this->callback, $params);
    }
}

class Router
{
    private array $routes = [];
    private string $static_dir;

    private const MIME_TYPES = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];

    public function __construct(string $static_dir = __DIR__)
    {
        $this->static_dir = rtrim($static_dir, '/');
    }

    private function serve_static(string $path): bool
    {
        $file     = realpath($this->static_dir . $path);
        $base     = realpath($this->static_dir);
        if (!$file || !$base || !str_starts_with($file, $base) || !is_file($file)) {
            return false;
        }
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME_TYPES[$ext] ?? 'application/octet-stream';
        header("Content-Type: $mime");
        readfile($file);
        return true;
    }

    private function add(string $path, string $method, callable $cb): self
    {
        $this->routes[] = new Route($path, $method, $cb);
        return $this;
    }

    public function get(string $p, callable $cb): self    { return $this->add($p, 'GET', $cb); }
    public function post(string $p, callable $cb): self   { return $this->add($p, 'POST', $cb); }
    public function put(string $p, callable $cb): self    { return $this->add($p, 'PUT', $cb); }
    public function delete(string $p, callable $cb): self { return $this->add($p, 'DELETE', $cb); }

    public function dispatch(string $raw_uri, string $method): void
    {
        $method = strtoupper($method);
        $path   = rtrim(parse_url($raw_uri, PHP_URL_PATH) ?? '/', '/') ?: '/';

        if ($method === 'GET' && $this->serve_static($path)) {
            return;
        }

        foreach ($this->routes as $route) {
            if ($route->method === $method && $route->matches($path)) {
                $route->run($route->extract_params($path));
                return;
            }
        }

        $method_mismatch = array_filter($this->routes, fn($r) => $r->matches($path));
        if ($method_mismatch) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_map(fn($r) => $r->method, $method_mismatch)));
            echo json_encode(['erro' => 'Método não permitido.']);
            return;
        }

        http_response_code(404);
        echo json_encode(['erro' => 'Rota não encontrada.']);
    }
}
