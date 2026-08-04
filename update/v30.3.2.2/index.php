<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$messages = [];
$errors = [];
$done = false;

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function backup_file(string $root, string $relative, string $backupDir): void {
    $src = $root . '/' . $relative;
    if (!is_file($src)) return;
    $dst = $backupDir . '/' . $relative;
    if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0755, true) && !is_dir(dirname($dst))) {
        throw new RuntimeException('Yedek klasörü oluşturulamadı: ' . dirname($dst));
    }
    if (!copy($src, $dst)) throw new RuntimeException('Dosya yedeklenemedi: ' . $relative);
}
function replace_limit(string $content): array {
    $count = 0;
    $patterns = [
        '/8\s*\*\s*1024\s*\*\s*1024/' => '32 * 1024 * 1024',
        '/8\*1024\*1024/' => '32*1024*1024',
        '/en fazla 8 MB olabilir/u' => 'en fazla 32 MB olabilir',
    ];
    foreach ($patterns as $pattern => $replacement) {
        $new = preg_replace($pattern, $replacement, $content, -1, $n);
        if ($new === null) throw new RuntimeException('Dosya içeriği işlenemedi.');
        $content = $new;
        $count += $n;
    }
    return [$content, $count];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backupDir = $root . '/storage/backups/v30.3.2.2-' . date('Ymd-His');
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }

        $targets = ['admin/index.php', 'admin/catalog.php', 'admin/enterprise/bootstrap.php'];
        $changedTotal = 0;
        foreach ($targets as $relative) {
            $path = $root . '/' . $relative;
            if (!is_file($path)) {
                $messages[] = 'Atlandı, dosya bulunamadı: ' . $relative;
                continue;
            }
            backup_file($root, $relative, $backupDir);
            $content = file_get_contents($path);
            if ($content === false) throw new RuntimeException('Dosya okunamadı: ' . $relative);
            [$updated, $changes] = replace_limit($content);
            if ($changes > 0) {
                if (file_put_contents($path, $updated, LOCK_EX) === false) {
                    throw new RuntimeException('Dosya yazılamadı: ' . $relative);
                }
                $messages[] = $relative . ' güncellendi (' . $changes . ' değişiklik).';
                $changedTotal += $changes;
            } else {
                $messages[] = $relative . ' zaten güncel veya farklı yapıda; değişiklik gerekmedi.';
            }
        }

        $userIni = $root . '/.user.ini';
        if (is_file($userIni)) backup_file($root, '.user.ini', $backupDir);
        $existing = is_file($userIni) ? (string)file_get_contents($userIni) : '';
        $lines = preg_split('/\R/', $existing) ?: [];
        $settings = [
            'upload_max_filesize' => '32M',
            'post_max_size' => '40M',
            'memory_limit' => '256M',
            'max_file_uploads' => '50',
            'max_execution_time' => '120',
        ];
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            $skip = false;
            foreach (array_keys($settings) as $key) {
                if (preg_match('/^' . preg_quote($key, '/') . '\s*=/', $trim)) { $skip = true; break; }
            }
            if (!$skip && $trim !== '') $kept[] = $line;
        }
        $kept[] = '';
        $kept[] = '; CherryHouse upload limits - v30.3.2.2';
        foreach ($settings as $key => $value) $kept[] = $key . ' = ' . $value;
        $iniContent = trim(implode(PHP_EOL, $kept)) . PHP_EOL;
        if (file_put_contents($userIni, $iniContent, LOCK_EX) === false) {
            throw new RuntimeException('.user.ini dosyası yazılamadı.');
        }
        $messages[] = 'Sunucu yükleme ayarları .user.ini üzerinden 32 MB olarak yapılandırıldı.';
        $messages[] = 'Yedek: ' . str_replace($root . '/', '', $backupDir);
        if (function_exists('opcache_reset')) @opcache_reset();
        $done = true;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$currentUpload = ini_get('upload_max_filesize') ?: 'bilinmiyor';
$currentPost = ini_get('post_max_size') ?: 'bilinmiyor';
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.2.2</title>
<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f2ec;color:#191714;margin:0;padding:32px}.card{max-width:760px;margin:auto;background:#fff;border:1px solid #ded8cf;border-radius:22px;padding:30px;box-shadow:0 20px 60px #20181014}h1{margin:0 0 8px}.muted{color:#726d66}.ok,.err,.info{padding:13px 15px;border-radius:12px;margin:10px 0}.ok{background:#eaf8ef;color:#17683b}.err{background:#fff0f0;color:#a22020}.info{background:#f2f0ff;color:#44319a}button{border:0;border-radius:12px;background:#191714;color:#fff;padding:14px 20px;font-weight:800;cursor:pointer;font-size:15px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}.meta div{background:#f7f5f1;padding:13px;border-radius:12px}code{background:#f3f1ec;padding:2px 6px;border-radius:6px}@media(max-width:600px){body{padding:14px}.card{padding:20px}.meta{grid-template-columns:1fr}}</style></head><body><div class="card">
<p class="muted">CherryHouse Hotfix</p><h1>v30.3.2.2 – 32 MB Görsel Yükleme</h1><p class="muted">Ürün ekranları ve Media Manager için uygulama ve PHP yükleme sınırlarını birlikte günceller.</p>
<div class="meta"><div><strong>Şu an upload_max_filesize</strong><br><?=h($currentUpload)?></div><div><strong>Şu an post_max_size</strong><br><?=h($currentPost)?></div></div>
<?php foreach($messages as $m):?><div class="ok">✓ <?=h($m)?></div><?php endforeach;?>
<?php foreach($errors as $m):?><div class="err">✕ <?=h($m)?></div><?php endforeach;?>
<?php if($done):?><div class="info">Paylaşımlı hostinglerde <code>.user.ini</code> değişikliklerinin etkinleşmesi 1–5 dakika sürebilir. Sonrasında ürün görseli yüklemeyi tekrar deneyin.</div><p><a href="../../admin/enterprise/products.php">Ürünlere git</a> · <a href="../../admin/enterprise/media.php">Media Manager’a git</a></p><?php else:?><form method="post"><button type="submit">32 MB sınırını uygula</button></form><?php endif;?>
</div></body></html>
