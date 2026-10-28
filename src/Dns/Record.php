<?php
declare(strict_types=1);
namespace Arzoie\Dns;

final class Record
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR'];

    public static function normalize(string $zone, array $input): array
    {
        $owner = Names::owner((string) ($input['owner'] ?? '@'), $zone);
        $type = strtoupper((string) ($input['type'] ?? ''));
        $ttl = filter_var($input['ttl'] ?? 300, FILTER_VALIDATE_INT, ['options' => ['min_range' => 60, 'max_range' => 2147483647]]);
        $value = trim((string) ($input['value'] ?? ''));
        if ($ttl === false || !in_array($type, self::TYPES, true) || $value === '' || strlen($value) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new \InvalidArgumentException('Invalid record, TTL, or value');
        }
        $rdata = match ($type) {
            'A' => self::ip($value, FILTER_FLAG_IPV4),
            'AAAA' => self::ip($value, FILTER_FLAG_IPV6),
            'CNAME', 'NS', 'PTR' => Names::domain($value) . '.',
            'TXT' => self::text($value),
            'MX' => self::target($value, 1),
            'SRV' => self::target($value, 3),
            'CAA' => self::caa($value),
        };
        if ($owner === '@' && $type === 'CNAME') { throw new \InvalidArgumentException('A zone apex cannot be a CNAME'); }
        return compact('owner', 'type', 'ttl', 'value', 'rdata');
    }

    public static function validateSet(array $records): void
    {
        $owners = []; $ttls = [];
        foreach ($records as $record) {
            $owners[$record['owner']][$record['type']] = ($owners[$record['owner']][$record['type']] ?? 0) + 1;
            $key = $record['owner'] . '/' . $record['type'];
            if (isset($ttls[$key]) && $ttls[$key] !== (int) $record['ttl']) { throw new \InvalidArgumentException('All records in an RRset must share a TTL'); }
            $ttls[$key] = (int) $record['ttl'];
        }
        foreach ($owners as $types) {
            if (isset($types['CNAME']) && (count($types) > 1 || $types['CNAME'] > 1)) {
                throw new \InvalidArgumentException('CNAME must be the only record at its owner');
            }
        }
    }

    private static function ip(string $value, int $flag): string
    {
        if (!filter_var($value, FILTER_VALIDATE_IP, $flag)) { throw new \InvalidArgumentException('Invalid IP address'); }
        return $value;
    }

    private static function text(string $value): string
    {
        return implode(' ', array_map(static fn(string $part): string => '"' . addcslashes($part, "\\\"") . '"', str_split($value, 255)));
    }

    private static function target(string $value, int $numbers): string
    {
        $parts = preg_split('/\s+/', $value);
        if (count($parts) !== $numbers + 1) { throw new \InvalidArgumentException('Use priority + host for MX; priority weight port + host for SRV'); }
        for ($i = 0; $i < $numbers; $i++) {
            if (!ctype_digit($parts[$i]) || (int) $parts[$i] > 65535) { throw new \InvalidArgumentException('Invalid MX/SRV number'); }
            $parts[$i] = (string) (int) $parts[$i];
        }
        $parts[$numbers] = $parts[$numbers] === '.' ? '.' : Names::domain($parts[$numbers]) . '.';
        return implode(' ', $parts);
    }

    private static function caa(string $value): string
    {
        if (!preg_match('/^(0|128)\s+(issue|issuewild|iodef)\s+(.+)$/D', $value, $m)) {
            throw new \InvalidArgumentException('CAA format: 0 issue letsencrypt.org');
        }
        return $m[1] . ' ' . $m[2] . ' ' . self::text($m[3]);
    }
}
