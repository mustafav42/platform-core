<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$source=__DIR__.'/files';
$ok=[];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=['index.php','admin/qr-experience/index.php','admin/qr-experience/assets/studio.js'];
  $backup=$root.'/storage/backups/v30.3.1-'.date('Ymd-His');
  if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
  foreach($files as $rel){
   $src=$source.'/'.$rel;$dst=$root.'/'.$rel;
   if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
   if(is_file($dst)){
    $backupFile=$backup.'/'.$rel;
    if(!is_dir(dirname($backupFile))&&!mkdir(dirname($backupFile),0775,true)&&!is_dir(dirname($backupFile)))throw new RuntimeException('Yedek alt klasörü oluşturulamadı: '.$rel);
    if(!copy($dst,$backupFile))throw new RuntimeException('Dosya yedeklenemedi: '.$rel);
   }
   if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('Hedef klasör oluşturulamadı: '.$rel);
   if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);
   $ok[]=$rel;
  }
  if(function_exists('opcache_reset'))@opcache_reset();
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.1</title><style>body{font-family:Arial,sans-serif;background:#f4f5f8;color:#171a22;padding:30px}.box{max-width:780px;margin:auto;background:#fff;border:1px solid #e2e5ec;border-radius:18px;padding:26px;box-shadow:0 18px 60px #19202a12}button,a{display:inline-block;border:0;border-radius:11px;padding:12px 16px;background:#171b25;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.ok{background:#eaf8f1;color:#237553;padding:14px;border-radius:11px}.err{background:#fff0f0;color:#a02d39;padding:14px;border-radius:11px}.note{background:#f4f1ff;color:#4b3c89;padding:14px;border-radius:11px;margin:16px 0}li{margin:6px 0}</style></head><body><div class="box"><h1>v30.3.1 QR Menü Başlık Düzenleyici</h1><p>QR menüde kategori listesinin üstünde yer alan küçük başlık, ana başlık ve açıklama metnini QR Experience Studio’dan yönetilebilir hâle getirir.</p><div class="note">Mevcut tema, kategori sırası, ürünler ve diğer QR ayarları korunur.</div><?php foreach($errors as $e):?><div class="err"><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?><?php if($ok):?><div class="ok"><b>Güncelleme başarılı.</b><ul><?php foreach($ok as $f):?><li><?=htmlspecialchars($f,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><p><a href="../../admin/qr-experience/?tab=menu">QR Studio’yu Aç</a></p><?php else:?><form method="post"><button type="submit">Güncellemeyi Uygula</button></form><?php endif;?></div></body></html>
