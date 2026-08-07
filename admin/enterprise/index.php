<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ent_media_install();
ent_platform_install();

$pageTitle = 'Genel Bakış';
$currentPage = 'dashboard';
$pdo = ent_db();

function cc_table_name(array $names): ?string { return ent_table_first_existing($names); }
function cc_cols(?string $table): array { return $table ? ent_columns($table) : []; }
function cc_sum_today(PDO $pdo, ?string $table, array $amountCandidates, array $dateCandidates): float {
    if (!$table) return 0.0; $cols=ent_columns($table); $amount=null; $date=null;
    foreach($amountCandidates as $c) if(in_array($c,$cols,true)){ $amount=$c; break; }
    foreach($dateCandidates as $c) if(in_array($c,$cols,true)){ $date=$c; break; }
    if(!$amount) return 0.0;
    try { return (float)$pdo->query("SELECT COALESCE(SUM(`{$amount}`),0) FROM `{$table}`".($date?" WHERE DATE(`{$date}`)=CURDATE()":''))->fetchColumn(); } catch(Throwable){ return 0.0; }
}

$productCols=ent_columns('products'); $categoryCols=ent_columns('categories');
$totalProducts=ent_count('products'); $totalCategories=ent_count('categories');
$productActive=in_array('is_active',$productCols,true)?ent_count('products','is_active=1'):$totalProducts;
$imageCol=null; foreach(['image_path','image','photo','image_url'] as $c) if(in_array($c,$productCols,true)){ $imageCol=$c; break; }
$missingImages=$imageCol?ent_count('products',"`{$imageCol}` IS NULL OR TRIM(`{$imageCol}`)=''"):0;

$tablesTable=cc_table_name(['restaurant_tables','tables','pos_tables']); $tableCols=cc_cols($tablesTable);
$activeTables=0; $totalTables=$tablesTable?ent_count($tablesTable):0;
if($tablesTable){
    foreach(['is_active','enabled','active'] as $c) if(in_array($c,$tableCols,true)){ $totalTables=ent_count($tablesTable,"`{$c}`=1"); break; }
    foreach(['status','is_occupied','occupied'] as $c) if(in_array($c,$tableCols,true)){
        $activeTables=ent_count($tablesTable,$c==='status'?"`{$c}` IN ('occupied','open','busy','dolu','active')":"`{$c}`=1"); break;
    }
}
$ordersTable=cc_table_name(['orders','tickets','pos_orders']); $orderCols=cc_cols($ordersTable); $openOrders=0;
if($ordersTable){ $where='1=1'; if(in_array('status',$orderCols,true))$where="status IN ('open','pending','active')"; elseif(in_array('is_closed',$orderCols,true))$where='is_closed=0'; $openOrders=ent_count($ordersTable,$where); }
$paymentsTable=cc_table_name(['payments','pos_payments','order_payments']);
$todaySales=cc_sum_today($pdo,$paymentsTable,['amount','paid_amount','total'],['created_at','paid_at','payment_date']);

$businessDayOpen=false; $bdTable=cc_table_name(['business_days','business_day_sessions']);
if($bdTable){ $cols=ent_columns($bdTable); $where=in_array('status',$cols,true)?"status IN ('open','active')":(in_array('closed_at',$cols,true)?'closed_at IS NULL':'1=0'); $businessDayOpen=ent_count($bdTable,$where)>0; }

$staffTable=cc_table_name(['users','staff','employees']); $activeStaff=0;
if($staffTable){ $cols=ent_columns($staffTable); $where='1=1'; foreach(['is_active','active','enabled'] as $c)if(in_array($c,$cols,true)){$where="`{$c}`=1";break;} $activeStaff=ent_count($staffTable,$where); }

