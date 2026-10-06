<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/favicon.php';
if (PHP_SAPI==='cli') return;
session_write_close();
header('X-Content-Type-Options: nosniff');
try {
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
    if(!$id || $id<1) {http_response_code(404);exit;}
    $stmt=db()->prepare('SELECT url FROM systems WHERE id=? AND active=1');$stmt->execute([$id]);$url=$stmt->fetchColumn();
    if(!$url || (settings()['system_icon_'.$id] ?? 'favicon')==='icon') {http_response_code(404);exit;}
    $dir=__DIR__.'/config/favicon-cache'; $file=$dir.'/'.hash('sha256',$url).'.bin';
    $body='';
    if(is_file($file) && filemtime($file)>time()-86400) $body=(string)file_get_contents($file);
    else {
        if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) {http_response_code(404);exit;}
        $lock=@fopen($file.'.lock','c');
        if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);http_response_code(404);exit;}
        $page=favicon_fetch($url,131072);
        foreach(favicon_candidates($page['url'] ?? $url,$page['body'] ?? '') as $candidate) {
            $result=favicon_fetch($candidate);
            if($result && favicon_mime($result['body'])) {$body=$result['body'];break;}
        }
        // Cache misses too so broken websites aren't fetched on every page load.
        $temp=tempnam($dir,'icon-'); if($temp!==false){file_put_contents($temp,$body);chmod($temp,0600);rename($temp,$file);}
        flock($lock,LOCK_UN);fclose($lock);
    }
    $mime=favicon_mime($body);
    if(!$mime){header('Cache-Control: public, max-age=300');http_response_code(404);exit;}
    header('Content-Type: '.$mime);header('Cache-Control: public, max-age=86400');echo $body;
} catch(Throwable $error){error_log('SENA favicon unavailable');http_response_code(404);}
