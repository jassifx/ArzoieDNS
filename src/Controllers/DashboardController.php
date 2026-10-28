<?php
declare(strict_types=1);
namespace Arzoie\Controllers;

use Arzoie\Core\{Database, View};

final class DashboardController
{
    public function index(): void
    {
        $servers = Database::run('SELECT * FROM servers ORDER BY name')->fetchAll();
        $zones = (int) Database::run('SELECT COUNT(*) FROM zones WHERE deleted_at IS NULL')->fetchColumn();
        $pending = (int) Database::run("SELECT COUNT(*) FROM servers s CROSS JOIN zones z LEFT JOIN deployments d ON d.server_id=s.id AND d.zone_id=z.id WHERE s.state='active' AND z.desired_revision IS NOT NULL AND (d.applied_revision IS NULL OR d.applied_revision <> z.desired_revision)")->fetchColumn();
        $failed = (int) Database::run("SELECT COUNT(*) FROM jobs WHERE state = 'failed'")->fetchColumn();
        $events = Database::run('SELECT * FROM audit_events ORDER BY id DESC LIMIT 12')->fetchAll();
        View::render('dashboard', compact('servers', 'zones', 'pending', 'failed', 'events') + ['title' => 'Network overview']);
    }

    public function reports(): void
    {
        $deployments = Database::run("SELECT s.name AS server_name, s.state, s.health_at, z.name AS zone_name, z.desired_revision, d.applied_revision, d.verified_at, d.last_error, r.signed_until FROM servers s CROSS JOIN zones z LEFT JOIN deployments d ON d.server_id=s.id AND d.zone_id=z.id LEFT JOIN zone_revisions r ON r.id=z.desired_revision WHERE s.state <> 'removed' AND z.desired_revision IS NOT NULL ORDER BY s.name,z.name")->fetchAll();
        $jobs = Database::run('SELECT * FROM jobs ORDER BY id DESC LIMIT 100')->fetchAll();
        $events = Database::run('SELECT * FROM audit_events ORDER BY id DESC LIMIT 100')->fetchAll();
        View::render('reports', compact('deployments', 'jobs', 'events') + ['title' => 'Reports & synchronization']);
    }
}
