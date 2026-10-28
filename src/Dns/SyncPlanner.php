<?php
declare(strict_types=1);
namespace Arzoie\Dns;

final class SyncPlanner
{
    public static function action(bool $deleted, int $desiredRevision, ?int $appliedRevision): string
    {
        if ($appliedRevision === $desiredRevision) { return 'none'; }
        if ($deleted) { return 'delete'; }
        return $appliedRevision === null ? 'add' : 'update';
    }
}
