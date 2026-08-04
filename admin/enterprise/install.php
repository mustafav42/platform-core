<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * CherryHouse v7.0 LTS Phase 1
 * Admin stability installer.
 *
 * This installer only cleans previous Enterprise UI injections from admin/index.php
 * and places two normal sidebar links inside the existing navigation flow.
 */
function v7_admin_stability_apply(): array
{
    $file = BASE_PATH . '/admin/index.php';
    if (!is_file($file)) {
        throw new RuntimeException('admin/index.php bulunamadı.');
    }

    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('admin/index.php okunamadı.');
    }
    $original = $source;

    // Remove global bridge assets and login-page style injections.
    $patterns = [
        '~\s*<link\b[^>]*href=["\'][^"\']*enterprise/assets/admin-bridge\.css(?:\?[^"\']*)?["\'][^>]*>\s*~i',
        '~\s*<script\b[^>]*src=["\'][^"\']*enterprise/assets/admin-bridge\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>\s*~i',
        '~\s*<!-- CHERRYHOUSE_ENTERPRISE_STYLE -->\s*<style>.*?</style>\s*~is',
        '~\s*<!-- data-enterprise-admin-links -->\s*<a href="enterprise/" class="enterprise-admin-link">.*?</a>\s*<a href="enterprise/media\.php" class="enterprise-admin-link">.*?</a>~is',
        '~\s*<!-- CHERRYHOUSE_ENTERPRISE_NAV_START -->.*?<!-- CHERRYHOUSE_ENTERPRISE_NAV_END -->\s*~is',
        '~\s*<!-- CHERRYHOUSE_V7_ENTERPRISE_NAV_START -->.*?<!-- CHERRYHOUSE_V7_ENTERPRISE_NAV_END -->\s*~is',
    ];
    foreach ($patterns as $pattern) {
        $source = preg_replace($pattern, '', $source) ?? $source;
    }

    $anchor = '<a href="qr-experience/"><span class="nav-ico">✦</span>QR Experience OS</a>';
    if (!str_contains($source, $anchor)) {
        throw new RuntimeException('QR Experience menü bağlantısı bulunamadı; güvenli otomatik yerleştirme yapılamadı.');
    }
    $links = $anchor
        . '<!-- CHERRYHOUSE_V7_ENTERPRISE_NAV_START -->'
        . '<a href="enterprise/"><span class="nav-ico">◫</span>Enterprise Dashboard</a>'
        . '<a href="enterprise/media.php"><span class="nav-ico">▧</span>Medya Kütüphanesi</a>'
        . '<!-- CHERRYHOUSE_V7_ENTERPRISE_NAV_END -->';
    $source = preg_replace('~'.preg_quote($anchor, '~').'~', $links, $source, 1) ?? $source;
    $source = preg_replace("/\n{3,}/", "\n\n", $source) ?? $source;

    $changed = $source !== $original;
    $backup = null;
    if ($changed) {
        $backupDir = BASE_PATH . '/storage/backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }
        $backup = $backupDir . '/admin-index-before-v7-phase1-' . date('Ymd-His') . '.php';
        if (!copy($file, $backup)) {
            throw new RuntimeException('admin/index.php yedeği oluşturulamadı.');
        }
        $tmp = $file . '.v7.tmp';
        if (file_put_contents($tmp, $source, LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('admin/index.php güncellenemedi.');
        }
    }
    return ['changed' => $changed, 'backup' => $backup];
}

try {
    $result = v7_admin_stability_apply();
    header('Location: ../index.php?v7_admin_stability=1&t=' . time());
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>v7 Kurulum Hatası</title><style>body{font-family:system-ui;background:#f4f1ed;padding:32px}.box{max-width:760px;margin:auto;background:#fff;padding:28px;border-radius:18px}.error{background:#fff0f0;color:#922;padding:14px;border-radius:12px}</style></head><body><main class="box"><h1>Kurulum tamamlanamadı</h1><p class="error">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p><p>Dosya izinlerini kontrol edip tekrar deneyin.</p></main></body></html>';
}
