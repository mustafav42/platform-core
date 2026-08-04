<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $backup=$root.'/storage/backups/v30.2.0-'.date('Ymd-His');
  $files=['app/assets/pos-v5-beta.css','app/assets/pos-v5-beta.js','api/pos-v5-state.php','cashier/index.php','staff/index.php'];
  foreach($files as $rel){$src=$source.'/'.$rel;$dest=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dest)){@mkdir($backup.'/'.dirname($rel),0775,true);if(!copy($dest,$backup.'/'.$rel))throw new RuntimeException('Yedek alınamadı: '.$rel);}@mkdir(dirname($dest),0775,true);if(!copy($src,$dest))throw new RuntimeException('Dosya kurulamadı: '.$rel);}
  if(function_exists('opcache_reset'))@opcache_reset();
  $message='CherryHouse v30.2.0 Beta başarıyla kuruldu.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.2.0</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;color:#172033;margin:0;display:grid;place-items:center;min-height:100vh}.card{width:min(620px,calc(100% - 32px));background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:28px;box-shadow:0 18px 55px #0f172a14}h1{margin:0 0 8px}.muted{color:#667085;line-height:1.6}.ok,.err{padding:13px;border-radius:12px;margin:15px 0}.ok{background:#eaf7ee;color:#166534}.err{background:#fff0f0;color:#b42318}button,a{display:inline-block;border:0;border-radius:12px;padding:13px 17px;font-weight:800;text-decoration:none;cursor:pointer}button{background:#df6b2f;color:#fff}a{background:#172338;color:#fff;margin-left:8px}</style></head><body><main class="card"><small>POS 5.0 BETA</small><h1>CherryHouse v30.2.0</h1><p class="muted">Tek çalışma ekranı, canlı masa toplamları, güvenli ödeme geri bildirimi ve yoğun servis sırasında çift işlem koruması.</p><?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><a href="../../cashier/">Kasiyeri Aç</a><a href="../../staff/">Garsonu Aç</a><?php elseif($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><button>Güncellemeyi Uygula</button></form></main></body></html>
