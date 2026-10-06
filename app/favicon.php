<?php
declare(strict_types=1);
// Only public HTTP(S) origins. Resolve once and pin the address to prevent DNS rebinding.
function favicon_public_ip(string $ip): bool {
    if(str_contains($ip, ':')) {
        $binary=@inet_pton($ip);
        return $binary!==false && strlen($binary)===16 && (ord($binary[0]) & 0xe0)===0x20
            && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)!==false
            && !preg_match('/^2001:(?:db8|0|20):/i',$ip);
    }
    return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        && !preg_match('/^(0\.|127\.|169\.254\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|192\.0\.0\.|198\.(18|19)\.)/',$ip);
}
function favicon_url(string $base, string $href): ?string {
    $href=trim(html_entity_decode($href,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if ($href==='' || preg_match('/[\\x00-\\x20\\\\\\\\]/',$href)) return null;
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i',$href)) return preg_match('/^https?:\\/\\//i',$href)?preg_replace('/#.*$/','',$href):null;
    $p=parse_url($base); if (!$p || empty($p['host'])) return null;
    if (str_starts_with($href,'//')) return strtolower($p['scheme']).':'.preg_replace('/#.*$/','',$href);
    $origin=strtolower($p['scheme']).'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
    $r=parse_url($href); if($r===false)return null;
    $path=$p['path'] ?? '/';
    if(!isset($r['path']) || $r['path']==='') $resolved=$path;
    else {
        $dir=substr($path,0,(int)strrpos($path,'/')+1);
        $raw=str_starts_with($r['path'],'/')?$r['path']:$dir.$r['path'];
        $parts=[]; foreach(explode('/',$raw) as $part) { if($part==='..') array_pop($parts); elseif($part!=='.' && $part!=='') $parts[]=$part; }
        $resolved='/'.implode('/',$parts); if(str_ends_with($raw,'/') && $resolved!=='/')$resolved.='/';
    }
    $query=$r['query'] ?? ((!isset($r['path']) || $r['path']==='')?($p['query'] ?? null):null);
    return $origin.$resolved.($query!==null?'?'.$query:'');
}
function favicon_fetch(string $url, int $limit=524288, bool $partialHtml=false, ?float $deadline=null): ?array {
    if (!function_exists('curl_init')) return null;
    for ($hop=0;$hop<4;$hop++) {
        $p=parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''),['http','https'],true) || isset($p['user']) || isset($p['pass']) || empty($p['host']) || preg_match('/[\x00-\x20\\\\]/',$url)) return null;
        $scheme=strtolower($p['scheme']);
        $port=$p['port'] ?? ($scheme==='https'?443:80);
        $remaining=$deadline===null?4.0:min(4.0,$deadline-microtime(true));
        if($remaining<=0)return null;
        if (!in_array($port,[80,443],true)) return null;
        $host=trim($p['host'],'[]'); $literal=filter_var($host,FILTER_VALIDATE_IP)!==false;
        $addresses=$literal?[$host]:gethostbynamel($host);
        if(!$addresses && !$literal && function_exists('dns_get_record')) $addresses=array_column(@dns_get_record($host,DNS_AAAA) ?: [],'ipv6');
        if (!$addresses) return null;
        foreach($addresses as $ip) if(!favicon_public_ip($ip)) return null;
        $body=''; $location=''; $oversize=false; $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT_MS=>min(2000,(int)($remaining*1000)),CURLOPT_TIMEOUT_MS=>max(1,(int)($remaining*1000)),CURLOPT_ENCODING=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>$literal?[]:[$host.':'.$port.':'.(str_contains($addresses[0],':')?'['.$addresses[0].']':$addresses[0])],CURLOPT_PROXY=>'',CURLOPT_USERAGENT=>'SENAHub-Favicon/1.0',
            CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$body,&$oversize,$limit){if(strlen($body)+strlen($chunk)>$limit){$body.=substr($chunk,0,max(0,$limit-strlen($body)));$oversize=true;return 0;} $body.=$chunk;return strlen($chunk);},
            CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);}]);
        $ok=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $curlError=curl_errno($curl); curl_close($curl);
        if($status>=300 && $status<400 && $location!==''){ $url=favicon_url($url,$location) ?? '';continue; }
        if($status!==200 || (($ok===false || $oversize) && !($partialHtml && $oversize && $curlError===CURLE_WRITE_ERROR))) return null;
        return ['url'=>$url,'body'=>$body];
    }
    return null;
}
function favicon_candidates(string $url, string $html=''): array {
    $ranked=[];
    if ($html!=='' && class_exists('DOMDocument')) {
        $dom=new DOMDocument(); $previous=libxml_use_internal_errors(true); $dom->loadHTML($html,LIBXML_NONET); libxml_clear_errors();libxml_use_internal_errors($previous);
        $base=$url; $baseTag=$dom->getElementsByTagName('base')->item(0);
        if($baseTag) $base=favicon_url($url,$baseTag->getAttribute('href')) ?? $url;
        foreach($dom->getElementsByTagName('link') as $link) {
            $rel=strtolower($link->getAttribute('rel'));
            if (!preg_match('/(?:^|\\s)(?:icon|apple-touch-icon|apple-touch-icon-precomposed)(?:\\s|$)/',$rel)) continue;
            $candidate=favicon_url($base,$link->getAttribute('href')); if(!$candidate)continue;
            $score=str_contains($rel,'apple-touch')?500:1000;
            $type=strtolower($link->getAttribute('type')); $path=strtolower(parse_url($candidate,PHP_URL_PATH) ?? '');
            if($type==='image/png' || str_ends_with($path,'.png'))$score+=200;
            if($type==='image/svg+xml' || str_ends_with($path,'.svg'))$score+=250;
            if(preg_match('/(\\d+)x(\\d+)/',$link->getAttribute('sizes'),$m))$score+=min(192,min((int)$m[1],(int)$m[2]));
            $ranked[]=['url'=>$candidate,'score'=>$score];
        }
    }
    usort($ranked,fn($a,$b)=>$b['score']<=>$a['score']);
    $urls=array_column(array_slice($ranked,0,6),'url');
    $urls[]=favicon_url($url,'favicon.ico'); $urls[]=favicon_url($url,'/favicon.ico');
    return array_values(array_unique(array_filter($urls)));
}
function favicon_safe_svg(string $body): bool {
    if(!class_exists('DOMDocument') || preg_match('/<!DOCTYPE|<!ENTITY/i',$body))return false;
    $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);$ok=$doc->loadXML($body,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
    if(!$ok || !$doc->documentElement || $doc->documentElement->localName!=='svg' || $doc->documentElement->namespaceURI!=='http://www.w3.org/2000/svg')return false;
    $allowed=['svg','g','defs','path','circle','ellipse','rect','polygon','polyline','line','use','symbol','linearGradient','radialGradient','stop','clipPath','mask','title','desc','text','tspan','pattern'];
    foreach($doc->getElementsByTagName('*') as $node){
        if($node->namespaceURI!=='http://www.w3.org/2000/svg' || !in_array($node->localName,$allowed,true))return false;
        foreach($node->attributes as $attr){
            if(str_starts_with(strtolower($attr->localName),'on') || str_contains(strtolower($attr->value),'@import'))return false;
            if(in_array(strtolower($attr->localName),['href','src'],true) && !str_starts_with(trim($attr->value),'#'))return false;
            if(preg_match_all('/url\\s*\\(([^)]*)\\)/i',$attr->value,$matches))foreach($matches[1] as $ref)if(!str_starts_with(trim($ref," \\t\\n\\r\"'"),'#'))return false;
        }
    }
    return true;
}
function favicon_mime(string $body): ?string {
    if($body==='' || strlen($body)>524288)return null;
    $info=@getimagesizefromstring($body);
    if($info && $info[0]<=4096 && $info[1]<=4096 && in_array($info['mime'] ?? '',['image/png','image/jpeg','image/gif','image/webp','image/x-icon','image/vnd.microsoft.icon'],true)) return $info['mime'];
    return favicon_safe_svg($body)?'image/svg+xml':null;
}
function favicon_cache_file(string $url, string $directory): string { return $directory.'/'.hash('sha256','v2|'.$url).'.bin'; }
function favicon_cached(string $file, ?int $now=null): ?string {
    if(!is_file($file))return null;
    $size=filesize($file);$ttl=$size===0?300:86400;
    if(($now ?? time())-filemtime($file)>=$ttl)return null;
    return (string)file_get_contents($file);
}
function favicon_discover(string $url, ?callable $fetch=null): string {
    $fetch ??= 'favicon_fetch'; $deadline=microtime(true)+10;
    $page=$fetch($url,131072,true,$deadline);
    foreach(favicon_candidates($page['url'] ?? $url,$page['body'] ?? '') as $candidate){
        if(microtime(true)>=$deadline)break;
        $result=$fetch($candidate,524288,false,$deadline);
        if($result && favicon_mime($result['body']))return $result['body'];
    }
    return '';
}
