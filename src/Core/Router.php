<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, bool $protected = true): void
    {
        $this->routes[$method . ' ' . $path] = [$handler, $protected];
    }

    public function dispatch(): void
    {
        $key = $_SERVER['REQUEST_METHOD'] . ' ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!isset($this->routes[$key])) { http_response_code(404); echo 'Not found'; return; }
        [$handler, $protected] = $this->routes[$key];
        if ($protected && empty($_SESSION['user_id'])) { header('Location: /login', true, 303); return; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['_token']) || !hash_equals($_SESSION['_token'], (string) $_POST['_token'])) {
                http_response_code(403); echo 'Invalid CSRF token'; return;
            }
        }
        $handler();
    }

    public static function redirect(string $path, string $message = ''): never
    {
        $_SESSION['flash'] = $message;
        header('Location: ' . $path, true, 303);
        exit;
    }
}
