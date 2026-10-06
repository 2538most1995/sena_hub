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
expect_icon(favicon_mime('<svg xmlns="http://www.w3.org/2000/svg"></svg>')===null && favicon_mime('<html>error</html>')===null,'SVG and HTML cannot be served on Hub origin');
expect_icon(favicon_mime(file_get_contents(dirname(__DIR__).'/assets/favicon/favicon-32.png'))==='image/png','local SDH favicon is valid PNG');
expect_icon(favicon_fetch('http://127.0.0.1/')===null && favicon_fetch('https://example.com:8443/icon.png')===null,'fetch rejects internal host and nonstandard port');
