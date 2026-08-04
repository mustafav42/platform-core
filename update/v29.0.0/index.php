<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$payload = __DIR__ . '/files';
$result = '';
$ok = false;
$backups = [];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

$files = [
    'index.php',
    'app/assets/qrx-premium-menu.css',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stamp = date('Ymd-His');
    $errors = [];

    foreach ($files as $relative) {
        $source = $payload . '/' . $relative;
        $target = $root . '/' . $relative;
        if (!is_file($source)) {
            $errors[] = "Paket dosyası eksik: {$relative}";
            continue;
        }
        if (!is_file($target)) {
            $errors[] = "Hedef dosya bulunamadı: {$relative}";
            continue;
        }
        if (!is_writable($target) || !is_writable(dirname($target))) {
            $errors[] = "Dosya yazılabilir değil: {$relative}";
            continue;
        }

        $backup = $target . '.bak-v29.0.0-' . $stamp;
        if (!copy($target, $backup)) {
            $errors[] = "Yedek oluşturulamadı: {$relative}";
            continue;
        }
        $backups[] = str_replace($root . '/', '', $backup);
    }

    if (!$errors) {
        foreach ($files as $relative) {
            $source = $payload . '/' . $relative;
            $target = $root . '/' . $relative;
            $temp = $target . '.v290.tmp';
            if (!copy($source, $temp) || !rename($temp, $target)) {
                @unlink($temp);
                $errors[] = "Dosya güncellenemedi: {$relative}";
                break;
            }
        }
    }

    if ($errors) {
        $result = implode('<br>', array_map('h', $errors));
    } else {
        $ok = true;
        $result = 'Mobil ürün listesi başarıyla güncellendi.';
    }
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.0.0 Güncelleme</title>
<style>body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4efe9;margin:0;padding:28px;color:#201b18}.box{max-width:760px;margin:38px auto;background:#fff;padding:30px;border-radius:22px;box-shadow:0 20px 70px #2b171715}.version{font-size:12px;font-weight:850;letter-spacing:.1em;color:#8b1e2d}.summary{background:#faf7f3;border:1px solid #eee4dc;border-radius:16px;padding:16px;margin:18px 0}.summary div{padding:5px 0}.msg{padding:14px;border-radius:12px;background:#fff0e3;color:#864b00;margin:16px 0}.msg.ok{background:#e9f7ee;color:#176738}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}button,a{display:inline-block;border:0;border-radius:12px;padding:12px 18px;text-decoration:none;font-weight:750;cursor:pointer}button{background:#2c1c1d;color:#fff}a{background:#eee7e2;color:#2c1c1d}.note{font-size:13px;color:#766c67;line-height:1.6}</style></head><body><div class="box">
<div class="version">CHERRYHOUSE v29.0.0</div><h1>Mobil Ürün Listesi</h1><p>Referans görünüm doğrultusunda ürün fotoğrafı, açıklama ve fiyat hizasını yeniler.</p>
<div class="summary"><div>✓ Sol tarafta zorunlu ürün görseli</div><div>✓ Görsel yoksa ürün baş harfiyle zarif placeholder</div><div>✓ Ürün adının altında iki satırlık açıklama</div><div>✓ Sağda sabit fiyat hizası</div><div>✓ Kartın tamamı dokunulabilir</div><div>✓ Mobil ekranlarda sıkı ve okunaklı yerleşim</div></div>
<?php if($result!==''):?><div class="msg <?=$ok?'ok':''?>"><?=$result?><?php if($backups):?><br><small>Yedekler: <?=h(implode(', ',$backups))?></small><?php endif;?></div><?php endif;?>
<div class="actions"><?php if(!$ok):?><form method="post"><button type="submit">Mobil Görünümü Güncelle</button></form><?php endif;?><a href="../../">QR Menüyü Aç</a><a href="../../admin/">Ana Paneli Aç</a></div>
<p class="note">Veritabanında değişiklik yapılmaz. Güncelleme öncesinde iki hedef dosya otomatik yedeklenir.</p></div></body></html>
