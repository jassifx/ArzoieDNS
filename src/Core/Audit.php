<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class Audit
{
    public static function log(string $action, string $subject, array $details = []): void
    {
        Database::run('INSERT INTO audit_events (actor, action, subject, details, created_at) VALUES (?, ?, ?, ?, ?)', [
            PHP_SAPI === 'cli' ? 'worker' : (string) ($_SESSION['user_email'] ?? 'anonymous'),
            $action, $subject, json_encode($details, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s'),
        ]);
    }
}
