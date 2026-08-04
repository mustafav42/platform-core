<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$status = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payload = __DIR__ . '/payload';
        $files = [
            'app/assets/pos-v4.js',
            'app/assets/pos-waiter-ui-v2.1.js',
            'staff/index.php',
        ];
        $backup = $root . '/storage/backups/v30.5.3.2-' . date('Ymd-His');
        foreach ($files as $relative) {
            $src = $payload . '/' . $relative;
            $dst = $root . '/' . $relative;
            if (!is_file($src)) throw new RuntimeException('Paket dosyası eksik: ' . $relative);
            if (is_file($dst)) {
                $bak = $backup . '/' . $relative;
                if (!is_dir(dirname($bak)) && !mkdir(dirname($bak), 0775, true) && !is_dir(dirname($bak))) throw new RuntimeException('Yedek klasörü oluşturulamadı.');
                if (!copy($dst, $bak)) throw new RuntimeException('Yedeklenemedi: ' . $relative);
            }
            if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0775, true) && !is_dir(dirname($dst))) throw new RuntimeException('Hedef klasör oluşturulamadı.');
            if (!copy($src, $dst)) throw new RuntimeException('Kopyalanamadı: ' . $relative);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $status = 'Eski ürün formu kilidi kaldırıldı. Ürün kartları yeniden kullanılabilir.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.5.3.2</title><style>body{font-family:system-ui;background:#f5f2ed;color:#182238;margin:0;padding:40px}.box{max-width:720px;margin:auto;background:#fff;padding:32px;border-radius:22px;box-shadow:0 18px 55px #16213a18}button{border:0;border-radius:13px;background:#eb6828;color:#fff;padding:14px 20px;font-weight:800;font-size:16px}.ok{background:#e8f7ef;color:#176b40;padding:14px;border-radius:12px}.err{background:#fdecec;color:#9d2424;padding:14px;border-radius:12px}</style></head><body><div class="box"><h1>POS Ürün Kartı Kilidi Onarımı</h1><p>Garson ürün kartlarını eski gecikmeli gönderim sisteminden ayırır ve kalıcı solukluk/kilit durumunu kaldırır.</p><?php if($status):?><p class="ok"><?=htmlspecialchars($status)?></p><p><a href="../../staff/">Garson ekranını aç</a></p><?php elseif($error):?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?><form method="post"><button type="submit">Onarımı Uygula</button></form></div></body></html>
