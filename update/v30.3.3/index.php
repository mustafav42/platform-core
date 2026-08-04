<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$ok=[];$errors=[];
$files=[
'admin/media-library.php','admin/assets/ch-media-library.js','admin/assets/ch-media-library.css','admin/assets/ch-media-library-picker.css',
'admin/catalog.php','admin/enterprise/bootstrap.php','admin/enterprise/media.php','admin/enterprise/products.php','admin/enterprise/categories.php',
'admin/enterprise/_header.php','admin/enterprise/_footer.php','admin/qr-experience/index.php'
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $backup=$root.'/storage/backups/v30.3.3-'.date('Ymd-His');
  if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
  foreach($files as $rel){$src=$source.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
   if(is_file($dst)){$bf=$backup.'/'.$rel;if(!is_dir(dirname($bf))&&!mkdir(dirname($bf),0775,true)&&!is_dir(dirname($bf)))throw new RuntimeException('Yedek alt klasörü oluşturulamadı.');if(!copy($dst,$bf))throw new RuntimeException('Dosya yedeklenemedi: '.$rel);}
   if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('Hedef klasör oluşturulamadı.');if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);$ok[]=$rel;
  }
  $ht=$root.'/.user.ini';$ini="upload_max_filesize=32M\npost_max_size=40M\nmax_file_uploads=50\n";@file_put_contents($ht,$ini);
  if(function_exists('opcache_reset'))@opcache_reset();
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.3</title><style>body{font-family:Inter,Arial,sans-serif;background:#f4f5f8;color:#171a22;padding:30px}.box{max-width:850px;margin:auto;background:#fff;border:1px solid #e2e5ec;border-radius:20px;padding:28px;box-shadow:0 18px 60px #19202a12}button,a{display:inline-block;border:0;border-radius:11px;padding:12px 16px;background:#201719;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.ok{background:#eaf8f1;color:#237553;padding:14px;border-radius:11px}.err{background:#fff0f0;color:#a02d39;padding:14px;border-radius:11px}.note{background:#f4f1ff;color:#4b3c89;padding:14px;border-radius:11px;margin:16px 0}li{margin:6px 0}</style></head><body><div class="box"><h1>v30.3.3 Universal Media Library</h1><p>WordPress mantığında çalışan ortak Ortam Kütüphanesi ve medya seçiciyi ürün, kategori, Menü Merkezi, QR Studio ve Enterprise ekranlarına bağlar.</p><div class="note">Açılan ortak pencerede mevcut görsel seçilebilir veya sayfadan ayrılmadan yeni görsel yüklenebilir. Görsel yükleme sınırı 32 MB'dir.</div><?php foreach($errors as $e):?><div class="err"><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?><?php if($ok):?><div class="ok"><b>Güncelleme başarılı.</b><ul><?php foreach($ok as $f):?><li><?=htmlspecialchars($f,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><p><a href="../../admin/menu-center.php">Menü Merkezini Aç</a> <a href="../../admin/qr-experience/">QR Studio’yu Aç</a></p><?php else:?><form method="post"><button type="submit">Universal Media Library’yi Kur</button></form><?php endif;?></div></body></html>
