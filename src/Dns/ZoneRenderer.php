<?php
declare(strict_types=1);
namespace Arzoie\Dns;

final class ZoneRenderer
{
    public function render(array $zone, array $records): string
    {
        $domain = Names::domain($zone['name']);
        $primary = Names::domain($zone['primary_ns']);
        $mailbox = Names::domain($zone['soa_email']);
        $serial = (int) $zone['serial'];
        if ($serial < 1 || $serial > 4294967295) { throw new \InvalidArgumentException('Invalid SOA serial'); }
        $normalized = array_map(static fn(array $r): array => Record::normalize($domain, $r), $records);
        Record::validateSet($normalized);
        $ns = array_values(array_filter($normalized, static fn(array $r): bool => $r['owner'] === '@' && $r['type'] === 'NS'));
        if (count(array_unique(array_column($ns, 'rdata'))) < 2) { throw new \InvalidArgumentException('At least two distinct apex NS records are required before publishing'); }
        if (!in_array($primary . '.', array_column($ns, 'rdata'), true)) { throw new \InvalidArgumentException('SOA primary must be listed in apex NS records'); }
        usort($normalized, static fn(array $a, array $b): int => [$a['owner'], $a['type'], $a['rdata']] <=> [$b['owner'], $b['type'], $b['rdata']]);
        $text = "; Managed by ArzoieDNS. Do not edit.\n\$ORIGIN $domain.\n\$TTL 300\n@ 300 IN SOA $primary. $mailbox. ($serial 3600 600 1209600 300)\n";
        foreach ($normalized as $r) { $text .= "{$r['owner']} {$r['ttl']} IN {$r['type']} {$r['rdata']}\n"; }
        return $text;
    }
}
