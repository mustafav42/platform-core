<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$pageTitle='Menü Merkezi';
$currentPage='menu';
$pdo=ent_db();
function em_scalar(PDO $pdo,string $sql): int { try{return (int)$pdo->query($sql)->fetchColumn();}catch(Throwable){return 0;} }
function em_rows(PDO $pdo,string $sql): array { try{return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable){return [];} }
$totalProducts=em_scalar($pdo,'SELECT COUNT(*) FROM products');
$activeProducts=em_scalar($pdo,'SELECT COUNT(*) FROM products WHERE is_active=1');
$totalCategories=em_scalar($pdo,'SELECT COUNT(*) FROM categories');
$missingImages=em_scalar($pdo,"SELECT COUNT(*) FROM products WHERE image_path IS NULL OR image_path=''");
$latest=em_rows($pdo,'SELECT p.id,p.name,p.price,p.is_active,p.image_path,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC LIMIT 8');
require __DIR__.'/_header.php';
?>
<section class="menu-hub-hero">
  <div><small>CHERRYHOUSE MENU OS</small><h2>Menünüzü tek çalışma alanından yönetin.</h2><p>Ürün, kategori, varyant, görsel ve QR görünümünü dağınık ekranlara geçmeden yönetin.</p></div>
  <div class="menu-hub-actions"><a class="ch-btn ch-btn-primary" href="products.php">Yeni ürün</a><a class="ch-btn" href="../qr-experience/">QR Studio’yu aç</a></div>
</section>
<section class="menu-hub-stats">
 <article><span>Toplam ürün</span><strong><?=$totalProducts?></strong><small><?=$activeProducts?> aktif</small></article>
 <article><span>Kategori</span><strong><?=$totalCategories?></strong><small>Sürükle-bırak sıralama</small></article>
 <article class="<?=$missingImages>0?'attention':''?>"><span>Görsel bekleyen</span><strong><?=$missingImages?></strong><small><?=$missingImages>0?'Tamamlanması önerilir':'Tüm ürünler hazır'?></small></article>
 <article><span>Canlı menü</span><strong>Aktif</strong><small><a href="../../" target="_blank">Yeni sekmede aç ↗</a></small></article>
</section>
<section class="menu-hub-grid">
 <a href="products.php"><i>01</i><div><strong>Ürün Yönetimi</strong><span>Fiyat, açıklama, görsel, alerjen ve kalori bilgileri</span></div><b>→</b></a>
 <a href="categories.php"><i>02</i><div><strong>Kategori Yönetimi</strong><span>Görünürlük ve sürükle-bırak sıralama</span></div><b>→</b></a>
 <a href="variants.php"><i>03</i><div><strong>Varyantlar</strong><span>Boyut, ekstra ve fiyat seçenekleri</span></div><b>→</b></a>
 <a href="media.php"><i>04</i><div><strong>Medya Merkezi</strong><span>Ürün ve kategori görsellerini tek merkezden yönetin</span></div><b>→</b></a>
 <a href="../qr-experience/"><i>05</i><div><strong>QR Experience Studio</strong><span>Canlı menü tasarımını düzenleyin ve yayınlayın</span></div><b>→</b></a>
 <a href="reorder.php"><i>06</i><div><strong>Sıralama Merkezi</strong><span>Kategorileri ve ürünleri hızlıca sıralayın</span></div><b>→</b></a>
</section>
<section class="menu-hub-latest"><header><div><small>SON EKLENENLER</small><h3>Son ürünler</h3></div><a href="products.php">Tüm ürünleri gör →</a></header><div class="latest-products">
<?php foreach($latest as $product): $img=trim((string)($product['image_path']??'')); ?>
<a href="products.php?edit=<?=(int)$product['id']?>"><div class="latest-image"><?php if($img!==''):?><img src="../../<?=ent_e(ltrim($img,'/'))?>" alt=""><?php else:?><span>Görsel yok</span><?php endif;?></div><div><strong><?=ent_e($product['name'])?></strong><small><?=ent_e($product['category_name']??'Kategorisiz')?></small><b><?=number_format((float)$product['price'],2,',','.')?> ₺</b></div></a>
<?php endforeach; ?>
<?php if(!$latest):?><p class="empty-state">Henüz ürün bulunmuyor.</p><?php endif;?>
</div></section>
<style>
.menu-hub-hero{background:linear-gradient(135deg,#241d1a,#171311);color:#fff;border-radius:28px;padding:34px 38px;display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin-bottom:22px}.menu-hub-hero small{letter-spacing:.18em;color:#d7b18e;font-weight:800}.menu-hub-hero h2{font-family:Georgia,serif;font-size:clamp(34px,4vw,58px);line-height:.98;max-width:760px;margin:12px 0 16px}.menu-hub-hero p{color:#c9c1bc;max-width:720px;margin:0}.menu-hub-actions{display:flex;gap:10px;flex-wrap:wrap;min-width:max-content}.ch-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 18px;border-radius:14px;background:#fff;color:#1f1917;text-decoration:none;font-weight:800}.ch-btn-primary{background:#a4233c;color:#fff}.menu-hub-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}.menu-hub-stats article{background:var(--surface,#fff);border:1px solid var(--border,#e9e2de);border-radius:20px;padding:20px}.menu-hub-stats span,.menu-hub-stats small{display:block;color:#81746e}.menu-hub-stats strong{display:block;font-size:32px;margin:8px 0}.menu-hub-stats .attention{border-color:#e7b67a;background:#fff9f1}.menu-hub-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:28px}.menu-hub-grid>a{display:grid;grid-template-columns:48px 1fr auto;gap:16px;align-items:center;background:#fff;border:1px solid #e9e2de;border-radius:20px;padding:20px;text-decoration:none;color:#201917;transition:.2s}.menu-hub-grid>a:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(36,29,26,.08)}.menu-hub-grid i{width:48px;height:48px;border-radius:15px;background:#f3ece8;display:grid;place-items:center;font-style:normal;font-weight:900;color:#9a2640}.menu-hub-grid strong,.menu-hub-grid span{display:block}.menu-hub-grid span{color:#81746e;margin-top:4px}.menu-hub-grid b{font-size:24px}.menu-hub-latest{background:#fff;border:1px solid #e9e2de;border-radius:24px;padding:24px}.menu-hub-latest>header{display:flex;justify-content:space-between;align-items:end;margin-bottom:18px}.menu-hub-latest h3{font-size:26px;margin:4px 0}.latest-products{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.latest-products>a{text-decoration:none;color:#201917;border:1px solid #eee7e2;border-radius:18px;overflow:hidden;background:#fff}.latest-image{aspect-ratio:4/3;background:#f2ece8;display:grid;place-items:center;color:#8d817a}.latest-image img{width:100%;height:100%;object-fit:cover}.latest-products>a>div:last-child{padding:14px}.latest-products strong,.latest-products small,.latest-products b{display:block}.latest-products small{color:#81746e;margin:5px 0 10px}@media(max-width:1000px){.menu-hub-stats{grid-template-columns:repeat(2,1fr)}.latest-products{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.menu-hub-hero{align-items:flex-start;flex-direction:column;padding:26px}.menu-hub-actions{min-width:0}.menu-hub-grid{grid-template-columns:1fr}.menu-hub-stats{grid-template-columns:1fr 1fr}.latest-products{grid-template-columns:1fr 1fr}}@media(max-width:480px){.menu-hub-stats,.latest-products{grid-template-columns:1fr}}
</style>
<?php require __DIR__.'/_footer.php'; ?>
