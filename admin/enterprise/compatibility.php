<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('dashboard.view');
$tools=[
 'business-day'=>['İş Günü','../business-day.php','business_day.view'],
 'tables'=>['Salon ve Masalar','../?page=tables','tables.manage'],
 'qrx'=>['QR Experience Studio','../qr-experience/','catalog.manage'],
 'printer-center'=>['Yazıcı Merkezi','../printer-center.php','maintenance.manage'],
 'print-queue'=>['Yazdırma Kuyruğu','../print-queue.php','maintenance.manage'],
 'reports'=>['Rapor Merkezi','../?page=reports','reports.view'],
 'backup'=>['Yedekleme Merkezi','../backup.php','maintenance.manage'],
 'staff'=>['Personel Merkezi','../?page=staff','staff.manage'],
 'permissions'=>['Roller ve Yetkiler','../permissions.php','permissions.manage'],
 'modules'=>['Modül Merkezi','../module-center.php','modules.manage'],
 'brand'=>['Marka Merkezi','../brand-center.php','maintenance.manage'],
 'system'=>['Sistem Merkezi','../system-center.php','maintenance.manage'],
 'maintenance'=>['Bakım Merkezi','../?page=maintenance','maintenance.manage'],
 'security'=>['Güvenlik ve Kayıtlar','../?page=security','security.manage'],
];
$key=(string)($_GET['tool']??'');
if(!isset($tools[$key])){$key='system';}
[$label,$src,$permission]=$tools[$key];
require_permission($permission);
$query=$_GET;unset($query['tool']);if($query){$src.=(str_contains($src,'?')?'&':'?').http_build_query($query);}
$pageTitle=$label;$currentPage=$key;
require __DIR__.'/_header.php';
?>
<section class="cc-compat-page" aria-label="<?=ent_e($label)?>">
 <div class="cc-compat-frame-wrap">
  <div class="cc-compat-loading" data-compat-loading><span></span><b><?=ent_e($label)?> yükleniyor…</b></div>
  <iframe class="cc-compat-frame" data-compat-frame src="<?=ent_e($src)?>" title="<?=ent_e($label)?>"></iframe>
 </div>
</section>
<?php require __DIR__.'/_footer.php'; ?>
