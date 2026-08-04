<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$payload = __DIR__ . '/payload';
$success = '';
$error = '';
$details = [];

$files = [
    'app/ControlCenter/ControlCenterRegistry.php',
    'app/bootstrap.php',
    'admin/index.php',
    'admin/module-center.php',
    'admin/enterprise/_header.php',
    'admin/enterprise/_footer.php',
    'admin/enterprise/index.php',
    'admin/enterprise/feature-inventory.php',
    'admin/enterprise/assets/control-center.css',
];

function cc_copy_file(string $root, string $payload, string $file, string $backup): void
{
    $source = $payload . '/' . $file;
    $target = $root . '/' . $file;
    if (!is_file($source)) {
        throw new RuntimeException('Paket dosyası eksik: ' . $file);
    }
    if (is_file($target)) {
        $backupFile = $backup . '/' . $file;
        if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0775, true) && !is_dir(dirname($backupFile))) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı: ' . dirname($file));
        }
        if (!copy($target, $backupFile)) {
            throw new RuntimeException('Mevcut dosya yedeklenemedi: ' . $file);
        }
    }
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Hedef klasör oluşturulamadı: ' . dirname($file));
    }
    if (!copy($source, $target)) {
        throw new RuntimeException('Dosya kopyalanamadı: ' . $file);
    }
    if (!is_file($target) || hash_file('sha256', $source) !== hash_file('sha256', $target)) {
        throw new RuntimeException('Dosya doğrulaması başarısız: ' . $file);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_file($root . '/storage/installed.lock')) {
            throw new RuntimeException('CherryHouse kurulumu doğrulanamadı. Bu paket yalnızca kurulu sistemde çalışır.');
        }

        $backup = $root . '/storage/backups/v30.7.0.1-' . date('Ymd-His');
        foreach ($files as $file) {
            cc_copy_file($root, $payload, $file, $backup);
            $details[] = 'Kopyalandı: ' . $file;
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // Bootstrap is loaded only after all related files are safely copied.
        require_once $root . '/app/bootstrap.php';

        try {
            $pdo = db();
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
                ->execute(['control_center.version', '1.0.1']);
            $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
                ->execute(['app_version', '30.7.0.1']);
            if (function_exists('audit_log')) {
                audit_log('control_center_installed', 'CherryHouse Control Center Sprint 1 kuruldu.', ['version' => '30.7.0.1']);
            }
            $details[] = 'Control Center ayar kaydı tamamlandı.';
        } catch (Throwable $databaseError) {
            // Files are already installed; show the DB warning without rolling them back.
            $details[] = 'Uyarı: Ayar kaydı yapılamadı: ' . $databaseError->getMessage();
        }

        $success = 'Control Center Sprint 1 dosyaları yüklendi ve doğrulandı.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CherryHouse v30.7.0.1</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:28px;font-family:Inter,system-ui,-apple-system,sans-serif;background:#f5f3fb;color:#2e2947}.card{width:min(820px,100%);background:#fff;border:1px solid #e8e4f1;border-radius:26px;padding:32px;box-shadow:0 24px 70px rgba(55,45,95,.12)}.eyebrow{font-size:12px;letter-spacing:.16em;font-weight:800;color:#7b6de4}.lead{color:#706b80;line-height:1.65}.notice{padding:14px 16px;border-radius:14px;margin:18px 0}.ok{background:#eaf9f2;color:#137451}.err{background:#fff0f1;color:#a92b3c}.detail{background:#f8f7fc;border:1px solid #ece9f4;border-radius:16px;padding:14px;max-height:240px;overflow:auto}.detail div{padding:6px 4px;font-size:13px;color:#625d72}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}button,a{border:0;border-radius:14px;padding:13px 18px;font-weight:800;text-decoration:none;cursor:pointer}button{background:#7668e6;color:#fff}a{background:#f0eef9;color:#554a9e}h1{margin:.35rem 0 0;font-size:clamp(28px,4vw,42px)}
</style>
</head>
<body><main class="card">
<div class="eyebrow">CHERRYHOUSE CONTROL CENTER</div>
<h1>v30.7.0.1 · Sprint 1 Onarımı</h1>
<p class="lead">Önceki kurulum sayfasındaki PIN yönlendirmesini kaldırır. Control Center dosyalarını paket içinden kopyalar, mevcut dosyaları yedekler ve kopyaları SHA-256 ile doğrular.</p>
<?php if ($success !== ''): ?><div class="notice ok">✓ <?=htmlspecialchars($success, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err">✕ <?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php if ($details): ?><div class="detail"><?php foreach ($details as $detail): ?><div>• <?=htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')?></div><?php endforeach; ?></div><?php endif; ?>
<div class="actions">
<?php if ($success === ''): ?><form method="post"><button type="submit">Control Center Sprint 1’i Kur</button></form><?php else: ?><a href="../../admin/">Yönetim girişine git</a><a href="../../admin/enterprise/">Control Center’ı aç</a><?php endif; ?>
</div>
</main></body></html>
