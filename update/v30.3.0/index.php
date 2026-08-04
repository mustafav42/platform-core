<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $backup=$root.'/storage/backups/v30.3.0-'.date('Ymd-His');
  $files=[
   'admin/enterprise/bootstrap.php','admin/enterprise/_header.php','admin/enterprise/_footer.php','admin/enterprise/index.php','admin/enterprise/audit.php',
   'admin/enterprise/api/search.php','admin/enterprise/api/dashboard.php','admin/enterprise/api/notifications.php',
   'admin/enterprise/assets/admin-ui.css','admin/enterprise/assets/admin-ui.js','admin/enterprise/assets/enterprise.css','admin/enterprise/assets/enterprise.js','admin/enterprise/assets/media-picker.css','admin/enterprise/assets/media-picker.js',
   'app/release.php'
  ];
  foreach($files as $rel){$src=$source.'/'.$rel;$dest=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);if(is_file($dest)){@mkdir($backup.'/'.dirname($rel),0775,true);if(!copy($dest,$backup.'/'.$rel))throw new RuntimeException('Yedek alınamadı: '.$rel);}@mkdir(dirname($dest),0775,true);if(!copy($src,$dest))throw new RuntimeException('Dosya kurulamadı: '.$rel);}
  require_once $root.'/admin/enterprise/bootstrap.php';
  if(function_exists('ent_platform_install')) ent_platform_install();
  if(function_exists('ent_audit')) ent_audit('system.updated','CherryHouse v30.3.0 Aurora kuruldu',['module'=>'Update Center','entity_type'=>'release','entity_id'=>'30.3.0']);
  if(function_exists('opcache_reset'))@opcache_reset();
  $message='CherryHouse v30.3.0 LTS “Aurora” başarıyla kuruldu.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.0 Aurora</title><style>body{font-family:Inter,system-ui;background:#f3f1ed;color:#211d1a;margin:0;display:grid;place-items:center;min-height:100vh}.card{width:min(680px,calc(100% - 32px));background:#fff;border:1px solid #e2ddd6;border-radius:24px;padding:30px;box-shadow:0 18px 55px #2a201818}small{letter-spacing:.15em;font-weight:900;color:#92263a}h1{margin:8px 0}.muted{color:#746b64;line-height:1.65}.features{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:20px 0}.features span{padding:10px 12px;border-radius:11px;background:#f8f6f2;font-weight:700;font-size:13px}.ok,.err{padding:13px;border-radius:12px;margin:15px 0}.ok{background:#eaf7ee;color:#166534}.err{background:#fff0f0;color:#b42318}button,a{display:inline-block;border:0;border-radius:12px;padding:13px 17px;font-weight:800;text-decoration:none;cursor:pointer}button{background:#92263a;color:#fff}a{background:#211d1a;color:#fff;margin-left:8px}@media(max-width:560px){.features{grid-template-columns:1fr}}</style></head><body><main class="card"><small>ENTERPRISE EXPERIENCE</small><h1>CherryHouse v30.3.0 LTS “Aurora”</h1><p class="muted">Mevcut CherryHouse mimarisini koruyarak yönetim deneyimini canlı, aranabilir ve izlenebilir hâle getirir.</p><div class="features"><span>Global canlı arama</span><span>Canlı Dashboard</span><span>Bildirim Merkezi</span><span>Audit Log 2.0 altyapısı</span><span>Son kullanılan sayfalar</span><span>Quick Actions</span></div><?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><a href="../../admin/enterprise/">Aurora’yı Aç</a><?php elseif($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><button>Güncellemeyi Uygula</button></form></main></body></html>
