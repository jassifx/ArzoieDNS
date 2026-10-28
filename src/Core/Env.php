<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class Env
{
    public static function load(string $file): void
    {
        foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/D', $name)) { throw new \RuntimeException('Invalid environment key'); }
            if (getenv($name) === false) { putenv($name . '=' . trim($value, "\"'")); }
        }
    }

    public static function get(string $name, string $default = ''): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}
