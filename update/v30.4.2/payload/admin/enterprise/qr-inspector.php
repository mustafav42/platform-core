<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pageTitle = 'QR Kalite Kontrolü';
$currentPage = 'qr-inspector';
$pdo = ent_db();

function qri_columns(string $table): array {
    try { return ent_columns($table); } catch (Throwable $e) { return []; }
}
function qri_setting(string $key, string $default=''): string { return ent_setting($key,$default); }
function qri_media_exists(string $path): bool {
    $path=trim($path);
    if($path==='') return false;
    if(preg_match('~^https?://~i',$path)) return true;
    $path=preg_replace('~[?#].*$~','',$path) ?? $path;
    $path=ltrim($path,'/');
    return is_file(BASE_PATH.'/'.$path);
}
function qri_count(PDO $pdo,string $sql,array $params=[]): int {
    try{$q=$pdo->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}
}

$productCols=qri_columns('products');
$categoryCols=qri_columns('categories');
$activeProductWhere=in_array('is_active',$productCols,true)?' WHERE is_active=1':'';
$activeCategoryWhere=in_array('is_active',$categoryCols,true)?' WHERE is_active=1':'';
$totalProducts=qri_count($pdo,'SELECT COUNT(*) FROM products'.$activeProductWhere);
$totalCategories=qri_count($pdo,'SELECT COUNT(*) FROM categories'.$activeCategoryWhere);
$missingImages=in_array('image_path',$productCols,true)?qri_count($pdo,"SELECT COUNT(*) FROM products".($activeProductWhere?:' WHERE 1=1')." AND (image_path IS NULL OR TRIM(image_path)='')"):0;
$missingDescriptions=in_array('description',$productCols,true)?qri_count($pdo,"SELECT COUNT(*) FROM products".($activeProductWhere?:' WHERE 1=1')." AND (description IS NULL OR TRIM(description)='')"):0;
$withCalories=in_array('calories_kcal',$productCols,true)?qri_count($pdo,"SELECT COUNT(*) FROM products".($activeProductWhere?:' WHERE 1=1')." AND calories_kcal IS NOT NULL AND calories_kcal>0"):0;
$withAllergens=in_array('allergen_codes',$productCols,true)?qri_count($pdo,"SELECT COUNT(*) FROM products".($activeProductWhere?:' WHERE 1=1')." AND allergen_codes IS NOT NULL AND TRIM(allergen_codes)<>''"):0;
$withPrep=in_array('prep_time_min',$productCols,true)?qri_count($pdo,"SELECT COUNT(*) FROM products".($activeProductWhere?:' WHERE 1=1')." AND prep_time_min IS NOT NULL AND prep_time_min>0"):0;
$emptyCategories=0;
if($totalCategories>0 && in_array('category_id',$productCols,true)){
    $catActive=in_array('is_active',$categoryCols,true)?'c.is_active=1':'1=1';
    $prodActive=in_array('is_active',$productCols,true)?' AND p.is_active=1':'';
    $emptyCategories=qri_count($pdo,"SELECT COUNT(*) FROM categories c LEFT JOIN products p ON p.category_id=c.id{$prodActive} WHERE {$catActive} AND p.id IS NULL");
}
$brokenImages=0;
if(in_array('image_path',$productCols,true)){
    $sql='SELECT image_path FROM products'.($activeProductWhere?:' WHERE 1=1')." AND image_path IS NOT NULL AND TRIM(image_path)<>''";
    try{$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);foreach($rows as $path)if(!qri_media_exists((string)$path))$brokenImages++;}catch(Throwable $e){}
}
$logo=qri_setting('qr_logo_image',qri_setting('logo_path',''));
$hero=qri_setting('qr_hero_image','');
$heroEnabled=qri_setting('qr_show_hero','1')==='1';
$menuEnabled=qri_setting('qr_menu_enabled','1')==='1';
$checks=[
 ['label'=>'QR menü yayında','ok'=>$menuEnabled,'detail'=>$menuEnabled?'Canlı menü açık.':'QR menü kapalı.','weight'=>20,'target'=>'../qr-experience/'],
 ['label'=>'Aktif ürünler','ok'=>$totalProducts>0,'detail'=>$totalProducts.' aktif ürün bulundu.','weight'=>15,'target'=>'products.php'],
 ['label'=>'Aktif kategoriler','ok'=>$totalCategories>0,'detail'=>$totalCategories.' aktif kategori bulundu.','weight'=>10,'target'=>'categories.php'],
 ['label'=>'Ürün görselleri','ok'=>$missingImages===0 && $brokenImages===0,'detail'=>$missingImages.' görselsiz, '.$brokenImages.' kırık görsel.','weight'=>20,'target'=>'products.php'],
 ['label'=>'Ürün açıklamaları','ok'=>$missingDescriptions===0,'detail'=>$missingDescriptions.' üründe açıklama eksik.','weight'=>10,'target'=>'products.php'],
 ['label'=>'Boş kategoriler','ok'=>$emptyCategories===0,'detail'=>$emptyCategories.' kategoride aktif ürün yok.','weight'=>10,'target'=>'categories.php'],
 ['label'=>'Logo','ok'=>$logo!=='' && qri_media_exists($logo),'detail'=>$logo!==''?'Logo yolu tanımlı.':'Logo seçilmemiş.','weight'=>5,'target'=>'../qr-experience/'],
 ['label'=>'Hero','ok'=>!$heroEnabled || ($hero!=='' && qri_media_exists($hero)),'detail'=>!$heroEnabled?'Hero kapalı.':($hero!==''?'Hero görseli tanımlı.':'Hero açık fakat görsel yok.'),'weight'=>10,'target'=>'../qr-experience/'],
];
$score=0;$max=0;foreach($checks as $c){$max+=$c['weight'];if($c['ok'])$score+=$c['weight'];}
$score=$max>0?(int)round(($score/$max)*100):0;
$status=$score>=90?'Yayına hazır':($score>=70?'Küçük düzenlemeler gerekli':'Düzenleme gerekli');
require __DIR__.'/_header.php';
?>
<section class="qri-hero enterprise-card">
 <div><small>QR EXPERIENCE v1.0 LTS HAZIRLIK</small><h2>QR Kalite Kontrolü</h2><p>Canlı menüye geçmeden önce içerik, görsel ve temel yayın ayarlarını tek ekranda doğrula.</p></div>
 <div class="qri-score" style="--score:<?=$score?>"><strong><?=$score?></strong><span>/ 100</span><b><?=$status?></b></div>
