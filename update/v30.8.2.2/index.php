<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$payload = __DIR__ . '/payload';
$message = '';
$error = '';
$details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $source = $payload . '/app/ControlCenter/ControlCenterRegistry.php';
        $targets = [
            $root . '/app/ControlCenter/ControlCenterRegistry.php',
        ];

        $migrationPayload = $root . '/update/v30.8.2/payload/app/ControlCenter/ControlCenterRegistry.php';
        if (is_file($migrationPayload)) {
            $targets[] = $migrationPayload;
        }

        if (!is_file($source)) {
            throw new RuntimeException('Onarım kaynağı pakette bulunamadı.');
        }

        $sourceHash = hash_file('sha256', $source);
        $backupDir = $root . '/storage/update-backups/v30.8.2.2-' . date('Ymd-His');

        foreach ($targets as $target) {
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Hedef klasör oluşturulamadı: ' . dirname($target));
            }

            if (is_file($target)) {
                $relative = ltrim(str_replace($root, '', $target), '/\\');
                $backupPath = $backupDir . '/' . $relative;
                if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0775, true) && !is_dir(dirname($backupPath))) {
                    throw new RuntimeException('Yedek klasörü oluşturulamadı.');
                }
                if (!copy($target, $backupPath)) {
                    throw new RuntimeException('Mevcut dosya yedeklenemedi: ' . $relative);
                }
            }

            if (!copy($source, $target)) {
                throw new RuntimeException('Onarım dosyası kopyalanamadı: ' . $target);
            }

            clearstatcache(true, $target);
            $targetHash = hash_file('sha256', $target);
            if ($sourceHash !== $targetHash) {
                throw new RuntimeException('Hash doğrulaması başarısız: ' . $target);
            }

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target, true);
            }
            $details[] = basename($target) . ' · ' . substr($targetHash, 0, 16);
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $message = 'Registry onarımı uygulandı, koruyucu parametre kontrolü eklendi ve PHP önbelleği temizlendi.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CherryHouse v30.8.2.2 Registry Repair</title>
<style>
body{margin:0;background:#f6f4ff;color:#27233a;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:820px;margin:56px auto;padding:24px}.card{background:#fff;border:1px solid #e8e3f6;border-radius:22px;padding:32px;box-shadow:0 18px 50px rgba(65,49,110,.10)}h1{margin:0 0 10px;font-size:28px}.muted{color:#706986;line-height:1.65}.alert{padding:14px 16px;border-radius:14px;margin:18px 0}.ok{background:#ecfdf3;color:#166534}.err{background:#fff1f2;color:#9f1239}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:13px;padding:13px 20px;background:#6d5bd0;color:#fff;font-weight:750;cursor:pointer;text-decoration:none}.btn.secondary{background:#f0edfb;color:#51468a;margin-left:8px}ul{padding-left:20px;color:#5f5873;line-height:1.7}code{background:#f4f1fb;padding:2px 7px;border-radius:7px}
</style>
</head>
<body><div class="wrap"><div class="card">
<h1>CherryHouse v30.8.2.2</h1>
<p class="muted">Personel Workspace sonrası devam eden Control Center Registry hatasını kalıcı biçimde onarır. Bu kurulum ana registry dosyasını ve varsa v30.8.2 migration kaynağını birlikte düzeltir.</p>
<?php if ($message !== ''): ?>
<div class="alert ok"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8')?></div>
<?php if ($details): ?><ul><?php foreach ($details as $detail): ?><li><?=htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')?></li><?php endforeach; ?></ul><?php endif; ?>
<a class="btn" href="../../admin/enterprise/personnel.php">Personel Workspace’i Aç</a>
<a class="btn secondary" href="../../admin/enterprise/permissions.php">Roller ve Yetkileri Aç</a>
<?php else: ?>
<?php if ($error !== ''): ?><div class="alert err"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<form method="post"><button class="btn" type="submit">Kalıcı Registry Onarımını Uygula</button></form>
<?php endif; ?>
</div></div></body></html>
