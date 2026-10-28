<?php
declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.5.12', '<')) {
    throw new RuntimeException('ArzoieDNS requires PHP 8.5.12 or newer.');
}
define('BASE_PATH', __DIR__);
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Arzoie\\')) {
            $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
            if (is_file($file)) { require $file; }
        }
    });
}
Arzoie\Core\Env::load(__DIR__ . '/.env');
date_default_timezone_set('UTC');
umask(0077);
