<?php
declare(strict_types=1);

// Standalone: no database writes, sessions, broadcasts or paid Push API calls.
function sena_line_signature(string $body, string $signature, string $secret): bool {
    return $secret !== '' && $signature !== '' && hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
}
function sena_line_should_reply(array $event): bool {
    if (($event['mode'] ?? 'active') !== 'active' || empty($event['replyToken'])) return false;
    if (($event['type'] ?? '') === 'join') return in_array($event['source']['type'] ?? '', ['group','room'], true);
    return ($event['type'] ?? '') === 'message' && ($event['message']['type'] ?? '') === 'text'
        && in_array(strtolower(trim($event['message']['text'] ?? '')), ['เมนู','menu','รวมระบบ','hub','sena','เว็บ','ระบบ','รวมเว็บ','เสนา'], true);
}
function sena_line_menu(string $base): array {
    $base = rtrim($base, '/').'/';
    $asset = $base.'assets/line/';
    // Raster icons are exported from the project's MIT-licensed Tabler set.
    $image = static fn(string $name, string $size): array => [
        'type'=>'image','url'=>$asset.$name.'.png','size'=>$size,'flex'=>0,
        'aspectRatio'=>'1:1','aspectMode'=>'fit'];
    $tile = static function (string $label, string $path, string $icon, string $tint, bool $primary = false) use ($base, $image): array {
        $box = ['type'=>'box','layout'=>'horizontal','flex'=>1,'spacing'=>'sm',
            'paddingAll'=>$primary ? '16px' : '10px','cornerRadius'=>'16px',
            'borderWidth'=>'1px','borderColor'=>$primary ? '#AA6BFF' : '#E8E1FC',
            'backgroundColor'=>$primary ? '#7524EF' : '#F8F5FF',
            'alignItems'=>'center','action'=>['type'=>'uri','label'=>$label,'uri'=>$base.$path],
            'contents'=>[
                ['type'=>'box','layout'=>'vertical','width'=>$primary ? '30px' : '32px','height'=>$primary ? '30px' : '32px',
                    'cornerRadius'=>'16px','backgroundColor'=>$tint,'justifyContent'=>'center','alignItems'=>'center',
                    'flex'=>0,'contents'=>[$image($icon, $primary ? '26px' : '22px')]],
                ['type'=>'text','text'=>$label,'weight'=>'bold','size'=>$primary ? '18px' : '12px',
                    'color'=>$primary ? '#FFFFFF' : '#20163E','wrap'=>true,'flex'=>1],
                $image($primary ? 'arrow-white' : 'arrow', '12px')]];
        if ($primary) $box['background'] = ['type'=>'linearGradient','angle'=>'100deg','startColor'=>'#A443F5','endColor'=>'#6014E7'];
        return $box;
    };
    return ['type'=>'flex','altText'=>'SENA Digital Hub — กดเปิดหน้าเว็บรวมระบบ สกร.เสนา','contents'=>[
        'type'=>'bubble','size'=>'mega',
        'hero'=>['type'=>'image','url'=>$asset.'menu-hero-v2.jpg','size'=>'full',
            'aspectRatio'=>'5:2','aspectMode'=>'cover'],
        'body'=>['type'=>'box','layout'=>'vertical','backgroundColor'=>'#FFFFFF','paddingAll'=>'12px','spacing'=>'sm','contents'=>[
            ['type'=>'box','layout'=>'horizontal','spacing'=>'md','paddingAll'=>'4px','margin'=>'sm','alignItems'=>'center','contents'=>[
                ['type'=>'box','layout'=>'vertical','width'=>'44px','height'=>'44px','flex'=>0,'cornerRadius'=>'22px',
                    'backgroundColor'=>'#F0E8FF','alignItems'=>'center','justifyContent'=>'center','contents'=>[$image('student','30px')]],
                ['type'=>'box','layout'=>'vertical','spacing'=>'xs','contents'=>[
                    ['type'=>'text','text'=>'ยินดีต้อนรับคุณครูและนักศึกษา','weight'=>'bold','size'=>'15px','color'=>'#20163E','wrap'=>true],
                    ['type'=>'text','text'=>'เลือกบริการด้านล่าง ใช้ได้ทั้งคอมและมือถือ','size'=>'11px','color'=>'#77728F','wrap'=>true]]]]],
            $tile('เปิดหน้าเว็บรวม','','portal','#8B38EF',true) + ['margin'=>'md'],
            ['type'=>'box','layout'=>'horizontal','spacing'=>'sm','contents'=>[
                $tile('ระบบนักศึกษา','?category=student','student','#EEE5FF'),
                $tile('ครูและบุคลากร','?category=staff','staff','#FCE5F1')]],
            ['type'=>'box','layout'=>'horizontal','spacing'=>'sm','contents'=>[
                $tile('ระบบบริหาร','?category=management','management','#E6EBFF'),
                $tile('แหล่งเรียนรู้','?category=learning','learning','#DCF8EE')]],
            $tile('ติดต่อเรา','?view=contact','contact','#FFF0DB')]],
        'footer'=>['type'=>'box','layout'=>'horizontal','paddingAll'=>'16px','spacing'=>'md','alignItems'=>'center','contents'=>[
            ['type'=>'box','layout'=>'vertical','height'=>'1px','backgroundColor'=>'#E8E1FC','flex'=>1,'contents'=>[]],
            ['type'=>'text','text'=>'พิมพ์ “เมนู” เพื่อเรียกอีกครั้ง','size'=>'11px','color'=>'#77728F','align'=>'center','wrap'=>true,'flex'=>4],
            ['type'=>'box','layout'=>'vertical','height'=>'1px','backgroundColor'=>'#E8E1FC','flex'=>1,'contents'=>[]]]]
    ]];
}

