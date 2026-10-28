<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class Process
{
    public static function run(array $args, ?string $cwd = null, int $timeout = 120): string
    {
        $proc = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($proc)) { throw new \RuntimeException('Cannot start process'); }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = ''; $start = microtime(true);
        do {
            $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            if (strlen($output) > 4 * 1024 * 1024 || microtime(true) - $start > $timeout) {
                proc_terminate($proc, 9);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
                throw new \RuntimeException('Command exceeded its output or time limit');
            }
            $status = proc_get_status($proc);
            if ($status['running']) { usleep(20000); }
        } while ($status['running']);
        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
        if ($status['exitcode'] !== 0) { throw new \RuntimeException('Command failed: ' . substr($output, -2000)); }
        return trim($output);
    }
}
