<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$done = '';
$error = '';
$files = [
    'admin/enterprise/bootstrap.php',
    'admin/enterprise/media.php',
    'admin/enterprise/media-picker.php',
    'admin/enterprise/image-studio.php',
    'admin/enterprise/_header.php',
    'admin/enterprise/assets/image-studio.css',
    'admin/enterprise/assets/image-studio.js',
    'admin/index.php',
    'admin/dashboard-v2.php',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backup = $root . '/storage/backups/v30.3.6.1-' . date('Ymd-His');
        foreach ($files as $rel) {
            $current = $root . '/' . $rel;
            if (!is_file($current)) continue;
            $backupFile = $backup . '/' . $rel;
            if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
                throw new RuntimeException('Yedek klasörü oluşturulamadı: ' . $rel);
            }
            if (!copy($current, $backupFile)) throw new RuntimeException('Yedeklenemedi: ' . $rel);
        }
        foreach ($files as $rel) {
            $source = __DIR__ . '/payload/' . $rel;
            $target = $root . '/' . $rel;
            if (!is_file($source)) throw new RuntimeException('Paket dosyası eksik: ' . $rel);
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Hedef klasör oluşturulamadı: ' . $rel);
            }
            if (!copy($source, $target)) throw new RuntimeException('Kopyalanamadı: ' . $rel);
        }
        require $root . '/admin/enterprise/bootstrap.php';
        ent_media_upgrade();
        if (function_exists('opcache_reset')) @opcache_reset();
        $done = 'Medya Merkezi ve mevcut görsel düzenleme akışı başarıyla kuruldu.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.6.1</title><style>body{font-family:Inter,Arial,sans-serif;background:#101114;color:#f5f5f5;max-width:780px;margin:50px auto;padding:20px}.card{background:#1b1c20;border:1px solid #34363d;border-radius:20px;padding:26px}.muted{color:#a4a7b0}.ok,.err{padding:13px 15px;border-radius:12px}.ok{background:#154d36}.err{background:#67263a}button,.link{display:inline-block;background:#a92443;color:#fff;border:0;border-radius:11px;padding:13px 18px;font-weight:800;text-decoration:none;cursor:pointer}.link{background:#343741;margin-left:8px}</style></head><body><div class="card"><p class="muted">CherryHouse v30.3.6.1</p><h1>Medya Merkezi</h1><p>Mevcut görsellere tıklama, ayrıntı paneli, Image Studio bağlantısı ve ana menü entegrasyonu.</p><?php if($done):?><p class="ok"><?=htmlspecialchars($done,ENT_QUOTES,'UTF-8')?></p><p><a class="link" href="../../admin/enterprise/media.php">Medya Merkezini Aç</a></p><?php elseif($error):?><p class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif;?><form method="post"><button>Güncellemeyi Uygula</button></form></div></body></html>
