<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ent_media_install();
ent_platform_install();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

$productColumns = ent_columns('products');
$categoryColumns = ent_columns('categories');
$productActiveColumn = in_array('is_active', $productColumns, true) ? 'is_active' : null;
$categoryActiveColumn = in_array('is_active', $categoryColumns, true) ? 'is_active' : null;
$imageColumn = null;
foreach (['image_path', 'image', 'photo', 'image_url'] as $candidate) {
    if (in_array($candidate, $productColumns, true)) { $imageColumn = $candidate; break; }
}

$totalProducts = ent_count('products');
$activeProducts = $productActiveColumn ? ent_count('products', "`{$productActiveColumn}` = 1") : $totalProducts;
$totalCategories = ent_count('categories');
$activeCategories = $categoryActiveColumn ? ent_count('categories', "`{$categoryActiveColumn}` = 1") : $totalCategories;
$missingImages = $imageColumn ? ent_count('products', "`{$imageColumn}` IS NULL OR TRIM(`{$imageColumn}`) = ''") : 0;
$mediaCount = ent_count('enterprise_media');
$mediaBytes = 0;
try { $mediaBytes = (int)ent_db()->query('SELECT COALESCE(SUM(file_size),0) FROM enterprise_media')->fetchColumn(); } catch (Throwable) {}

