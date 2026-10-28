<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use Arzoie\Core\Crypto;
use Arzoie\Dns\{Names, Record, SyncPlanner, ZoneRenderer};
use Arzoie\Infrastructure\Platform;

$passed = 0; $failed = 0;
$test = static function (string $name, callable $fn) use (&$passed, &$failed): void {
    try { $fn(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $e) { $failed++; echo "FAIL $name: {$e->getMessage()}\n"; }
};
$equal = static function (mixed $a, mixed $b): void { if ($a !== $b) { throw new RuntimeException(var_export($a, true) . ' != ' . var_export($b, true)); } };
$reject = static function (callable $fn): void { try { $fn(); } catch (InvalidArgumentException|RuntimeException) { return; } throw new Exception('Invalid input was accepted'); };
$record = static fn(array $input): array => Record::normalize('example.com', ['owner'=>'@', 'ttl'=>300] + $input);

$test('Normalize domain and reject configuration injection', function () use ($equal, $reject): void {
    $equal(Names::domain('EXAMPLE.com.'), 'example.com');
    foreach (['x.com; include "evil"', '-bad.example', 'a..com', '../example.com', 'localhost', str_repeat('x',64) . '.com'] as $bad) { $reject(fn() => Names::domain($bad)); }
});
$test('Owners stay inside their zone; service labels and wildcards work', function () use ($equal, $reject): void {
    $equal(Names::owner('_sip._tcp', 'example.com'), '_sip._tcp');
    $equal(Names::owner('www.example.com.', 'example.com'), 'www');
    $equal(Names::owner('*.www', 'example.com'), '*.www');
    $reject(fn() => Names::owner('www.other.com.', 'example.com'));
    $reject(fn() => Names::owner('www.*', 'example.com'));
});
$test('A/AAAA addresses are validated', function () use ($equal, $reject, $record): void {
    $equal($record(['type'=>'A','value'=>'192.0.2.1'])['rdata'], '192.0.2.1');
    $equal($record(['type'=>'AAAA','value'=>'2001:db8::1'])['rdata'], '2001:db8::1');
    $reject(fn() => $record(['type'=>'A','value'=>'2001:db8::1']));
    $reject(fn() => $record(['type'=>'A','value'=>'999.1.1.1']));
});
$test('TXT strings escape quotes and split long records', function () use ($equal, $record, $reject): void {
    $equal($record(['type'=>'TXT','value'=>'a"b\\c'])['rdata'], '"a\\"b\\\\c"');
    $value = $record(['type'=>'TXT','value'=>str_repeat('a',300)])['rdata'];
    $equal(substr_count($value, '" "'), 1);
    $reject(fn() => $record(['type'=>'TXT','value'=>"safe\n\$INCLUDE /etc/passwd"]));
});
$test('MX, SRV and CAA normalize typed RDATA', function () use ($equal, $record, $reject): void {
    $equal($record(['type'=>'MX','value'=>'10 MAIL.example.com'])['rdata'], '10 mail.example.com.');
    $equal($record(['type'=>'SRV','value'=>'10 5 443 service.example.com'])['rdata'], '10 5 443 service.example.com.');
    $equal($record(['type'=>'CAA','value'=>'0 issuewild letsencrypt.org'])['rdata'], '0 issuewild "letsencrypt.org"');
    $reject(fn() => $record(['type'=>'MX','value'=>'99999 mail.example.com']));
    $reject(fn() => $record(['type'=>'CAA','value'=>'1 invalid example.com']));
});
$test('Reject CNAME conflicts and inconsistent RRset TTLs', function () use ($record, $reject): void {
    $reject(fn() => $record(['type'=>'CNAME','value'=>'other.example.com']));
    $a = Record::normalize('example.com', ['owner'=>'www','type'=>'A','value'=>'192.0.2.1','ttl'=>300]);
    $c = Record::normalize('example.com', ['owner'=>'www','type'=>'CNAME','value'=>'other.example.com','ttl'=>300]);
    $reject(fn() => Record::validateSet([$a, $c]));
    $reject(fn() => Record::validateSet([$c, $c]));
    $reject(fn() => Record::validateSet([$a, array_replace($a,['ttl'=>600])]));
});
$test('Render a deterministic authoritative zone and require NS redundancy', function () use ($equal, $reject): void {
    $zone = ['name'=>'example.com','primary_ns'=>'ns1.example.net','soa_email'=>'hostmaster.example.com','serial'=>42];
    $records = [['owner'=>'@','type'=>'NS','ttl'=>300,'value'=>'ns1.example.net'],['owner'=>'@','type'=>'NS','ttl'=>300,'value'=>'ns2.example.net']];
    $renderer = new ZoneRenderer();
    $text = $renderer->render($zone, $records);
    $equal(str_contains($text, 'SOA ns1.example.net. hostmaster.example.com. (42 '), true);
    $equal($text, $renderer->render($zone,array_reverse($records)));
    $reject(fn() => $renderer->render($zone,[$records[0]]));
    $reject(fn() => $renderer->render($zone,[$records[0],$records[0]]));
});
$test('Sync only missing revisions, including deletion tombstones', function () use ($equal): void {
    $equal(SyncPlanner::action(false, 10, 10), 'none');
    $equal(SyncPlanner::action(false, 10, 9), 'update');
    $equal(SyncPlanner::action(false, 10, null), 'add');
    $equal(SyncPlanner::action(true, 11, 9), 'delete');
    $equal(SyncPlanner::action(true, 11, null), 'delete');
    $equal(SyncPlanner::action(true, 11, 11), 'none');
});
$test('Platform allowlist rejects Ubuntu and unsupported EL releases', function () use ($equal, $reject): void {
    foreach (['rocky','almalinux','ol','rhel'] as $id) { $equal(Platform::profile($id,'10.1')['group'], 'wheel'); }
    $equal(Platform::profile('debian','13')['group'], 'sudo');
    $equal(Platform::profile('debian','14')['family'], 'debian');
    foreach ([['ubuntu','26.04'],['debian','12'],['rocky','9'],['fedora','43'],['rhel','11']] as [$id,$version]) { $reject(fn() => Platform::profile($id,$version)); }
});
$test('Bootstrap credentials are encrypted and authenticated', function () use ($equal, $reject): void {
    putenv('APP_KEY=' . base64_encode(random_bytes(32)));
    $secret = ['username'=>'root','password'=>'not-a-real-password'];
    $sealed = Crypto::seal($secret);
    $equal(str_contains($sealed,$secret['password']), false);
    $equal(Crypto::open($sealed),$secret);
    $data = base64_decode($sealed); $data[30] = chr(ord($data[30]) ^ 1);
    $reject(fn() => Crypto::open(base64_encode($data)));
});
echo "$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
