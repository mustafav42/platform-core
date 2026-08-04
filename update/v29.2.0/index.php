<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$ok=[];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=['admin/qr-experience/bootstrap.php','admin/qr-experience/index.php','admin/qr-experience/assets/studio.js','admin/qr-experience/assets/studio.css','app/qr/QrExperience.php','admin/qr-experience-v2/index.php','admin/qr-experience-v2/designer.php'];
  $backup=$root.'/storage/backups/v29.2.0-'.date('Ymd-His');@mkdir($backup,0775,true);
  foreach($files as $rel){$src=$source.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dst)){@mkdir(dirname($backup.'/'.$rel),0775,true);@copy($dst,$backup.'/'.$rel);}@mkdir(dirname($dst),0775,true);if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);$ok[]=$rel;}
  if(function_exists('opcache_reset'))@opcache_reset();
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.2.0</title><style>body{font-family:Arial;background:#f4f5f8;color:#171a22;padding:30px}.box{max-width:760px;margin:auto;background:#fff;border:1px solid #e2e5ec;border-radius:18px;padding:25px}button,a{display:inline-block;border:0;border-radius:10px;padding:12px 16px;background:#171b25;color:#fff;text-decoration:none;font-weight:700}.ok{background:#eaf8f1;color:#237553;padding:12px;border-radius:10px}.err{background:#fff0f0;color:#a02d39;padding:12px;border-radius:10px}li{margin:6px 0}</style></head><body><div class="box"><h1>v29.2.0 QR Studio Rebuild</h1><p>Eski taslak/yayın mimarisini kaldırır ve QR Experience Studio’yu tek canlı ayar kaynağıyla yeniden kurar.</p><?php foreach($errors as $e):?><div class="err"><?=htmlspecialchars($e)?></div><?php endforeach;?><?php if($ok):?><div class="ok"><b>Güncelleme başarılı.</b><ul><?php foreach($ok as $f):?><li><?=htmlspecialchars($f)?></li><?php endforeach;?></ul></div><p><a href="../../admin/qr-experience/">Yeni Studio’yu Aç</a></p><?php else:?><form method="post"><button>Güncellemeyi Uygula</button></form><?php endif;?></div></body></html>
