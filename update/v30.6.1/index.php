<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if (empty($_SESSION['cashier_id']) && empty($_SESSION['admin_id'])) { http_response_code(403); exit('Yetkisiz erişim'); }
$root=dirname(__DIR__,2); $payload=__DIR__.'/payload'; $done=false; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();
  $files=['cashier/index.php','app/assets/cashier-payment-v61.css','app/assets/cashier-payment-v61.js'];
  $backup=$root.'/storage/backups/v30.6.1-'.date('Ymd-His');
  foreach($files as $rel){$src=$payload.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dst)){@mkdir(dirname($backup.'/'.$rel),0775,true);copy($dst,$backup.'/'.$rel);}@mkdir(dirname($dst),0775,true);if(!copy($src,$dst))throw new RuntimeException('Kopyalanamadı: '.$rel);}
  if(function_exists('opcache_reset'))@opcache_reset(); $done=true;
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Workspace 2.1</title><style>body{font-family:Inter,system-ui;background:#f4f6f9;color:#172033;margin:0;display:grid;place-items:center;min-height:100vh}.card{width:min(680px,90vw);background:#fff;border:1px solid #e2e7ef;border-radius:22px;padding:30px;box-shadow:0 20px 60px #10203a14}button,a{display:inline-flex;padding:13px 18px;border:0;border-radius:12px;background:#f0641d;color:#fff;font-weight:800;text-decoration:none}.ok{color:#16834a}.err{color:#c73743}</style></head><body><div class="card"><h1>Payment Workspace 2.1</h1><?php if($done):?><p class="ok">Gerçek ödeme ekranı yeniden tasarlandı.</p><a href="../../../cashier/">Kasiyer ekranını aç</a><?php elseif($error):?><p class="err"><?=htmlspecialchars($error)?></p><?php else:?><p>Bu güncelleme doğrudan <code>ch-pay-screen</code> ödeme ekranını değiştirir.</p><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><button>Gerçek Tasarımı Uygula</button></form><?php endif;?></div></body></html>
