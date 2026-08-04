<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$payload = __DIR__ . '/payload';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $source = $payload . '/app/ControlCenter/ControlCenterRegistry.php';
        $target = $root . '/app/ControlCenter/ControlCenterRegistry.php';

        if (!is_file($source)) {
            throw new RuntimeException('Onarım dosyası pakette bulunamadı.');
        }
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            throw new RuntimeException('Hedef klasör oluşturulamadı.');
        }

        if (is_file($target)) {
            $backupDir = $root . '/storage/update-backups/v30.8.2.1-' . date('Ymd-His');
            if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
                throw new RuntimeException('Yedek klasörü oluşturulamadı.');
            }
            if (!copy($target, $backupDir . '/ControlCenterRegistry.php')) {
                throw new RuntimeException('Mevcut registry yedeklenemedi.');
            }
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Onarım dosyası kopyalanamadı.');
        }
        if (hash_file('sha256', $source) !== hash_file('sha256', $target)) {
            throw new RuntimeException('Dosya doğrulaması başarısız oldu.');
        }

        $message = 'Control Center Registry onarıldı. Roller ve Yetkiler menü kaydı artık doğru parametrelerle çalışıyor.';
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
<title>CherryHouse v30.8.2.1 Onarım</title>
<style>
body{margin:0;background:#f6f4ff;color:#27233a;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:760px;margin:60px auto;padding:24px}.card{background:#fff;border:1px solid #e8e3f6;border-radius:22px;padding:32px;box-shadow:0 18px 50px rgba(65,49,110,.10)}h1{margin:0 0 12px;font-size:28px}.muted{color:#706986;line-height:1.6}.alert{padding:14px 16px;border-radius:14px;margin:18px 0}.ok{background:#ecfdf3;color:#166534}.err{background:#fff1f2;color:#9f1239}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:13px;padding:13px 20px;background:#6d5bd0;color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.btn.secondary{background:#f0edfb;color:#51468a;margin-left:8px}code{background:#f4f1fb;padding:2px 7px;border-radius:7px}
</style>
</head>
<body><div class="wrap"><div class="card">
<h1>CherryHouse v30.8.2.1</h1>
<p class="muted">Personel Workspace migration sonrasında oluşan Control Center menü kayıt hatasını onarır.</p>
<?php if ($message !== ''): ?><div class="alert ok"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert err"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php if ($message === ''): ?><form method="post"><button class="btn" type="submit">Registry Onarımını Uygula</button></form><?php else: ?><a class="btn" href="../../admin/enterprise/personnel.php">Personel Workspace’i Aç</a><a class="btn secondary" href="../../admin/enterprise/permissions.php">Roller ve Yetkileri Aç</a><?php endif; ?>
</div></div></body></html>