if (PHP_SAPI === 'cli') return;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function sena_line_finish(int $status): void { http_response_code($status); echo '{}'; exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); sena_line_finish(405); }
$configPath = __DIR__.'/config/line.php';
if (!is_file($configPath)) sena_line_finish(503);
$config = require $configPath;
$secret = (string)($config['channel_secret'] ?? '');
$token = (string)($config['channel_access_token'] ?? '');
$base = (string)($config['hub_url'] ?? 'https://krumost.com/sena_hub/');
if ($secret === '' || $token === '' || !function_exists('curl_init') || !filter_var($base, FILTER_VALIDATE_URL)
    || parse_url($base, PHP_URL_SCHEME) !== 'https' || parse_url($base, PHP_URL_QUERY) || parse_url($base, PHP_URL_FRAGMENT)
    || parse_url($base, PHP_URL_USER) || parse_url($base, PHP_URL_PASS)) sena_line_finish(503);
$body = file_get_contents('php://input', false, null, 0, 262145);
if ($body === false || strlen($body) > 262144) sena_line_finish(413);
if (!sena_line_signature($body, (string)($_SERVER['HTTP_X_LINE_SIGNATURE'] ?? ''), $secret)) sena_line_finish(403);
$data = json_decode($body, true);
if (!is_array($data) || !isset($data['events']) || !is_array($data['events'])) sena_line_finish(400);
// Verification requests from LINE contain an empty events array.
$cache = __DIR__.'/config/line-events';
foreach ($data['events'] as $event) {
    if (!is_array($event) || !sena_line_should_reply($event)) continue;
    if (!is_dir($cache) && !@mkdir($cache, 0700, true) && !is_dir($cache)) sena_line_finish(503);
    // Hash only; no message text, user IDs, group IDs or credentials are logged.
    $key = hash('sha256', (string)($event['webhookEventId'] ?? $event['replyToken']));
    $lock = @fopen($cache.'/'.$key, 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) sena_line_finish(503);
    if (stream_get_contents($lock) === 'sent') { fclose($lock); continue; }
    $curl = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($curl, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token],
        CURLOPT_POSTFIELDS=>json_encode(['replyToken'=>$event['replyToken'],'messages'=>[sena_line_menu($base)]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $status !== 200) {
        fclose($lock);
        error_log('SENA LINE reply failed: HTTP '.$status);
        sena_line_finish(502);
    }
    rewind($lock); fwrite($lock, 'sent'); fflush($lock); fclose($lock);
}
sena_line_finish(200);
