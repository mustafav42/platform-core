<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$ok=[];$errors=[];$warm=[];
$files=['index.php','app/media/QrImage.php','app/media/image.php'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $backup=$root.'/storage/backups/v30.3.4-'.date('Ymd-His');
  if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
  foreach($files as $rel){$src=$source.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
   if(is_file($dst)){$bf=$backup.'/'.$rel;if(!is_dir(dirname($bf))&&!mkdir(dirname($bf),0775,true)&&!is_dir(dirname($bf)))throw new RuntimeException('Yedek alt klasörü oluşturulamadı.');if(!copy($dst,$bf))throw new RuntimeException('Dosya yedeklenemedi: '.$rel);}
   if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('Hedef klasör oluşturulamadı.');if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);$ok[]=$rel;
  }
  $cache=$root.'/storage/cache/qr-images';if(!is_dir($cache))@mkdir($cache,0775,true);
  require_once $root.'/app/media/QrImage.php';
  // Mevcut ürün görsellerini önceden hazırla; ilk ziyaretçi beklemesin.
  if(is_file($root.'/app/bootstrap.php')){
   require_once $root.'/app/bootstrap.php';
   try{$rows=db()->query("SELECT image_path FROM products WHERE is_active=1 AND image_path IS NOT NULL AND image_path<>''")->fetchAll(PDO::FETCH_COLUMN);foreach($rows as $rel){$file=qr_image_resolve((string)$rel);if(!$file)continue;foreach([[480,78],[800,80],[1280,84]] as [$w,$q]){qr_image_generate($file,$w,$q);}$warm[]=(string)$rel;}}catch(Throwable $ignore){}
   try{foreach(['qr_hero_image'=>[1600,82],'qr_logo_image'=>[360,84]] as $key=>$spec){$rel=(string)setting($key,'');$file=qr_image_resolve($rel);if($file)qr_image_generate($file,$spec[0],$spec[1]);}}catch(Throwable $ignore){}
  }
  if(function_exists('opcache_reset'))@opcache_reset();
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.4</title><style>body{font-family:Inter,Arial,sans-serif;background:#f4f5f8;color:#171a22;padding:30px}.box{max-width:850px;margin:auto;background:#fff;border:1px solid #e2e5ec;border-radius:20px;padding:28px;box-shadow:0 18px 60px #19202a12}button,a{display:inline-block;border:0;border-radius:11px;padding:12px 16px;background:#201719;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.ok{background:#eaf8f1;color:#237553;padding:14px;border-radius:11px}.err{background:#fff0f0;color:#a02d39;padding:14px;border-radius:11px}.note{background:#fff7e8;color:#805d18;padding:14px;border-radius:11px;margin:16px 0}</style></head><body><div class="box"><h1>v30.3.4 QR Image Performance</h1><p>QR menü görsellerini cihaz boyutuna uygun, önbellekli WebP kopyalarıyla hızlandırır.</p><div class="note">Orijinal görseller korunur. Ürün kartları küçük sürümü, ürün detayları ise yalnızca açıldığında yüksek çözünürlüklü sürümü indirir.</div><?php foreach($errors as $e):?><div class="err"><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?><?php if($ok):?><div class="ok"><b>Güncelleme başarılı.</b><br><?=count($warm)?> ürün görseli önbelleğe hazırlandı.</div><p><a href="../../">QR Menüyü Aç</a> <a href="../../admin/qr-experience/">QR Studio’yu Aç</a></p><?php else:?><form method="post"><button type="submit">Görsel optimizasyonunu uygula</button></form><?php endif;?></div></body></html>
