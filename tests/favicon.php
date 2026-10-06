<?php
require dirname(__DIR__).'/app/favicon.php';
function expect_icon(bool $condition,string $label): void { if(!$condition)throw new RuntimeException($label);echo "PASS $label\n"; }
expect_icon(!favicon_public_ip('127.0.0.1') && !favicon_public_ip('10.0.0.1') && !favicon_public_ip('169.254.169.254') && !favicon_public_ip('100.64.0.1') && !favicon_public_ip('::ffff:127.0.0.1'),'private and metadata destinations blocked');
expect_icon(favicon_public_ip('8.8.8.8'),'public address allowed');
expect_icon(favicon_url('https://example.com/app/index.php','../icons/site.png')==='https://example.com/icons/site.png','relative icon path resolved');
expect_icon(favicon_url('https://example.com/app/','//cdn.example.com/icon.png')==='https://cdn.example.com/icon.png','protocol relative icon resolved');
expect_icon(favicon_url('https://example.com/','data:image/svg+xml,test')===null,'data scheme rejected');
$urls=favicon_candidates('https://example.com/app/index.php','<html><head><base href="/brand/"><link rel="shortcut icon" href="logo.png"><link rel="apple-touch-icon" href="/apple.png"></head></html>');
expect_icon($urls[0]==='https://example.com/brand/logo.png' && $urls[1]==='https://example.com/apple.png','declared icons and base URL discovered');
expect_icon(favicon_mime('<html>error</html>')===null,'HTML is rejected');
expect_icon(favicon_mime(file_get_contents(dirname(__DIR__).'/assets/favicon/favicon-32.png'))==='image/png','local SDH favicon is valid PNG');
expect_icon(favicon_fetch('http://127.0.0.1/')===null && favicon_fetch('https://example.com:8443/icon.png')===null,'fetch rejects internal host and nonstandard port');
$svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="a"><stop stop-color="#7524ef"/></linearGradient></defs><path fill="url(#a)" d="M0 0h64v64H0z"/></svg>';
expect_icon(favicon_mime($svg)==='image/svg+xml','safe SVG favicon with internal gradient supported');
foreach(['<script>alert(1)</script>','<foreignObject/>','<path onload="alert(1)"/>','<use href="https://bad.example/x"/>','<path fill="url(https://bad.example/x)"/>'] as $unsafe) expect_icon(favicon_mime('<svg xmlns="http://www.w3.org/2000/svg">'.$unsafe.'</svg>')===null,'unsafe SVG rejected: '.$unsafe);
expect_icon(favicon_mime('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>')===null,'XML entity declarations rejected');
expect_icon(favicon_url('https://example.com/app/index.php?old=1','?v=2#ignored')==='https://example.com/app/index.php?v=2','query-only relative URL keeps document path');
$ranked=favicon_candidates('https://example.com/app/','<link rel="icon" href="small.ico"><link rel="icon" type="image/png" sizes="64x64" href="sharp.png"><link rel="icon" type="image/png" sizes="16x16" href="tiny.png">');
expect_icon($ranked[0]==='https://example.com/app/sharp.png','sharp PNG preferred over tiny ICO and PNG');
$png=file_get_contents(dirname(__DIR__).'/assets/favicon/favicon-32.png');
$calls=[];
$body=favicon_discover('https://example.com/app/',function($url,$limit,$partial,$deadline)use(&$calls,$png){$calls[]=$url;return str_ends_with($url,'/app/')?['url'=>$url,'body'=>'<link rel="icon" href="logo.png">']:['url'=>$url,'body'=>$png];});
expect_icon($body===$png && $calls[1]==='https://example.com/app/logo.png','discovery uses declared subfolder image');
$file=tempnam(sys_get_temp_dir(),'hub-icon-test-');
try {
    file_put_contents($file,'');touch($file,time()-301);clearstatcache(true,$file);expect_icon(favicon_cached($file)===null,'failed download retries after five minutes');
    file_put_contents($file,$png);touch($file,time()-3600);clearstatcache(true,$file);expect_icon(favicon_cached($file)===$png,'successful icon remains cached');
} finally {unlink($file);}

expect_icon(favicon_public_ip('2606:4700:4700::1111') && !favicon_public_ip('::1') && !favicon_public_ip('fe80::1') && !favicon_public_ip('2001:db8::1'),'public IPv6 supported while local and documentation ranges blocked');
