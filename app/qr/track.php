<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try{
 $type=(string)($_GET['type']??'session');$productId=$type==='product'?(int)($_GET['product_id']??0):null;$session=session_id()?:bin2hex(random_bytes(8));$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);$lang=substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??''),0,20);$ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'').'|'.(string)setting('app_key','qr'));
 $stmt=db()->prepare('INSERT INTO qr_menu_views(session_key,event_type,product_id,user_agent,language_code,ip_hash,viewed_at) VALUES(?,?,?,?,?,?,NOW())');$stmt->execute([$session,$type,$productId?:null,$ua,$lang,$ip]);echo '{"ok":true}';
}catch(Throwable $e){http_response_code(204);}