</section>
<div class="qri-grid">
 <section class="enterprise-card qri-checks"><header><div><small>KONTROL LİSTESİ</small><h3>Yayın kontrolleri</h3></div><a class="preview-button" href="../../" target="_blank">Canlı Menüyü Aç ↗</a></header>
 <?php foreach($checks as $c):?><a class="qri-row <?=$c['ok']?'ok':'warn'?>" href="<?=ent_e($c['target'])?>"><i><?=$c['ok']?'✓':'!'?></i><div><strong><?=ent_e($c['label'])?></strong><span><?=ent_e($c['detail'])?></span></div><b>→</b></a><?php endforeach;?>
 </section>
 <aside class="qri-side">
  <section class="enterprise-card"><small>ÜRÜN BİLGİSİ KAPSAMI</small><div class="qri-metric"><b><?=$withCalories?></b><span>Kalorisi girilmiş ürün</span></div><div class="qri-metric"><b><?=$withAllergens?></b><span>Alerjen bilgili ürün</span></div><div class="qri-metric"><b><?=$withPrep?></b><span>Hazırlama süresi girilmiş ürün</span></div><p class="qri-note">Bu bilgiler isteğe bağlıdır ve kalite puanını düşürmez. Girildiğinde ürün detayında sade biçimde gösterilir.</p></section>
  <section class="enterprise-card"><small>HIZLI İŞLEMLER</small><a class="qri-action" href="products.php">Ürünleri düzenle <span>→</span></a><a class="qri-action" href="media.php">Medya Merkezi <span>→</span></a><a class="qri-action" href="../qr-experience/">QR Studio <span>→</span></a></section>
 </aside>
</div>
<style>
.qri-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:28px;margin-bottom:18px}.qri-hero small,.qri-checks small,.qri-side small{font-size:11px;font-weight:900;letter-spacing:.12em;color:var(--ent-muted)}.qri-hero h2{font-size:clamp(28px,4vw,46px);margin:6px 0}.qri-hero p{max-width:650px;color:var(--ent-muted);line-height:1.6}.qri-score{width:150px;height:150px;border-radius:50%;display:grid;place-content:center;text-align:center;background:conic-gradient(var(--ent-accent,#92263a) calc(var(--score)*1%),var(--ent-line) 0);position:relative}.qri-score:before{content:"";position:absolute;inset:10px;border-radius:50%;background:var(--ent-surface,#fff)}.qri-score>*{position:relative}.qri-score strong{font-size:40px;line-height:1}.qri-score span{color:var(--ent-muted);font-weight:700}.qri-score b{font-size:11px;margin-top:7px}.qri-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr);gap:18px}.qri-checks{padding:0;overflow:hidden}.qri-checks header{display:flex;align-items:center;justify-content:space-between;padding:20px 22px;border-bottom:1px solid var(--ent-line)}.qri-checks h3{margin:4px 0 0}.qri-row{display:grid;grid-template-columns:38px 1fr auto;gap:13px;align-items:center;padding:16px 20px;border-bottom:1px solid var(--ent-line);text-decoration:none;color:inherit}.qri-row:hover{background:var(--ent-surface-2)}.qri-row i{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;font-style:normal;font-weight:900}.qri-row.ok i{background:#e7f7ed;color:#187744}.qri-row.warn i{background:#fff1db;color:#a45c00}.qri-row div{display:grid;gap:4px}.qri-row span{font-size:13px;color:var(--ent-muted)}.qri-side{display:grid;gap:18px;align-content:start}.qri-side .enterprise-card{padding:20px}.qri-metric{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid var(--ent-line)}.qri-metric b{font-size:28px}.qri-metric span{font-size:13px;color:var(--ent-muted)}.qri-note{font-size:12px;color:var(--ent-muted);line-height:1.55}.qri-action{display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px solid var(--ent-line);text-decoration:none;color:inherit;font-weight:750}@media(max-width:800px){.qri-hero{align-items:flex-start}.qri-score{width:112px;height:112px;flex:0 0 auto}.qri-score strong{font-size:30px}.qri-grid{grid-template-columns:1fr}}@media(max-width:560px){.qri-hero{display:grid}.qri-score{justify-self:start}.qri-checks header{align-items:flex-start;gap:12px;flex-direction:column}.qri-row{grid-template-columns:34px 1fr}.qri-row>b{display:none}}
</style>
<?php require __DIR__.'/_footer.php'; ?>
