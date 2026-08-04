<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$message = '';
$ok = false;
$files = [
    'admin/enterprise/categories.php',
    'admin/enterprise/products.php',
    'admin/enterprise/reorder.php',
    'admin/enterprise/assets/sortable-manager.js',
    'admin/enterprise/assets/sortable-manager.css',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = $root . '/payload';
    $backup = $root . '/storage/backups/v29.1.0-' . date('Ymd-His');
    try {
        if (!is_dir($backup) && !mkdir($backup, 0775, true) && !is_dir($backup)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }
        foreach ($files as $relative) {
            $source = $payload . '/' . $relative;
            $destination = $root . '/' . $relative;
            if (!is_file($source)) {
                throw new RuntimeException('Paket dosyası eksik: ' . $relative);
            }
            if (is_file($destination)) {
                $backupFile = $backup . '/' . $relative;
                if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true) && !is_dir(dirname($backupFile))) {
                    throw new RuntimeException('Yedek alt klasörü oluşturulamadı: ' . dirname($relative));
                }
                if (!copy($destination, $backupFile)) {
                    throw new RuntimeException('Dosya yedeklenemedi: ' . $relative);
                }
            }
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true) && !is_dir(dirname($destination))) {
                throw new RuntimeException('Hedef klasör oluşturulamadı: ' . dirname($relative));
            }
            if (!copy($source, $destination)) {
                throw new RuntimeException('Dosya güncellenemedi: ' . $relative);
            }
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $ok = true;
        $message = 'v29.1.0 başarıyla uygulandı. Kategori ve ürün sürükle-bırak sıralaması aktif edildi.';
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.1.0</title><style>body{font-family:Inter,system-ui;background:#f5f1e9;color:#171717;margin:0;padding:30px}.card{max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 18px 50px #0001}button,a{display:inline-block;border:0;background:#8b1e2d;color:#fff;padding:14px 20px;border-radius:12px;font-weight:800;cursor:pointer;text-decoration:none}.msg{margin:16px 0;padding:13px;border-radius:10px;background:<?= $ok?'#eaf8ef':'#fff2f2' ?>}.features{line-height:1.8;color:#4b5563}code{background:#f2eee8;padding:2px 6px;border-radius:6px}</style></head><body><div class="card"><h1>CherryHouse v29.1.0</h1><h2>Drag & Drop Manager</h2><div class="features">✓ Kategori sürükle-bırak sıralaması<br>✓ Kategori içi ürün sürükle-bırak sıralaması<br>✓ Mobil ve dokunmatik ekran desteği<br>✓ AJAX otomatik kayıt ve transaction güvenliği<br>✓ QR menüde anında yeni sıralama</div><?php if($message):?><div class="msg"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if(!$ok):?><form method="post"><button type="submit">Güncellemeyi Uygula</button></form><?php else:?><a href="../../admin/enterprise/categories.php">Kategori Yönetimi</a> <a href="../../admin/enterprise/products.php">Ürün Yönetimi</a><?php endif;?></div></body></html>
