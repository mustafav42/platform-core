<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$done='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=['admin/enterprise/bootstrap.php','admin/enterprise/media-picker.php'];
  $backup=$root.'/storage/backups/v30.3.6-'.date('Ymd-His');
  foreach($files as $rel){$src=$root.'/'.$rel;if(is_file($src)){@mkdir($backup.'/'.dirname($rel),0755,true);if(!copy($src,$backup.'/'.$rel))throw new RuntimeException('Yedeklenemedi: '.$rel);}}
  foreach(['admin/enterprise/bootstrap.php','admin/enterprise/media-picker.php','admin/enterprise/image-studio.php','admin/enterprise/assets/image-studio.css','admin/enterprise/assets/image-studio.js'] as $rel){$src=__DIR__.'/payload/'.$rel;$dst=$root.'/'.$rel;@mkdir(dirname($dst),0755,true);if(!copy($src,$dst))throw new RuntimeException('Kopyalanamadı: '.$rel);}
  require $root.'/admin/enterprise/bootstrap.php';ent_media_upgrade();
  if(function_exists('opcache_reset'))@opcache_reset();
  $done='CherryHouse Image Studio başarıyla kuruldu.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>v30.3.6 Image Studio</title><style>body{font-family:Inter,Arial;background:#111;color:#eee;max-width:760px;margin:50px auto;padding:20px}.card{background:#1b1b1d;border:1px solid #333;border-radius:18px;padding:24px}button{background:#a92443;color:#fff;border:0;border-radius:11px;padding:13px 18px;font-weight:800}.ok{background:#174d35;padding:12px;border-radius:10px}.err{background:#642134;padding:12px;border-radius:10px}</style></head><body><div class="card"><h1>CherryHouse v30.3.6</h1><h2>Image Studio</h2><?php if($done):?><p class="ok"><?=$done?></p><p><a style="color:#fff" href="../../admin/enterprise/media-picker.php">Ortam Kütüphanesini aç</a></p><?php elseif($error):?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?><form method="post"><button>Image Studio’yu Kur</button></form></div></body></html>
