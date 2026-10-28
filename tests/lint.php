<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$failed = false;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (preg_match('~[/\\\\](vendor|\.tools|\.git|storage)[/\\\\]~', $path)) { continue; }
    if ($file->getExtension() !== 'php' && $path !== $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'arzoie') { continue; }
    $command = [PHP_BINARY, '-l', $path];
    $process = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { $failed = true; echo $out . $err; }
}
echo $failed ? "Lint failed\n" : "All PHP source files pass syntax checks.\n";
exit($failed ? 1 : 0);
