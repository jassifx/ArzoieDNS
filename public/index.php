<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use Arzoie\Core\{Env, Router, View};
use Arzoie\Controllers\{AuthController, DashboardController, ServerController, ZoneController};

ini_set('display_errors', '0');
$production = Env::get('APP_ENV', 'production') === 'production';
if ($production && !str_starts_with(Env::get('APP_URL'), 'https://')) { http_response_code(503); exit('Configure an HTTPS APP_URL.'); }
session_name('arzoie_session');
session_start(['cookie_httponly' => true, 'cookie_secure' => $production, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true]);
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1200) { $_SESSION = []; session_regenerate_id(true); }
$_SESSION['last_seen'] = time();
$_SESSION['_token'] ??= bin2hex(random_bytes(32));
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
if ($production) { header('Strict-Transport-Security: max-age=31536000'); }
$router = new Router();
$auth = new AuthController(); $dashboard = new DashboardController(); $zones = new ZoneController(); $servers = new ServerController();
$router->add('GET', '/login', $auth->form(...), false);
$router->add('POST', '/login', $auth->login(...), false);
$router->add('POST', '/logout', $auth->logout(...));
$router->add('GET', '/', $dashboard->index(...));
$router->add('GET', '/reports', $dashboard->reports(...));
$router->add('GET', '/zones', $zones->index(...));
$router->add('POST', '/zones/create', $zones->create(...));
$router->add('POST', '/zones/publish', $zones->publish(...));
$router->add('POST', '/zones/delete', $zones->delete(...));
$router->add('POST', '/records/add', $zones->addRecord(...));
$router->add('POST', '/records/update', $zones->updateRecord(...));
$router->add('POST', '/records/delete', $zones->deleteRecord(...));
$router->add('GET', '/servers', $servers->index(...));
$router->add('POST', '/servers/create', $servers->create(...));
$router->add('POST', '/servers/action', $servers->action(...));
$router->add('POST', '/servers/bootstrap', $servers->retryBootstrap(...));
try { $router->dispatch(); }
catch (InvalidArgumentException $e) { http_response_code(422); View::render('error', ['title' => 'Check your input', 'message' => $e->getMessage()]); }
catch (Throwable $e) {
    $ref = bin2hex(random_bytes(6));
    // Do not log SQL parameters, credentials, stack arguments or request bodies.
    error_log('ArzoieDNS error ' . $ref . ' class=' . $e::class . ' file=' . basename($e->getFile()) . ' line=' . $e->getLine());
    http_response_code(500); View::render('error', ['title' => 'Operation failed', 'message' => 'The operation could not complete. Reference: ' . $ref]);
}
