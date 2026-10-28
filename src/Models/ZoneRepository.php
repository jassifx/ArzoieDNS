<?php
declare(strict_types=1);
namespace Arzoie\Models;

use Arzoie\Core\{Audit, Database};
use Arzoie\Dns\{Names, Record, ZoneRenderer};

final class ZoneRepository
{
    public function all(): array
    {
        return Database::run('SELECT z.*, r.checksum, r.signed_until, r.ds_records FROM zones z LEFT JOIN zone_revisions r ON r.id = z.desired_revision ORDER BY z.name')->fetchAll();
    }

    public function get(int $id, bool $lock = false): array
    {
        $row = Database::run('SELECT * FROM zones WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
        if (!$row) { throw new \InvalidArgumentException('Zone not found'); }
        return $row;
    }

    public function records(int $id): array
    {
        return Database::run('SELECT * FROM records WHERE zone_id = ? ORDER BY owner, type, id', [$id])->fetchAll();
    }

    public function create(array $input): void
    {
        $name = Names::domain($input['name'] ?? '');
        $primary = Names::domain($input['primary_ns'] ?? '');
        $secondary = Names::domain($input['secondary_ns'] ?? '');
        $email = Names::domain($input['soa_email'] ?? '');
        if ($primary === $secondary) { throw new \InvalidArgumentException('Use two distinct nameservers'); }
        Database::transaction(function () use ($name, $primary, $secondary, $email): void {
            Database::run('INSERT INTO zones (name, primary_ns, soa_email, serial, created_at) VALUES (?, ?, ?, 1, ?)', [$name, $primary, $email, gmdate('Y-m-d H:i:s')]);
            $id = (int) Database::run('SELECT id FROM zones WHERE name = ?', [$name])->fetchColumn();
            foreach ([$primary, $secondary] as $ns) {
                Database::run('INSERT INTO records (zone_id, owner, type, ttl, value) VALUES (?, ?, ?, ?, ?)', [$id, '@', 'NS', 300, $ns]);
            }
            Audit::log('zone.created', $name);
        });
    }

    public function addRecord(int $id, array $input): void
    {
        Database::transaction(function () use ($id, $input): void {
            $zone = $this->get($id, true);
            if ($zone['deleted_at'] !== null) { throw new \InvalidArgumentException('Cannot edit a deleted zone'); }
            $record = Record::normalize($zone['name'], $input);
            $records = $this->records($id);
            $records[] = $record;
            Record::validateSet($records);
            Database::run('INSERT INTO records (zone_id, owner, type, ttl, value) VALUES (?, ?, ?, ?, ?)', [$id, $record['owner'], $record['type'], $record['ttl'], $record['value']]);
            Audit::log('record.added', $zone['name'], ['owner' => $record['owner'], 'type' => $record['type']]);
        });
    }

    public function deleteRecord(int $id, int $recordId): void
    {
        Database::transaction(function () use ($id, $recordId): void {
            $zone = $this->get($id, true);
            Database::run('DELETE FROM records WHERE id = ? AND zone_id = ?', [$recordId, $id]);
            Audit::log('record.deleted', $zone['name'], ['record_id' => $recordId]);
        });
    }

    public function updateRecord(int $id, int $recordId, array $input): void
    {
        Database::transaction(function () use ($id, $recordId, $input): void {
            $zone = $this->get($id, true);
            if ($zone['deleted_at'] !== null) { throw new \InvalidArgumentException('Cannot edit a deleted zone'); }
            $record = Record::normalize($zone['name'], $input);
            $records = $this->records($id); $found = false;
            foreach ($records as &$existing) {
                if ((int) $existing['id'] === $recordId) { $existing = $record; $found = true; }
            }
            unset($existing);
            if (!$found) { throw new \InvalidArgumentException('Record not found in this zone'); }
            Record::validateSet($records);
            Database::run('UPDATE records SET owner=?, type=?, ttl=?, value=? WHERE id=? AND zone_id=?', [$record['owner'], $record['type'], $record['ttl'], $record['value'], $recordId, $id]);
            Audit::log('record.updated', $zone['name'], ['record_id' => $recordId]);
        });
    }

    public function publish(int $id, bool $delete = false): void
    {
        Database::transaction(function () use ($id, $delete): void {
            $zone = $this->get($id, true);
            if ($zone['deleted_at'] !== null) { throw new \InvalidArgumentException('Zone is already deleted'); }
            $zone['serial'] = (int) $zone['serial'] + 1;
            if ($zone['serial'] > 4294967295) { throw new \RuntimeException('SOA serial exhausted; manual RFC 1982 rollover required'); }
            $source = $delete ? '' : (new ZoneRenderer())->render($zone, $this->records($id));
            Database::run('INSERT INTO zone_revisions (zone_id, serial, operation, source, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $zone['serial'], $delete ? 'delete' : 'publish', $source, gmdate('Y-m-d H:i:s')]);
            $revision = (int) Database::run('SELECT id FROM zone_revisions WHERE zone_id = ? AND serial = ?', [$id, $zone['serial']])->fetchColumn();
            Database::run('UPDATE zones SET serial = ?, desired_revision = ?, deleted_at = ? WHERE id = ?', [$zone['serial'], $revision, $delete ? gmdate('Y-m-d H:i:s') : null, $id]);
            JobRepository::enqueue('reconcile', zoneId: $id);
            Audit::log($delete ? 'zone.deleted' : 'zone.published', $zone['name'], ['revision' => $revision]);
        });
    }

    public function renew(int $id, string $cutoff): void
    {
        Database::transaction(function () use ($id, $cutoff): void {
            $zone = $this->get($id, true);
            if ($zone['deleted_at'] !== null || !$zone['desired_revision']) { return; }
            $current = Database::run('SELECT * FROM zone_revisions WHERE id=?', [$zone['desired_revision']])->fetch();
            if (!$current || $current['signed_until'] === null || $current['signed_until'] >= $cutoff) { return; }
            $serial = (int) $zone['serial'] + 1;
            if ($serial > 4294967295) { throw new \RuntimeException('SOA serial exhausted'); }
            // Renew the published snapshot, NEVER pending administrator drafts.
            $before = '(' . $current['serial'] . ' 3600 600 1209600 300)';
            $after = '(' . $serial . ' 3600 600 1209600 300)';
            $source = str_replace($before, $after, $current['source'], $count);
            if ($count !== 1) { throw new \RuntimeException('Published SOA cannot be safely renewed'); }
            Database::run('INSERT INTO zone_revisions (zone_id, serial, operation, source, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $serial, 'publish', $source, gmdate('Y-m-d H:i:s')]);
            $revision = (int) Database::run('SELECT id FROM zone_revisions WHERE zone_id=? AND serial=?', [$id, $serial])->fetchColumn();
            Database::run('UPDATE zones SET serial=?, desired_revision=? WHERE id=?', [$serial, $revision, $id]);
            JobRepository::enqueue('reconcile', zoneId: $id);
            Audit::log('zone.renewed', $zone['name'], ['revision' => $revision]);
        });
    }
}