$recentAudit=ent_recent_audit(7);
$modulesAll=modules()->all(); $enabledModules=array_filter($modulesAll,static fn($m)=>!empty($m['enabled']));
$moduleCards=[];
$moduleLabels=['qr-menu'=>'QR Menü','cashier'=>'Kasiyer','waiter'=>'Garson','kds'=>'KDS','reports'=>'Raporlar','tables'=>'Masalar','kitchen-printer'=>'Mutfak Yazıcısı'];
foreach($moduleLabels as $id=>$label){ if(isset($modulesAll[$id])) $moduleCards[]=['id'=>$id,'label'=>$label,'enabled'=>!empty($modulesAll[$id]['enabled'])]; }

$topProducts=[];
try{
    $items=cc_table_name(['order_items','ticket_items','pos_order_items']);
    if($items && ent_table_exists('products')){
        $ic=ent_columns($items); $pid=null;$qty=null; foreach(['product_id','menu_item_id'] as $c)if(in_array($c,$ic,true)){$pid=$c;break;} foreach(['quantity','qty'] as $c)if(in_array($c,$ic,true)){$qty=$c;break;}
        $pc=ent_columns('products'); $pn=in_array('name',$pc,true)?'name':null;
        if($pid&&$qty&&$pn) $topProducts=$pdo->query("SELECT p.`{$pn}` name, SUM(i.`{$qty}`) qty FROM `{$items}` i JOIN products p ON p.id=i.`{$pid}` GROUP BY i.`{$pid}`,p.`{$pn}` ORDER BY qty DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
}catch(Throwable){}

$hour=(int)date('G'); $greeting=$hour<12?'Günaydın':($hour<18?'İyi günler':'İyi akşamlar');
$currency=ent_setting('currency_symbol','₺');
require __DIR__ . '/_header.php';
?>
<section class="cc-welcome">
  <div><span class="cc-eyebrow">CHERRYHOUSE CONTROL CENTER</span><h2><?=$greeting?> 👋</h2><p>İşletmenizin bugünkü durumunu tek bakışta takip edin.</p></div>
  <div class="cc-day-state <?=$businessDayOpen?'is-open':'is-closed'?>"><i></i><div><small>İş Günü</small><strong><?=$businessDayOpen?'Açık':'Kapalı'?></strong></div><a href="../business-day.php">Yönet</a></div>
</section>

<section class="cc-kpi-grid" data-live-dashboard>
  <article class="cc-kpi cc-kpi-purple"><div class="cc-kpi-icon">₺</div><div><span>Bugünkü Ciro</span><strong data-live="today_sales"><?=number_format($todaySales,2,',','.')?> <?=$currency?></strong><small>Bugünkü tahsilatlar</small></div></article>
  <article class="cc-kpi cc-kpi-green"><div class="cc-kpi-icon">▦</div><div><span>Açık Masa</span><strong><b data-live="active_tables"><?=number_format($activeTables)?></b> / <?=number_format($totalTables)?></strong><small>Anlık masa durumu</small></div></article>
  <article class="cc-kpi cc-kpi-orange"><div class="cc-kpi-icon">▤</div><div><span>Aktif Adisyon</span><strong data-live="open_orders"><?=number_format($openOrders)?></strong><small>Devam eden hesaplar</small></div></article>
  <article class="cc-kpi cc-kpi-blue"><div class="cc-kpi-icon">♙</div><div><span>Aktif Personel</span><strong><?=number_format($activeStaff)?></strong><small>Kayıtlı aktif hesaplar</small></div></article>
</section>

<section class="cc-dashboard-layout">
  <div class="cc-dashboard-main">
    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>HIZLI İŞLEMLER</span><h3>Sık kullanılan araçlar</h3></div><button class="ch-button ch-button-ghost" type="button" data-command-open>Tümünü ara</button></header>
      <div class="cc-quick-actions">
        <a href="products.php?action=create"><i>＋</i><b>Yeni Ürün</b><small>Menüye ürün ekle</small></a>
        <a href="categories.php?action=create"><i>≡</i><b>Yeni Kategori</b><small>Kategori oluştur</small></a>
        <?php if(module_enabled('tables',true)):?><a href="../?page=tables"><i>▦</i><b>Masalar</b><small>Masa yönetimi</small></a><?php endif;?>
        <a href="../?page=staff"><i>♙</i><b>Personel</b><small>Kullanıcıları yönet</small></a>
        <?php if(module_enabled('qr-menu',true)):?><a href="../qr-experience/"><i>◈</i><b>QR Studio</b><small>Menü görünümü</small></a><?php endif;?>
        <a href="backup.php"><i>⇩</i><b>Yedek Al</b><small>Yedekle ve geri yükle</small></a>
      </div>
    </article>

    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>CANLI AKIŞ</span><h3>Son işlemler</h3></div><a href="audit.php">Tümünü gör →</a></header>
      <?php if($recentAudit):?><div class="cc-activity-list"><?php foreach($recentAudit as $log):?>
        <div class="cc-activity"><i></i><div><b><?=ent_e($log['summary'])?></b><small><?=ent_e($log['actor_name'])?> · <?=ent_e($log['module_name']?:'Sistem')?></small></div><time><?=ent_e(date('H:i',strtotime((string)$log['created_at'])))?></time></div>
      <?php endforeach;?></div><?php else:?><div class="ch-empty">Henüz gösterilecek yeni işlem bulunmuyor.</div><?php endif;?>
    </article>

    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>MENÜ DURUMU</span><h3>İçerik özeti</h3></div><a href="products.php">Ürünlere git →</a></header>
      <div class="cc-content-stats"><div><span>Toplam ürün</span><b><?=number_format($totalProducts)?></b><small><?=number_format($productActive)?> aktif</small></div><div><span>Kategori</span><b><?=number_format($totalCategories)?></b><small>Menü yapısı</small></div><div class="<?=$missingImages?'has-warning':''?>"><span>Eksik görsel</span><b><?=number_format($missingImages)?></b><small><?=$missingImages?'Tamamlanması önerilir':'Tüm ürünler hazır'?></small></div></div>
    </article>
  </div>

  <aside class="cc-dashboard-side">
    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>MODÜL DURUMU</span><h3>Aktif servisler</h3></div><a href="../module-center.php">Yönet →</a></header>
      <div class="cc-module-list"><?php foreach($moduleCards as $m):?><div><i class="<?=$m['enabled']?'on':'off'?>"></i><b><?=ent_e($m['label'])?></b><span><?=$m['enabled']?'Etkin':'Kapalı'?></span></div><?php endforeach;?><?php if(!$moduleCards):?><div class="ch-empty">Modül kaydı bulunamadı.</div><?php endif;?></div>
    </article>

    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>SİSTEM ÖZETİ</span><h3>Bugünkü kontrol</h3></div><a href="../system-center.php">Detay →</a></header>
      <div class="cc-health-list">
        <div><i class="ok"></i><b>Control Center</b><span>Çalışıyor</span></div>
        <div><i class="<?=$businessDayOpen?'ok':'warn'?>"></i><b>İş Günü</b><span><?=$businessDayOpen?'Açık':'Kapalı'?></span></div>
        <div><i class="<?=$missingImages===0?'ok':'warn'?>"></i><b>Ürün Görselleri</b><span><?=$missingImages===0?'Hazır':$missingImages.' eksik'?></span></div>
        <div><i class="ok"></i><b>Etkin Modüller</b><span><?=count($enabledModules)?></span></div>
      </div>
    </article>

    <article class="ch-card cc-panel">
      <header class="cc-panel-head"><div><span>EN ÇOK SATANLAR</span><h3>Ürün sıralaması</h3></div></header>
      <?php if($topProducts):?><ol class="cc-top-products"><?php foreach($topProducts as $p):?><li><b><?=ent_e($p['name'])?></b><span><?=number_format((float)$p['qty'],0,',','.')?> adet</span></li><?php endforeach;?></ol><?php else:?><div class="ch-empty cc-small-empty">Satış verisi oluştuğunda burada görünecek.</div><?php endif;?>
    </article>
  </aside>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
