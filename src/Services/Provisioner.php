<?php
declare(strict_types=1);
namespace Arzoie\Services;

use Arzoie\Core\{Crypto, Database, Env};
use Arzoie\Infrastructure\{Platform, SshTransport};
use Arzoie\Models\JobRepository;

final class Provisioner
{
    public function onboard(array $server): void
    {
        if ($server['state'] === 'active') { return; }
        if ($server['state'] === 'draining' || $server['state'] === 'removed') { throw new \RuntimeException('Member is being removed'); }
        if (!$server['bootstrap_secret'] || strtotime($server['secret_expires_at'] . ' UTC') < time()) {
            throw new \RuntimeException('Bootstrap credential expired; submit fresh credentials from the member page');
        }
        $secret = Crypto::open($server['bootstrap_secret']);
        try {
            $ssh = new SshTransport($server, $secret['username'], $secret['password']);
            $os = $ssh->run(['/bin/sh', '-c', '. /etc/os-release; printf "%s\\n%s\\n" "$ID" "$VERSION_ID"']);
            [$id, $version] = array_pad(explode("\n", $os), 2, '');
            Platform::profile(trim($id), trim($version));
            $publicFile = Env::get('SSH_KEY_PATH') . '.pub';
            if (!is_file($publicFile)) { throw new \RuntimeException('Generate the worker SSH key first'); }
            $public = trim(file_get_contents($publicFile));
            if (!preg_match('/^ssh-ed25519 [A-Za-z0-9+\/=]+(?: .*)?$/D', $public)) { throw new \RuntimeException('Expected an Ed25519 public key'); }
            $cidr = Env::get('CONTROL_CIDR');
            if ($cidr !== '' && !preg_match('/^[0-9a-fA-F:.\/]+$/D', $cidr)) { throw new \RuntimeException('Invalid CONTROL_CIDR'); }
            $script = file_get_contents(BASE_PATH . '/resources/member/bootstrap.sh');
            $script = str_replace(['@@PUBLIC_KEY@@', '@@CONTROL_CIDR@@', '@@HELPER_BASE64@@'], [base64_encode($public), base64_encode($cidr), base64_encode(file_get_contents(BASE_PATH . '/resources/member/arzoie-member'))], $script);
            $temp = tempnam(sys_get_temp_dir(), 'arzoie-');
            try {
                file_put_contents($temp, $script);
                $ssh->bootstrap($temp, $secret['username'], $secret['sudo_password'] ?: $secret['password']);
            } finally { unlink($temp); }
            $member = new SshTransport($server);
            $health = $member->run(['sudo', '-n', '/usr/local/sbin/arzoie-member', 'health']);
            json_decode($health, true, flags: JSON_THROW_ON_ERROR);
            Database::run("UPDATE servers SET state = 'active', os_name = ?, health = ?, health_at = ?, bootstrap_secret = NULL, secret_expires_at = NULL, last_error = NULL WHERE id = ?", [trim($id) . ' ' . trim($version), $health, gmdate('Y-m-d H:i:s'), $server['id']]);
            JobRepository::enqueue('reconcile', (int) $server['id']);
        } finally {
            // Even failed attempts require fresh credentials. Never persist reusable root passwords.
            Database::run('UPDATE servers SET bootstrap_secret = NULL, secret_expires_at = NULL WHERE id = ?', [$server['id']]);
            unset($secret);
        }
    }
}
