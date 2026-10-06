<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/uploads.php';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: no-cache');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) { http_response_code(404); exit; }
try {
    $query=db()->prepare('SELECT active FROM systems WHERE id=?'); $query->execute([$id]); $system=$query->fetch();
    if (!$system) { http_response_code(404); exit; }
    if (!$system['active']) {
        if (empty($_SESSION['admin_id'])) { http_response_code(404); exit; }
        require_admin();
    }
    $filename=uploaded_icon_filename(settings(),$id);
    $path=$filename ? uploaded_icon_directory().'/'.$filename : '';
    if (!$path || !is_file($path)) { http_response_code(404); exit; }
    session_write_close();
    header('Content-Type: image/png');
    $etag='"'.hash_file('sha256',$path).'"'; header('ETag: '.$etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')===$etag) { http_response_code(304); exit; }
    header('Content-Length: '.filesize($path)); readfile($path);
} catch(Throwable $error) { error_log((string)$error); http_response_code(503); }
