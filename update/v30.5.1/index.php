<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
$serviceFile = $base . '/app/Core/TableLifecycleService.php';
if (!is_file($serviceFile)) {
    http_response_code(500);
    exit('TableLifecycleService.php bulunamadı. ZIP içeriğini CherryHouse ana dizinine yükleyin.');
}
require_once $serviceFile;
require_once $base . '/app/bootstrap.php';

$done = false;
$error = '';
$cleaned = 0;
$backupDir = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!class_exists('TableLifecycleService', false)) {
            throw new RuntimeException('TableLifecycleService sınıfı yüklenemedi.');
        }
        $pdo = db();
        $backupDir = BASE_PATH . '/storage/backups/v30.5.1-' . date('Ymd-His');
        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }
        foreach (['app/bootstrap.php','app/Core/TableLifecycleService.php','staff/index.php','cashier/index.php','api/pos-v5-state.php'] as $relative) {
            $source = BASE_PATH . '/' . $relative;
            if (!is_file($source)) continue;
            $dest = $backupDir . '/' . $relative;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
            @copy($source, $dest);
        }

        $service = new TableLifecycleService($pdo);
        $cleaned = $service->cleanupEmptyOpenSessions();

        if (db_table_exists($pdo, 'settings')) {
            $q = $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('app_version','30.5.1') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $q->execute();
        }
        audit_log('pos_core_service_repaired', 'Table Lifecycle servis yükleme sorunu düzeltildi.', ['cleaned_empty_sessions' => $cleaned]);
        if (function_exists('opcache_reset')) @opcache_reset();
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (function_exists('app_log')) app_log($e, ['update' => '30.5.1']);
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.5.1</title><style>body{margin:0;background:#f4f6f8;font-family:Inter,system-ui;color:#172033}.wrap{max-width:760px;margin:60px auto;padding:24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:32px;box-shadow:0 20px 60px #0f172a10}h1{margin:0 0 8px}p{color:#667085;line-height:1.65}.items{display:grid;gap:10px;margin:24px 0}.item{padding:13px 15px;background:#f8fafc;border-radius:12px}.ok{padding:16px;background:#ecfdf3;color:#067647;border-radius:14px}.err{padding:16px;background:#fef3f2;color:#b42318;border-radius:14px}button,a.btn{display:inline-block;border:0;border-radius:13px;padding:14px 18px;background:#e11d2e;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}</style></head><body><main class="wrap"><section class="card"><small>CHERRYHOUSE POS CORE</small><h1>v30.5.1 — Service Repair</h1><p>Table Lifecycle servisinin güvenli ve sınıf tabanlı yüklenmesini sağlar.</p><div class="items"><div class="item">Yardımcı fonksiyon bağımlılığı kaldırıldı.</div><div class="item">Servis sınıfı güncelleme başlamadan önce yüklenir.</div><div class="item">Boş masalar gerçek sipariş olmadan dolu sayılmaz.</div><div class="item">Garson, kasiyer ve canlı masa API’si aynı kuralı kullanır.</div></div><?php if($error):?><div class="err"><?=e($error)?></div><?php elseif($done):?><div class="ok">Onarım tamamlandı. <?=$cleaned?> boş oturum temizlendi.</div><p><a class="btn" href="../../staff/">Garson ekranını test et</a></p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button>POS Core Onarımını Uygula</button></form><?php endif;?></section></main></body></html>
