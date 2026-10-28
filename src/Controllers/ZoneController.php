<?php
declare(strict_types=1);
namespace Arzoie\Controllers;

use Arzoie\Core\{Database, Router, View};
use Arzoie\Models\ZoneRepository;

final class ZoneController
{
    public function index(): void
    {
        $repo = new ZoneRepository();
        $selected = isset($_GET['id']) ? $repo->get((int) $_GET['id']) : null;
        $revision = $selected && $selected['desired_revision'] ? Database::run('SELECT * FROM zone_revisions WHERE id = ?', [$selected['desired_revision']])->fetch() : null;
        View::render('zones', ['title' => 'Domains & records', 'zones' => $repo->all(), 'selected' => $selected, 'records' => $selected ? $repo->records((int) $selected['id']) : [], 'revision' => $revision]);
    }

    public function create(): void { (new ZoneRepository())->create($_POST); Router::redirect('/zones', 'Domain created as a draft. Add records, then publish.'); }
    public function addRecord(): void { $id = (int) ($_POST['zone_id'] ?? 0); (new ZoneRepository())->addRecord($id, $_POST); Router::redirect('/zones?id=' . $id, 'Draft record saved. Publish to deploy.'); }
    public function updateRecord(): void { $id = (int) ($_POST['zone_id'] ?? 0); (new ZoneRepository())->updateRecord($id, (int) ($_POST['record_id'] ?? 0), $_POST); Router::redirect('/zones?id=' . $id, 'Draft record updated. Publish to deploy.'); }
    public function deleteRecord(): void { $id = (int) ($_POST['zone_id'] ?? 0); (new ZoneRepository())->deleteRecord($id, (int) ($_POST['record_id'] ?? 0)); Router::redirect('/zones?id=' . $id, 'Draft record removed. Publish to deploy.'); }
    public function publish(): void { $id = (int) ($_POST['zone_id'] ?? 0); (new ZoneRepository())->publish($id); Router::redirect('/zones?id=' . $id, 'Revision queued for signing and deployment.'); }
    public function delete(): void
    {
        $repo = new ZoneRepository(); $id = (int) ($_POST['zone_id'] ?? 0); $zone = $repo->get($id);
        if (!hash_equals($zone['name'], (string) ($_POST['confirm_name'] ?? ''))) { throw new \InvalidArgumentException('Type the domain name to confirm removal'); }
        $repo->publish($id, true);
        Router::redirect('/zones', 'Deletion revision queued. Offline members retain a pending deletion until reachable.');
    }
}
