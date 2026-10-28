<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use Arzoie\Core\View;

$_SESSION = ['user_id'=>1,'user_email'=>'admin@example.com','_token'=>str_repeat('a',64)];
$evil = '<script>alert("xss")</script>';
$server = ['id'=>1,'name'=>$evil,'address'=>'192.0.2.10','ssh_port'=>22,'host_fingerprint'=>'SHA256:fake','location'=>'Frankfurt','state'=>'active','health_at'=>gmdate('Y-m-d H:i:s'),'health'=>'{"bind":"running","disk_free_kb":1234567,"active_zones":1}','last_error'=>null,'os_name'=>'debian 13'];
$zone = ['id'=>1,'name'=>'example.com','serial'=>42,'desired_revision'=>1,'deleted_at'=>null];
$record = ['id'=>1,'owner'=>'@','type'=>'TXT','ttl'=>300,'value'=>$evil];
$pages = [
    'dashboard'=>['servers'=>[$server],'zones'=>1,'pending'=>0,'failed'=>0,'events'=>[]],
    'servers'=>['servers'=>[$server]],
    'zones'=>['zones'=>[$zone],'selected'=>$zone,'records'=>[$record],'revision'=>['id'=>1,'checksum'=>'abc','operation'=>'publish','signed_until'=>null,'ds_records'=>$evil]],
    'reports'=>['deployments'=>[],'jobs'=>[],'events'=>[['created_at'=>'2026-01-01','actor'=>$evil,'action'=>'test','subject'=>$evil,'details'=>$evil]]],
    'login'=>[],
    'error'=>['message'=>$evil],
];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message,0,$severity,$file,$line); });
foreach ($pages as $page=>$data) {
    ob_start(); View::render($page,$data+['title'=>'Test']); $html = ob_get_clean();
    if (!str_contains($html,'<!doctype html>') || str_contains($html,$evil)) { throw new RuntimeException('Unsafe or incomplete view: '.$page); }
    echo "PASS $page view renders without warnings and escapes user content\n";
}
