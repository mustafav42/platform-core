<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=[
   'staff/index.php',
   'app/assets/pos-waiter-ui-v2.1.css',
   'app/assets/pos-waiter-ui-v2.1.js',
  ];
  $backup=$root.'/storage/backups/v30.5.3-'.date('Ymd-His');
  foreach($files as $rel){
   $src=__DIR__.'/payload/'.$rel;
   $dst=$root.'/'.$rel;
   if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
   if(is_file($dst)){
    $bak=$backup.'/'.$rel;
    if(!is_dir(dirname($bak))&&!mkdir(dirname($bak),0775,true)&&!is_dir(dirname($bak)))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
    if(!copy($dst,$bak))throw new RuntimeException('Dosya yedeklenemedi: '.$rel);
   }
   if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('Hedef klasör oluşturulamadı.');
   if(!copy($src,$dst))throw new RuntimeException('Dosya kopyalanamadı: '.$rel);
  }
  if(function_exists('opcache_reset'))@opcache_reset();
  $message='POS UI v2.1 başarıyla uygulandı.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.5.3</title><style>body{font-family:Inter,system-ui;background:#f5f7fa;color:#172033;margin:0;display:grid;place-items:center;min-height:100vh}.card{width:min(680px,92vw);background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:32px;box-shadow:0 22px 70px #0f172a18}h1{margin:0 0 8px}.muted{color:#667085;line-height:1.6}.ok,.err{padding:13px 15px;border-radius:12px;margin:16px 0}.ok{background:#ecfdf3;color:#067647}.err{background:#fef3f2;color:#b42318}button,a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border:0;border-radius:12px;font-weight:800;text-decoration:none;cursor:pointer}button{background:#ea6a2a;color:#fff}a{background:#17263e;color:#fff;margin-left:8px}</style></head><body><section class="card"><small>CherryHouse POS 5.0</small><h1>v30.5.3 — Garson Workspace UI v2.1</h1><p class="muted">Tam ekran yerleşimi, kategori taşmaları ve anlık ürün ekleme tepkimesini düzeltir.</p><?php if($message):?><div class="ok"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><a href="../../staff/">Garson ekranını aç</a><?php elseif($error):?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post"><button type="submit">Güncellemeyi Uygula</button></form></section></body></html>
