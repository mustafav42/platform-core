<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ent_media_upgrade();
$pdo = ent_db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM enterprise_media WHERE id=? LIMIT 1');
$q->execute([$id]);
$item = $q->fetch(PDO::FETCH_ASSOC);
if (!$item) { http_response_code(404); exit('Görsel bulunamadı.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ent_verify_csrf();
        if ((string)($_POST['action'] ?? '') !== 'save') throw new RuntimeException('Geçersiz işlem.');
        $file = $_FILES['cropped_image'] ?? null;
        if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Düzenlenmiş görsel alınamadı.');
        $tmp = (string)$file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) throw new RuntimeException('Geçersiz görsel biçimi.');
        $dim = @getimagesize($tmp);
        if (!$dim || (int)$dim[0] !== 1600 || (int)$dim[1] !== 1200) throw new RuntimeException('Çıktı 1600×1200 olmalıdır.');
        $current = (string)$item['relative_path'];
        if (!str_starts_with($current, 'storage/uploads/media/')) throw new RuntimeException('Güvenli olmayan medya yolu.');
        $original = trim((string)($item['original_relative_path'] ?? ''));
        if ($original === '') $original = $current;
        $ext = $mime === 'image/webp' ? 'webp' : 'jpg';
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(7)) . '-43.' . $ext;
        $destination = ent_media_root() . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) throw new RuntimeException('Düzenlenmiş görsel kaydedilemedi.');
        $relative = 'storage/uploads/media/' . $filename;
        $size = filesize($destination) ?: 0;
        $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE enterprise_media SET filename=?,relative_path=?,original_relative_path=?,mime_type=?,file_size=?,width=1600,height=1200,updated_at=NOW(),edited_at=NOW() WHERE id=?');
        $st->execute([$filename,$relative,$original,$mime,$size,$id]);
        $pdo->commit();
        $item['relative_path'] = $relative;
        $item['original_relative_path'] = $original;
        $item['width'] = 1600; $item['height'] = 1200;
        header('Location: image-studio.php?id='.$id.'&saved=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}
$sourcePath = (string)($item['original_relative_path'] ?: $item['relative_path']);
$sourceUrl = ent_media_url($sourcePath);
$currentUrl = ent_media_url((string)$item['relative_path']);
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse Image Studio</title><link rel="stylesheet" href="assets/image-studio.css?v=3036"></head><body>
<header class="cis-top"><div><strong>CherryHouse Image Studio</strong><span>Ürün görselini 4:3 QR çerçevesine hazırlayın.</span></div><button type="button" onclick="history.back()">Kapat</button></header>
<?php if($error):?><div class="cis-alert error"><?=ent_e($error)?></div><?php endif;?>
<?php if(isset($_GET['saved'])):?><div class="cis-alert ok">Görsel optimize edildi ve kütüphaneye kaydedildi.</div><?php endif;?>
<main class="cis-layout">
<section class="cis-editor">
  <div class="cis-stage" id="stage"><canvas id="canvas" width="1600" height="1200"></canvas><div class="cis-safe"><span>4:3 QR SAFE AREA</span></div></div>
  <div class="cis-controls">
    <label>Yakınlaştır <input id="zoom" type="range" min="0.2" max="3" value="1" step="0.01"><output id="zoomOut">100%</output></label>
    <button type="button" id="rotateLeft">↺ Sola Döndür</button><button type="button" id="rotateRight">↻ Sağa Döndür</button><button type="button" id="reset">Sıfırla</button>
  </div>
  <p class="cis-hint">Görseli fareyle veya parmağınızla sürükleyin. Ürün çevresinde küçük bir güvenli boşluk bırakın.</p>
</section>
<aside class="cis-preview">
  <h2>QR Önizleme</h2><div class="cis-phone"><img id="preview" alt="QR önizleme"><div><small>ÜRÜN</small><b>Ürün adı</b><strong>₺</strong></div></div>
  <div class="cis-checks" id="checks"></div>
  <form id="saveForm" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$id?>"><input type="file" name="cropped_image" id="croppedFile" hidden><button class="cis-save" type="submit">Optimize Et ve Kaydet</button></form>
  <?php if(isset($_GET['saved'])):?><button class="cis-use" type="button" data-path="<?=ent_e($item['relative_path'])?>" data-url="<?=ent_e($currentUrl)?>">Bu Görseli Kullan</button><?php endif;?>
</aside>
</main>
<script>window.CH_IMAGE_STUDIO={source:<?=json_encode($sourceUrl,JSON_UNESCAPED_SLASHES)?>,width:<?=(int)$item['width']?>,height:<?=(int)$item['height']?>};</script><script src="assets/image-studio.js?v=3036"></script>
</body></html>
