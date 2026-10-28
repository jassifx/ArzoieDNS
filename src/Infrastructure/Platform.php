<?php
declare(strict_types=1);
namespace Arzoie\Infrastructure;

final class Platform
{
    public static function profile(string $id, string $version): array
    {
        $major = (int) explode('.', $version)[0];
        if ($id === 'debian' && $major >= 13) { return ['family' => 'debian', 'group' => 'sudo', 'service' => 'named', 'bind_group' => 'bind']; }
        if (in_array($id, ['rocky', 'almalinux', 'ol', 'rhel'], true) && $major === 10) {
            return ['family' => 'el', 'group' => 'wheel', 'service' => 'named', 'bind_group' => 'named'];
        }
        throw new \InvalidArgumentException('Only Rocky/AlmaLinux/Oracle Linux/RHEL 10 and Debian 13+ are accepted.');
    }
}
