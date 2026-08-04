<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$ok=false;$error='';$done=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $files=['admin/enterprise/bootstrap.php','admin/enterprise/media.php','admin/enterprise/api/media-center.php','admin/enterprise/assets/media-center-v2.css','admin/enterprise/assets/media-center-v2.js'];
  $backup=$root.'/storage/backups/v30.4.1-'.date('Ymd-His');
  foreach($files as $rel){$src=__DIR__.'/payload/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dst)){@mkdir(dirname($backup.'/'.$rel),0755,true);copy($dst,$backup.'/'.$rel);}@mkdir(dirname($dst),0755,true);if(!copy($src,$dst))throw new RuntimeException('Dosya kopyalanamadı: '.$rel);$done[]=$rel;}
  if(function_exists('opcache_reset'))@opcache_reset();$ok=true;
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.4.1</title><style>body{font-family:system-ui;background:#07101d;color:#eef2ff;display:grid;place-items:center;min-height:100vh;margin:0}.box{width:min(720px,92vw);background:#111c2d;border:1px solid #2b3a50;border-radius:24px;padding:30px}h1{margin:0 0 10px}p{color:#aab7ca;line-height:1.6}button,a{display:inline-block;border:0;border-radius:12px;padding:13px 18px;background:#f59e0b;color:#241600;font-weight:800;text-decoration:none}.ok{padding:14px;background:#16a34a22;border-radius:12px}.err{padding:14px;background:#ef444422;border-radius:12px}</style></head><body><main class="box"><small>CHERRYHOUSE UPDATE CENTER</small><h1>v30.4.1 · Medya Merkezi 2.0</h1><p>Eski medya ekranını kaldırır; otomatik yükleme, dosya tarama, modern kart kütüphanesi ve canlı ayrıntı panelini kurar.</p><?php if($ok):?><div class="ok">Güncelleme başarıyla tamamlandı.</div><p><?=count($done)?> dosya kuruldu ve eski sürümler yedeklendi.</p><a href="../../admin/enterprise/media.php">Medya Merkezini Aç</a><?php elseif($error):?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php else:?><form method="post"><button>Medya Merkezi 2.0’ı Kur</button></form><?php endif;?></main></body></html>
