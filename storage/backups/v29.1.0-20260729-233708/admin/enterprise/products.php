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
$edit=['id'=>0,'category_id'=>$categories[0]['id']??0,'name'=>'','description'=>'','price'=>'','sort_order'=>0,'image_path'=>'','is_active'=>1];
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
<?php if($notice):?><div class="ent-alert success"><?=ent_e($notice)?></div><?php endif;?>
<?php if($error):?><div class="ent-alert danger"><?=ent_e($error)?></div><?php endif;?>
<section class="enterprise-hero compact-hero"><div><span>KATALOG YÖNETİMİ</span><h2>Ürünleri tek ekrandan yönetin.</h2><p>Fiyat, kategori, açıklama, görsel, sıralama ve yayın durumu birlikte güncellenir.</p></div><div class="hero-actions"><a class="secondary-action" href="categories.php">Kategorileri Aç</a><a class="primary-action" href="products.php">Yeni Ürün</a></div></section>
<div class="enterprise-grid catalog-layout">
<section class="enterprise-panel catalog-form"><header><div><span><?=$editId?'DÜZENLE':'YENİ KAYIT'?></span><h3><?=$editId?'Ürünü düzenle':'Yeni ürün ekle'?></h3></div><?php if($editId):?><a href="products.php">İptal</a><?php endif;?></header>
<?php if(!$categories):?><div class="empty-state"><strong>Önce kategori oluşturmalısın.</strong><a href="categories.php">Kategori ekle</a></div><?php else:?><form method="post" class="enterprise-form"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=(int)$edit['id']?>">
<label>Kategori<select name="category_id" required><?php foreach($categories as $c):?><option value="<?=(int)$c['id']?>" <?=(int)$edit['category_id']===(int)$c['id']?'selected':''?>><?=ent_e($c['name'])?></option><?php endforeach;?></select></label>
<label>Ürün adı<input name="name" value="<?=ent_e($edit['name'])?>" required maxlength="190"></label>
<label>Açıklama<textarea name="description" rows="4"><?=ent_e($edit['description'])?></textarea></label>
<div class="form-columns"><label>Fiyat (₺)<input name="price" type="number" min="0" step="0.01" value="<?=ent_e((string)$edit['price'])?>" required></label><?php if($hasSort):?><label>Sıralama<input name="sort_order" type="number" value="<?=(int)$edit['sort_order']?>"></label><?php endif;?></div>
<?php if($hasImage):?><label>Ürün görseli<input data-media-picker name="image_path" value="<?=ent_e((string)$edit['image_path'])?>" placeholder="Görsel seçilmedi"><small>Kütüphaneden seçebilir veya mevcut görseli temizleyebilirsin.</small></label><?php endif;?>
<?php if($hasActive):?><label class="switch-row"><input type="checkbox" name="is_active" value="1" <?=!empty($edit['is_active'])?'checked':''?>><span>QR menüde aktif olarak göster</span></label><?php endif;?>
<button class="primary-action" type="submit"><?=$editId?'Değişiklikleri Kaydet':'Ürünü Ekle'?></button></form><?php endif;?></section>
<section class="enterprise-panel catalog-list"><header><div><span>ÜRÜN LİSTESİ</span><h3><?=count($products)?> ürün</h3></div></header>
<form method="get" class="catalog-filters"><input type="search" name="q" value="<?=ent_e($search)?>" placeholder="Ürün ara…"><select name="category"><option value="0">Tüm kategoriler</option><?php foreach($categories as $c):?><option value="<?=(int)$c['id']?>" <?=$categoryFilter===(int)$c['id']?'selected':''?>><?=ent_e($c['name'])?></option><?php endforeach;?></select><button type="submit">Filtrele</button></form>
<div class="catalog-table-wrap"><table class="catalog-table"><thead><tr><th>Ürün</th><th>Kategori</th><th>Fiyat</th><th>Durum</th><th>İşlem</th></tr></thead><tbody><?php foreach($products as $p):?><tr><td><div class="product-cell"><?php if($hasImage&&!empty($p['image_path'])):?><img src="../../<?=ent_e(ltrim((string)$p['image_path'],'/'))?>" alt=""><?php else:?><span><?=ent_e(mb_substr((string)$p['name'],0,1,'UTF-8'))?></span><?php endif;?><div><strong><?=ent_e($p['name'])?></strong><small><?=ent_e(mb_strimwidth((string)$p['description'],0,75,'…','UTF-8'))?></small></div></div></td><td><?=ent_e($p['category_name'])?></td><td><strong><?=number_format((float)$p['price'],2,',','.')?> ₺</strong></td><td><?php if(!$hasActive||!empty($p['is_active'])):?><span class="status-pill active">Aktif</span><?php else:?><span class="status-pill passive">Pasif</span><?php endif;?></td><td><div class="row-actions"><a href="products.php?edit=<?=(int)$p['id']?>">Düzenle</a><?php if($hasActive):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button type="submit"><?=!empty($p['is_active'])?'Pasifleştir':'Aktifleştir'?></button></form><?php endif;?></div></td></tr><?php endforeach;?><?php if(!$products):?><tr><td colspan="5"><div class="empty-state">Bu filtreye uygun ürün bulunamadı.</div></td></tr><?php endif;?></tbody></table></div></section></div>
<style>
.compact-hero{min-height:auto}.catalog-layout{grid-template-columns:minmax(290px,.7fr) minmax(0,1.5fr);align-items:start}.catalog-form{position:sticky;top:92px}.enterprise-form{display:grid;gap:14px}.enterprise-form label{display:grid;gap:7px;font-weight:750;font-size:13px}.enterprise-form input,.enterprise-form select,.enterprise-form textarea,.catalog-filters input,.catalog-filters select{width:100%;border:1px solid var(--ent-line);background:var(--ent-surface-2);color:var(--ent-text);border-radius:12px;padding:12px 13px;font:inherit}.enterprise-form small{font-weight:500;color:var(--ent-muted)}.form-columns{display:grid;grid-template-columns:1fr 1fr;gap:12px}.switch-row{display:flex!important;align-items:center;grid-template-columns:auto 1fr!important}.switch-row input{width:auto}.catalog-filters{display:grid;grid-template-columns:1fr 190px auto;gap:10px;padding:0 0 16px}.catalog-filters button,.row-actions button{border:1px solid var(--ent-line);background:var(--ent-surface-2);color:var(--ent-text);border-radius:10px;padding:9px 12px;cursor:pointer}.catalog-table-wrap{overflow:auto}.catalog-table{width:100%;border-collapse:collapse;min-width:760px}.catalog-table th,.catalog-table td{padding:13px 11px;border-top:1px solid var(--ent-line);text-align:left;vertical-align:middle}.catalog-table th{color:var(--ent-muted);font-size:11px;letter-spacing:.06em}.product-cell{display:flex;align-items:center;gap:11px;min-width:240px}.product-cell>img,.product-cell>span{width:46px;height:46px;border-radius:12px;object-fit:cover;background:var(--ent-surface-2);display:grid;place-items:center;font-weight:900}.product-cell div{display:grid;gap:3px}.product-cell small{color:var(--ent-muted)}.status-pill{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:800}.status-pill.active{background:#16a34a22;color:#4ade80}.status-pill.passive{background:#ef444422;color:#f87171}.row-actions{display:flex;align-items:center;gap:8px}.row-actions a{color:var(--ent-accent);font-weight:800;text-decoration:none}.row-actions form{margin:0}.ent-alert{padding:13px 15px;border-radius:13px;margin-bottom:14px}.ent-alert.success{background:#16a34a22;color:#4ade80}.ent-alert.danger{background:#ef444422;color:#f87171}@media(max-width:1050px){.catalog-layout{grid-template-columns:1fr}.catalog-form{position:static}}@media(max-width:640px){.catalog-filters,.form-columns{grid-template-columns:1fr}}
</style>
<?php require __DIR__.'/_footer.php'; ?>
