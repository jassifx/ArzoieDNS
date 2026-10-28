<?php
declare(strict_types=1);
namespace Arzoie\Infrastructure;

use Arzoie\Core\Env;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

final class SshTransport
{
    private SFTP $ssh;

    public function __construct(array $server, string $username = 'arzoie-sync', ?string $password = null)
    {
        $this->ssh = new SFTP($server['address'], (int) $server['ssh_port'], 15);
        $this->ssh->setPreferredAlgorithms(['hostkey' => ['ssh-ed25519']]);
        $key = $this->ssh->getServerPublicHostKey();
        if (!is_string($key)) { throw new \RuntimeException('Cannot obtain SSH host key'); }
        $parts = explode(' ', $key);
        $raw = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        $fingerprint = $raw === false ? '' : 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=');
        if (!hash_equals($server['host_fingerprint'], $fingerprint)) { throw new \RuntimeException('SSH host key mismatch; verify the fingerprint through the server console'); }
        $credential = $password ?? PublicKeyLoader::loadPrivateKey(file_get_contents(Env::get('SSH_KEY_PATH')));
        if (!$this->ssh->login($username, $credential)) { throw new \RuntimeException('SSH authentication failed'); }
        $this->ssh->setTimeout(900);
    }

    public function run(array $args): string
    {
        $output = $this->ssh->exec(implode(' ', array_map(escapeshellarg(...), $args)));
        if ($output === false || $this->ssh->getExitStatus() !== 0) { throw new \RuntimeException('Remote operation failed: ' . substr((string) $output, -1500)); }
        return trim($output);
    }

    public function upload(string $local, string $remote): void
    {
        if (!$this->ssh->put($remote, $local, SFTP::SOURCE_LOCAL_FILE)) { throw new \RuntimeException('SFTP upload failed'); }
        if (!$this->ssh->chmod(0600, $remote)) { throw new \RuntimeException('Cannot restrict staged file permissions'); }
    }

    public function bootstrap(string $script, string $username, string $sudoPassword): void
    {
        $remote = '/tmp/arzoie-bootstrap-' . bin2hex(random_bytes(12)) . '.sh';
        $this->upload($script, $remote);
        try {
            if ($username === 'root') { $this->run(['/bin/bash', $remote]); return; }
            // A password travels in SSH channel stdin, never command arguments or logs.
            $this->ssh->enablePTY();
            $this->ssh->exec('sudo -S -p ARZOIE_SUDO_PROMPT -- /bin/bash ' . escapeshellarg($remote), false);
            $first = $this->ssh->read('/ARZOIE_SUDO_PROMPT|ARZOIE_BOOTSTRAP_OK|ARZOIE_BOOTSTRAP_FAILED/', \phpseclib3\Net\SSH2::READ_REGEX);
            if ($first === false) { throw new \RuntimeException('Privilege escalation timed out'); }
            if (str_contains($first, 'ARZOIE_SUDO_PROMPT')) {
                $this->ssh->write($sudoPassword . "\n");
                $first = $this->ssh->read('/ARZOIE_BOOTSTRAP_OK|ARZOIE_BOOTSTRAP_FAILED|ARZOIE_SUDO_PROMPT/', \phpseclib3\Net\SSH2::READ_REGEX);
            }
            $this->ssh->disablePTY();
            if (!is_string($first) || !str_contains($first, 'ARZOIE_BOOTSTRAP_OK')) { throw new \RuntimeException('Bootstrap failed; inspect the target journal. No credentials were logged.'); }
        } finally { $this->ssh->delete($remote); }
    }
}
