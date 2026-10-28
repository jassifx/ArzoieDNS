<?php
declare(strict_types=1);
namespace Arzoie\Models;

use Arzoie\Core\Database;

final class JobRepository
{
    public static function enqueue(string $kind, ?int $serverId = null, ?int $zoneId = null): void
    {
        Database::run('INSERT INTO jobs (kind, server_id, zone_id, available_at, created_at) VALUES (?, ?, ?, ?, ?)', [$kind, $serverId, $zoneId, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);
    }

    public static function claim(): ?array
    {
        return Database::transaction(function (): ?array {
            $job = Database::run("SELECT * FROM jobs WHERE state = 'queued' AND available_at <= ? ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED", [gmdate('Y-m-d H:i:s')])->fetch();
            if (!$job) { return null; }
            Database::run("UPDATE jobs SET state = 'running', locked_at = ?, attempts = attempts + 1 WHERE id = ?", [gmdate('Y-m-d H:i:s'), $job['id']]);
            return $job;
        });
    }

    public static function finish(int $id, ?string $error = null, int $attempts = 0): void
    {
        Database::run('UPDATE jobs SET state = ?, last_error = ?, available_at = ?, locked_at = NULL WHERE id = ?', [
            $error === null ? 'done' : ($attempts >= 5 ? 'failed' : 'queued'), $error,
            gmdate('Y-m-d H:i:s', time() + min(3600, 30 * (2 ** $attempts))), $id,
        ]);
    }
}
