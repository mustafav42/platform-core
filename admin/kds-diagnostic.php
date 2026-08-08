<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

if(empty($_SESSION['admin_id'])) redirect('./');
require_permission('modules.manage');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$value = null;
try{
    $q=db()->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
    $q->execute(['module.kds.enabled']);
    $raw=$q->fetchColumn();
    $value=$raw===false?null:(string)$raw;
}catch(Throwable $e){
    $value='DB ERROR: '.$e->getMessage();
}

$kitchenFile=BASE_PATH.'/kitchen/index.php';
$lines=is_file($kitchenFile)?file($kitchenFile, FILE_IGNORE_NEW_LINES):[];
$hasRc=false;
foreach($lines as $line){
    if(str_contains($line,'KDS RC1.2')){$hasRc=true;break;}
}

?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><title>KDS Tanılama</title>
<style>body{font-family:system-ui;background:#f6f4f2;color:#211d1a;padding:30px}.card{max-width:760px;margin:auto;background:#fff;border:1px solid #e7e1dc;border-radius:18px;padding:24px}code{background:#f3f4f6;padding:3px 7px;border-radius:6px}.ok{color:#218a61}.bad{color:#c94f5d}</style></head>
<body><div class="card">
<h1>KDS Tanılama</h1>
<p>Persist edilen modül değeri: <code><?=e(var_export($value,true))?></code></p>
<p>ModuleManager sonucu: <code><?=module_enabled('kds',false)?'AÇIK':'KAPALI'?></code></p>
<p>Canlı kitchen dosyası: <code><?=e($kitchenFile)?></code></p>
<p>RC1.2 dosyası aktif mi? <b class="<?=$hasRc?'ok':'bad'?>"><?=$hasRc?'EVET':'HAYIR'?></b></p>
<p>Dosya SHA1: <code><?=is_file($kitchenFile)?e(sha1_file($kitchenFile)):'dosya yok'?></code></p>
<p><a href="enterprise/">Control Center</a></p>
</div></body></html>
