<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/favicon.php';
if (PHP_SAPI==='cli') return;
session_write_close();
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
// Revalidate in the browser; the server cache prevents unnecessary origin fetches.
header('Cache-Control: no-cache');
try {
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
    if(!$id || $id<1) {http_response_code(404);exit;}
    $stmt=db()->prepare('SELECT url FROM systems WHERE id=? AND active=1');$stmt->execute([$id]);$url=$stmt->fetchColumn();
    if(!$url || (settings()['system_icon_'.$id] ?? 'favicon')==='icon') {http_response_code(404);exit;}
    $dir=__DIR__.'/config/favicon-cache'; $file=favicon_cache_file($url,$dir);
    $body=favicon_cached($file);
    if($body===null){
        // Cache is optional: hosting permissions must not stop icon retrieval.
        $lock=null;
        if(is_dir($dir) || @mkdir($dir,0700,true)) {
            $lock=@fopen($file.'.lock','c');
            if($lock && !flock($lock,LOCK_EX)) {fclose($lock);$lock=null;}
        }
        try {
            clearstatcache(true,$file); $body=favicon_cached($file);
            if($body===null){
                $body=favicon_discover($url);
                if($lock){
                    $temp=@tempnam($dir,'icon-');
                    if($temp!==false){if(@file_put_contents($temp,$body)!==false){@chmod($temp,0600);@rename($temp,$file);} if(is_file($temp))@unlink($temp);}
                }
            }
        } finally { if($lock){flock($lock,LOCK_UN);fclose($lock);} }
    }
    $mime=favicon_mime($body);
    if(!$mime){http_response_code(404);exit;}
    $etag='"'.hash('sha256',$body).'"';header('ETag: '.$etag);
    if(($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')===$etag){http_response_code(304);exit;}
    header('Content-Type: '.$mime);echo $body;
} catch(Throwable $error){error_log('SENA favicon unavailable: '.get_class($error));http_response_code(404);}
