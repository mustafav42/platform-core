<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
if (!class_exists('BusinessDayService', false)) {
    $serviceFile = __DIR__.'/../app/Core/BusinessDayService.php';
    if (is_file($serviceFile)) require_once $serviceFile;
}
if (!class_exists('BusinessDayService', false)) {
    throw new RuntimeException('BusinessDayService dosyası yüklenemedi. Güncelleme paketi eksik yüklenmiş olabilir.');
}
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../admin/');
require_permission('maintenance.manage');$pdo=db();$messages=[];$error='';
try{
 BusinessDayService::install($pdo);
 $openDay=$pdo->query("SELECT id FROM business_days WHERE status IN ('open','closing') ORDER BY id DESC LIMIT 1")->fetchColumn();
 $legacyOpen=(int)$pdo->query("SELECT COUNT(*) FROM table_sessions WHERE status='open'")->fetchColumn();
 if(!$openDay && $legacyOpen>0){
  $opened=(string)$pdo->query("SELECT MIN(opened_at) FROM table_sessions WHERE status='open'")->fetchColumn();
  $next=(int)$pdo->query('SELECT COALESCE(MAX(business_no),0)+1 FROM business_days')->fetchColumn();
  $q=$pdo->prepare("INSERT INTO business_days(business_no,business_date,status,opened_at,opened_by_type,opened_by_name,opening_note) VALUES(?,?,'open',?,'system','Sistem','Güncelleme sırasında açık operasyonlardan otomatik devralındı.')");$q->execute([$next,date('Y-m-d',strtotime($opened)),$opened]);$openDay=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO business_day_events(business_day_id,event_type,actor_type,actor_name,description) VALUES(?,'legacy_recovery','system','Sistem','Mevcut açık operasyon yeni İş Günü motoruna devralındı.')")->execute([$openDay]);
 }
 if($openDay){foreach(['table_sessions','orders','payments','cash_sessions','cashier_payment_groups'] as $t){if(db_table_exists($pdo,$t)&&db_column_exists($pdo,$t,'business_day_id')){$where=$t==='orders'?"session_id IN (SELECT id FROM table_sessions WHERE status='open')":($t==='payments'?"table_session_id IN (SELECT id FROM table_sessions WHERE status='open')":($t==='cashier_payment_groups'?"table_session_id IN (SELECT id FROM table_sessions WHERE status='open')":"status='open'"));$pdo->exec("UPDATE `$t` SET business_day_id=".(int)$openDay." WHERE business_day_id IS NULL AND $where");}}}
 if(db_table_exists($pdo,'role_permissions')){
  $ins=$pdo->prepare("INSERT INTO role_permissions(role_key,permission_key,is_allowed) VALUES(?,?,?) ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed)");
  foreach(['manager','cashier'] as $role){foreach(['business_day.view','business_day.open','business_day.close','business_day.archive'] as $perm)$ins->execute([$role,$perm,1]);}
  foreach(['manager','cashier'] as $role)$ins->execute([$role,'business_day.force_close',$role==='manager'?1:0]);
 }
 if(db_table_exists($pdo,'app_migrations')){$q=$pdo->prepare("INSERT IGNORE INTO app_migrations(migration_key,version,description,executed_at) VALUES('20260729_280_business_day_engine','28.0.1','İş Günü motoru, zorunlu satış kilidi, gün başı/gün sonu ve iş günü veri bağları.',NOW())");$q->execute();}
 $q=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('app_version','28.0.1') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$q->execute();
 audit_log('system_update','CherryHouse v28.0.1 Business Day Core Loader kuruldu.');
 $messages=['İş Günü çekirdek tabloları oluşturuldu.','Satış tablolarına business_day_id bağlantıları eklendi.','Mevcut açık operasyonlar güvenli biçimde devralındı.','Gün Başı / Gün Sonu yetkileri Personel Merkezi altyapısına eklendi.','Kasiyer ve garson satış kilidi etkinleştirildi.'];
}catch(Throwable $e){$error=$e->getMessage();app_log($e,['update'=>'28.0.1']);}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v28.0 Güncelleme</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}.box{max-width:820px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px;margin:10px 0}.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#e11d2e;color:#fff;text-decoration:none;border-radius:10px}</style></head><body><div class="box"><h1>CherryHouse v28.0.1 · Business Day Engine</h1><?php if($error):?><div class="err"><?=e($error)?></div><?php else:foreach($messages as $m):?><div class="ok">✓ <?=e($m)?></div><?php endforeach;?><a href="../admin/business-day.php">İş Günü Merkezi</a><a href="../admin/">Yönetim Paneli</a><?php endif;?></div></body></html>