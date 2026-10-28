<?php
declare(strict_types=1);
namespace Arzoie\Services;

use Arzoie\Core\{Audit, Database, Env};
use Arzoie\Infrastructure\SshTransport;
use Arzoie\Models\{JobRepository, ZoneRepository};

final class Worker
{
    public function run(bool $once = false): void
    {
        $dir = Env::get('STATE_PATH');
        if ($dir === '' || !is_dir($dir)) { throw new \RuntimeException('Create STATE_PATH for the worker first'); }
        $lock = fopen($dir . '/worker.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new \RuntimeException('Another worker holds the lock'); }
        // A single worker is deliberate: serialize signing, publication and offboarding.
        Database::run("UPDATE jobs SET state = 'queued', locked_at = NULL WHERE state = 'running'");
        do {
            Database::run('UPDATE servers SET bootstrap_secret = NULL, secret_expires_at = NULL WHERE secret_expires_at < ?', [gmdate('Y-m-d H:i:s')]);
            $job = JobRepository::claim();
            if (!$job) { if (!$once) { sleep(3); } continue; }
            try {
                $this->execute($job);
                JobRepository::finish((int) $job['id']);
            } catch (\Throwable $e) {
                $error = substr($e->getMessage(), 0, 4000);
                JobRepository::finish((int) $job['id'], $error, (int) $job['attempts'] + 1);
                if ($job['server_id']) { Database::run('UPDATE servers SET last_error = ? WHERE id = ?', [$error, $job['server_id']]); }
                fwrite(STDERR, 'Job ' . $job['id'] . ' failed: ' . $error . "\n");
            }
        } while (!$once);
        flock($lock, LOCK_UN); fclose($lock);
    }

    private function execute(array $job): void
    {
        $server = $job['server_id'] ? Database::run('SELECT * FROM servers WHERE id = ?', [$job['server_id']])->fetch() : null;
        switch ($job['kind']) {
            case 'onboard':
                if (!$server) { throw new \RuntimeException('Unknown server'); }
                (new Provisioner())->onboard($server);
                break;
            case 'reconcile':
                (new Reconciler())->reconcile($server ? (int) $server['id'] : null, $job['zone_id'] ? (int) $job['zone_id'] : null);
                break;
            case 'health':
                if (!$server || $server['state'] !== 'active') { return; }
                $health = (new SshTransport($server))->run(['sudo', '-n', '/usr/local/sbin/arzoie-member', 'health']);
                json_decode($health, true, flags: JSON_THROW_ON_ERROR);
                Database::run('UPDATE servers SET health = ?, health_at = ?, last_error = NULL WHERE id = ?', [$health, gmdate('Y-m-d H:i:s'), $server['id']]);
                break;
            case 'deboard':
                if (!$server || $server['state'] === 'removed') { return; }
                if ($server['state'] !== 'draining') { throw new \RuntimeException('Server must be drained before deboarding'); }
                (new SshTransport($server))->run(['sudo', '-n', '/usr/local/sbin/arzoie-member', 'deboard']);
                Database::run("UPDATE servers SET state = 'removed', bootstrap_secret = NULL, last_error = NULL WHERE id = ?", [$server['id']]);
                Audit::log('server.removed', $server['name']);
                break;
            default: throw new \RuntimeException('Unknown job type');
        }
    }

    public static function schedule(): void
    {
        // Run each minute from systemd timer; queued/running jobs are coalesced here.
        if (!(int) Database::run("SELECT COUNT(*) FROM jobs WHERE kind = 'reconcile' AND server_id IS NULL AND zone_id IS NULL AND state IN ('queued', 'running')")->fetchColumn()) {
            JobRepository::enqueue('reconcile');
        }
        foreach (Database::run("SELECT id FROM servers WHERE state = 'active'")->fetchAll() as $server) {
            if (!(int) Database::run("SELECT COUNT(*) FROM jobs WHERE kind = 'health' AND server_id = ? AND state IN ('queued', 'running')", [$server['id']])->fetchColumn()) { JobRepository::enqueue('health', (int) $server['id']); }
        }
        $zones = Database::run("SELECT z.id FROM zones z JOIN zone_revisions r ON r.id = z.desired_revision WHERE z.deleted_at IS NULL AND r.signed_until IS NOT NULL AND r.signed_until < ?", [gmdate('Y-m-d H:i:s', time() + 10 * 86400)])->fetchAll();
        foreach ($zones as $zone) { (new ZoneRepository())->renew((int) $zone['id'], gmdate('Y-m-d H:i:s', time() + 10 * 86400)); }
    }
}
