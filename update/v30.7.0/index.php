<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);require $root.'/app/bootstrap.php';
if(empty($_SESSION['admin_id'])) redirect('../../admin/');require_permission('maintenance.manage');
$ok='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{verify_csrf();
  $checks=['app/ControlCenter/ControlCenterRegistry.php','admin/enterprise/assets/control-center.css','admin/enterprise/feature-inventory.php','admin/enterprise/_header.php'];
  foreach($checks as $f)if(!is_file($root.'/'.$f))throw new RuntimeException('Eksik dosya: '.$f);
  db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['control_center.version','1.0.0']);
  db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['app.version','30.7.0']);
  audit_log('control_center_installed','CherryHouse Control Center Sprint 1 etkinleştirildi.',['version'=>'30.7.0']);
  if(function_exists('opcache_reset'))@opcache_reset();$ok='Control Center Sprint 1 başarıyla etkinleştirildi.';
 }catch(Throwable $e){$err=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Control Center v30.7.0</title><style>body{font-family:system-ui;background:#f7f7fc;color:#26223c;padding:36px}.box{max-width:760px;margin:auto;background:#fff;border:1px solid #ebe9f4;border-radius:24px;padding:30px;box-shadow:0 20px 50px rgba(58,49,105,.1)}button{border:0;border-radius:14px;padding:14px 20px;background:#7367e8;color:#fff;font-weight:800}.ok{background:#eafaf4;color:#168864;padding:12px;border-radius:12px}.err{background:#fff0f0;color:#c43d3d;padding:12px;border-radius:12px}li{margin:8px 0;color:#666178}</style></head><body><div class="box"><small>CHERRYHOUSE CONTROL CENTER</small><h1>v30.7.0 · Sprint 1</h1><p>Tek yönetim kabuğu, dinamik sidebar, modül görünürlüğü ve özellik envanteri.</p><?php if($ok):?><p class="ok"><?=e($ok)?></p><p><a href="../../admin/enterprise/">Control Center’ı aç →</a></p><?php endif;?><?php if($err):?><p class="err"><?=e($err)?></p><?php endif;?><ul><li>Aktif modüllere göre dinamik menü</li><li>Tasarım 3 tabanlı modern yönetim kabuğu</li><li>Klasik panel bağlantısının kaldırılması</li><li>Feature Parity envanteri</li><li>Eski dashboard girişinin Control Center’a yönlendirilmesi</li></ul><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button>Control Center Sprint 1’i Etkinleştir</button></form></div></body></html>
