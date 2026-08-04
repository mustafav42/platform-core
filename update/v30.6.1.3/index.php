<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $payload=__DIR__.'/payload';
  $files=['cashier/index.php','app/assets/cashier-payment-v613.css'];
  $backup=$root.'/storage/backups/v30.6.1.3-'.date('Ymd-His');
  foreach($files as $file){
   $src=$payload.'/'.$file;$dst=$root.'/'.$file;
   if(!is_file($src)) throw new RuntimeException('Paket dosyası eksik: '.$file);
   if(is_file($dst)){@mkdir($backup.'/'.dirname($file),0775,true);copy($dst,$backup.'/'.$file);}
   @mkdir(dirname($dst),0775,true);
   if(!copy($src,$dst)) throw new RuntimeException('Kopyalanamadı: '.$file);
  }
  if(function_exists('opcache_reset')) @opcache_reset();
  $message='Payment Workspace eski CSS katmanlarından ayrıldı ve tek yerleşim dosyasına geçirildi.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.6.1.3</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;color:#172033;margin:0;display:grid;place-items:center;min-height:100vh}.card{width:min(680px,92vw);background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:30px;box-shadow:0 20px 60px #0f172a12}h1{margin:0 0 8px}.ok{background:#ecfdf3;color:#087a3d;padding:14px;border-radius:12px}.err{background:#fff1f2;color:#b42318;padding:14px;border-radius:12px}button{border:0;border-radius:12px;padding:14px 20px;background:#ef6425;color:#fff;font-weight:800;cursor:pointer}</style></head><body><main class="card"><h1>Payment Workspace 2.1.3</h1><p>Eski ödeme CSS zincirini kaldırır ve ekranı tek, çakışmasız yerleşim dosyasına bağlar.</p><?php if($message):?><p class="ok"><?=htmlspecialchars($message)?></p><?php endif;?><?php if($error):?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?><form method="post"><button>Tek Kaynaklı Yerleşimi Uygula</button></form></main></body></html>
