<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use Arzoie\Core\{Database, Env};
use Arzoie\Models\{JobRepository, Schema, ZoneRepository};

if (Env::get('ARZOIE_TEST_DB') !== '1' || !str_ends_with(Env::get('DB_DATABASE'), '_test')) {
    fwrite(STDERR, "Refusing: set ARZOIE_TEST_DB=1 and use a disposable DB_DATABASE ending in _test.\n"); exit(1);
}
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$name = 'test-' . bin2hex(random_bytes(6)) . '.example.com';
$id = null;
try {
    Schema::migrate();
    $repo = new ZoneRepository();
    $repo->create(['name'=>$name,'primary_ns'=>'ns1.example.net','secondary_ns'=>'ns2.example.net','soa_email'=>'hostmaster.example.com']);
    $id = (int) Database::run('SELECT id FROM zones WHERE name=?',[$name])->fetchColumn();
    $assert(count($repo->records($id)) === 2, 'Initial NS records missing');
    $repo->addRecord($id,['owner'=>'www','type'=>'A','value'=>'192.0.2.10','ttl'=>300]);
    $rid = (int) Database::run("SELECT id FROM records WHERE zone_id=? AND owner='www'",[$id])->fetchColumn();
    $repo->updateRecord($id,$rid,['owner'=>'www','type'=>'A','value'=>'192.0.2.20','ttl'=>600]);
    $repo->publish($id);
    $zone = $repo->get($id);
    $revision = Database::run('SELECT * FROM zone_revisions WHERE id=?',[$zone['desired_revision']])->fetch();
    $assert(str_contains($revision['source'],'www 600 IN A 192.0.2.20'), 'Published content differs from draft');
    $assert((int) $zone['serial'] === 2, 'Serial not incremented');
    $repo->updateRecord($id,$rid,['owner'=>'www','type'=>'A','value'=>'192.0.2.30','ttl'=>600]);
    $assert(!str_contains($revision['source'],'192.0.2.30'), 'Revision should be immutable');
    Database::run('UPDATE zone_revisions SET signed_until=? WHERE id=?', [gmdate('Y-m-d H:i:s',time()+86400),$zone['desired_revision']]);
    $repo->renew($id,gmdate('Y-m-d H:i:s',time()+10*86400));
    $renewed = $repo->get($id);
    $renewedSource = Database::run('SELECT source FROM zone_revisions WHERE id=?',[$renewed['desired_revision']])->fetchColumn();
    $assert(str_contains($renewedSource,'192.0.2.20') && !str_contains($renewedSource,'192.0.2.30'),'Automatic signing renewal must not publish drafts');
    $assert((int)$renewed['serial']===3,'Renewal serial incorrect');
    $repo->publish($id);
    $assert((int) $repo->get($id)['serial'] === 4, 'Second publication serial incorrect');
    try {
        Database::transaction(static function () use ($id): void { Database::run("UPDATE zones SET primary_ns='bad.example.net' WHERE id=?",[$id]); throw new RuntimeException('rollback'); });
    } catch (RuntimeException) {}
    $assert($repo->get($id)['primary_ns']==='ns1.example.net','Transaction failed to roll back');
    $repo->deleteRecord($id,$rid);
    $assert(count($repo->records($id))===2,'Record removal failed');
    $repo->publish($id,true);
    $zone = $repo->get($id);
    $assert($zone['deleted_at']!==null,'Deletion tombstone missing');
    $assert(Database::run('SELECT operation FROM zone_revisions WHERE id=?',[$zone['desired_revision']])->fetchColumn()==='delete','Deletion revision missing');
    $assert((int)Database::run('SELECT COUNT(*) FROM jobs WHERE zone_id=?',[$id])->fetchColumn()===4,'Publication jobs missing');
    echo 'PASS ' . Env::get('DB_DRIVER') . " transactions, drafts, updates, immutable revisions, serials and tombstones\n";
} finally {
    if ($id) {
        Database::run('DELETE FROM jobs WHERE zone_id=?',[$id]);
        Database::run('DELETE FROM deployments WHERE zone_id=?',[$id]);
        Database::run('DELETE FROM zone_revisions WHERE zone_id=?',[$id]);
        Database::run('DELETE FROM records WHERE zone_id=?',[$id]);
        Database::run('DELETE FROM zones WHERE id=?',[$id]);
        Database::run('DELETE FROM audit_events WHERE subject=?',[$name]);
    }
}
