<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$payload=__DIR__.'/payload';$ok=false;$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=['cashier/index.php','app/assets/cashier-payment-v60.css','app/assets/cashier-payment-v60.js'];
  $backup=$root.'/storage/backups/v30.6.0.1-'.date('Ymd-His');@mkdir($backup,0775,true);
  foreach($files as $rel){$src=$payload.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dst)){@mkdir(dirname($backup.'/'.$rel),0775,true);if(!copy($dst,$backup.'/'.$rel))throw new RuntimeException('Yedek alınamadı: '.$rel);}@mkdir(dirname($dst),0775,true);if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);}
  if(function_exists('opcache_reset'))@opcache_reset();$ok=true;$msg='Payment Workspace 2.0 dosyaları gerçek ekrana uygulandı.';
 }catch(Throwable $e){$msg=$e->getMessage();}
}
?><!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.6.0.1</title><style>body{font-family:system-ui;background:#eef2f7;color:#102038;margin:0;padding:30px}.box{max-width:760px;margin:auto;background:#fff;padding:30px;border-radius:22px;box-shadow:0 20px 70px #0f172a18}button{border:0;border-radius:12px;padding:15px 20px;background:#f06b25;color:#fff;font-weight:850;font-size:16px}.ok{background:#dcfce7;color:#166534;padding:14px;border-radius:12px}.err{background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px}code{background:#f1f5f9;padding:2px 6px;border-radius:6px}</style><div class="box"><h1>CherryHouse v30.6.0.1</h1><h2>Payment Workspace Gerçek Entegrasyon Onarımı</h2><?php if($msg):?><div class="<?=$ok?'ok':'err'?>"><?=htmlspecialchars($msg,ENT_QUOTES,'UTF-8')?></div><?php endif;?><p>Önceki ZIP içinde <code>payload</code> dosyaları eksik kaldığı için kurulum ekranı açılmış fakat ödeme arayüzü kopyalanmamıştı. Bu paket gerçek ödeme ekranı, CSS ve JavaScript dosyalarını içerir.</p><?php if(!$ok):?><form method="post"><button>Gerçek Entegrasyonu Uygula</button></form><?php else:?><p><a href="../../cashier/">Kasiyer ekranını aç</a></p><?php endif;?></div></html>