$recentProducts = [];
if (ent_table_exists('products')) {
    try {
        $nameColumn = in_array('name', $productColumns, true) ? 'name' : ($productColumns[0] ?? 'id');
        $priceColumn = in_array('price', $productColumns, true) ? 'price' : null;
        $select = "p.id, p.`{$nameColumn}` AS name" . ($priceColumn ? ", p.`{$priceColumn}` AS price" : '') . ($imageColumn ? ", p.`{$imageColumn}` AS image" : '');
        $order = in_array('created_at', $productColumns, true) ? 'p.created_at DESC' : 'p.id DESC';
        $recentProducts = ent_db()->query("SELECT {$select} FROM products p ORDER BY {$order} LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $recentProducts = []; }
}

$recentMedia = [];
try { $recentMedia = ent_db()->query('SELECT * FROM enterprise_media ORDER BY id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) {}


$recentAudit = ent_recent_audit(8);
if ($missingImages > 0) {
    ent_notification_upsert('products.missing_images', 'Eksik ürün görselleri', $missingImages . ' üründe görsel bulunmuyor.', 'warning', 'products.php');
}
if ($missingImages === 0 && ent_table_exists('enterprise_notifications')) {
    try { ent_db()->prepare("UPDATE enterprise_notifications SET is_read=1, read_at=? WHERE notification_key='products.missing_images' AND is_read=0")->execute([date('Y-m-d H:i:s')]); } catch (Throwable) {}
}
$activeTables = 0; $openOrders = 0; $todaySales = 0.0; $businessDayStatus = 'Kontrol ediliyor';
try {
    $tableName = ent_table_first_existing(['restaurant_tables','tables','pos_tables']);
    if ($tableName) {
        $cols = ent_columns($tableName);
        foreach (['status','is_occupied','occupied'] as $col) if (in_array($col,$cols,true)) {
            $activeTables = ent_count($tableName, $col === 'status' ? "`{$col}` IN ('occupied','open','busy','dolu')" : "`{$col}`=1"); break;
        }
    }
    $ordersTable = ent_table_first_existing(['orders','tickets','pos_orders']);
    if ($ordersTable) {
        $cols=ent_columns($ordersTable); $where='1=1';
        if(in_array('status',$cols,true)) $where="status IN ('open','pending','active')";
        elseif(in_array('is_closed',$cols,true)) $where='is_closed=0';
        $openOrders=ent_count($ordersTable,$where);
    }
    $paymentsTable=ent_table_first_existing(['payments','pos_payments','order_payments']);
    if($paymentsTable){$cols=ent_columns($paymentsTable);$amount=null;$dateCol=null;foreach(['amount','paid_amount','total'] as $c)if(in_array($c,$cols,true)){$amount=$c;break;}foreach(['created_at','paid_at','payment_date'] as $c)if(in_array($c,$cols,true)){$dateCol=$c;break;}if($amount){$sql="SELECT COALESCE(SUM(`{$amount}`),0) FROM `{$paymentsTable}`".($dateCol?" WHERE DATE(`{$dateCol}`)=CURDATE()":'');$todaySales=(float)ent_db()->query($sql)->fetchColumn();}}
    $bdTable=ent_table_first_existing(['business_days','business_day_sessions']);
    if($bdTable){$cols=ent_columns($bdTable);$where=in_array('status',$cols,true)?"status IN ('open','active')":(in_array('closed_at',$cols,true)?'closed_at IS NULL':'1=0');$businessDayStatus=ent_count($bdTable,$where)>0?'Açık':'Kapalı';}
} catch(Throwable) {}

$healthItems = [
    ['label' => 'QR Menü', 'ok' => ent_setting('qr_menu_enabled', '1') === '1', 'text' => ent_setting('qr_menu_enabled', '1') === '1' ? 'Yayında' : 'Kapalı'],
    ['label' => 'Ürün görselleri', 'ok' => $missingImages === 0, 'text' => $missingImages === 0 ? 'Eksik yok' : $missingImages . ' ürün eksik'],
    ['label' => 'Aktif kategoriler', 'ok' => $activeCategories > 0, 'text' => $activeCategories . ' aktif'],
    ['label' => 'Medya klasörü', 'ok' => is_dir(ent_media_root()) && is_writable(ent_media_root()), 'text' => is_writable(ent_media_root()) ? 'Yazılabilir' : 'İzin gerekli'],
];

require __DIR__ . '/_header.php';
?>
<section class="welcome-card aurora-welcome">
    <div><span>CHERRYHOUSE AURORA</span><h2>Bugünün operasyonunu tek bakışta yönetin.</h2><p>Canlı iş günü, masa, adisyon ve satış bilgileri otomatik güncellenir.</p></div>
    <div class="welcome-actions"><button type="button" class="primary-action" data-command-open>Hızlı İşlem</button><a href="../../cashier/" class="secondary-action">POS’u Aç</a></div>
</section>

<section class="live-stat-grid" data-live-dashboard>
    <article class="live-stat"><span>İş Günü</span><strong data-live="business_day"><?=ent_e($businessDayStatus)?></strong><small>Business Day Engine</small></article>
    <article class="live-stat"><span>Aktif Masa</span><strong data-live="active_tables"><?=number_format($activeTables,0,',','.')?></strong><small>Anlık masa durumu</small></article>
    <article class="live-stat"><span>Açık Adisyon</span><strong data-live="open_orders"><?=number_format($openOrders,0,',','.')?></strong><small>Devam eden hesaplar</small></article>
    <article class="live-stat"><span>Bugünkü Tahsilat</span><strong data-live="today_sales"><?=number_format($todaySales,2,',','.')?> <?=ent_e(ent_setting('currency_symbol','₺'))?></strong><small>Bugünkü ödemeler</small></article>
</section>

<div class="dashboard-grid aurora-grid">
<section class="panel-card">
    <header><div><span>HIZLI İŞLEMLER</span><h3>En sık kullanılan araçlar</h3></div></header>
    <div class="quick-grid aurora-actions">
        <a href="products.php?action=create"><b>＋</b><span>Yeni ürün</span></a>
        <a href="categories.php?action=create"><b>≡</b><span>Yeni kategori</span></a>
        <a href="variants.php"><b>◇</b><span>Varyantlar</span></a>
        <a href="media.php"><b>▧</b><span>Medya yükle</span></a>
        <a href="../qr-experience/"><b>◈</b><span>QR Studio</span></a>
        <a href="../business-day.php"><b>◷</b><span>İş günü</span></a>
        <a href="../backup.php"><b>⇩</b><span>Yedek al</span></a>
        <button type="button" data-command-open><b>⌕</b><span>Sistemde ara</span></button>
    </div>
</section>
<section class="panel-card">
    <header><div><span>SİSTEM DURUMU</span><h3>Hızlı sağlık kontrolü</h3></div><a href="../system-center.php">Detaylar →</a></header>
    <div class="health-list">
    <?php foreach($healthItems as $item):?>
        <div><i class="<?=$item['ok']?'ok':'problem'?>"></i><strong><?=ent_e($item['label'])?></strong><span><?=ent_e($item['text'])?></span></div>
    <?php endforeach;?>
    </div>
</section>
</div>

<div class="dashboard-grid aurora-grid">
<section class="panel-card">
<header><div><span>SON İŞLEMLER</span><h3>Enterprise aktivitesi</h3></div><a href="audit.php">Tümünü gör →</a></header>
<?php if($recentAudit):?><div class="activity-list">
<?php foreach($recentAudit as $log):?><article><i></i><div><strong><?=ent_e($log['summary'])?></strong><span><?=ent_e($log['actor_name'])?> · <?=ent_e($log['module_name'] ?: 'Sistem')?></span></div><time><?=ent_e(date('d.m H:i',strtotime((string)$log['created_at'])))?></time></article><?php endforeach;?>
</div><?php else:?><div class="empty-panel">Yeni Audit Log altyapısı aktif. Bundan sonraki işlemler burada görünecek.</div><?php endif;?>
</section>
<section class="panel-card recent-pages-card">
<header><div><span>SON KULLANILANLAR</span><h3>Kaldığınız yere dönün</h3></div></header>
<div data-recent-pages class="recent-pages"><div class="empty-panel">Ziyaret ettiğiniz sayfalar burada görünecek.</div></div>
</section>
</div>

<section class="panel-card">
<header><div><span>İÇERİK DURUMU</span><h3>Menü ve medya özeti</h3></div></header>
<section class="stat-grid compact-stats">
    <article class="stat-card"><span>Toplam Ürün</span><strong><?=number_format($totalProducts,0,',','.')?></strong><small><?=$activeProducts?> aktif ürün</small></article>
    <article class="stat-card"><span>Kategoriler</span><strong><?=number_format($totalCategories,0,',','.')?></strong><small><?=$activeCategories?> aktif kategori</small></article>
    <article class="stat-card <?=$missingImages>0?'warning':''?>"><span>Eksik Görsel</span><strong><?=number_format($missingImages,0,',','.')?></strong><small><?=$missingImages>0?'Tamamlanması önerilir':'Tüm ürünler hazır'?></small></article>
    <article class="stat-card"><span>Medya</span><strong><?=number_format($mediaCount,0,',','.')?></strong><small><?=ent_human_bytes($mediaBytes)?> depolama</small></article>
</section>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
