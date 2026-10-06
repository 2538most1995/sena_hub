<?php
declare(strict_types=1);
// Only public HTTP(S) origins. Resolve once and pin the address to prevent DNS rebinding.
function favicon_public_ip(string $ip): bool {
    return !str_contains($ip, ':') && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        && !preg_match('/^(0\.|127\.|169\.254\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|192\.0\.0\.|198\.(18|19)\.)/',$ip);
}
function favicon_url(string $base, string $href): ?string {
    $href=trim(html_entity_decode($href,ENT_QUOTES|ENT_HTML5,'UTF-8'));
    if ($href==='' || preg_match('/[\x00-\x20\\\\]/',$href)) return null;
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i',$href)) return preg_match('/^https?:\/\//i',$href)?$href:null;
    $p=parse_url($base); if (!$p || empty($p['host'])) return null;
    if (str_starts_with($href,'//')) return $p['scheme'].':'.$href;
    $origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
    if (str_starts_with($href,'/')) return $origin.$href;
    $path=$p['path'] ?? '/'; $dir=substr($path,0,(int)strrpos($path,'/')+1);
    $parts=[]; foreach(explode('/',$dir.$href) as $part) { if($part==='..') array_pop($parts); elseif($part!=='.' && $part!=='') $parts[]=$part; }
    return $origin.'/'.implode('/',$parts);
}
function favicon_fetch(string $url, int $limit=262144): ?array {
    if (!function_exists('curl_init')) return null;
    for ($hop=0;$hop<4;$hop++) {
        $p=parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''),['http','https'],true) || isset($p['user']) || isset($p['pass']) || empty($p['host']) || preg_match('/[\x00-\x20\\\\]/',$url)) return null;
        $port=$p['port'] ?? ($p['scheme']==='https'?443:80);
        if (!in_array($port,[80,443],true)) return null;
        $host=$p['host']; $addresses=filter_var($host,FILTER_VALIDATE_IP)?[$host]:gethostbynamel($host);
        if (!$addresses) return null;
        foreach($addresses as $ip) if(!favicon_public_ip($ip)) return null;
        $body=''; $location=''; $oversize=false; $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':'.$port.':'.$addresses[0]],CURLOPT_PROXY=>'',CURLOPT_USERAGENT=>'SENAHub-Favicon/1.0',
            CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$body,&$oversize,$limit){if(strlen($body)+strlen($chunk)>$limit){$oversize=true;return 0;} $body.=$chunk;return strlen($chunk);},
            CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);}]);
        $ok=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        if($status>=300 && $status<400 && $location!==''){ $url=favicon_url($url,$location) ?? '';continue; }
        if($ok===false || $oversize || $status!==200) return null;
        return ['url'=>$url,'body'=>$body];
    }
    return null;
}
function favicon_candidates(string $url, string $html=''): array {
    $urls=[];
    if ($html!=='' && class_exists('DOMDocument')) {
        $dom=new DOMDocument(); $previous=libxml_use_internal_errors(true); $dom->loadHTML($html,LIBXML_NONET); libxml_clear_errors();libxml_use_internal_errors($previous);
        $base=$url; $baseTag=$dom->getElementsByTagName('base')->item(0);
        if($baseTag) $base=favicon_url($url,$baseTag->getAttribute('href')) ?? $url;
        foreach($dom->getElementsByTagName('link') as $link) {
            if (preg_match('/(?:^|\s)(?:icon|apple-touch-icon)(?:\s|$)/i',$link->getAttribute('rel'))) {
                $candidate=favicon_url($base,$link->getAttribute('href')); if($candidate)$urls[]=$candidate;
            }
        }
    }
    $urls[]=favicon_url($url,'favicon.ico'); $urls[]=favicon_url($url,'/favicon.ico');
    return array_slice(array_values(array_unique(array_filter($urls))),0,6);
}
function favicon_mime(string $body): ?string {
    // Raster only: do not serve remote SVG or HTML on the Hub origin.
    $info=@getimagesizefromstring($body);
    if($info && $info[0]<=4096 && $info[1]<=4096 && in_array($info['mime'] ?? '',['image/png','image/jpeg','image/gif','image/webp','image/x-icon','image/vnd.microsoft.icon'],true)) return $info['mime'];
    return null;
}
