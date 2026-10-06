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
check_line(count($menu['contents']['footer']['contents']) === 7, 'six buttons and hint');
check_line($menu['contents']['footer']['contents'][1]['action']['uri'] === 'https://krumost.com/sena_hub/?category=student', 'student URL');
check_line(json_decode(json_encode($menu, JSON_THROW_ON_ERROR), true)['type'] === 'flex', 'valid JSON');
