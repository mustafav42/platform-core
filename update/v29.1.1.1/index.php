<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../../admin/');
if(function_exists('require_permission')) require_permission('maintenance.manage');
$error='';
try {
    $pdo=db();
    $q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $q->execute(['app_version','29.1.1.1']);
    if (db_table_exists($pdo,'app_migrations')) {
        $m=$pdo->prepare("INSERT IGNORE INTO app_migrations(migration_key,version,description,executed_at) VALUES('20260729_29111_autoloader_null_hotfix','29.1.1.1','Autoloader str_contains null hatası giderildi.',NOW())");
        $m->execute();
    }
    audit_log('system_update','CherryHouse v29.1.1.1 autoloader hotfix kuruldu.');
} catch(Throwable $e) { $error=$e->getMessage(); if(function_exists('app_log')) app_log($e,['update'=>'29.1.1.1']); }
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.1.1.1 Hotfix</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}.box{max-width:760px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px}.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#8b1e2d;color:#fff;text-decoration:none;border-radius:10px}</style></head><body><div class="box"><h1>CherryHouse v29.1.1.1</h1><h2>PHP 8 Autoloader Hotfix</h2><?php if($error):?><div class="err"><?=e($error)?></div><?php else:?><div class="ok">✓ str_contains() null hatası giderildi.</div><a href="../../admin/qr-experience/">QR Experience’ı Aç</a><a href="../v29.1.1/">v29.1.1 Güncellemesini Yeniden Çalıştır</a><?php endif;?></div></body></html>
