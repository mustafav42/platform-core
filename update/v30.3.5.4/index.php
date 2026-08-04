<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$payload = $root . '/payload';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $files = [
            'index.php',
            'app/assets/qrx-premium-menu.js',
            'app/assets/qrx-detail-image-v30354.css',
            'app/release.php',
        ];
        $backup = $root . '/storage/backups/v30.3.5.4-' . date('Ymd-His');
        foreach ($files as $relative) {
            $source = $payload . '/' . $relative;
            $target = $root . '/' . $relative;
            if (!is_file($source)) throw new RuntimeException('Paket dosyası eksik: ' . $relative);
            if (is_file($target)) {
                $backupFile = $backup . '/' . $relative;
                if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true) && !is_dir(dirname($backupFile))) {
                    throw new RuntimeException('Yedek klasörü oluşturulamadı.');
                }
                if (!copy($target, $backupFile)) throw new RuntimeException('Yedek alınamadı: ' . $relative);
            }
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Hedef klasör oluşturulamadı.');
            }
            if (!copy($source, $target)) throw new RuntimeException('Dosya güncellenemedi: ' . $relative);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $message = 'Mobil ürün detay görseli düzeltildi. Yeni ve önbellekten bağımsız stil dosyası etkinleştirildi.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.5.4</title><style>body{font-family:system-ui;background:#f5f1eb;color:#211d1a;display:grid;place-items:center;min-height:100vh;margin:0}.box{width:min(620px,calc(100% - 32px));background:#fff;border:1px solid #ddd3ca;border-radius:22px;padding:30px;box-shadow:0 20px 60px #0001}button{border:0;border-radius:12px;background:#8f263a;color:#fff;padding:13px 18px;font-weight:800}.ok{padding:14px;background:#eaf7ef;color:#237448;border-radius:12px}.err{padding:14px;background:#fff0ee;color:#a22f29;border-radius:12px}code{background:#f3eee8;padding:2px 6px;border-radius:6px}</style></head><body><div class="box"><h1>v30.3.5.4</h1><p>Mobil ürün detayındaki yakınlaştırılmış görseli kesin olarak düzeltir. Ayrı CSS dosyası kullanıldığı için eski telefon önbelleği bu kuralı ezemez.</p><?php if($message):?><p class="ok"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><p><a href="../../" target="_blank">QR menüyü aç</a></p><?php elseif($error):?><p class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif;?><form method="post"><button type="submit">Düzeltmeyi Uygula</button></form></div></body></html>
