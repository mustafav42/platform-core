<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$target = $root . '/admin/catalog.php';
$backupDir = $root . '/storage/backups/v30.3.2.3-' . date('Ymd-His');
$messages = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_file($target)) {
            throw new RuntimeException('admin/catalog.php bulunamadı.');
        }
        if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }
        if (!copy($target, $backupDir . '/catalog.php')) {
            throw new RuntimeException('catalog.php yedeklenemedi.');
        }

        $content = file_get_contents($target);
        if ($content === false) {
            throw new RuntimeException('catalog.php okunamadı.');
        }

        $original = $content;
        $replacements = [
            "8*1024*1024" => "32*1024*1024",
            "8 * 1024 * 1024" => "32 * 1024 * 1024",
            "en fazla 8 MB" => "en fazla 32 MB",
            "En fazla 8 MB" => "En fazla 32 MB",
        ];
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Daha önce farklı biçimde yazılmış olabilecek kontrolü de güvenli şekilde yakala.
        $content = preg_replace(
            '/>\s*8\s*\*\s*1024\s*\*\s*1024/',
            '>32*1024*1024',
            $content
        ) ?? $content;

        if ($content === $original) {
            if (str_contains($content, '32*1024*1024') || str_contains($content, '32 MB')) {
                $messages[] = 'Menü Merkezi ürün yükleme sınırı zaten 32 MB.';
            } else {
                throw new RuntimeException('8 MB sınırı catalog.php içinde bulunamadı; dosya yapısı beklenenden farklı.');
            }
        } else {
            if (file_put_contents($target, $content, LOCK_EX) === false) {
                throw new RuntimeException('catalog.php güncellenemedi.');
            }
            $messages[] = 'Menü Merkezi ürün görseli sınırı 32 MB olarak güncellendi.';
            $messages[] = 'Arayüzdeki “En fazla 8 MB” metni 32 MB olarak değiştirildi.';
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $messages[] = 'PHP önbelleği temizlendi.';
        $messages[] = 'Yedek: ' . str_replace($root . '/', '', $backupDir);
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
<title>CherryHouse v30.3.2.3 Hotfix</title>
<style>
body{margin:0;background:#f5f3ef;color:#211d1a;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{max-width:760px;margin:60px auto;padding:24px}.card{background:#fff;border:1px solid #e3ddd5;border-radius:22px;padding:28px;box-shadow:0 18px 50px #2c21170d}h1{margin:0 0 8px;font-size:28px}p{color:#776e66;line-height:1.6}.ok,.err{padding:14px 16px;border-radius:13px;margin:12px 0}.ok{background:#eaf8f1;color:#176846}.err{background:#fff0f0;color:#a12d2d}button,a.btn{border:0;border-radius:13px;background:#92263a;color:#fff;padding:13px 18px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex}.meta{font-size:13px;background:#f8f6f2;border-radius:13px;padding:14px;margin:18px 0;color:#665e57}code{background:#f1eee9;padding:2px 6px;border-radius:6px}</style>
</head>
<body><div class="wrap"><div class="card">
<h1>v30.3.2.3 Menü Merkezi Upload Hotfix</h1>
<p><code>admin/menu-center.php</code> ekranının yönlendirdiği <code>admin/catalog.php</code> içindeki bağımsız 8 MB kontrolünü 32 MB’ye çıkarır.</p>
<div class="meta">Bu işlem ürün, kategori, sıralama veya görsel kayıtlarını değiştirmez. Sadece ürün görseli yükleme sınırını ve açıklama metnini günceller.</div>
<?php if ($error): ?><div class="err"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php foreach ($messages as $message): ?><div class="ok"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8')?></div><?php endforeach; ?>
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $error): ?>
<form method="post"><button type="submit">Menü Merkezi sınırını 32 MB yap</button></form>
<?php else: ?>
<a class="btn" href="../../admin/menu-center.php">Menü Merkezi’ni Aç</a>
<?php endif; ?>
</div></div></body></html>
