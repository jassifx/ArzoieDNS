<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        require BASE_PATH . '/views/' . $template . '.php';
        $content = ob_get_clean();
        require BASE_PATH . '/views/layout.php';
    }
}
