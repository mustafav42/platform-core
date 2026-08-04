<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once BASE_PATH.'/app/Brand/BrandManager.php';
if (empty($_SESSION['admin_id'])) redirect('./');
require_permission('maintenance.manage');
$pdo=db();
$error=''; $notice='';

function brand_setting_save(PDO $pdo, string $key, string $value): void {
    $q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $q->execute([$key,$value]);
}
function brand_upload(string $field, array $extensions, int $maxBytes=4194304): ?string {
    if (empty($_FILES[$field]) || (int)$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
    $f=$_FILES[$field];
    if ((int)$f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi: '.$field);
    if ((int)$f['size']>$maxBytes) throw new RuntimeException('Dosya boyutu 4 MB sınırını aşıyor.');
    $ext=strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,$extensions,true)) throw new RuntimeException('Desteklenmeyen dosya türü.');
    $info=@getimagesize((string)$f['tmp_name']);
    if ($info===false) throw new RuntimeException('Yüklenen dosya geçerli bir görsel değil.');
    $dir=BASE_PATH.'/storage/branding';
    if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Marka klasörü oluşturulamadı.');
    $name=$field.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
    if (!move_uploaded_file((string)$f['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Görsel kaydedilemedi.');
    return 'storage/branding/'.$name;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        $keys=['business_name','brand_short_name','brand_welcome_text','brand_footer_text','brand_version_text','brand_primary_color','brand_secondary_color','brand_surface_color','brand_login_background_color','brand_license_label','brand_license_owner'];
        foreach($keys as $key){
            $value=trim((string)($_POST[$key]??''));
            if (str_contains($key,'color')) $value=BrandManager::safeColor($value,BrandManager::defaults()[$key]);
            brand_setting_save($pdo,$key,mb_substr($value,0,180,'UTF-8'));
        }
        $logoWidth=max(220,min(720,(int)($_POST['brand_login_logo_width']??520)));
        brand_setting_save($pdo,'brand_login_logo_width',(string)$logoWidth);
        brand_setting_save($pdo,'brand_show_license',!empty($_POST['brand_show_license'])?'1':'0');
        $uploads=[
            'brand_login_logo'=>['png','jpg','jpeg','webp','gif'],
            'brand_admin_logo'=>['png','jpg','jpeg','webp','gif'],
            'brand_favicon'=>['png','ico','jpg','jpeg','webp'],
            'brand_login_background'=>['png','jpg','jpeg','webp'],
        ];
        foreach($uploads as $field=>$extensions){
            $path=brand_upload($field,$extensions);
            if ($path!==null) brand_setting_save($pdo,$field,$path);
            if (!empty($_POST['remove_'.$field])) brand_setting_save($pdo,$field,'');
        }
        audit_log('brand_settings_updated','Marka Merkezi ayarları güncellendi.');
        $notice='Marka ayarları kaydedildi.';
    } catch(Throwable $e){ $error=$e->getMessage(); app_log($e,['page'=>'brand-center']); }
}
$brand=BrandManager::defaults(); foreach(array_keys($brand) as $k) $brand[$k]=BrandManager::get($k);
function preview_src(string $value): string { return $value===''?'':'../'.ltrim($value,'/'); }
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marka Merkezi</title><link rel="stylesheet" href="assets/brand-center.css?v=2710"></head>
<body style="<?=e(BrandManager::cssVars())?>"><header class="top"><div><a href="./?page=dashboard">← Yönetim Paneli</a><h1>Marka Merkezi</h1><p>Logo, renkler ve giriş ekranını kod değiştirmeden yönetin.</p></div><div class="live-badge">Canlı Marka Ayarları</div></header>
<main><?php if($notice):?><div class="notice ok"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="notice err"><?=e($error)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data" id="brandForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="layout"><section class="editor">
<div class="card"><h2>Kurumsal Kimlik</h2><div class="form-grid"><label>İşletme adı<input name="business_name" value="<?=e($brand['business_name'])?>" maxlength="120" required></label><label>Kısa ad<input name="brand_short_name" value="<?=e($brand['brand_short_name'])?>" maxlength="60"></label><label>Karşılama yazısı<input name="brand_welcome_text" value="<?=e($brand['brand_welcome_text'])?>" maxlength="80"></label><label>Versiyon yazısı<input name="brand_version_text" value="<?=e($brand['brand_version_text'])?>" maxlength="40"></label><label class="full">Alt bilgi<input name="brand_footer_text" value="<?=e($brand['brand_footer_text'])?>" maxlength="160"></label></div></div>
<div class="card"><h2>Giriş Ekranı Düzeni</h2><div class="form-grid"><label class="full">Logo genişliği <span class="range-value"><b id="logoWidthValue"><?=e($brand['brand_login_logo_width'])?></b> px</span><input type="range" name="brand_login_logo_width" min="220" max="720" step="10" value="<?=e($brand['brand_login_logo_width'])?>"></label><label>Lisans başlığı<input name="brand_license_label" value="<?=e($brand['brand_license_label'])?>" maxlength="60"></label><label>Lisans sahibi<input name="brand_license_owner" value="<?=e($brand['brand_license_owner'])?>" maxlength="100"></label><label class="switch-row full"><input type="checkbox" name="brand_show_license" value="1" <?=$brand['brand_show_license']==='1'?'checked':''?>><span>Lisans sahibi kutusunu göster</span></label></div></div>
<div class="card"><h2>Renk Sistemi</h2><div class="colors"><?php foreach(['brand_primary_color'=>'Ana renk','brand_secondary_color'=>'Vurgu rengi','brand_surface_color'=>'Kart rengi','brand_login_background_color'=>'Giriş arka plan rengi'] as $key=>$label):?><label><span><?=$label?></span><div class="color-row"><input type="color" name="<?=$key?>" value="<?=e($brand[$key])?>"><code><?=e($brand[$key])?></code></div></label><?php endforeach;?></div></div>
<div class="card"><h2>Görseller</h2><div class="upload-grid"><?php foreach(['brand_login_logo'=>'Giriş logosu','brand_admin_logo'=>'Panel logosu','brand_favicon'=>'Favicon','brand_login_background'=>'Giriş arka planı'] as $key=>$label):?><label class="upload"><strong><?=$label?></strong><?php if($brand[$key]):?><img src="<?=e(preview_src($brand[$key]))?>" alt=""><span><input type="checkbox" name="remove_<?=$key?>" value="1"> Görseli kaldır</span><?php else:?><div class="empty">Görsel seçilmedi</div><?php endif;?><input type="file" name="<?=$key?>" accept="image/*,.ico"></label><?php endforeach;?></div></div>
<div class="actions"><button type="submit">Ayarları Kaydet</button><span>Değişiklikler giriş ekranına anında uygulanır.</span></div>
</section><aside class="preview"><div class="preview-sticky"><div class="preview-title">Canlı Önizleme</div><div class="login-preview" id="loginPreview" <?php if($brand['brand_login_background']):?>style="background-image:linear-gradient(#0f172a55,#0f172a55),url('<?=e(preview_src($brand['brand_login_background']))?>')"<?php endif;?>>
<div class="preview-brand"><?php if($brand['brand_login_logo']):?><img id="previewLogo" src="<?=e(preview_src($brand['brand_login_logo']))?>" alt="" style="width:min(100%,<?=e($brand['brand_login_logo_width'])?>px)"><?php else:?><div class="fallback-logo">CH</div><b id="previewName"><?=e($brand['business_name'])?></b><small><?=e($brand['brand_footer_text'])?></small><?php endif;?></div><div class="pin-box"><h3 id="previewWelcome"><?=e($brand['brand_welcome_text'])?></h3><div class="dots">● ● ○ ○</div><div class="keypad"><?php foreach([1,2,3,4,5,6,7,8,9,'⌫',0,'→'] as $n):?><span><?=$n?></span><?php endforeach;?></div></div><div class="version"><?=e($brand['brand_version_text'])?></div></div></div></aside></div></form></main>
<script src="assets/brand-center.js?v=2710"></script></body></html>
