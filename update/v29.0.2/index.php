<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$message='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $payload=dirname(__DIR__,2).'/payload';
  $files=['index.php','app/assets/qrx-premium-menu.css'];
  $backup=$root.'/storage/backups/v29.0.2-'.date('Ymd-His');
  try{
    if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
    foreach($files as $rel){
      $src=$payload.'/'.$rel;$dst=$root.'/'.$rel;
      if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
      if(is_file($dst)){
        $bak=$backup.'/'.$rel;
        if(!is_dir(dirname($bak)))mkdir(dirname($bak),0775,true);
        if(!copy($dst,$bak))throw new RuntimeException('Yedeklenemedi: '.$rel);
      }
      if(!is_dir(dirname($dst)))mkdir(dirname($dst),0775,true);
      if(!copy($src,$dst))throw new RuntimeException('Güncellenemedi: '.$rel);
    }
    if(function_exists('opcache_reset'))@opcache_reset();
    $ok=true;$message='v29.0.2 başarıyla uygulandı. index.php ve CSS dosyası doğrudan güncellendi.';
  }catch(Throwable $e){$message=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.0.2</title><style>body{font-family:system-ui;background:#f5f1e9;color:#171717;margin:0;padding:30px}.card{max-width:680px;margin:auto;background:#fff;padding:28px;border-radius:18px;box-shadow:0 18px 50px #0001}button{border:0;background:#8b1e2d;color:#fff;padding:14px 20px;border-radius:12px;font-weight:800;cursor:pointer}.msg{margin:16px 0;padding:13px;border-radius:10px;background:<?= $ok?'#eaf8ef':'#fff2f2' ?>}code{background:#f2eee8;padding:2px 6px;border-radius:6px}</style></head><body><div class="card"><h1>v29.0.2 Kesin Mobil Liste Düzeltmesi</h1><p>Bu güncelleme arama/değiştirme yapmaz; gerçek <code>index.php</code> ve <code>qrx-premium-menu.css</code> dosyalarını doğrudan yükler.</p><?php if($message):?><div class="msg"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if(!$ok):?><form method="post"><button type="submit">Düzeltmeyi Uygula</button></form><?php else:?><p><a href="../../" target="_blank">QR Menüyü Aç</a></p><?php endif;?></div></body></html>
