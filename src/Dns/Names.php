<?php
declare(strict_types=1);
namespace Arzoie\Dns;

final class Names
{
    public static function domain(string $value): string
    {
        $value = strtolower(rtrim(trim($value), '.'));
        if (strlen($value) > 253 || !str_contains($value, '.')) { throw new \InvalidArgumentException('Enter a fully qualified ASCII domain (use punycode for IDNs).'); }
        foreach (explode('.', $value) as $label) {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                throw new \InvalidArgumentException('Invalid domain name');
            }
        }
        return $value;
    }

    public static function owner(string $value, string $domain): string
    {
        $value = strtolower(trim($value));
        if ($value === '@') { return $value; }
        if (str_ends_with($value, '.')) {
            $value = rtrim($value, '.');
            if ($value === $domain) { return '@'; }
            if (!str_ends_with($value, '.' . $domain)) { throw new \InvalidArgumentException('Record owner must be inside its zone'); }
            $value = substr($value, 0, -strlen($domain) - 1);
        }
        if (strlen($value . '.' . $domain) > 253) { throw new \InvalidArgumentException('Owner name too long'); }
        foreach (explode('.', $value) as $index => $label) {
            if ($label === '*' && $index === 0) { continue; }
            if (!preg_match('/^[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/D', $label)) { throw new \InvalidArgumentException('Invalid record owner'); }
        }
        return $value;
    }
}
