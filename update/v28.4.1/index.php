<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$target = $root . '/admin/index.php';
$result = '';
$ok = false;
$backupName = '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function menuBlock(): string
{
    return <<<'HTML'
<div class="nav-label">MENÜ YÖNETİMİ</div>
<a href="menu-center.php"><span class="nav-ico">▤</span>Menü Dashboard</a>
<a href="catalog.php?tab=products"><span class="nav-ico">◇</span>Ürünler</a>
<a href="catalog.php?tab=categories"><span class="nav-ico">◫</span>Kategoriler</a>
<a href="enterprise/media.php"><span class="nav-ico">▧</span>Medya Kütüphanesi</a>
<a href="qr-experience/"><span class="nav-ico">✦</span>QR Tasarımı</a>
HTML;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_file($target)) {
        $result = 'admin/index.php bulunamadı.';
    } elseif (!is_readable($target) || !is_writable($target)) {
        $result = 'admin/index.php okunamıyor veya yazılamıyor. Dosya izinlerini kontrol edin.';
    } else {
        $source = file_get_contents($target);
        if ($source === false) {
            $result = 'admin/index.php okunamadı.';
        } else {
            $newBlock = menuBlock();
            $alreadyIntegrated = str_contains($source, 'href="menu-center.php"')
                && str_contains($source, 'href="catalog.php?tab=products"')
                && str_contains($source, 'href="catalog.php?tab=categories"')
                && !str_contains($source, 'href="?page=products"')
                && !str_contains($source, 'href="?page=categories"');

            if ($alreadyIntegrated) {
                $result = 'Ana panel menüsü zaten yeni Menü Yönetimi yapısını kullanıyor.';
                $ok = true;
            } else {
                $startMarkers = [
                    '<div class="nav-label">KATALOG VE QR</div>',
                    '<div class="nav-label">MENÜ YÖNETİMİ</div>',
                ];
                $endMarker = '<div class="nav-label">İŞLETME</div>';
                $start = false;
                foreach ($startMarkers as $marker) {
                    $position = strpos($source, $marker);
                    if ($position !== false) {
                        $start = $position;
                        break;
                    }
                }
                $end = $start !== false ? strpos($source, $endMarker, (int)$start) : false;

                if ($start === false || $end === false || $end <= $start) {
                    $result = 'Ana panelde katalog menü bloğu otomatik bulunamadı. Hiçbir dosya değiştirilmedi.';
                } else {
                    $backupName = 'index.php.bak-v28.4.1-' . date('Ymd-His');
                    $backup = dirname($target) . '/' . $backupName;
                    if (!copy($target, $backup)) {
                        $result = 'Yedek oluşturulamadığı için güncelleme durduruldu.';
                    } else {
                        $patched = substr($source, 0, (int)$start)
                            . $newBlock . "\n"
                            . substr($source, (int)$end);

                        // Eski v28.4.0 güncelleyicisi bağlantıyı aside/nav kapanışına çıplak eklediyse temizle.
                        $patched = str_replace('<a href="menu-center.php">🍽 Menü Yönetimi</a>', '', $patched);

                        $temp = $target . '.v2841.tmp';
                        $written = file_put_contents($temp, $patched, LOCK_EX);
                        if ($written === false) {
                            @unlink($temp);
                            $result = 'Geçici güncelleme dosyası yazılamadı. Orijinal dosya korunuyor.';
                        } elseif (!rename($temp, $target)) {
                            @unlink($temp);
                            $result = 'Güncellenen dosya yerine taşınamadı. Orijinal dosya korunuyor.';
                        } else {
                            $result = 'Ana panel menüsü başarıyla yenilendi. Eski Ürünler/Kategoriler bağlantıları kaldırıldı.';
                            $ok = true;
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CherryHouse v28.4.1 Güncelleme</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f6f2f0;margin:0;padding:30px;color:#291d1d}.box{max-width:720px;margin:40px auto;background:#fff;padding:30px;border-radius:22px;box-shadow:0 18px 60px #2b171717}.version{font-size:12px;font-weight:800;letter-spacing:.08em;color:#7c3aed}.summary{background:#faf8f7;border:1px solid #eee6e3;border-radius:16px;padding:16px;margin:18px 0}.summary div{padding:5px 0}button,a.btn{display:inline-block;border:0;border-radius:12px;padding:12px 18px;text-decoration:none;cursor:pointer;font-weight:750}button{background:#371d20;color:#fff}.btn{background:#eee8e6;color:#371d20}.msg{padding:14px;border-radius:12px;background:#fff3e5;color:#864b00;margin:16px 0}.ok{background:#eaf7ef;color:#196b3a}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.note{font-size:13px;color:#766b68;line-height:1.6}
</style>
</head>
<body><div class="box">
<div class="version">CHERRYHOUSE v28.4.1</div>
<h1>Ana Panel Menü Entegrasyonu</h1>
<p>Eski Ürünler ve Kategoriler bağlantılarını kaldırır; yeni Menü Yönetimi Merkezi'ni ana panelin resmi menüsü yapar.</p>
<div class="summary">
<div>✓ Menü Dashboard</div>
<div>✓ Yeni Ürünler ekranı</div>
<div>✓ Yeni Kategoriler ekranı</div>
<div>✓ Medya Kütüphanesi</div>
<div>✓ QR Tasarımı</div>
</div>
<?php if ($result !== ''): ?><div class="msg <?= $ok ? 'ok' : '' ?>"><?= h($result) ?><?php if ($backupName !== ''): ?><br><small>Yedek: <?= h($backupName) ?></small><?php endif; ?></div><?php endif; ?>
<div class="actions">
<?php if (!$ok): ?><form method="post"><button type="submit">Ana Menüyü Güncelle</button></form><?php endif; ?>
<a class="btn" href="../../admin/">Ana Paneli Aç</a>
<a class="btn" href="../../admin/menu-center.php">Menü Merkezi'ni Aç</a>
</div>
<p class="note">Güncelleme öncesinde <code>admin/index.php</code> otomatik olarak yedeklenir. Veri tabanında değişiklik yapılmaz.</p>
</div></body></html>
