<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'Ürün Yönetimi';
$currentPage = 'products';
$notice = trim((string)($_GET['notice'] ?? ''));
$error = '';
$pdo = ent_db();
$columns = ent_columns('products');
$hasImage = in_array('image_path', $columns, true);
$hasActive = in_array('is_active', $columns, true);
$hasSort = in_array('sort_order', $columns, true);
$hasCalories = in_array('calories_kcal', $columns, true);
$hasPrepTime = in_array('prep_time_min', $columns, true);
$hasAllergens = in_array('allergen_codes', $columns, true);
$allergenOptions=['gluten'=>'Gluten','milk'=>'Süt','egg'=>'Yumurta','peanut'=>'Yer fıstığı','nuts'=>'Sert kabuklu yemişler','soy'=>'Soya','sesame'=>'Susam','fish'=>'Balık','crustaceans'=>'Kabuklu deniz ürünleri','molluscs'=>'Yumuşakçalar','mustard'=>'Hardal','celery'=>'Kereviz','lupin'=>'Acı bakla','sulphites'=>'Sülfit'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ent_verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_product') {
            $id = (int)($_POST['id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $priceText = str_replace(',', '.', trim((string)($_POST['price'] ?? '0')));
            $price = (float)$priceText;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $imagePath = ltrim(trim((string)($_POST['image_path'] ?? '')), '/');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $caloriesRaw=trim((string)($_POST['calories_kcal']??''));$calories=$caloriesRaw===''?null:max(0,min(9999,(int)$caloriesRaw));
            $prepRaw=trim((string)($_POST['prep_time_min']??''));$prepTime=$prepRaw===''?null:max(0,min(999,(int)$prepRaw));
            $allergens=array_values(array_unique(array_filter(array_map(static fn($v)=>preg_replace('/[^a-z_]/','',strtolower(trim((string)$v))),is_array($_POST['allergens']??null)?$_POST['allergens']:[]),static fn($v)=>array_key_exists($v,$allergenOptions))));
            $allergenCodes=implode(',',$allergens);
            if ($categoryId < 1 || $name === '') throw new RuntimeException('Kategori ve ürün adı zorunludur.');
            if ($price < 0) throw new RuntimeException('Fiyat sıfırdan küçük olamaz.');
            $categoryCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id=?');
            $categoryCheck->execute([$categoryId]);
            if (!(int)$categoryCheck->fetchColumn()) throw new RuntimeException('Seçilen kategori bulunamadı.');

            $sets = ['category_id=?','name=?','description=?','price=?'];
            $values = [$categoryId, $name, $description, $price];
            if ($hasSort) { $sets[]='sort_order=?'; $values[]=$sortOrder; }
            if ($hasImage) { $sets[]='image_path=?'; $values[]=$imagePath; }
            if ($hasActive) { $sets[]='is_active=?'; $values[]=$isActive; }
            if ($hasCalories) { $sets[]='calories_kcal=?'; $values[]=$calories; }
            if ($hasPrepTime) { $sets[]='prep_time_min=?'; $values[]=$prepTime; }
            if ($hasAllergens) { $sets[]='allergen_codes=?'; $values[]=$allergenCodes!==''?$allergenCodes:null; }
            if ($id > 0) {
                $values[]=$id;
                $pdo->prepare('UPDATE products SET '.implode(',', $sets).' WHERE id=?')->execute($values);
                ent_redirect('products.php?notice='.rawurlencode('Ürün güncellendi.'));
            }
            $fields=['category_id','name','description','price'];
            $insertValues=[$categoryId,$name,$description,$price];
            if($hasSort){$fields[]='sort_order';$insertValues[]=$sortOrder;}
            if($hasImage){$fields[]='image_path';$insertValues[]=$imagePath;}
            if($hasActive){$fields[]='is_active';$insertValues[]=$isActive;}
            if($hasCalories){$fields[]='calories_kcal';$insertValues[]=$calories;}
            if($hasPrepTime){$fields[]='prep_time_min';$insertValues[]=$prepTime;}
            if($hasAllergens){$fields[]='allergen_codes';$insertValues[]=$allergenCodes!==''?$allergenCodes:null;}
            $placeholders=implode(',',array_fill(0,count($fields),'?'));
            $pdo->prepare('INSERT INTO products ('.implode(',',$fields).') VALUES ('.$placeholders.')')->execute($insertValues);
            ent_redirect('products.php?notice='.rawurlencode('Ürün eklendi.'));
        }
        if ($action === 'toggle_product' && $hasActive) {
            $id=(int)($_POST['id']??0);
            $pdo->prepare('UPDATE products SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
            ent_redirect('products.php?notice='.rawurlencode('Ürün durumu güncellendi.'));
        }
    }
} catch (Throwable $e) { $error=$e->getMessage(); }

$categories=$pdo->query('SELECT id,name'.($hasActive?',is_active':'').' FROM categories ORDER BY '.(in_array('sort_order',ent_columns('categories'),true)?'sort_order,':'').' id')->fetchAll(PDO::FETCH_ASSOC);
$editId=max(0,(int)($_GET['edit']??0));
$edit=['id'=>0,'category_id'=>$categories[0]['id']??0,'name'=>'','description'=>'','price'=>'','sort_order'=>0,'image_path'=>'','is_active'=>1,'calories_kcal'=>'','prep_time_min'=>'','allergen_codes'=>''];
if($editId>0){$q=$pdo->prepare('SELECT * FROM products WHERE id=? LIMIT 1');$q->execute([$editId]);$row=$q->fetch(PDO::FETCH_ASSOC);if($row)$edit=array_merge($edit,$row);}
$search=trim((string)($_GET['q']??''));
$categoryFilter=max(0,(int)($_GET['category']??0));
$where=[];$params=[];
if($search!==''){$where[]='(p.name LIKE ? OR p.description LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
if($categoryFilter>0){$where[]='p.category_id=?';$params[]=$categoryFilter;}
$sql='SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY c.'.(in_array('sort_order',ent_columns('categories'),true)?'sort_order,':'').' p.'.($hasSort?'sort_order,':'').' p.id DESC';
$q=$pdo->prepare($sql);$q->execute($params);$products=$q->fetchAll(PDO::FETCH_ASSOC);
require __DIR__.'/_header.php';
?>

<?php if($notice):?><div class="ch-flash ch-flash--success"><?=ent_e($notice)?></div><?php endif;?>
<?php if($error):?><div class="ch-flash ch-flash--danger"><?=ent_e($error)?></div><?php endif;?>
<section class="mw-page">
<header class="mw-page-head"><div><span class="ch-eyebrow">MENÜ YÖNETİMİ</span><h2>Ürünler</h2><p>Ürünleri listeleyin, filtreleyin ve ayrıntıları düzenleme panelinden yönetin.</p></div><div class="mw-head-actions"><a class="ch-btn ch-btn--ghost" href="reorder.php">Sıralamayı Yönet</a><a class="ch-btn ch-btn--primary" href="products.php?action=create">＋ Yeni Ürün</a></div></header>
<nav class="ch-workspace-tabs"><a href="menu.php">Genel Bakış</a><a class="active" href="products.php">Ürünler</a><a href="categories.php">Kategoriler</a><a href="variants.php">Varyantlar</a><a href="media.php">Medya Merkezi</a></nav>
<section class="mw-toolbar ch-card"><form method="get" class="mw-filter-form"><label class="mw-search"><span>⌕</span><input type="search" name="q" value="<?=ent_e($search)?>" placeholder="Ürün adı veya açıklama ara"></label><select name="category"><option value="0">Tüm kategoriler</option><?php foreach($categories as $c):?><option value="<?=(int)$c['id']?>" <?=$categoryFilter===(int)$c['id']?'selected':''?>><?=ent_e($c['name'])?></option><?php endforeach;?></select><button class="ch-btn ch-btn--secondary">Filtrele</button><?php if($search!==''||$categoryFilter):?><a class="ch-btn ch-btn--ghost" href="products.php">Temizle</a><?php endif;?></form><div class="mw-result-count"><b><?=count($products)?></b><span>ürün gösteriliyor</span></div></section>
<section class="ch-card mw-list-card"><div class="mw-table-head"><span>Ürün</span><span>Kategori</span><span>Fiyat</span><span>Durum</span><span></span></div><div class="mw-product-list"><?php foreach($products as $p):$img=trim((string)($p['image_path']??''));?><article class="mw-product-row"><div class="mw-product-main"><div class="mw-thumb"><?php if($hasImage&&$img!==''):?><img src="../../<?=ent_e(ltrim($img,'/'))?>" alt=""><?php else:?><span><?=ent_e(mb_substr((string)$p['name'],0,1,'UTF-8'))?></span><?php endif;?></div><div><b><?=ent_e($p['name'])?></b><small><?=ent_e(mb_strimwidth((string)$p['description'],0,92,'…','UTF-8'))?></small></div></div><div class="mw-cell" data-label="Kategori"><?=ent_e($p['category_name'])?></div><div class="mw-price" data-label="Fiyat"><?=number_format((float)$p['price'],2,',','.')?> ₺</div><div data-label="Durum"><span class="ch-badge <?=(!$hasActive||!empty($p['is_active']))?'ch-badge--success':'ch-badge--muted'?>"><?=(!$hasActive||!empty($p['is_active']))?'Aktif':'Pasif'?></span></div><div class="mw-row-actions"><a class="ch-icon-action" href="products.php?edit=<?=(int)$p['id']?>" title="Düzenle">✎</a><?php if($hasActive):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button class="ch-icon-action" type="submit" title="<?=!empty($p['is_active'])?'Pasifleştir':'Aktifleştir'?>"><?=!empty($p['is_active'])?'◉':'○'?></button></form><?php endif;?></div></article><?php endforeach;?><?php if(!$products):?><div class="ch-empty"><b>Ürün bulunamadı</b><small>Filtreyi değiştirin veya yeni ürün ekleyin.</small></div><?php endif;?></div></section>
</section>
<?php $drawerOpen=$editId>0||($_GET['action']??'')==='create'; if($drawerOpen):?><div class="mw-drawer-backdrop"><aside class="mw-drawer"><header><div><span class="ch-eyebrow"><?=$editId?'ÜRÜNÜ DÜZENLE':'YENİ ÜRÜN'?></span><h3><?=$editId?ent_e((string)$edit['name']):'Yeni ürün oluştur'?></h3></div><a class="ch-icon-action" href="products.php">×</a></header><?php if(!$categories):?><div class="ch-empty"><b>Önce kategori oluşturmalısınız.</b><a class="ch-btn ch-btn--primary" href="categories.php?action=create">Kategori Ekle</a></div><?php else:?><form method="post" class="mw-editor"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=(int)$edit['id']?>"><nav class="mw-editor-tabs"><button type="button" class="active" data-editor-tab="general">Genel</button><button type="button" data-editor-tab="media">Medya</button><button type="button" data-editor-tab="nutrition">Besin & Alerjen</button><button type="button" data-editor-tab="publish">Yayın</button></nav><section class="mw-editor-panel active" data-editor-panel="general"><div class="mw-field-grid"><label class="span-2">Ürün adı<input name="name" value="<?=ent_e($edit['name'])?>" required maxlength="190"></label><label>Kategori<select name="category_id" required><?php foreach($categories as $c):?><option value="<?=(int)$c['id']?>" <?=(int)$edit['category_id']===(int)$c['id']?'selected':''?>><?=ent_e($c['name'])?></option><?php endforeach;?></select></label><label>Fiyat (₺)<input name="price" type="number" min="0" step="0.01" value="<?=ent_e((string)$edit['price'])?>" required></label><label class="span-2">Açıklama<textarea name="description" rows="5"><?=ent_e($edit['description'])?></textarea></label></div></section><section class="mw-editor-panel" data-editor-panel="media"><?php if($hasImage):?><div class="mw-media-editor" data-product-media><input type="hidden" data-media-picker data-ch-media data-product-media-input name="image_path" value="<?=ent_e((string)$edit['image_path'])?>"><div class="mw-media-preview" data-product-media-preview><div class="mw-media-empty" data-product-media-empty><span>▧</span><b>Ürün görseli seçilmedi</b><small>Medya Merkezi'nden seçin veya bilgisayarınızdan yükleyin.</small></div><img data-product-media-image alt="Ürün görseli önizlemesi"><div class="mw-media-preview-actions"><button class="ch-btn ch-btn--dark" type="button" data-product-media-library>Medya Merkezi'nden Seç</button><button class="ch-icon-action ch-icon-action--danger" type="button" data-product-media-remove title="Görseli kaldır">⌫</button></div></div><div class="mw-media-choice"><div><span class="ch-eyebrow">GÖRSEL SEÇ</span><h4>Medya Merkezi veya anlık yükleme</h4><p>Mevcut kütüphaneden seçim yapabilir ya da yeni görseli burada yükleyebilirsiniz.</p></div><div class="mw-media-actions"><button class="ch-btn ch-btn--secondary" type="button" data-product-media-library>▦ Medya Merkezi</button><label class="ch-btn ch-btn--primary mw-upload-button">↑ Bilgisayardan Yükle<input type="file" accept="image/jpeg,image/png,image/webp" data-product-media-upload hidden></label></div></div><div class="mw-upload-zone" data-product-media-drop><span>⇧</span><b>Görseli buraya sürükleyin</b><small>JPG, PNG veya WebP · Dosya başına en fazla 32 MB</small><div class="mw-upload-progress" data-product-media-progress hidden><i></i><em>Yükleniyor…</em></div></div><div class="mw-media-path" data-product-media-path></div></div><?php else:?><div class="ch-empty"><b>Görsel alanı mevcut değil.</b></div><?php endif;?></section><section class="mw-editor-panel" data-editor-panel="nutrition"><div class="mw-field-grid"><?php if($hasCalories):?><label>Kalori (kcal)<input name="calories_kcal" type="number" min="0" max="9999" value="<?=ent_e((string)($edit['calories_kcal']??''))?>"></label><?php endif;?><?php if($hasPrepTime):?><label>Hazırlama süresi<select name="prep_time_min"><option value="">Gösterme</option><?php foreach([5,10,15,20,25,30,45,60] as $minute):?><option value="<?=$minute?>" <?=((string)($edit['prep_time_min']??''))===(string)$minute?'selected':''?>><?=$minute===60?'60 dk+':$minute.' dk'?></option><?php endforeach;?></select></label><?php endif;?></div><?php if($hasAllergens):?><div class="mw-allergen-grid"><?php $selectedAllergens=array_filter(explode(',',(string)($edit['allergen_codes']??'')));foreach($allergenOptions as $code=>$label):?><label><input type="checkbox" name="allergens[]" value="<?=ent_e($code)?>" <?=in_array($code,$selectedAllergens,true)?'checked':''?>><span><?=ent_e($label)?></span></label><?php endforeach;?></div><?php endif;?></section><section class="mw-editor-panel" data-editor-panel="publish"><div class="mw-field-grid"><?php if($hasSort):?><label>Sıralama<input name="sort_order" type="number" value="<?=(int)$edit['sort_order']?>"></label><?php endif;?></div><?php if($hasActive):?><label class="mw-switch"><input type="checkbox" name="is_active" value="1" <?=!empty($edit['is_active'])?'checked':''?>><span><b>Aktif ürün</b><small>Menü ve QR görünümünde yayınla</small></span></label><?php endif;?></section><footer class="mw-editor-actions"><a class="ch-btn ch-btn--ghost mw-cancel-button" href="products.php"><span>←</span> Vazgeç</a><button class="ch-btn ch-btn--primary mw-save-button" type="submit"><span>✓</span> <?=$editId?'Değişiklikleri Kaydet':'Ürünü Oluştur'?></button></footer></form><?php endif;?></aside></div><?php endif;?>
<?php require __DIR__.'/_footer.php';?>
