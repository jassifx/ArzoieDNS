<?php
declare(strict_types=1);
namespace Arzoie\Controllers;

use Arzoie\Core\{Audit, Crypto, Database, Router, View};
use Arzoie\Models\JobRepository;

final class ServerController
{
    public function index(): void
    {
        View::render('servers', ['title' => 'Member servers', 'servers' => Database::run('SELECT id,name,address,ssh_port,host_fingerprint,location,state,os_name,health,health_at,last_error FROM servers ORDER BY name')->fetchAll()]);
    }

    private function credentials(): array
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $sudo = (string) ($_POST['sudo_password'] ?? '');
        if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/D', $username) || $password === '' || strlen($password) > 1024 || preg_match('/[\r\n\x00]/', $password . $sudo)) { throw new \InvalidArgumentException('Invalid bootstrap credentials'); }
        return ['username' => $username, 'password' => $password, 'sudo_password' => $sudo];
    }

    public function create(): void
    {
        $name = trim((string) ($_POST['name'] ?? '')); $location = trim((string) ($_POST['location'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? '')); $fingerprint = trim((string) ($_POST['host_fingerprint'] ?? ''));
        $port = filter_var($_POST['ssh_port'] ?? 22, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($name === '' || strlen($name) > 100 || $location === '' || strlen($location) > 100 || !filter_var($address, FILTER_VALIDATE_IP) || $port === false || !preg_match('/^SHA256:[A-Za-z0-9+\/]{43}$/D', $fingerprint)) { throw new \InvalidArgumentException('Provide a name, location, literal IP address, valid SSH port and SHA256 host fingerprint'); }
        $secret = Crypto::seal($this->credentials());
        Database::transaction(function () use ($name, $location, $address, $fingerprint, $port, $secret): void {
            Database::run('INSERT INTO servers (name,address,ssh_port,host_fingerprint,location,bootstrap_secret,secret_expires_at,created_at) VALUES (?,?,?,?,?,?,?,?)', [$name, $address, $port, $fingerprint, $location, $secret, gmdate('Y-m-d H:i:s', time() + 900), gmdate('Y-m-d H:i:s')]);
            $id = (int) Database::run('SELECT id FROM servers WHERE name = ?', [$name])->fetchColumn();
            JobRepository::enqueue('onboard', $id);
            Audit::log('server.enrolled', $name, ['address' => $address]);
        });
        Router::redirect('/servers', 'Onboarding queued. Bootstrap credentials expire in 15 minutes and are erased after the attempt.');
    }

    public function retryBootstrap(): void
    {
        $secret = Crypto::seal($this->credentials());
        Database::transaction(function () use ($secret): void {
            $id = (int) ($_POST['server_id'] ?? 0);
            $server = Database::run('SELECT state FROM servers WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$server || $server['state'] !== 'pending') { throw new \InvalidArgumentException('Only pending members accept bootstrap credentials'); }
            Database::run('UPDATE servers SET bootstrap_secret = ?, secret_expires_at = ?, last_error = NULL WHERE id = ?', [$secret, gmdate('Y-m-d H:i:s', time() + 900), $id]);
            JobRepository::enqueue('onboard', $id);
            Audit::log('server.bootstrap-retry', (string) $id);
        });
        Router::redirect('/servers', 'Fresh bootstrap credentials queued.');
    }

    public function action(): void
    {
        $id = (int) ($_POST['server_id'] ?? 0); $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['health', 'reconcile', 'drain', 'deboard'], true)) { throw new \InvalidArgumentException('Unknown member action'); }
        Database::transaction(function () use ($id, $action): void {
            $server = Database::run('SELECT * FROM servers WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$server || $server['state'] === 'removed') { throw new \InvalidArgumentException('Member is unavailable'); }
            if ($action === 'drain') {
                $count = (int) Database::run("SELECT COUNT(*) FROM servers WHERE state='active' AND id <> ? AND last_error IS NULL AND health_at > ?", [$id, gmdate('Y-m-d H:i:s', time() - 300)])->fetchColumn();
                $pending = (int) Database::run("SELECT COUNT(*) FROM servers s CROSS JOIN zones z LEFT JOIN deployments d ON d.server_id=s.id AND d.zone_id=z.id WHERE s.state='active' AND s.id <> ? AND z.desired_revision IS NOT NULL AND (d.applied_revision IS NULL OR d.applied_revision <> z.desired_revision)", [$id])->fetchColumn();
                if ($count < 2 || $pending > 0) { throw new \InvalidArgumentException('Draining requires at least two other recently healthy members and no pending revisions on remaining members'); }
                Database::run("UPDATE servers SET state='draining' WHERE id=?", [$id]);
            } elseif ($action === 'deboard') {
                if ($server['state'] !== 'draining' || ($_POST['delegation_removed'] ?? '') !== 'yes' || ($_POST['ttl_elapsed'] ?? '') !== 'yes') { throw new \InvalidArgumentException('Drain first; confirm registrar/zone NS removal and that delegation TTLs have elapsed'); }
                JobRepository::enqueue('deboard', $id);
            } else {
                if ($server['state'] !== 'active') { throw new \InvalidArgumentException('Member must be active'); }
                JobRepository::enqueue($action, $id);
            }
            Audit::log('server.' . $action, $server['name']);
        });
        Router::redirect('/servers', 'Member action recorded.');
    }
}
