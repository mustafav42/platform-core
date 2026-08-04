<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
$pdo=db();
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('./');
if(function_exists('require_permission')) require_permission('catalog.manage');

function cat_money(float $v): string { return number_format($v,2,',','.').' ₺'; }
function cat_redirect(string $tab, string $msg='', string $type='ok'): never {
    $q=['tab'=>$tab]; if($msg!==''){$q['msg']=$msg;$q['type']=$type;} redirect('catalog.php?'.http_build_query($q));
}
function upload_product_image(array $file): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Görsel yüklenemedi.');
    if((int)($file['size']??0)>32*1024*1024) throw new RuntimeException('Görsel en fazla 32 MB olabilir.');
    $tmp=(string)($file['tmp_name']??'');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('Yalnızca JPG, PNG veya WebP yükleyebilirsiniz.');
    $dir=BASE_PATH.'/uploads/products'; if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)) throw new RuntimeException('Görsel klasörü oluşturulamadı.');
    $name='product-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$ext;
    if(!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('Görsel kaydedilemedi.');
    return 'uploads/products/'.$name;
}
function unlink_local_image(?string $path): void {
    $path=trim((string)$path); if($path===''||!str_starts_with($path,'uploads/products/')) return;
    $full=BASE_PATH.'/'.$path; if(is_file($full)) @unlink($full);
}

$tab=in_array(($_GET['tab']??'products'),['products','categories'],true)?(string)$_GET['tab']:'products';
$error='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
   verify_csrf();
   $action=(string)($_POST['action']??'');
   if($action==='save_product'){
      $id=(int)($_POST['id']??0); $categoryId=(int)($_POST['category_id']??0); $name=trim((string)($_POST['name']??''));
      $price=(float)($_POST['price']??0); $description=trim((string)($_POST['description']??'')); $sort=(int)($_POST['sort_order']??0); $active=isset($_POST['is_active'])?1:0;
      if($categoryId<1||$name==='') throw new RuntimeException('Kategori ve ürün adı zorunludur.');
      $q=$pdo->prepare('SELECT id FROM categories WHERE id=? LIMIT 1');$q->execute([$categoryId]);if(!$q->fetchColumn())throw new RuntimeException('Kategori bulunamadı.');
      $selectedImage=ltrim(trim((string)($_POST['image_path']??'')),'/');
      $newImage=upload_product_image($_FILES['image']??[]);
      if($id>0){
         $q=$pdo->prepare('SELECT image_path FROM products WHERE id=? LIMIT 1');$q->execute([$id]);$old=$q->fetchColumn();if($old===false)throw new RuntimeException('Ürün bulunamadı.');
         $image=(string)$old;
         if(isset($_POST['remove_image'])&&$_POST['remove_image']==='1'){unlink_local_image($image);$image='';}
         if($selectedImage!==''){$image=$selectedImage;}
         if($newImage){unlink_local_image($image);$image=$newImage;}
         $pdo->prepare('UPDATE products SET category_id=?,name=?,description=?,price=?,image_path=?,sort_order=?,is_active=? WHERE id=?')->execute([$categoryId,$name,$description,$price,$image!==''?$image:null,$sort,$active,$id]);
         if(function_exists('audit_log'))audit_log('product_updated','Ürün güncellendi.',['product_id'=>$id]);
         cat_redirect('products','Ürün güncellendi.');
      }
      $initialImage=$newImage?:($selectedImage!==''?$selectedImage:null);
      $pdo->prepare('INSERT INTO products(category_id,name,description,price,image_path,sort_order,is_active) VALUES(?,?,?,?,?,?,?)')->execute([$categoryId,$name,$description,$price,$initialImage,$sort,$active]);
      $id=(int)$pdo->lastInsertId(); if(function_exists('audit_log'))audit_log('product_created','Ürün oluşturuldu.',['product_id'=>$id]);
      cat_redirect('products','Ürün eklendi.');
   }
   if($action==='toggle_product'){
      $id=(int)$_POST['id'];$pdo->prepare('UPDATE products SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);cat_redirect('products','Ürün durumu değiştirildi.');
   }
   if($action==='duplicate_product'){
      $id=(int)$_POST['id'];$q=$pdo->prepare('SELECT category_id,name,description,price,image_path,sort_order FROM products WHERE id=?');$q->execute([$id]);$p=$q->fetch();if(!$p)throw new RuntimeException('Ürün bulunamadı.');
      $pdo->prepare('INSERT INTO products(category_id,name,description,price,image_path,sort_order,is_active) VALUES(?,?,?,?,?,?,1)')->execute([(int)$p['category_id'],$p['name'].' (Kopya)',$p['description'],$p['price'],$p['image_path'],(int)$p['sort_order']+1]);cat_redirect('products','Ürün kopyalandı.');
   }
   if($action==='delete_product'){
      $id=(int)$_POST['id'];$q=$pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id=?');$q->execute([$id]);$used=(int)$q->fetchColumn()>0;
      if($used){$pdo->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);cat_redirect('products','Geçmiş satışlarda kullanıldığı için ürün silinmedi, pasife alındı.');}
      $q=$pdo->prepare('SELECT image_path FROM products WHERE id=?');$q->execute([$id]);$img=$q->fetchColumn();$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);unlink_local_image(is_string($img)?$img:null);cat_redirect('products','Ürün silindi.');
   }
   if($action==='save_category'){
      $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));$sort=(int)($_POST['sort_order']??0);$active=isset($_POST['is_active'])?1:0;
      if($name==='')throw new RuntimeException('Kategori adı zorunludur.');
      if($id>0){$pdo->prepare('UPDATE categories SET name=?,description=?,sort_order=?,is_active=? WHERE id=?')->execute([$name,$description,$sort,$active,$id]);cat_redirect('categories','Kategori güncellendi.');}
      $pdo->prepare('INSERT INTO categories(name,description,sort_order,is_active) VALUES(?,?,?,?)')->execute([$name,$description,$sort,$active]);cat_redirect('categories','Kategori eklendi.');
   }
   if($action==='toggle_category'){$pdo->prepare('UPDATE categories SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([(int)$_POST['id']]);cat_redirect('categories','Kategori durumu değiştirildi.');}
   if($action==='delete_category'){
      $id=(int)$_POST['id'];$q=$pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');$q->execute([$id]);
      if((int)$q->fetchColumn()>0){$pdo->prepare('UPDATE categories SET is_active=0 WHERE id=?')->execute([$id]);cat_redirect('categories','Kategori ürün içerdiği için silinmedi, pasife alındı.');}
      $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);cat_redirect('categories','Kategori silindi.');
   }
 }
}catch(Throwable $e){$error=$e->getMessage();}

