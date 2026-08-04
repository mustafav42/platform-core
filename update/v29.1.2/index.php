<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../../admin/');
if(function_exists('require_permission')) require_permission('maintenance.manage');

$pdo=db();$messages=[];$error='';
try{
    $pdo->beginTransaction();
    $deleted=(int)$pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'qrx_draft_%'");
    $write=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $revision=date('YmdHis').'-'.bin2hex(random_bytes(4));
    $write->execute(['qrx_publish_revision',$revision]);
    $write->execute(['qrx_last_published_at',date('Y-m-d H:i:s')]);
    $write->execute(['qr_menu_enabled','1']);
    $write->execute(['app_version','29.1.2']);
    if(function_exists('db_table_exists') && db_table_exists($pdo,'app_migrations')){
        $q=$pdo->prepare("INSERT IGNORE INTO app_migrations(migration_key,version,description,executed_at) VALUES('20260729_2912_qrx_color_cache_fix','29.1.2','QR Experience renk yayını tek canlı kaynağa bağlandı ve asset cache busting etkinleştirildi.',NOW())");
        $q->execute();
    }
    $pdo->commit();
    if(class_exists('QrExperience') && method_exists('QrExperience','clearCache')) QrExperience::clearCache();
    if(function_exists('opcache_reset')) @opcache_reset();
    if(function_exists('audit_log')) audit_log('system_update','CherryHouse v29.1.2 QR renk ve önbellek düzeltmesi kuruldu.',['deleted_drafts'=>$deleted,'revision'=>$revision]);
    $messages=[
        'Eski taslak ayarları tamamen temizlendi: '.$deleted.' kayıt.',
        'QR menü yalnızca canlı ayarları okuyacak şekilde düzeltildi.',
        'Renk ve tema dosyalarına yayın revizyonu eklendi.',
        'HTML, CSS ve JavaScript önbelleği yayın sonrası otomatik kırılıyor.',
        'Kaydetme işlemi artık veritabanına geri okuyarak doğrulanıyor.',
        'Yeni yayın revizyonu: '.$revision
    ];
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $error=$e->getMessage();
    if(function_exists('app_log')) app_log($e,['update'=>'29.1.2']);
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.1.2 Güncelleme</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}.box{max-width:820px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px;margin:10px 0}.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#8b1e2d;color:#fff;text-decoration:none;border-radius:10px}</style></head><body><div class="box"><h1>CherryHouse v29.1.2 LTS</h1><h2>QR Experience Renk Yayını Düzeltmesi</h2><?php if($error):?><div class="err"><?=e($error)?></div><?php else:foreach($messages as $m):?><div class="ok">✓ <?=e($m)?></div><?php endforeach;?><a href="../../admin/qr-experience/?tab=theme">Theme Studio’yu Aç</a><a href="../../?qrx_check=<?=e($revision)?>" target="_blank">Canlı Menüyü Aç</a><?php endif;?></div></body></html>
