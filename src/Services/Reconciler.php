<?php
declare(strict_types=1);
namespace Arzoie\Services;

use Arzoie\Core\{Audit, Database};
use Arzoie\Dns\{Signer, SyncPlanner};
use Arzoie\Infrastructure\SshTransport;

final class Reconciler
{
    public function reconcile(?int $serverId = null, ?int $zoneId = null): void
    {
        $zones = Database::run('SELECT z.id AS zone_id, z.name, z.deleted_at, z.desired_revision, r.* FROM zones z JOIN zone_revisions r ON r.id = z.desired_revision' . ($zoneId ? ' WHERE z.id = ?' : '') . ' ORDER BY z.id', $zoneId ? [$zoneId] : [])->fetchAll();
        $servers = Database::run("SELECT * FROM servers WHERE state = 'active'" . ($serverId ? ' AND id = ?' : ''), $serverId ? [$serverId] : [])->fetchAll();
        $errors = [];
        foreach ($zones as $zone) {
            if ($zone['operation'] !== 'delete') {
                try { $zone = (new Signer())->sign($zone, $zone['name']); }
                catch (\Throwable $e) { $errors[] = $zone['name'] . ': signing failed: ' . $e->getMessage(); continue; }
            }
            foreach ($servers as $server) {
                $deployment = Database::run('SELECT * FROM deployments WHERE server_id = ? AND zone_id = ?', [$server['id'], $zone['zone_id']])->fetch();
                $action = SyncPlanner::action($zone['operation'] === 'delete', (int) $zone['id'], $deployment && $deployment['applied_revision'] !== null ? (int) $deployment['applied_revision'] : null);
                if ($action === 'none') { continue; }
                if (!$deployment) {
                    Database::run('INSERT INTO deployments (server_id, zone_id) VALUES (?, ?)', [$server['id'], $zone['zone_id']]);
                }
                try {
                    $ssh = new SshTransport($server);
                    if ($action === 'delete') {
                        $output = $ssh->run(['sudo', '-n', '/usr/local/sbin/arzoie-member', 'delete', $zone['name'], (string) $zone['id']]);
                    } else {
                        $remote = '/var/lib/arzoiedns/incoming/' . $zone['name'] . '.' . $zone['id'] . '.zone';
                        $ssh->upload($zone['artifact_path'], $remote);
                        $output = $ssh->run(['sudo', '-n', '/usr/local/sbin/arzoie-member', 'apply', $zone['name'], (string) $zone['id'], (string) $zone['serial'], $zone['checksum']]);
                    }
                    $receipt = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
                    if (($receipt['revision'] ?? null) !== (int) $zone['id'] || ($receipt['zone'] ?? null) !== $zone['name'] || ($receipt['ok'] ?? false) !== true) { throw new \RuntimeException('Invalid member acknowledgment'); }
                    Database::run('UPDATE deployments SET applied_revision = ?, verified_at = ?, last_error = NULL WHERE server_id = ? AND zone_id = ?', [$zone['id'], gmdate('Y-m-d H:i:s'), $server['id'], $zone['zone_id']]);
                    Audit::log('sync.' . $action, $zone['name'], ['server_id' => $server['id'], 'revision' => $zone['id']]);
                } catch (\Throwable $e) {
                    Database::run('UPDATE deployments SET last_error = ? WHERE server_id = ? AND zone_id = ?', [substr($e->getMessage(), 0, 2000), $server['id'], $zone['zone_id']]);
                    $errors[] = $server['name'] . '/' . $zone['name'] . ': ' . $e->getMessage();
                }
            }
        }
        if ($errors !== []) { throw new \RuntimeException(implode('; ', $errors)); }
    }
}
