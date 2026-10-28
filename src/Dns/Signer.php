<?php
declare(strict_types=1);
namespace Arzoie\Dns;

use Arzoie\Core\{Database, Env, Process};

final class Signer
{
    public function sign(array $revision, string $domain): array
    {
        Names::domain($domain);
        if ($revision['artifact_path'] !== null) {
            if (!is_file($revision['artifact_path']) || !hash_equals($revision['checksum'], hash_file('sha256', $revision['artifact_path']))) {
                throw new \RuntimeException('Stored signed artifact is missing or has changed');
            }
            if (strtotime($revision['signed_until'] . ' UTC') <= time()) { throw new \RuntimeException('Revision signatures expired; publish a new revision'); }
            return $revision;
        }
        $root = rtrim(Env::get('SIGNER_PATH'), '/');
        if ($root === '' || !str_starts_with($root, '/')) { throw new \RuntimeException('SIGNER_PATH must be absolute'); }
        $keys = "$root/keys/$domain";
        $work = "$root/artifacts/{$revision['id']}";
        foreach ([$keys, $work] as $dir) { if (!is_dir($dir) && !mkdir($dir, 0700, true)) { throw new \RuntimeException('Cannot create signing directory'); } }
        // Caller holds the single-worker lock: no simultaneous key creation or signing.
        if (count(glob($keys . '/*.private')) === 0) {
            Process::run(['dnssec-keygen', '-q', '-a', 'ECDSAP256SHA256', '-f', 'KSK', '-K', $keys, $domain]);
            Process::run(['dnssec-keygen', '-q', '-a', 'ECDSAP256SHA256', '-K', $keys, $domain]);
        }
        if (count(glob($keys . '/*.private')) < 2) { throw new \RuntimeException('Incomplete DNSSEC key set; repair the signer before publishing'); }
        $unsigned = "$work/zone.db"; $signed = "$work/zone.signed";
        file_put_contents($unsigned, $revision['source']);
        Process::run(['named-checkzone', $domain, $unsigned]);
        $expiry = gmdate('YmdHis', time() + 30 * 86400);
        Process::run(['dnssec-signzone', '-S', '-K', $keys, '-o', $domain, '-s', 'now-3600', '-e', $expiry, '-f', $signed, $unsigned], $work);
        Process::run(['dnssec-verify', '-o', $domain, $signed], $work);
        $ds = [];
        foreach (glob($keys . '/*.key') as $key) {
            if (preg_match('/\bDNSKEY\s+257\s/', file_get_contents($key))) { $ds[] = Process::run(['dnssec-dsfromkey', '-2', $key]); }
        }
        if ($ds === []) { throw new \RuntimeException('No KSK DS records generated'); }
        $checksum = hash_file('sha256', $signed);
        $until = \DateTimeImmutable::createFromFormat('!YmdHis', $expiry)->format('Y-m-d H:i:s');
        Database::run('UPDATE zone_revisions SET artifact_path = ?, checksum = ?, ds_records = ?, signed_until = ? WHERE id = ?', [$signed, $checksum, implode("\n", $ds), $until, $revision['id']]);
        return array_replace($revision, ['artifact_path' => $signed, 'checksum' => $checksum, 'signed_until' => $until]);
    }
}
