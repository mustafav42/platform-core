<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$pageTitle='Menü Yönetimi';
$currentPage='menu';
$pdo=ent_db();
function chw_scalar(PDO $pdo,string $sql): int { try{return (int)$pdo->query($sql)->fetchColumn();}catch(Throwable){return 0;} }
function chw_rows(PDO $pdo,string $sql): array { try{return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable){return [];} }
$totalProducts=chw_scalar($pdo,'SELECT COUNT(*) FROM products');
$activeProducts=chw_scalar($pdo,'SELECT COUNT(*) FROM products WHERE is_active=1');
$totalCategories=chw_scalar($pdo,'SELECT COUNT(*) FROM categories');
$activeCategories=chw_scalar($pdo,'SELECT COUNT(*) FROM categories WHERE is_active=1');
$missingImages=chw_scalar($pdo,"SELECT COUNT(*) FROM products WHERE image_path IS NULL OR TRIM(image_path)=''");
$passiveProducts=max(0,$totalProducts-$activeProducts);
$latest=chw_rows($pdo,'SELECT p.id,p.name,p.price,p.is_active,p.image_path,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC LIMIT 6');
$qrEnabled=function_exists('module_enabled')?module_enabled('qr-menu'):false;
require __DIR__.'/_header.php';
?>
<section class="ch-workspace" data-menu-workspace>
<header class="ch-workspace-head">
  <div>
    <span class="ch-eyebrow">MENÜ YÖNETİMİ WORKSPACE</span>
    <h2>Menünüzün tamamı tek çalışma alanında.</h2>
    <p>Ürünler, kategoriler, varyantlar ve görseller aynı düzen içinde; QR araçları yalnızca modül etkinse görünür.</p>
  </div>
  <div class="ch-workspace-actions">
    <a class="ch-btn ch-btn--secondary" href="products.php?action=create">＋ Yeni Ürün</a>
    <a class="ch-btn ch-btn--primary" href="categories.php?action=create">＋ Yeni Kategori</a>
  </div>
</header>

<nav class="ch-workspace-tabs" aria-label="Menü Yönetimi sekmeleri">
  <a class="active" href="menu.php">Genel Bakış</a>
  <a href="products.php">Ürünler</a>
  <a href="categories.php">Kategoriler</a>
  <a href="variants.php">Varyantlar</a>
  <a href="media.php">Medya Merkezi</a>
  <?php if($qrEnabled):?><a href="../qr-experience/">QR Studio</a><a href="qr-inspector.php">Kalite Kontrolü</a><?php endif;?>
</nav>

<section class="ch-menu-kpis">
  <article class="ch-stat-card"><span>Toplam Ürün</span><strong><?=number_format($totalProducts)?></strong><small><?=number_format($activeProducts)?> aktif ürün</small></article>
  <article class="ch-stat-card"><span>Kategoriler</span><strong><?=number_format($totalCategories)?></strong><small><?=number_format($activeCategories)?> aktif kategori</small></article>
  <article class="ch-stat-card<?=$missingImages>0?' is-warning':''?>"><span>Görsel Bekleyen</span><strong><?=number_format($missingImages)?></strong><small><?=$missingImages>0?'Tamamlanması önerilir':'Tüm ürünler hazır'?></small></article>
  <article class="ch-stat-card"><span>Pasif Ürün</span><strong><?=number_format($passiveProducts)?></strong><small>Menüde gösterilmiyor</small></article>
</section>

<div class="ch-menu-layout">
  <section class="ch-card ch-menu-tools">
    <header class="ch-section-head"><div><span class="ch-eyebrow">ÇALIŞMA ALANLARI</span><h3>Hızlı erişim</h3></div><small>En sık kullanılan işlemler</small></header>
    <div class="ch-tool-grid">
      <a href="products.php"><span>01</span><div><b>Ürün Yönetimi</b><small>Fiyat, görsel, açıklama ve ürün bilgileri</small></div><i>→</i></a>
      <a href="categories.php"><span>02</span><div><b>Kategori Yönetimi</b><small>Görünürlük ve sürükle-bırak sıralama</small></div><i>→</i></a>
      <a href="variants.php"><span>03</span><div><b>Varyantlar</b><small>Boyut, ekstra ve fiyat seçenekleri</small></div><i>→</i></a>
      <a href="media.php"><span>04</span><div><b>Medya Merkezi</b><small>Görselleri yükleyin, düzenleyin ve seçin</small></div><i>→</i></a>
      <?php if($qrEnabled):?><a href="../qr-experience/"><span>05</span><div><b>QR Experience Studio</b><small>Tema, Hero ve canlı menü görünümü</small></div><i>→</i></a><?php endif;?>
      <a href="reorder.php"><span><?=$qrEnabled?'06':'05'?></span><div><b>Sıralama Merkezi</b><small>Ürün ve kategorilerin görünüm sırasını yönetin</small></div><i>→</i></a>
    </div>
  </section>

  <aside class="ch-card ch-menu-status">
    <header class="ch-section-head"><div><span class="ch-eyebrow">MENÜ DURUMU</span><h3>Hazırlık özeti</h3></div></header>
    <div class="ch-status-list">
      <div><span>Aktif ürün oranı</span><b><?=$totalProducts>0?round($activeProducts/$totalProducts*100):0?>%</b></div>
      <div><span>Görsel tamamlama</span><b><?=$totalProducts>0?round(($totalProducts-$missingImages)/$totalProducts*100):100?>%</b></div>
      <div><span>QR Menü</span><b class="<?=$qrEnabled?'ok':'muted'?>"><?=$qrEnabled?'Etkin':'Kapalı'?></b></div>
      <div><span>Son güncelleme</span><b><?=date('d.m.Y H:i')?></b></div>
    </div>
    <div class="ch-menu-note"><b>Tek çalışma alanı</b><p>QR modülü kapalı olduğunda QR Studio, Hero ve kalite araçları bu Workspace içinde otomatik olarak gizlenir.</p></div>
  </aside>
</div>

<section class="ch-card ch-latest-products">
  <header class="ch-section-head"><div><span class="ch-eyebrow">SON EKLENENLER</span><h3>Yeni ürünler</h3></div><a href="products.php">Tüm ürünleri gör →</a></header>
  <div class="ch-product-strip">
    <?php foreach($latest as $product):$img=trim((string)($product['image_path']??''));?>
      <a href="products.php?edit=<?=(int)$product['id']?>">
        <div class="ch-product-image"><?php if($img!==''):?><img src="../../<?=ent_e(ltrim($img,'/'))?>" alt=""><?php else:?><span>Görsel yok</span><?php endif;?></div>
        <div><b><?=ent_e($product['name'])?></b><small><?=ent_e((string)($product['category_name']??'Kategorisiz'))?></small><strong><?=number_format((float)$product['price'],2,',','.')?> ₺</strong></div>
      </a>
    <?php endforeach;?>
    <?php if(!$latest):?><div class="ch-empty"><b>Henüz ürün bulunmuyor.</b><a class="ch-btn ch-btn--primary" href="products.php?action=create">İlk ürünü ekle</a></div><?php endif;?>
  </div>
</section>
</section>
<?php require __DIR__.'/_footer.php';?>
