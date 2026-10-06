<?php
require __DIR__.'/app/bootstrap.php';require __DIR__.'/app/uploads.php';
if(empty($_SESSION['admin_id'])){http_response_code(404);exit;}
$account=require_account();$id=(int)($_GET['id'] ?? 0);
if($id!==(int)$account['id'] && ($account['role'] ?? 'admin')!=='admin'){http_response_code(403);exit;}
$stmt=db()->prepare('SELECT avatar_file FROM admins WHERE id=?');$stmt->execute([$id]);$file=$stmt->fetchColumn();
if(!is_string($file) || !preg_match('/^[a-f0-9]{32}\.png$/D',$file) || !is_file(uploaded_icon_directory().'/'.$file)){http_response_code(404);exit;}
header('Content-Type: image/png');header('Cache-Control: private, no-store');readfile(uploaded_icon_directory().'/'.$file);
