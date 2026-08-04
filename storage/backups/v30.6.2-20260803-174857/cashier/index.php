<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if(!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
$pdo=db(); $error=''; $notice='';
function money(float $v): string { return number_format($v,2,',','.').' ₺'; }
function sessionFinancials(PDO $pdo,int $sid): array {
    $q=$pdo->prepare("SELECT discount_amount FROM table_sessions WHERE id=?");$q->execute([$sid]);$discount=(float)($q->fetchColumn()?:0);
    $q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN oi.status='active' THEN oi.unit_price*oi.quantity ELSE 0 END),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.session_id=?");$q->execute([$sid]);$subtotal=(float)$q->fetchColumn();
    $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE table_session_id=?");$q->execute([$sid]);$paid=(float)$q->fetchColumn();
    $total=max(0,$subtotal-$discount); return compact('subtotal','discount','total','paid')+['remaining'=>max(0,$total-$paid)];
}

function ensureCashierV52Schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashier_payment_groups (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        table_session_id BIGINT UNSIGNED NOT NULL,
        cash_session_id BIGINT UNSIGNED NOT NULL,
        staff_id INT UNSIGNED NOT NULL,
        payment_mode ENUM('all','products','amount') NOT NULL DEFAULT 'amount',
        gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_cpg_session(table_session_id,created_at),
        INDEX idx_cpg_cash(cash_session_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashier_payment_allocations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_group_id BIGINT UNSIGNED NOT NULL,
        order_item_id BIGINT UNSIGNED NOT NULL,
        quantity DECIMAL(8,2) NOT NULL DEFAULT 1,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_cpa_group(payment_group_id),
        INDEX idx_cpa_item(order_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function itemPaidQuantities(PDO $pdo,int $sid): array {
    $q=$pdo->prepare("SELECT a.order_item_id,COALESCE(SUM(a.quantity),0) paid_quantity
        FROM cashier_payment_allocations a
        JOIN cashier_payment_groups g ON g.id=a.payment_group_id
        WHERE g.table_session_id=? GROUP BY a.order_item_id");
    $q->execute([$sid]); $out=[];
    foreach($q->fetchAll() as $r)$out[(int)$r['order_item_id']]=(float)$r['paid_quantity'];
    return $out;
}
ensureCashierV52Schema($pdo);
// v3.2.2: /cashier altında hiçbir yerel giriş seçeneği yoktur.
// Oturum açma ve çıkış işlemleri yalnızca merkezi /admin/ PIN ekranından yürütülür.
if (isset($_GET['logout'])) {
    $_SESSION=[];
    if(session_status()===PHP_SESSION_ACTIVE) session_destroy();
    redirect('../admin/');
}
if (empty($_SESSION['cashier_id']) || !in_array((string)($_SESSION['cashier_role']??''), ['cashier','manager'], true)) {
    $_SESSION['login_return_to']='cashier';
    redirect('../admin/');
}
if ((time()-(int)($_SESSION['cashier_last_activity']??0))>43200) {
    $_SESSION=[];
    if(session_status()===PHP_SESSION_ACTIVE) session_destroy();
    redirect('../admin/');
}
$_SESSION['cashier_last_activity']=time();
$staffId=(int)$_SESSION['cashier_id'];$tableLifecycle=new TableLifecycleService($pdo);$selected=(int)($_GET['session']??0);$categoryId=(int)($_GET['category']??0);
BusinessDayService::install($pdo);$businessDayService=business_day_service();$businessDay=$businessDayService->current();
if($selected===0)$tableLifecycle->cleanupEmptyOpenSessions();
$q=$pdo->prepare("SELECT * FROM cash_sessions WHERE opened_by_staff_id=? AND status='open' ORDER BY id DESC LIMIT 1");$q->execute([$staffId]);$cashSession=$q->fetch();
try{
 if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){verify_csrf();$action=(string)$_POST['action'];
  if(in_array($action,['open_cash','open_table','add_item','qty_plus','qty_minus','cancel_item','complimentary','save_note','discount','move_table','take_payment','close_cash'],true)){$businessDay=$businessDayService->requireOpen();}
  if($action==='open_cash'){$opening=max(0,(float)($_POST['opening_amount']??0));if($cashSession)throw new RuntimeException('Zaten açık kasanız bulunuyor.');$pdo->prepare("INSERT INTO cash_sessions(opened_by_staff_id,opening_amount,status,opened_at,business_day_id) VALUES(?,?,'open',NOW(),?)")->execute([$staffId,$opening,(int)$businessDay['id']]);redirect('./');}
  if($action==='open_table'){$tableId=(int)$_POST['table_id'];$guest=1;$pdo->beginTransaction();$q=$pdo->prepare("SELECT status FROM restaurant_tables WHERE id=? AND is_active=1 FOR UPDATE");$q->execute([$tableId]);$status=$q->fetchColumn();if($status===false||$status!=='empty')throw new RuntimeException('Masa açılmaya uygun değil.');$pdo->prepare("INSERT INTO table_sessions(table_id,opened_by_staff_id,guest_count,status,opened_at,business_day_id) VALUES(?,?,?,'open',NOW(),?)")->execute([$tableId,$staffId,$guest,(int)$businessDay['id']]);$sid=(int)$pdo->lastInsertId();$pdo->commit();redirect('./?session='.$sid);}
  if($action==='add_item'){$sid=(int)$_POST['session_id'];$pid=(int)$_POST['product_id'];$qty=max(.01,min(99,(float)($_POST['quantity']??1)));$note=mb_substr(trim((string)($_POST['item_note']??'')),0,500);$q=$pdo->prepare("SELECT id,name,price FROM products WHERE id=? AND is_active=1");$q->execute([$pid]);$p=$q->fetch();if(!$p)throw new RuntimeException('Ürün bulunamadı.');$q=$pdo->prepare("SELECT id FROM table_sessions WHERE id=? AND status='open'");$q->execute([$sid]);if(!$q->fetchColumn())throw new RuntimeException('Açık adisyon bulunamadı.');$pdo->beginTransaction();$pdo->prepare("INSERT INTO orders(session_id,staff_id,status,created_at,business_day_id) VALUES(?,?,'submitted',NOW(),?)")->execute([$sid,$staffId,(int)$businessDay['id']]);$oid=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity,item_note,status) VALUES(?,?,?,?,?,?,'active')")->execute([$oid,$pid,$p['name'],$p['price'],$qty,$note]);$tableLifecycle->activateSession($sid);$pdo->commit();$returnTo=(string)($_POST['return_to']??''); redirect($returnTo==='payment'?'./?session='.$sid.'&pay=1&drawer=1&category='.$categoryId:'./?session='.$sid.'&category='.$categoryId);}
  if(in_array($action,['qty_plus','qty_minus','cancel_item','complimentary','save_note'],true)){$sid=(int)$_POST['session_id'];$iid=(int)$_POST['item_id'];
   if($action==='qty_plus')$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.quantity=LEAST(99,oi.quantity+1) WHERE oi.id=? AND o.session_id=? AND oi.status IN ('active','complimentary')")->execute([$iid,$sid]);
   if($action==='qty_minus'){$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.quantity=IF(oi.quantity<=1,oi.quantity,oi.quantity-1) WHERE oi.id=? AND o.session_id=? AND oi.status IN ('active','complimentary')")->execute([$iid,$sid]);}
   if($action==='cancel_item'){$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.status='cancelled' WHERE oi.id=? AND o.session_id=?")->execute([$iid,$sid]);if($tableLifecycle->reconcileSession($sid))redirect('./');}
   if($action==='complimentary')$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.status=IF(oi.status='complimentary','active','complimentary') WHERE oi.id=? AND o.session_id=? AND oi.status IN ('active','complimentary')")->execute([$iid,$sid]);
   if($action==='save_note'){$note=mb_substr(trim((string)($_POST['item_note']??'')),0,500);$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.item_note=? WHERE oi.id=? AND o.session_id=?")->execute([$note,$iid,$sid]);}
   redirect('./?session='.$sid.'&category='.$categoryId);
  }
  if($action==='discount'){$sid=(int)$_POST['session_id'];$amount=max(0,(float)($_POST['discount_amount']??0));$note=mb_substr(trim((string)($_POST['discount_note']??'')),0,255);$f=sessionFinancials($pdo,$sid);if($amount>$f['subtotal'])throw new RuntimeException('İndirim ara toplamdan yüksek olamaz.');$pdo->prepare("UPDATE table_sessions SET discount_amount=?,discount_note=? WHERE id=? AND status='open'")->execute([$amount,$note,$sid]);redirect('./?session='.$sid);}
  if($action==='move_table'){$sid=(int)$_POST['session_id'];$newTable=(int)$_POST['new_table_id'];$pdo->beginTransaction();$q=$pdo->prepare("SELECT table_id FROM table_sessions WHERE id=? AND status='open' FOR UPDATE");$q->execute([$sid]);$old=(int)$q->fetchColumn();$q=$pdo->prepare("SELECT status FROM restaurant_tables WHERE id=? AND is_active=1 FOR UPDATE");$q->execute([$newTable]);if($q->fetchColumn()!=='empty')throw new RuntimeException('Hedef masa boş değil.');$pdo->prepare("UPDATE table_sessions SET table_id=? WHERE id=?")->execute([$newTable,$sid]);$pdo->prepare("UPDATE restaurant_tables SET status='empty' WHERE id=?")->execute([$old]);$pdo->prepare("UPDATE restaurant_tables SET status='open' WHERE id=?")->execute([$newTable]);$pdo->commit();redirect('./?session='.$sid);}
  if($action==='take_payment'){
   $sid=(int)$_POST['session_id'];if(!$cashSession)throw new RuntimeException('Önce kasayı açın.');
   $mode=(string)($_POST['payment_mode']??'amount');if(!in_array($mode,['all','products','amount'],true))$mode='amount';
   $amounts=['cash'=>(float)($_POST['cash']??0),'card'=>(float)($_POST['card']??0),'meal_card'=>(float)($_POST['meal_card']??0),'transfer'=>(float)($_POST['transfer']??0)];
   foreach($amounts as $v)if($v<0)throw new RuntimeException('Negatif ödeme girilemez.');
   $sum=round(array_sum($amounts),2);if($sum<=0)throw new RuntimeException('Ödeme tutarı girin.');
   $pdo->beginTransaction();
   $q=$pdo->prepare("SELECT table_id,status FROM table_sessions WHERE id=? FOR UPDATE");$q->execute([$sid]);$ts=$q->fetch();
   if(!$ts||$ts['status']!=='open')throw new RuntimeException('Açık adisyon bulunamadı.');
   $f=sessionFinancials($pdo,$sid);if($sum>$f['remaining']+.009)throw new RuntimeException('Ödeme kalan tutardan fazla olamaz.');
   $allocations=[];
   if($mode==='products'){
    $raw=(string)($_POST['product_selection']??'');$selection=json_decode($raw,true);
    if(!is_array($selection)||!$selection)throw new RuntimeException('Ödenecek ürünleri seçin.');
    $paidQty=itemPaidQuantities($pdo,$sid);$selectedTotal=0.0;
    $itemQ=$pdo->prepare("SELECT oi.id,oi.product_name,oi.unit_price,oi.quantity,oi.status FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.id=? AND o.session_id=? FOR UPDATE");
    foreach($selection as $itemId=>$qty){$itemId=(int)$itemId;$qty=round((float)$qty,2);if($qty<=0)continue;$itemQ->execute([$itemId,$sid]);$it=$itemQ->fetch();if(!$it||$it['status']!=='active')throw new RuntimeException('Seçilen ürün artık ödemeye uygun değil.');$available=max(0,(float)$it['quantity']-(float)($paidQty[$itemId]??0));if($qty>$available+.009)throw new RuntimeException($it['product_name'].' için seçilen adet kullanılabilir adetten fazla.');$line=round((float)$it['unit_price']*$qty,2);$selectedTotal+=$line;$allocations[]=['id'=>$itemId,'qty'=>$qty,'amount'=>$line];}
    if(!$allocations)throw new RuntimeException('Ödenecek ürünleri seçin.');
    $selectedTotal=round($selectedTotal,2);
    if(abs($sum-min($selectedTotal,$f['remaining']))>.02)throw new RuntimeException('Ödeme toplamı seçilen ürünlerin tutarıyla eşleşmiyor.');
   }elseif($mode==='all'){
    if(abs($sum-$f['remaining'])>.02)throw new RuntimeException('Tüm hesap ödemesinde kalan tutarın tamamı tahsil edilmelidir.');
   }
   $pdo->prepare("INSERT INTO cashier_payment_groups(table_session_id,cash_session_id,staff_id,payment_mode,gross_amount,note,created_at,business_day_id) VALUES(?,?,?,?,?,?,NOW(),?)")->execute([$sid,(int)$cashSession['id'],$staffId,$mode,$sum,mb_substr(trim((string)($_POST['payment_note']??'')),0,255),(int)$businessDay['id']]);
   $groupId=(int)$pdo->lastInsertId();
   $ins=$pdo->prepare("INSERT INTO payments(table_session_id,cash_session_id,staff_id,method,amount,paid_at,business_day_id) VALUES(?,?,?,?,?,NOW(),?)");
   foreach($amounts as $m=>$a)if($a>0)$ins->execute([$sid,(int)$cashSession['id'],$staffId,$m,round($a,2),(int)$businessDay['id']]);
   if($allocations){$ai=$pdo->prepare("INSERT INTO cashier_payment_allocations(payment_group_id,order_item_id,quantity,amount,created_at) VALUES(?,?,?,?,NOW())");foreach($allocations as $a)$ai->execute([$groupId,$a['id'],$a['qty'],$a['amount']]);}
   $willClose=($f['paid']+$sum>=$f['total']-.009);
   if($willClose){$pdo->prepare("UPDATE table_sessions SET status='closed',closed_at=NOW() WHERE id=?")->execute([$sid]);$pdo->prepare("UPDATE restaurant_tables SET status='empty' WHERE id=?")->execute([(int)$ts['table_id']]);}
   $pdo->commit();redirect($willClose?'./?paid=1':'./?session='.$sid.'&pay=1&paid=1');
  }
  if($action==='close_cash'){$counted=max(0,(float)($_POST['counted_cash']??0));$q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE cash_session_id=? AND method='cash'");$q->execute([(int)$cashSession['id']]);$expected=(float)$cashSession['opening_amount']+(float)$q->fetchColumn();$pdo->prepare("UPDATE cash_sessions SET closed_by_staff_id=?,expected_cash=?,counted_cash=?,difference_amount=?,note=?,status='closed',closed_at=NOW() WHERE id=?")->execute([$staffId,$expected,$counted,$counted-$expected,mb_substr(trim((string)($_POST['note']??'')),0,500),(int)$cashSession['id']]);redirect('./?closed=1');}
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
if($selected===0)$tableLifecycle->cleanupEmptyOpenSessions();
$q=$pdo->prepare("SELECT * FROM cash_sessions WHERE opened_by_staff_id=? AND status='open' ORDER BY id DESC LIMIT 1");$q->execute([$staffId]);$cashSession=$q->fetch();
$tables=$pdo->query("SELECT t.id,t.name,t.status,t.area_id,a.name area_name,ts.id session_id,ts.opened_at,ts.guest_count, GREATEST(0, COALESCE((SELECT SUM(CASE WHEN oi.status='active' THEN oi.unit_price*oi.quantity ELSE 0 END) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.session_id=ts.id),0) - COALESCE(ts.discount_amount,0) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.table_session_id=ts.id),0)) table_total FROM restaurant_tables t JOIN dining_areas a ON a.id=t.area_id LEFT JOIN table_sessions ts ON ts.table_id=t.id AND ts.status='open' AND EXISTS (SELECT 1 FROM orders ox JOIN order_items oix ON oix.order_id=ox.id WHERE ox.session_id=ts.id AND oix.status IN ('active','complimentary') AND oix.quantity>0) WHERE t.is_active=1 AND t.status<>'disabled' AND a.is_active=1 ORDER BY a.sort_order,t.id")->fetchAll();
$categories=$pdo->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();if(!$categoryId&&$categories)$categoryId=(int)$categories[0]['id'];
$q=$pdo->prepare("SELECT id,name,price,image_path FROM products WHERE is_active=1 AND category_id=? ORDER BY sort_order,id");$q->execute([$categoryId]);$products=$q->fetchAll();
$drawerProducts=$pdo->query("SELECT id,category_id,name,price,image_path FROM products WHERE is_active=1 ORDER BY category_id,sort_order,id")->fetchAll();
$active=null;$items=[];$financial=['subtotal'=>0,'discount'=>0,'total'=>0,'paid'=>0,'remaining'=>0];$emptyTables=[];$paidQuantities=[];
if($selected){$q=$pdo->prepare("SELECT ts.*,t.name table_name,a.name area_name FROM table_sessions ts JOIN restaurant_tables t ON t.id=ts.table_id JOIN dining_areas a ON a.id=t.area_id WHERE ts.id=? AND ts.status='open'");$q->execute([$selected]);$active=$q->fetch();if($active){$q=$pdo->prepare("SELECT oi.* FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.session_id=? AND oi.status IN ('active','complimentary') ORDER BY oi.id DESC");$q->execute([$selected]);$items=$q->fetchAll();$paidQuantities=itemPaidQuantities($pdo,$selected);foreach($items as &$it){$it['paid_quantity']=(float)($paidQuantities[(int)$it['id']]??0);$it['open_quantity']=max(0,(float)$it['quantity']-$it['paid_quantity']);}unset($it);$financial=sessionFinancials($pdo,$selected);$emptyTables=$pdo->query("SELECT id,name FROM restaurant_tables WHERE status='empty' AND is_active=1 ORDER BY name")->fetchAll();}}
$payMode=(bool)$active;
if(!$businessDay){?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>İş Günü Kapalı</title><style>body{margin:0;font-family:Inter,system-ui;background:#f4f6f8;display:grid;place-items:center;min-height:100vh;color:#172033}.lock{max-width:620px;background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:34px;text-align:center;box-shadow:0 20px 60px #0f172a12}.icon{font-size:56px}.lock h1{font-size:30px;margin:12px}.lock p{color:#667085;line-height:1.6}.lock a{display:inline-block;margin:10px 5px;padding:13px 18px;border-radius:12px;background:#e11d2e;color:#fff;text-decoration:none;font-weight:800}.lock a.secondary{background:#344054}</style></head><body><section class="lock"><div class="icon">🔒</div><h1>İş günü henüz açılmadı</h1><p>Masa açma, sipariş ve tahsilat işlemleri için yetkili personelin Gün Başı yapması gerekir.</p><a href="../admin/business-day.php">İş Günü Merkezine Git</a><a class="secondary" href="?logout=1">Çıkış</a></section></body></html><?php exit;}?>
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>CherryHouse POS · Kasiyer</title><link rel="stylesheet" href="../app/assets/pos-v4.css?v=420"><link rel="stylesheet" href="../app/assets/cherry-design/tokens.css?v=300"><link rel="stylesheet" href="../app/assets/pos-v5-alpha.css?v=3010"><link rel="stylesheet" href="../app/assets/pos-v5-beta.css?v=3020"><link rel="stylesheet" href="../app/assets/cashier-v55.css?v=545"><link rel="stylesheet" href="../app/assets/table-flow-hotfix.css?v=2701"><link rel="stylesheet" href="../app/assets/cashier-payment-v613.css?v=306113"><script defer src="../app/assets/pos-v4.js?v=420"></script><script defer src="../app/assets/pos-v5-alpha.js?v=3010"></script><script defer src="../app/assets/pos-v5-beta.js?v=3020"></script><script defer src="../app/assets/cashier-v55.js?v=545"></script><script defer src="../app/assets/cashier-payment-v60.js?v=30600"></script><script defer src="../app/assets/cashier-payment-v61.js?v=30610"></script><script defer src="../app/assets/cashier-payment-v611.js?v=306111"></script></head><body class="chv4 chv4-cashier ch-pos5" data-pos-role="cashier" data-pos-state-endpoint="../api/pos-v5-state.php">
<header class="v4-topbar"><a class="v4-back" href="../admin/">‹</a><div class="v4-brand"><span class="v4-mark">🍒</span><div><strong><?=e(setting('business_name','CherryHouse'))?></strong><small>POS 5.0 Beta · Kasiyer</small></div></div><div class="v4-top-status"><span class="online-dot"></span> İnternet <b>Bağlı</b><span class="server-dot"></span> Sunucu <b>Aktif</b><b class="v4-live-clock" data-live-clock></b></div><button type="button" class="v4-fullscreen" data-fullscreen title="Tam ekran">⛶</button><div class="v4-user"><span><?=e($_SESSION['cashier_name']??'Kasiyer')?></span><a href="?logout=1">Çıkış</a></div></header>
<?php if(isset($_GET['paid'])):?><div class="v4-alert success" data-auto-dismiss>Ödeme başarıyla kaydedildi.</div><?php endif;?><?php if($error):?><div class="v4-alert danger"><?=e($error)?></div><?php endif;?>
<?php if(!$cashSession):?><main class="v4-cash-open"><div class="v4-cash-open-card"><span class="v4-mark big">🍒</span><span class="v4-eyebrow">GÜN BAŞLANGICI</span><h1>Kasayı Aç</h1><p>Satışa başlamak için açılış nakdini girin.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="open_cash"><label>Açılış nakdi<div class="v4-money-input"><span>₺</span><input type="number" min="0" step="0.01" name="opening_amount" value="0"></div></label><button class="v4-primary v4-full">Kasayı Aç ve Başla</button></form></div></main>
<?php else:?>
<?php if($payMode):?>
<main class="ch-pay-screen" data-pay-screen>
 <header class="ch-pay-header">
  <div class="ch-pay-identity"><span class="ch-pay-logo">🍒 <b>Cherry<span>House</span></b></span><div><strong><?=e($active['table_name'])?></strong><small><?=e($active['area_name'])?> · <?=(int)$active['guest_count']?> kişi · Kasiyer: <?=e((string)($_SESSION['cashier_name']??'Kasiyer'))?></small></div></div>
  <div class="ch-pay-header-actions"><a target="_blank" href="../print/?type=bill&session=<?=$selected?>">▤ Ön Hesap</a><button type="button" onclick="moveDialog.showModal()">⇄ Masa Taşı</button><a class="ch-pay-back" href="./">← Masalar</a></div>
 </header>
 <form method="post" id="unifiedPaymentForm" class="ch-pay-grid">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="take_payment"><input type="hidden" name="session_id" value="<?=$selected?>"><input type="hidden" name="payment_mode" id="uPaymentMode" value="all"><input type="hidden" name="product_selection" id="uProductSelection" value="{}">
  <section class="ch-pay-products ch-pay-ticket">
   <div class="ch-pay-section-head"><div><small>ADİSYON</small><h2><?=e($active['table_name'])?></h2></div><button type="button" data-add-products>＋ Ürün Ekle</button></div>
   <div class="ch-pay-product-list">
    <?php if(!$items):?><p class="ch-pay-empty">Henüz ürün eklenmedi.</p><?php endif;?>
    <?php foreach($items as $it):?><?php if($it['status']==='active'&&$it['open_quantity']>.009):?><button type="button" class="ch-pay-product" data-u-item data-id="<?=$it['id']?>" data-price="<?=number_format((float)$it['unit_price'],2,'.','')?>" data-max="<?=number_format((float)$it['open_quantity'],2,'.','')?>"><span class="qty"><?=e($it['open_quantity'])?></span><span class="name"><?=e($it['product_name'])?><small><?=money((float)$it['unit_price'])?> / adet<?php if($it['paid_quantity']>.009):?> · <?=e($it['paid_quantity'])?> ödendi<?php endif;?></small><?php if($it['item_note']):?><small>📝 <?=e($it['item_note'])?></small><?php endif;?></span><span class="line"><?=money((float)$it['unit_price']*(float)$it['open_quantity'])?></span><span class="picked" data-picked>0</span></button><?php endif;?><?php endforeach;?>
   </div>
   <div class="ch-pay-product-actions"><button type="button" data-select-all>Tümünü Seç</button><button type="button" data-clear-selection>Seçimi Temizle</button></div>
   <div class="ch-pay-ticket-summary"><span>Ara Toplam <b><?=money($financial['subtotal'])?></b></span><span>İndirim <b>− <?=money($financial['discount'])?></b></span><strong>Kalan <em><?=money($financial['remaining'])?></em></strong></div>
  </section>
  <section class="ch-pay-center">
   <div class="ch-pay-amount-card"><div class="ch-pay-mode-row"><span class="ch-pay-mode-badge" data-mode-label>Tüm hesap</span><span data-selection-label>Kalan bakiyenin tamamı</span></div><p>Tahsil Edilecek</p><output id="uAmountDisplay">0,00 ₺</output><small>Ürüne dokunarak seç veya numaratörden serbest tutar gir.</small></div>
   <div class="ch-pay-keypad"><?php foreach(['1','2','3','4','5','6','7','8','9',',','0','⌫'] as $k):?><button type="button" data-u-key="<?=$k?>"><?=$k?></button><?php endforeach;?></div>
   <div class="ch-pay-presets"><button type="button" data-add-amount="100">+100 ₺</button><button type="button" data-add-amount="200">+200 ₺</button><button type="button" data-add-amount="500">+500 ₺</button><button type="button" data-full-remaining>Kalanın Tamamı</button></div>
   <div class="ch-pay-tools"><button type="button" onclick="discountDialog.showModal()">% İskonto</button><button type="button" data-free-amount>₺ Serbest Tutar</button><a target="_blank" href="../print/?type=bill&session=<?=$selected?>">▤ Ön Hesap Yaz</a></div>
   <div class="ch-pay-live-summary"><span>Adisyon <b><?=money($financial['total'])?></b></span><span>Tahsil Edilen <b><?=money($financial['paid'])?></b></span><span>Kalan <b><?=money($financial['remaining'])?></b></span></div>
  </section>
  <section class="ch-pay-methods">
   <div class="ch-pay-method-head"><small>ÖDEME YÖNTEMLERİ</small><h2>Yöntem Seç</h2></div>
   <button type="button" data-u-method="cash"><span>💵</span><b>Nakit</b><strong data-method-amount="cash">0,00 ₺</strong></button>
   <button type="button" data-u-method="card"><span>💳</span><b>Kredi Kartı</b><strong data-method-amount="card">0,00 ₺</strong></button>
   <button type="button" data-u-method="meal_card"><span>🍽️</span><b>Yemek Kartı</b><strong data-method-amount="meal_card">0,00 ₺</strong></button>
   <button type="button" data-u-method="transfer"><span>↔</span><b>Havale / EFT</b><strong data-method-amount="transfer">0,00 ₺</strong></button>
   <div class="ch-pay-method-sums">
    <div class="ch-pay-distribution-title"><span>Ödeme Dağılımı</span><b>Toplam</b></div>
    <label>Nakit<input readonly name="cash" value="0"></label><label>Kart<input readonly name="card" value="0"></label><label>Yemek Kartı<input readonly name="meal_card" value="0"></label><label>Havale / EFT<input readonly name="transfer" value="0"></label>
    <div class="ch-pay-distribution-total"><span>TOPLAM</span><strong data-distribution-total>0,00 ₺</strong></div>
   </div>
   <button type="button" class="ch-pay-reset-methods" data-reset-methods>🗑 Ödeme dağılımını temizle</button>
   <button class="ch-pay-complete" data-u-submit disabled>✓ Tahsilatı Tamamla</button>
  </section>
 </form>
 <aside class="ch-product-drawer" data-product-drawer data-open-on-load="<?=isset($_GET['drawer'])?'1':'0'?>">
  <div class="drawer-head"><div><small>ÖDEME EKRANINDAN ÇIKMADAN</small><h2>Ürün Ekle</h2></div><button type="button" data-close-products aria-label="Kapat">×</button></div>
  <div class="drawer-categories" role="tablist"><?php foreach($categories as $i=>$c):?><button type="button" class="<?=$categoryId===(int)$c['id']?'active':''?>" data-drawer-category="<?=$c['id']?>"><?=e($c['name'])?></button><?php endforeach;?></div>
  <div class="drawer-products"><?php foreach($drawerProducts as $p):?><form method="post" data-drawer-product data-category-id="<?=$p['category_id']?>" <?=$categoryId===(int)$p['category_id']?'':'hidden'?>>
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_item"><input type="hidden" name="session_id" value="<?=$selected?>"><input type="hidden" name="product_id" value="<?=$p['id']?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="payment">
    <button type="submit"><b><?=e($p['name'])?></b><span><?=money((float)$p['price'])?></span><small>＋ Adisyona Ekle</small></button>
  </form><?php endforeach;?></div>
 </aside><div class="ch-drawer-mask" data-drawer-mask></div>
</main>
<?php else:?>
<main class="v4-cash-layout <?=!$active?'tables-mode':'sale-mode'?>">
 <aside class="v4-action-rail"><button class="active">▥<span>Masalar</span></button><button onclick="openTable.showModal()">＋<span>Masa Aç</span></button><?php if($active):?><button onclick="moveDialog.showModal()">⇄<span>Taşı</span></button><button onclick="discountDialog.showModal()">％<span>İndirim</span></button><a target="_blank" href="../print/?type=bill&session=<?=$selected?>">▤<span>Hesap</span></a><?php endif;?><div></div><button onclick="closeCash.showModal()">⌁<span>Kasa</span></button></aside>
 <?php if(!$active):?>
 <section class="v4-table-main cashier cashier-tables-only ch-cashier-tables"><div class="v4-page-head"><div><span class="v4-eyebrow">KATLAR / SALON</span><h1>Masalar</h1></div><div class="v4-metrics"><div><b><?=count(array_filter($tables,fn($t)=>$t['status']==='empty'))?></b><span>Boş</span></div><div><b><?=count(array_filter($tables,fn($t)=>!empty($t['session_id'])))?></b><span>Dolu</span></div></div></div><div class="v4-table-toolbar"><label class="v4-search"><span>⌕</span><input type="search" placeholder="Masa ara..." data-table-search></label></div><div class="v4-table-grid ch-table-grid"><?php foreach($tables as $t):?><article class="v4-table-card <?=!empty($t['session_id'])?'is-open':'is-'.$t['status']?>" <?php if(!empty($t['opened_at'])):?>data-opened="<?=e((string)$t['opened_at'])?>"<?php endif;?> data-table-id="<?=(int)$t['id']?>" data-session-id="<?=(int)($t['session_id']??0)?>" data-table-name="<?=e(mb_strtolower((string)$t['name']))?>"><?php if($t['session_id']):?><a href="?session=<?=(int)$t['session_id']?>"><div class="v4-table-top"><span class="v4-table-pill">DOLU</span><small><span data-open-duration>-- dk</span> · <?=date('H:i',strtotime((string)$t['opened_at']))?></small></div><h3><?=e($t['name'])?></h3><div class="v4-table-meta"><span>👤 <?=(int)$t['guest_count']?></span><span><?=e($t['area_name'])?></span></div><strong class="v4-table-total"><?=money((float)$t['table_total'])?></strong></a><?php elseif($t['status']==='empty'):?><form method="post" class="v4-direct-open-form ch-direct-table-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="open_table"><input type="hidden" name="table_id" value="<?=(int)$t['id']?>"><button type="submit" class="ch-direct-table-button"><div class="v4-table-top"><span class="v4-table-pill">BOŞ</span></div><h3><?=e($t['name'])?></h3><div class="v4-table-meta"><span><?=e($t['area_name'])?></span></div><strong class="v4-open-hint">Adisyona geç</strong></button></form><?php else:?><div><h3><?=e($t['name'])?></h3><small><?=e($t['status'])?></small></div><?php endif;?></article><?php endforeach;?></div></section>
 <?php else:?>
 <aside class="v4-category-rail cashier"><div class="v4-rail-head"><a href="./">‹ Masalar</a><small>KATEGORİLER</small></div><nav><?php foreach($categories as $c):?><a class="<?=$categoryId===(int)$c['id']?'active':''?>" href="?<?=http_build_query(['session'=>$selected,'category'=>$c['id']])?>"><?=e($c['name'])?></a><?php endforeach;?></nav></aside>
 <section class="v4-product-zone cashier"><div class="v4-work-head"><div><span class="v4-eyebrow"><?=e($active['area_name'])?></span><h1>Ürünler</h1></div><label class="v4-search"><span>⌕</span><input type="search" placeholder="Ürün ara..." data-product-search></label></div><div class="v4-mobile-view-switch" data-mobile-switch><button type="button" class="active" data-mobile-view="products">Ürünler</button><button type="button" data-mobile-view="ticket">Adisyon <span><?=count($items)?></span></button></div><div class="v4-product-grid"><?php foreach($products as $p):?><form method="post" class="v4-product-form" data-product-name="<?=e(mb_strtolower($p['name']))?>" data-quick-product data-product-label="<?=e($p['name'])?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_item"><input type="hidden" name="session_id" value="<?=$selected?>"><input type="hidden" name="product_id" value="<?=$p['id']?>"><input type="hidden" name="quantity" value="1" data-quick-quantity><input type="hidden" name="item_note" value="" data-quick-note><button type="submit" class="v4-product-card" data-quick-trigger><span class="v4-qty-badge" data-quick-badge hidden>1</span><div class="v4-product-image"<?php if($p['image_path']):?> style="background-image:url('../<?=e(ltrim($p['image_path'],'/'))?>')"<?php endif;?>><?=$p['image_path']?'':'🍽️'?></div><div><strong><?=e($p['name'])?></strong><b><?=money((float)$p['price'])?></b></div><small class="v4-hold-hint">Basılı tut: not</small></button></form><?php endforeach;?></div></section>
 <aside class="v4-ticket cashier"><header><div><span class="v4-eyebrow">ADİSYON</span><h2><?=e($active['table_name'])?></h2><small><?=e($active['area_name'])?> · <?=(int)$active['guest_count']?> kişi</small></div><span class="v4-ticket-count"><?=count($items)?></span></header><div class="v4-ticket-body cashier-items" id="cashierItems"><?php if(!$items):?><p class="v4-empty-text">Henüz ürün eklenmedi.</p><?php endif;?><?php foreach($items as $it):?><?php $fullyPaid=$it['open_quantity']<=.009;$partPaid=$it['paid_quantity']>.009&&!$fullyPaid;?><button type="button" class="cashier-pay-item <?=$fullyPaid?'is-paid':($partPaid?'is-partial':'')?>" data-payment-item data-item-id="<?=$it['id']?>" data-name="<?=e($it['product_name'])?>" data-unit-price="<?=number_format((float)$it['unit_price'],2,'.','')?>" data-open-qty="<?=number_format((float)$it['open_quantity'],2,'.','')?>" <?=$fullyPaid?'disabled':''?>><span class="cashier-item-check">✓</span><span class="cashier-item-main"><strong><?=e($it['product_name'])?></strong><small><?=e($it['quantity'])?> adet<?php if($it['paid_quantity']>.009):?> · <?=e($it['paid_quantity'])?> ödendi<?php endif;?></small><?php if($it['item_note']):?><small>📝 <?=e($it['item_note'])?></small><?php endif;?></span><span class="cashier-item-price"><?=$it['status']==='complimentary'?'İkram':money((float)$it['unit_price']*(float)$it['open_quantity'])?></span></button><?php endforeach;?></div><footer><div class="v4-financial"><span>Ara toplam <b><?=money($financial['subtotal'])?></b></span><span>İndirim <b>− <?=money($financial['discount'])?></b></span><span>Ödenen <b><?=money($financial['paid'])?></b></span><strong>Kalan <em><?=money($financial['remaining'])?></em></strong></div><div class="cashier-payment-entry"><a class="cashier-mode-btn all" href="?session=<?=$selected?>&pay=1"><span>₺</span><b>Ödeme Ekranı</b></a></div></footer></aside>
 <?php endif;?>
</main>
<?php endif;?>
<?php if(false && $active):?>
<nav class="v4-quick-dock cashier" aria-label="Hızlı işlemler">
 <a href="./"><span>▦</span><b>Masalar</b></a>
 <button type="button" onclick="discountDialog.showModal()"><span>%</span><b>İndirim</b></button>
 <button type="button" onclick="moveDialog.showModal()"><span>⇄</span><b>Taşı</b></button>
 <button type="button" data-focus-product><span>⌕</span><b>Ürün Ara</b></button>
 <a class="is-primary" href="?session=<?=$selected?>&pay=1"><span>₺</span><b>Ödeme</b></a>
</nav>
<?php endif;?>
<dialog id="openTable" class="v4-dialog"><form method="post" class="v4-dialog-card"><button type="button" class="v4-dialog-x" onclick="openTable.close()">×</button><span class="v4-eyebrow">YENİ ADİSYON</span><h2 id="openTableTitle">Masa Aç</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="open_table"><select name="table_id" id="openTableSelect" required><?php foreach($tables as $t)if($t['status']==='empty'):?><option value="<?=$t['id']?>"><?=e($t['area_name'].' / '.$t['name'])?></option><?php endif;?></select><input type="hidden" name="guest_count" value="1"><p class="v4-dialog-help">Masa varsayılan olarak açılır; kişi sayısı daha sonra opsiyonel olarak düzenlenebilir.</p><button class="v4-primary v4-full">Masayı Aç</button></form></dialog>
<?php if($active):?><dialog id="paymentDialog" class="v4-dialog payment cashier-payment-dialog"><div class="v4-dialog-card"><button type="button" class="v4-dialog-x" data-payment-close>×</button><span class="v4-eyebrow">DOKUNMATİK TAHSİLAT</span><h2><?=e($active['table_name'])?></h2><div class="cashier-summary"><span>Adisyon <b><?=money($financial['total'])?></b></span><span>Tahsil edilen <b><?=money($financial['paid'])?></b></span><strong>Kalan <em><?=money($financial['remaining'])?></em></strong></div><form method="post" id="cashierPaymentForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="take_payment"><input type="hidden" name="session_id" value="<?=$selected?>"><input type="hidden" name="payment_mode" id="paymentMode" value="amount"><input type="hidden" name="product_selection" id="productSelection" value="{}"><section class="cashier-mode-tabs"><button type="button" data-payment-mode="all">Tüm Hesap</button><button type="button" data-payment-mode="products">Ürün Seç</button><button type="button" data-payment-mode="amount">Tutar Gir</button></section><section class="cashier-product-picker" data-product-picker><div class="cashier-picker-head"><b>Ödenecek ürünlere dokun</b><span data-selected-label>0 ürün</span></div><div class="cashier-picker-list"><?php foreach($items as $it):?><?php if($it['status']==='active'&&$it['open_quantity']>.009):?><div class="cashier-picker-row" data-picker-row data-item-id="<?=$it['id']?>" data-name="<?=e($it['product_name'])?>" data-price="<?=number_format((float)$it['unit_price'],2,'.','')?>" data-max="<?=number_format((float)$it['open_quantity'],2,'.','')?>"><button type="button" data-picker-toggle><span><?=e($it['product_name'])?></span><small><?=e($it['open_quantity'])?> adet · <?=money((float)$it['unit_price'])?></small></button><div class="cashier-qty"><button type="button" data-qty-minus>−</button><b data-qty-value>0</b><button type="button" data-qty-plus>+</button></div></div><?php endif;?><?php endforeach;?></div></section><section class="cashier-touch-amount"><label>Ödenecek tutar<input type="text" inputmode="none" readonly id="touchAmount" value="0,00 ₺"></label><div class="cashier-keypad" data-keypad><?php foreach(['1','2','3','4','5','6','7','8','9','C','0',','] as $key):?><button type="button" data-key="<?=$key?>"><?=$key?></button><?php endforeach;?></div><div class="cashier-fast-amounts"><button type="button" data-fast-amount="500">500 ₺</button><button type="button" data-fast-amount="300">300 ₺</button><button type="button" data-fast-amount="half">Yarısı</button><button type="button" data-fast-amount="all">Kalanın Tamamı</button></div></section><section class="cashier-methods"><h3>Ödeme yöntemi</h3><div class="cashier-method-buttons"><button type="button" data-method="cash">Nakit</button><button type="button" data-method="card">Kart</button><button type="button" data-method="meal_card">Yemek Kartı</button><button type="button" data-method="transfer">Havale</button></div><div class="cashier-method-values"><label>Nakit<input readonly name="cash" value="0"></label><label>Kart<input readonly name="card" value="0"></label><label>Yemek Kartı<input readonly name="meal_card" value="0"></label><label>Havale<input readonly name="transfer" value="0"></label></div><div class="cashier-payment-balance"><span>Dağıtılan <b data-distributed>0,00 ₺</b></span><span>Kalan <b data-undistributed>0,00 ₺</b></span></div></section><button class="v4-primary v4-full cashier-submit" data-payment-submit disabled>Ödemeyi Tamamla</button></form></div></dialog>
<dialog id="discountDialog" class="v4-dialog"><form method="post" class="v4-dialog-card"><button type="button" class="v4-dialog-x" onclick="discountDialog.close()">×</button><h2>Adisyon İndirimi</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="discount"><input type="hidden" name="session_id" value="<?=$selected?>"><label>Tutar<input type="number" min="0" max="<?=$financial['subtotal']?>" step="0.01" name="discount_amount" value="<?=$financial['discount']?>"></label><label>Açıklama<input name="discount_note" value="<?=e($active['discount_note']??'')?>"></label><button class="v4-primary v4-full">Uygula</button></form></dialog>
<dialog id="moveDialog" class="v4-dialog"><form method="post" class="v4-dialog-card"><button type="button" class="v4-dialog-x" onclick="moveDialog.close()">×</button><h2>Masa Taşı</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="move_table"><input type="hidden" name="session_id" value="<?=$selected?>"><select name="new_table_id"><?php foreach($emptyTables as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach;?></select><button class="v4-primary v4-full">Taşı</button></form></dialog>
<dialog id="noteDialog" class="v4-dialog"><form method="post" class="v4-dialog-card"><button type="button" class="v4-dialog-x" onclick="noteDialog.close()">×</button><h2>Ürün Notu</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_note"><input type="hidden" name="session_id" value="<?=$selected?>"><input type="hidden" name="item_id" id="noteItem"><input name="item_note" id="noteText" placeholder="Az pişmiş, sos olmasın..."><button class="v4-primary v4-full">Notu Kaydet</button></form></dialog><?php endif;?>
<dialog id="quickNoteDialog" class="v4-dialog v4-quick-note-dialog"><form method="dialog" class="v4-dialog-card"><button type="button" class="v4-dialog-x" data-quick-note-close>×</button><span class="v4-eyebrow">HIZLI NOT</span><h2 data-quick-note-title>Ürün Notu</h2><div class="v4-note-presets" data-note-presets><button type="button">Az pişmiş</button><button type="button">Orta</button><button type="button">İyi pişmiş</button><button type="button">Acısız</button><button type="button">Az acılı</button><button type="button">Bol acılı</button><button type="button">Soğansız</button><button type="button">Sarımsaksız</button></div><label>Özel not<textarea rows="3" data-quick-note-text placeholder="Örn. sos ayrı gelsin"></textarea></label><div class="v4-dialog-actions"><button type="button" class="v4-ghost" data-quick-note-cancel>Vazgeç</button><button type="button" class="v4-primary" data-quick-note-apply>Ürünü Ekle</button></div></form></dialog>
<dialog id="closeCash" class="v4-dialog"><form method="post" class="v4-dialog-card"><button type="button" class="v4-dialog-x" onclick="closeCash.close()">×</button><h2>Kasayı Kapat</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="close_cash"><label>Sayılan nakit<input type="number" min="0" step="0.01" name="counted_cash" required></label><label>Not<input name="note"></label><button class="v4-primary v4-full">Kasayı Kapat</button></form></dialog>
<script>window.CH_CASHIER={remaining:<?=json_encode((float)$financial['remaining'])?>};function openNote(id,text){noteItem.value=id;noteText.value=text||'';noteDialog.showModal()}</script>
<?php endif;?></body></html>