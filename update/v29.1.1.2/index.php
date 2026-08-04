<?php
declare(strict_types=1);
$bootstrapFile=dirname(__DIR__,2).'/app/bootstrap.php';
if(function_exists('opcache_invalidate')) @opcache_invalidate($bootstrapFile,true);
clearstatcache(true,$bootstrapFile);
require_once $bootstrapFile;

if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../../admin/');
if(function_exists('require_permission')) require_permission('maintenance.manage');

$error='';
try{
    $pdo=db();
    if(function_exists('db_table_exists') && db_table_exists($pdo,'app_migrations')){
        $q=$pdo->prepare("INSERT IGNORE INTO app_migrations(migration_key,version,description,executed_at) VALUES('20260729_29112_bootstrap_emergency_repair','29.1.1.2','Bootstrap autoloader ve OPcache onarımı.',NOW())");
        $q->execute();
    }
    $q=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('app_version','29.1.1.2') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $q->execute();
}catch(Throwable $e){$error=$e->getMessage();}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CherryHouse v29.1.1.2</title><style>
body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}.box{max-width:760px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px}.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#8b1e2d;color:#fff;text-decoration:none;border-radius:10px}</style>
</head><body><div class="box"><h1>CherryHouse v29.1.1.2</h1><h2>PHP 8 Bootstrap Onarımı</h2>
<?php if($error):?><div class="err"><?=e($error)?></div><?php else:?><div class="ok">✓ Çekirdek onarımı doğrulandı ve sürüm kaydı tamamlandı.</div><a href="../v29.1.1/">v29.1.1 Güncellemesini Çalıştır</a><a href="../../admin/qr-experience/">QR Experience’ı Aç</a><?php endif;?>
</div></body></html>