$editProduct=null;$editCategory=null;
if(isset($_GET['edit_product'])){$q=$pdo->prepare('SELECT * FROM products WHERE id=?');$q->execute([(int)$_GET['edit_product']]);$editProduct=$q->fetch()?:null;$tab='products';}
if(isset($_GET['edit_category'])){$q=$pdo->prepare('SELECT * FROM categories WHERE id=?');$q->execute([(int)$_GET['edit_category']]);$editCategory=$q->fetch()?:null;$tab='categories';}
$categories=$pdo->query('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) product_count FROM categories c ORDER BY c.sort_order,c.id')->fetchAll();
$products=$pdo->query('SELECT p.*,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY c.sort_order,p.sort_order,p.id')->fetchAll();
$msg=(string)($_GET['msg']??'');
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Menü Yönetimi</title><link rel="stylesheet" href="assets/catalog.css?v=2840"><link rel="stylesheet" href="assets/ch-media-library.css?v=3033"></head>
<body><div class="shell"><aside><a class="brand" href="./">🍒 <span>CherryHouse</span></a><nav><a href="./">← Ana Panel</a><a href="menu-center.php">Menü Merkezi</a><a class="<?=$tab==='products'?'active':''?>" href="catalog.php?tab=products">Ürünler</a><a class="<?=$tab==='categories'?'active':''?>" href="catalog.php?tab=categories">Kategoriler</a><a href="../" target="_blank">QR Menüyü Aç ↗</a></nav></aside><main>
<header><div><div class="eyebrow">MENÜ YÖNETİMİ</div><h1><?=$tab==='products'?'Ürünler':'Kategoriler'?></h1><p><?=$tab==='products'?'QR menü ve POS ürünlerini tek merkezden yönetin.':'Menü kategorilerini düzenleyin, aktif veya pasif yapın.'?></p></div><button class="primary" type="button" onclick="openEditor()">+ <?=$tab==='products'?'Yeni Ürün':'Yeni Kategori'?></button></header>
<?php if($msg):?><div class="notice"><?=e($msg)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<div class="stats"><?php if($tab==='products'):?><div><small>Toplam ürün</small><b><?=count($products)?></b></div><div><small>Aktif ürün</small><b><?=count(array_filter($products,fn($p)=>(int)$p['is_active']===1))?></b></div><div><small>Görselsiz</small><b><?=count(array_filter($products,fn($p)=>empty($p['image_path'])))?></b></div><div><small>Kategori</small><b><?=count($categories)?></b></div><?php else:?><div><small>Toplam kategori</small><b><?=count($categories)?></b></div><div><small>Aktif kategori</small><b><?=count(array_filter($categories,fn($c)=>(int)$c['is_active']===1))?></b></div><div><small>Toplam ürün</small><b><?=count($products)?></b></div><?php endif;?></div>
<section class="panel"><div class="toolbar"><input id="search" type="search" placeholder="Ara..."><select id="statusFilter"><option value="">Tüm durumlar</option><option value="active">Aktif</option><option value="passive">Pasif</option></select><?php if($tab==='products'):?><select id="categoryFilter"><option value="">Tüm kategoriler</option><?php foreach($categories as $c):?><option value="<?=e(mb_strtolower($c['name'],'UTF-8'))?>"><?=e($c['name'])?></option><?php endforeach;?></select><?php endif;?></div>
<div class="table-wrap"><table><thead><tr><?php if($tab==='products'):?><th>Görsel</th><th>Ürün</th><th>Kategori</th><th>Fiyat</th><th>Durum</th><th class="actions">İşlemler</th><?php else:?><th>Kategori</th><th>Ürün</th><th>Sıra</th><th>Durum</th><th class="actions">İşlemler</th><?php endif;?></tr></thead><tbody>
<?php if($tab==='products'): foreach($products as $p):?><tr data-row data-search="<?=e(mb_strtolower($p['name'].' '.$p['category_name'],'UTF-8'))?>" data-status="<?=$p['is_active']?'active':'passive'?>" data-category="<?=e(mb_strtolower((string)$p['category_name'],'UTF-8'))?>"><td><?php if($p['image_path']):?><img class="thumb" src="../<?=e($p['image_path'])?>" alt=""><?php else:?><div class="thumb empty">📷</div><?php endif;?></td><td><strong><?=e($p['name'])?></strong><small><?=e(mb_strimwidth((string)$p['description'],0,80,'…','UTF-8'))?></small></td><td><?=e((string)$p['category_name'])?></td><td><strong><?=cat_money((float)$p['price'])?></strong></td><td><span class="pill <?=$p['is_active']?'on':'off'?>"><?=$p['is_active']?'Aktif':'Pasif'?></span></td><td class="actions"><a class="icon" title="Düzenle" href="?tab=products&edit_product=<?=$p['id']?>">✏️</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="duplicate_product"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="icon" title="Kopyala">📄</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="icon" title="Aktif/Pasif">⏯</button></form><form method="post" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz? Geçmiş satışı varsa pasife alınacaktır.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="icon danger" title="Sil">🗑</button></form></td></tr><?php endforeach; else: foreach($categories as $c):?><tr data-row data-search="<?=e(mb_strtolower($c['name'].' '.$c['description'],'UTF-8'))?>" data-status="<?=$c['is_active']?'active':'passive'?>"><td><strong><?=e($c['name'])?></strong><small><?=e(mb_strimwidth((string)$c['description'],0,90,'…','UTF-8'))?></small></td><td><strong><?=(int)$c['product_count']?></strong></td><td><?=(int)$c['sort_order']?></td><td><span class="pill <?=$c['is_active']?'on':'off'?>"><?=$c['is_active']?'Aktif':'Pasif'?></span></td><td class="actions"><a class="icon" title="Düzenle" href="?tab=categories&edit_category=<?=$c['id']?>">✏️</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_category"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="icon">⏯</button></form><form method="post" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz? Ürün içeriyorsa pasife alınacaktır.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="icon danger">🗑</button></form></td></tr><?php endforeach; endif;?></tbody></table></div></section>
</main></div>
<div class="modal <?=($editProduct||$editCategory)?'show':''?>" id="editor"><div class="dialog"><div class="dialog-head"><div><small><?=$tab==='products'?'ÜRÜN':'KATEGORİ'?></small><h2><?=$tab==='products'?($editProduct?'Ürünü Düzenle':'Yeni Ürün'):($editCategory?'Kategoriyi Düzenle':'Yeni Kategori')?></h2></div><a class="close" href="catalog.php?tab=<?=$tab?>">×</a></div>
<?php if($tab==='products'): $p=$editProduct?:['id'=>0,'category_id'=>'','name'=>'','description'=>'','price'=>'','image_path'=>'','sort_order'=>0,'is_active'=>1];?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><div class="form-grid"><label>Kategori<select name="category_id" required><option value="">Kategori seçin</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=(int)$p['category_id']===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label><label>Ürün adı<input name="name" value="<?=e((string)$p['name'])?>" required></label><label>Fiyat<input name="price" type="number" min="0" step="0.01" value="<?=e((string)$p['price'])?>" required></label><label>Sıra<input name="sort_order" type="number" value="<?=(int)$p['sort_order']?>"></label><label class="wide">Açıklama<textarea name="description" rows="4"><?=e((string)$p['description'])?></textarea></label><label class="wide">Ürün görseli<input data-media-picker name="image_path" value="<?=e((string)$p['image_path'])?>"><span>Ortam Kütüphanesinden seçebilir veya aynı pencerede yeni görsel yükleyebilirsin.</span></label><label class="wide upload">Bilgisayardan doğrudan yükle<input id="imageInput" name="image" type="file" accept="image/jpeg,image/png,image/webp"><span>JPG, PNG veya WebP · En fazla 32 MB</span></label><?php if($p['image_path']):?><div class="current-image wide"><img src="../<?=e((string)$p['image_path'])?>"><label><input type="checkbox" name="remove_image" value="1"> Mevcut görseli kaldır</label></div><?php endif;?><div id="previewWrap" class="current-image wide" hidden><img id="preview" alt="Önizleme"></div><label class="switch wide"><input type="checkbox" name="is_active" value="1" <?=$p['is_active']?'checked':''?>><span></span> Aktif olarak yayınla</label></div><div class="dialog-actions"><a class="secondary" href="catalog.php?tab=products">İptal</a><button class="primary">Kaydet</button></div></form>
<?php else: $c=$editCategory?:['id'=>0,'name'=>'','description'=>'','sort_order'=>0,'is_active'=>1];?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><div class="form-grid"><label class="wide">Kategori adı<input name="name" value="<?=e((string)$c['name'])?>" required></label><label class="wide">Açıklama<textarea name="description" rows="4"><?=e((string)$c['description'])?></textarea></label><label>Sıra<input name="sort_order" type="number" value="<?=(int)$c['sort_order']?>"></label><label class="switch"><input type="checkbox" name="is_active" value="1" <?=$c['is_active']?'checked':''?>><span></span> Aktif</label></div><div class="dialog-actions"><a class="secondary" href="catalog.php?tab=categories">İptal</a><button class="primary">Kaydet</button></div></form><?php endif;?></div></div>
<script>const modal=document.getElementById('editor');function openEditor(){modal.classList.add('show')}modal.addEventListener('click',e=>{if(e.target===modal)location.href='catalog.php?tab=<?=$tab?>'});const search=document.getElementById('search'),statusF=document.getElementById('statusFilter'),catF=document.getElementById('categoryFilter');function filter(){const q=(search.value||'').toLocaleLowerCase('tr-TR'),s=statusF.value,c=catF?catF.value:'';document.querySelectorAll('[data-row]').forEach(r=>{r.hidden=!((!q||r.dataset.search.includes(q))&&(!s||r.dataset.status===s)&&(!c||r.dataset.category===c))})}search.addEventListener('input',filter);statusF.addEventListener('change',filter);if(catF)catF.addEventListener('change',filter);const img=document.getElementById('imageInput');if(img)img.addEventListener('change',()=>{const f=img.files[0];if(!f)return;document.getElementById('preview').src=URL.createObjectURL(f);document.getElementById('previewWrap').hidden=false});</script><script src="assets/ch-media-library.js?v=3033"></script></body></html>
