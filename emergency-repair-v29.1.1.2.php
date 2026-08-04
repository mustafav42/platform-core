<?php
declare(strict_types=1);

$root = __DIR__;
$source = $root . '/repair-assets/bootstrap.fixed.php';
$target = $root . '/app/bootstrap.php';
$messages = [];
$error = '';

try {
    if (!is_file($source)) {
        throw new RuntimeException('Onarım kaynağı bulunamadı: repair-assets/bootstrap.fixed.php');
    }
    if (!is_dir(dirname($target))) {
        throw new RuntimeException('app klasörü bulunamadı.');
    }

    $contents = file_get_contents($source);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Onarım kaynağı okunamadı.');
    }

    $backupDir = $root . '/storage/backups/v29.1.1.2-' . date('Ymd-His');
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Yedek klasörü oluşturulamadı.');
    }
    if (is_file($target)) {
        copy($target, $backupDir . '/bootstrap.php');
    }

    $tmp = $target . '.repair-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Geçici çekirdek dosyası yazılamadı.');
    }
    if (!rename($tmp, $target)) {
        @unlink($tmp);
        throw new RuntimeException('Çekirdek dosyası değiştirilemedi.');
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    clearstatcache(true, $target);

    $check = file_get_contents($target);
    if (!is_string($check) || strpos($check, "preg_replace('/[^A-Za-z0-9_\\\\]/'") !== false) {
        throw new RuntimeException('Eski hatalı autoloader kodu hâlâ etkin görünüyor.');
    }

    $messages[] = 'app/bootstrap.php güvenli sürümle değiştirildi.';
    $messages[] = 'Eski dosya storage/backups altında yedeklendi.';
    $messages[] = 'PHP OPcache temizlendi.';
    $messages[] = 'Artık v29.1.1 güncellemesi çalıştırılabilir.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CherryHouse Acil Onarım v29.1.1.2</title>
<style>
body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}
.box{max-width:820px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}
.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px;margin:10px 0}
.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}
a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#8b1e2d;color:#fff;text-decoration:none;border-radius:10px}
small{display:block;margin-top:18px;color:#667085}
</style>
</head><body><div class="box">
<h1>CherryHouse v29.1.1.2</h1><h2>Bootstrap Acil Onarım</h2>
<?php if ($error): ?>
<div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div>
<?php else: foreach ($messages as $message): ?>
<div class="ok">✓ <?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div>
<?php endforeach; ?>
<a href="update/v29.1.1/">QR Experience Güncellemesini Çalıştır</a>
<a href="admin/qr-experience/">QR Experience’ı Aç</a>
<small>Onarım tamamlandıktan sonra güvenlik için emergency-repair-v29.1.1.2.php dosyasını sunucudan silebilirsin.</small>
<?php endif; ?>
</div></body></html>
