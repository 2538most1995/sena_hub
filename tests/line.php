<?php
declare(strict_types=1);
require dirname(__DIR__).'/line-webhook.php';
function check_line(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; }
$body = '{"events":[]}';
$signature = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));
check_line(sena_line_signature($body, $signature, 'test-secret'), 'valid signature');
check_line(!sena_line_signature($body.' ', $signature, 'test-secret'), 'reject tampered body');
check_line(!sena_line_signature($body, '', ''), 'reject empty credentials');
$join = ['type'=>'join','source'=>['type'=>'group'],'replyToken'=>'test'];
check_line(sena_line_should_reply($join), 'reply when bot joins group');
check_line(!sena_line_should_reply($join + ['mode'=>'standby']), 'ignore standby');
check_line(!sena_line_should_reply(['type'=>'memberJoined','replyToken'=>'test']), 'do not greet every member');
check_line(sena_line_should_reply(['type'=>'message','message'=>['type'=>'text','text'=>' เมนู '],'replyToken'=>'test']), 'menu command');
check_line(!sena_line_should_reply(['type'=>'message','message'=>['type'=>'text','text'=>'เรื่องส่วนตัว'],'replyToken'=>'test']), 'ignore unrelated messages');
$menu = sena_line_menu('https://krumost.com/sena_hub/');
$actions = [];
$images = [];
$walk = function (array $node) use (&$walk, &$actions, &$images): void {
    if (isset($node['action'])) $actions[] = $node['action'];
    if (($node['type'] ?? '') === 'image') $images[] = $node['url'];
    foreach ($node as $child) if (is_array($child)) $walk($child);
};
$walk($menu);
check_line(array_column($actions, 'uri') === array_map(fn($path) => 'https://krumost.com/sena_hub/'.$path,
    ['', '?category=student', '?category=staff', '?category=management', '?category=learning', '?view=contact']), 'six service destinations preserved in order');
check_line(count(array_filter($actions, fn($action) => $action['type'] === 'uri')) === 6, 'all service tiles are clickable URI actions');
check_line($menu === sena_line_menu('https://krumost.com/sena_hub'), 'normalize trailing slash');
foreach (array_unique($images) as $url) {
    $path = dirname(__DIR__).'/'.substr($url, strlen('https://krumost.com/sena_hub/'));
    $info = is_file($path) ? getimagesize($path) : false;
    check_line($info !== false && in_array($info['mime'], ['image/png', 'image/jpeg'], true), 'LINE raster asset '.basename($path));
}
check_line(strlen(json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) < 30000, 'bubble below LINE 30 KB limit');
check_line(json_decode(json_encode($menu, JSON_THROW_ON_ERROR), true)['type'] === 'flex', 'valid JSON');
