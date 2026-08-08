<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__).'/app/bootstrap.php';
if (!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
$pdo=db(); $error=''; $notice='';
if(isset($_GET['logout'])){$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();redirect('../admin/');}
if(empty($_SESSION['staff_id']) || (string)($_SESSION['staff_role']??'')!=='waiter'){$_SESSION['login_return_to']='staff';redirect('../admin/');}
if((time()-(int)($_SESSION['staff_last_activity']??0))>43200){$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();redirect('../admin/');}
$_SESSION['staff_last_activity']=time();
$staffId=(int)$_SESSION['staff_id'];
$tableLifecycle=new TableLifecycleService($pdo);
BusinessDayService::install($pdo);$businessDayService=business_day_service();$businessDay=$businessDayService->current();
$sessionId=(int)($_GET['session']??0);
$categoryId=(int)($_GET['category']??0);
$_SESSION['waiter_pending_orders'] ??= [];
if($sessionId===0){
 foreach(array_keys($_SESSION['waiter_pending_orders']) as $draftSid){
  if($tableLifecycle->reconcileSession((int)$draftSid)) unset($_SESSION['waiter_pending_orders'][$draftSid]);
 }
 $tableLifecycle->cleanupEmptyOpenSessions($staffId);
}

function waiterCart(int $sessionId): array { return $_SESSION['waiter_pending_orders'][$sessionId] ?? []; }
function waiterCartTotal(array $cart): float { $total=0.0; foreach($cart as $row){$total+=(float)$row['unit_price']*(float)$row['quantity'];} return $total; }
function waiterWantsJson(): bool { return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest' || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json'); }
function waiterJson(array $payload,int $status=200): never { if(ob_get_length())ob_clean(); http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

try{
 if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
  verify_csrf(); $action=(string)$_POST['action'];
  if(in_array($action,['open_table','stage_item','pending_qty','remove_pending','discard_pending','submit_order','remove_item'],true)){$businessDay=$businessDayService->requireOpen();}
  if($action==='open_table'){
   $tableId=(int)$_POST['table_id']; $guest=1;
   $pdo->beginTransaction();
   $q=$pdo->prepare("SELECT status FROM restaurant_tables WHERE id=? AND is_active=1 FOR UPDATE");$q->execute([$tableId]);$status=$q->fetchColumn();
   if($status===false)throw new RuntimeException('Masa bulunamadı.');
   $q=$pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status='open' ORDER BY id DESC LIMIT 1");$q->execute([$tableId]);$existing=(int)($q->fetchColumn()?:0);
   if($existing){$pdo->commit();redirect('./?session='.$existing);}
   if($status!=='empty')throw new RuntimeException('Masa açılmaya uygun değil.');
   $pdo->prepare("INSERT INTO table_sessions(table_id,opened_by_staff_id,guest_count,status,opened_at,business_day_id) VALUES(?,?,?,'open',NOW(),?)")->execute([$tableId,$staffId,$guest,(int)$businessDay['id']]);
   $newId=(int)$pdo->lastInsertId();$pdo->commit();redirect('./?session='.$newId);
  }
  if($action==='stage_item'){
   $sid=(int)$_POST['session_id'];$productId=(int)$_POST['product_id'];$qty=max(0.01,min(99,(float)($_POST['quantity']??1)));$note=mb_substr(trim((string)($_POST['item_note']??'')),0,500);
   $q=$pdo->prepare("SELECT id FROM table_sessions WHERE id=? AND status='open'");$q->execute([$sid]);if(!$q->fetchColumn())throw new RuntimeException('Açık adisyon bulunamadı.');
   $q=$pdo->prepare("SELECT id,name,price FROM products WHERE id=? AND is_active=1");$q->execute([$productId]);$p=$q->fetch();if(!$p)throw new RuntimeException('Ürün bulunamadı.');
   $cart=waiterCart($sid);$merged=false;
   foreach($cart as &$row){if((int)$row['product_id']===$productId && (string)$row['item_note']===$note){$row['quantity']=min(99,(float)$row['quantity']+$qty);$merged=true;break;}}unset($row);
   if(!$merged)$cart[]=['key'=>bin2hex(random_bytes(5)),'product_id'=>$productId,'product_name'=>$p['name'],'unit_price'=>(float)$p['price'],'quantity'=>$qty,'item_note'=>$note];
   $_SESSION['waiter_pending_orders'][$sid]=$cart;
   if(waiterWantsJson()) waiterJson(['ok'=>true,'pending'=>$cart,'pending_total'=>waiterCartTotal($cart),'pending_count'=>array_sum(array_map(fn($r)=>(float)$r['quantity'],$cart)),'ticket_count'=>count($cart)]);
   redirect('./?session='.$sid.'&category='.$categoryId);
  }
  if($action==='pending_qty'){
   $sid=(int)$_POST['session_id'];$key=(string)$_POST['key'];$delta=(float)($_POST['delta']??0);$cart=waiterCart($sid);
   foreach($cart as $i=>$row){if(hash_equals((string)$row['key'],$key)){$new=(float)$row['quantity']+$delta;if($new<=0)unset($cart[$i]);else $cart[$i]['quantity']=min(99,$new);break;}}
   $_SESSION['waiter_pending_orders'][$sid]=array_values($cart);redirect('./?session='.$sid.'&category='.$categoryId);
  }
  if($action==='remove_pending'){
   $sid=(int)$_POST['session_id'];$key=(string)$_POST['key'];$_SESSION['waiter_pending_orders'][$sid]=array_values(array_filter(waiterCart($sid),fn($r)=>!hash_equals((string)$r['key'],$key)));redirect('./?session='.$sid.'&category='.$categoryId);
  }
  if($action==='discard_pending'){
   $sid=(int)$_POST['session_id'];unset($_SESSION['waiter_pending_orders'][$sid]);redirect('./?session='.$sid.'&category='.$categoryId);
  }
  if($action==='submit_order'){
   $sid=(int)$_POST['session_id'];$cart=waiterCart($sid);if(!$cart)throw new RuntimeException('Gönderilecek yeni ürün yok.');
   $q=$pdo->prepare("SELECT id FROM table_sessions WHERE id=? AND status='open'");$q->execute([$sid]);if(!$q->fetchColumn())throw new RuntimeException('Açık adisyon bulunamadı.');
   $pdo->beginTransaction();$pdo->prepare("INSERT INTO orders(session_id,staff_id,status,created_at,business_day_id) VALUES(?,?,'submitted',NOW(),?)")->execute([$sid,$staffId,(int)$businessDay['id']]);$oid=(int)$pdo->lastInsertId();
   $ins=$pdo->prepare("INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity,item_note,status) VALUES(?,?,?,?,?,?,'active')");
   foreach($cart as $row)$ins->execute([$oid,$row['product_id'],$row['product_name'],$row['unit_price'],$row['quantity'],$row['item_note']]);
   $tableLifecycle->activateSession($sid);$pdo->commit();unset($_SESSION['waiter_pending_orders'][$sid]);redirect('./?sent=1');
  }
  if($action==='remove_item'){
   $sid=(int)$_POST['session_id'];$itemId=(int)$_POST['item_id'];$q=$pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id=oi.order_id SET oi.status='cancelled' WHERE oi.id=? AND o.session_id=? AND oi.status='active'");$q->execute([$itemId,$sid]);if($tableLifecycle->reconcileSession($sid)){unset($_SESSION['waiter_pending_orders'][$sid]);redirect('./');}redirect('./?session='.$sid.'&category='.$categoryId);
  }
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(waiterWantsJson())waiterJson(['ok'=>false,'message'=>$e->getMessage()],422);$error=$e->getMessage();}

$areas=$pdo->query('SELECT * FROM dining_areas WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$tables=$pdo->query("SELECT t.*,a.name area_name,ts.id session_id,ts.guest_count,ts.opened_at,COALESCE(SUM(CASE WHEN oi.status='active' THEN oi.unit_price*oi.quantity ELSE 0 END),0)-COALESCE(ts.discount_amount,0) table_total FROM restaurant_tables t JOIN dining_areas a ON a.id=t.area_id LEFT JOIN table_sessions ts ON ts.table_id=t.id AND ts.status='open' AND EXISTS (SELECT 1 FROM orders ox JOIN order_items oix ON oix.order_id=ox.id WHERE ox.session_id=ts.id AND oix.status IN ('active','complimentary') AND oix.quantity>0) LEFT JOIN orders o ON o.session_id=ts.id LEFT JOIN order_items oi ON oi.order_id=o.id WHERE t.is_active=1 AND t.status<>'disabled' AND a.is_active=1 GROUP BY t.id,a.name,ts.id,ts.guest_count,ts.opened_at,ts.discount_amount,a.sort_order ORDER BY a.sort_order,t.id")->fetchAll();
$active=null;$items=[];$categories=[];$products=[];$total=0.0;$pending=[];
if($sessionId){
 $q=$pdo->prepare("SELECT ts.*,t.name table_name,a.name area_name,s.name opener_name FROM table_sessions ts JOIN restaurant_tables t ON t.id=ts.table_id JOIN dining_areas a ON a.id=t.area_id LEFT JOIN staff_users s ON s.id=ts.opened_by_staff_id WHERE ts.id=? AND ts.status='open'");$q->execute([$sessionId]);$active=$q->fetch();
 if(!$active){$error='Açık adisyon bulunamadı.';$sessionId=0;}else{
  $q=$pdo->prepare("SELECT oi.*,o.created_at,s.name staff_name FROM order_items oi JOIN orders o ON o.id=oi.order_id LEFT JOIN staff_users s ON s.id=o.staff_id WHERE o.session_id=? AND oi.status='active' ORDER BY oi.id DESC");$q->execute([$sessionId]);$items=$q->fetchAll();foreach($items as $it)$total+=(float)$it['unit_price']*(float)$it['quantity'];
  $categories=$pdo->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();if(!$categoryId&&$categories)$categoryId=(int)$categories[0]['id'];
  $q=$pdo->prepare("SELECT id,name,price,image_path FROM products WHERE is_active=1 AND category_id=? ORDER BY sort_order,id");$q->execute([$categoryId]);$products=$q->fetchAll();$pending=waiterCart($sessionId);
 }
}
if(ob_get_length())ob_clean();
if(!$businessDay){?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>İş Günü Kapalı</title><style>body{margin:0;font-family:Inter,system-ui;background:#f4f6f8;display:grid;place-items:center;min-height:100vh;color:#172033}.lock{max-width:600px;background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:34px;text-align:center;box-shadow:0 20px 60px #0f172a12}.icon{font-size:56px}.lock h1{font-size:29px;margin:12px}.lock p{color:#667085;line-height:1.6}.lock a{display:inline-block;margin-top:12px;padding:13px 18px;border-radius:12px;background:#344054;color:#fff;text-decoration:none;font-weight:800}</style></head><body><section class="lock"><div class="icon">🔒</div><h1>İşletme henüz satışa açılmadı</h1><p>Yetkili personel Gün Başı yapana kadar masa ve sipariş işlemleri kullanılamaz.</p><a href="?logout=1">Çıkış Yap</a></section></body></html><?php exit;}?>
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>CherryHouse POS · Garson</title><link rel="stylesheet" href="../app/assets/pos-v4.css?v=420"><link rel="stylesheet" href="../app/assets/cherry-design/tokens.css?v=300"><link rel="stylesheet" href="../app/assets/pos-v5-alpha.css?v=3010"><link rel="stylesheet" href="../app/assets/pos-v5-beta.css?v=3020"><link rel="stylesheet" href="../app/assets/table-flow-hotfix.css?v=5100"><link rel="stylesheet" href="../app/assets/pos-waiter-ui-v2.css?v=3052"><link rel="stylesheet" href="../app/assets/pos-waiter-ui-v2.1.css?v=3053"><script defer src="../app/assets/pos-v4.js?v=30532"></script><script defer src="../app/assets/pos-v5-alpha.js?v=3010"></script><script defer src="../app/assets/pos-v5-beta.js?v=3020"></script><script defer src="../app/assets/pos-waiter-ui-v2.1.js?v=30532"></script></head>
<body class="chv4 chv4-waiter ch-pos5">
<header class="v4-topbar"><a class="v4-back" href="<?=$active?'./':'../admin/'?>">‹</a><div class="v4-brand"><span class="v4-mark">🍒</span><div><strong><?=e(setting('business_name','CherryHouse'))?></strong><small>POS 5.0 Beta · Garson</small></div></div><div class="v4-top-status"><span class="online-dot"></span> Çevrimiçi <b class="v4-live-clock" data-live-clock></b></div><button type="button" class="v4-fullscreen" data-fullscreen title="Tam ekran">⛶</button><div class="v4-user"><span><?=e($_SESSION['staff_name'])?></span><a href="?logout=1">Çıkış</a></div></header>
<?php if($error):?><div class="v4-alert danger"><?=e($error)?></div><?php endif;?><?php if(isset($_GET['sent'])):?><div class="v4-alert success">Sipariş mutfağa gönderildi.</div><?php endif;?>
<?php if(!$active):?>
<main class="v4-table-layout">
 <aside class="v4-side-nav"><div class="v4-side-title">SALONLAR</div><button class="v4-side-link active" data-area="all">Tüm Masalar</button><?php foreach($areas as $area):?><button class="v4-side-link" data-area="area-<?=(int)$area['id']?>"><?=e($area['name'])?></button><?php endforeach;?><div class="v4-side-spacer"></div><a class="v4-side-link" href="../kitchen/">Mutfak</a></aside>
 <section class="v4-table-main"><div class="v4-page-head"><div><h1>Masalar</h1><p>Sipariş almak için masa seçin.</p></div><div class="v4-metrics"><div><b><?=count(array_filter($tables,fn($t)=>$t['status']==='empty'))?></b><span>Boş</span></div><div><b><?=count(array_filter($tables,fn($t)=>!empty($t['session_id'])))?></b><span>Dolu</span></div><div><b><?=count($tables)?></b><span>Toplam</span></div></div></div>
 <div class="v4-table-toolbar ch-table-toolbar"><label class="v4-search"><span>⌕</span><input type="search" placeholder="Masa ara..." data-table-search></label><div class="v4-legend"><i class="empty"></i>Boş <i class="open"></i>Dolu <i class="reserved"></i>Rezerve</div></div>
 <div class="v4-table-grid ch-table-grid" data-table-grid><?php foreach($tables as $t):$status=(string)$t['status'];$isOpen=!empty($t['session_id']);$statusLabel=$isOpen?'DOLU':($status==='empty'?'BOŞ':($status==='reserved'?'REZERVELİ':mb_strtoupper($status)));?><article class="v4-table-card <?=$isOpen?'is-open':'is-'.$status?>" <?php if(!empty($t['opened_at'])):?>data-opened="<?=e((string)$t['opened_at'])?>"<?php endif;?> data-area="area-<?=(int)$t['area_id']?>" data-table-id="<?=(int)$t['id']?>" data-session-id="<?=(int)($t['session_id']??0)?>" data-table-name="<?=e(mb_strtolower((string)$t['name']))?>">
 <?php if($isOpen):?><a href="?session=<?=(int)$t['session_id']?>" aria-label="<?=e($t['name'])?> sipariş ekranına geç"><div class="ch-table-card-v2"><div class="ch-table-card-v2__top"><span class="ch-table-card-v2__status"><?=e($statusLabel)?></span><span class="ch-table-card-v2__more" aria-hidden="true">⋮</span></div><div class="ch-table-card-v2__identity"><h3 class="ch-table-card-v2__name"><?=e($t['name'])?></h3><span class="ch-table-card-v2__area"><?=e($t['area_name'])?></span></div><div class="ch-table-card-v2__divider"></div><div class="ch-table-card-v2__metrics"><span class="ch-table-card-v2__metric"><span class="ch-table-card-v2__metric-icon">♙</span><span class="ch-table-card-v2__metric-copy"><b><?=(int)$t['guest_count']?></b><small>Kişi</small></span></span><span class="ch-table-card-v2__metric"><span class="ch-table-card-v2__metric-icon">◷</span><span class="ch-table-card-v2__metric-copy"><b data-open-duration>-- dk</b><small>Bekleme</small></span></span></div><strong class="ch-table-card-v2__footer"><?=number_format((float)$t['table_total'],2,',','.')?> ₺</strong></div></a>
 <?php elseif($status==='empty'):?><form method="post" class="ch-direct-table-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="open_table"><input type="hidden" name="table_id" value="<?=(int)$t['id']?>"><button type="submit" class="ch-direct-table-button" aria-label="<?=e($t['name'])?> masasını aç ve siparişe geç"><div class="ch-table-card-v2"><div class="ch-table-card-v2__top"><span class="ch-table-card-v2__status"><?=e($statusLabel)?></span><span class="ch-table-card-v2__more" aria-hidden="true">⋮</span></div><div class="ch-table-card-v2__identity"><h3 class="ch-table-card-v2__name"><?=e($t['name'])?></h3><span class="ch-table-card-v2__area"><?=e($t['area_name'])?></span></div><div class="ch-table-card-v2__divider"></div><div class="ch-table-card-v2__metrics is-empty"><span class="ch-table-card-v2__empty-icon" aria-hidden="true">◫</span></div><span class="ch-table-card-v2__footer is-empty" aria-hidden="true">—</span></div></button></form>
 <?php else:?><div class="ch-table-card-v2"><div class="ch-table-card-v2__top"><span class="ch-table-card-v2__status"><?=e($statusLabel)?></span><span class="ch-table-card-v2__more" aria-hidden="true">⋮</span></div><div class="ch-table-card-v2__identity"><h3 class="ch-table-card-v2__name"><?=e($t['name'])?></h3><span class="ch-table-card-v2__area"><?=e($t['area_name'])?></span></div><div class="ch-table-card-v2__divider"></div><div class="ch-table-card-v2__metrics is-empty"><span class="ch-table-card-v2__empty-icon" aria-hidden="true">◫</span></div><span class="ch-table-card-v2__footer is-reservation"><?=e($statusLabel)?></span></div><?php endif;?></article><?php endforeach;?></div></section>
</main>
<?php if($active):?>
<nav class="v4-quick-dock" aria-label="Hızlı işlemler">
 <a href="./"><span>▦</span><b>Masalar</b></a>
 <button type="button" data-focus-product><span>⌕</span><b>Ürün Ara</b></button>
 <button type="button" data-scroll-ticket><span>≡</span><b>Adisyon</b></button>
 <?php if($pending):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="discard_pending"><input type="hidden" name="session_id" value="<?=$sessionId?>"><button type="submit"><span>↺</span><b>Temizle</b></button></form><?php endif;?>
 <form method="post" class="is-primary"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="submit_order"><input type="hidden" name="session_id" value="<?=$sessionId?>"><button type="submit" <?=$pending?'':'disabled'?>> <span>➜</span><b>Gönder</b></button></form>
</nav>
<?php endif;?>

<?php else:?>
<main class="v4-order-layout">
 <aside class="v4-category-rail"><div class="v4-rail-head"><a href="./">‹ Masalar</a><small>KATEGORİLER</small></div><nav><?php foreach($categories as $c):?><a class="<?=$categoryId===(int)$c['id']?'active':''?>" href="?session=<?=$sessionId?>&category=<?=(int)$c['id']?>" title="<?=e($c['name'])?>"><span><?=e($c['name'])?></span></a><?php endforeach;?></nav></aside>
 <section class="v4-product-zone"><div class="v4-work-head"><div><span class="v4-eyebrow"><?=e($active['area_name'])?></span><h1><?=e($active['table_name'])?></h1></div><label class="v4-search"><span>⌕</span><input type="search" placeholder="Ürün ara..." data-product-search></label></div><div class="v4-mobile-view-switch" data-mobile-switch><button type="button" class="active" data-mobile-view="products">Ürünler</button><button type="button" data-mobile-view="ticket">Adisyon <span><?=count($items)+count($pending)?></span></button></div><div class="v4-product-grid"><?php foreach($products as $p):?><form method="post" class="v4-product-form" data-allow-repeat="1" data-optimistic-product="1" data-product-name="<?=e(mb_strtolower($p['name']))?>" data-quick-product data-product-label="<?=e($p['name'])?>" data-product-id="<?=(int)$p['id']?>" data-product-price="<?=e((string)$p['price'])?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="stage_item"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><input type="hidden" name="quantity" value="1" data-quick-quantity><input type="hidden" name="item_note" value="" data-quick-note><button type="submit" class="v4-product-card" data-quick-trigger><span class="v4-qty-badge" data-quick-badge hidden>1</span><div class="v4-product-image"<?php if($p['image_path']):?> style="background-image:url('../<?=e(ltrim((string)$p['image_path'],'/'))?>')"<?php endif;?>><?=$p['image_path']?'':'🍽️'?></div><div><strong><?=e($p['name'])?></strong><b><?=number_format((float)$p['price'],2,',','.')?> ₺</b></div><small class="v4-hold-hint">Basılı tut: not</small></button></form><?php endforeach;?></div></section>
 <aside class="v4-ticket ch-waiter-ticket">
  <header class="ch-ticket-header"><div><span class="v4-eyebrow">ADİSYON</span><h2><?=e($active['table_name'])?></h2><small><?=(int)$active['guest_count']?> kişi · <?=date('H:i',strtotime((string)$active['opened_at']))?></small></div><span class="v4-ticket-count" data-ticket-count><?=count($items)+count($pending)?></span></header>
  <section class="ch-ticket-actions">
   <div class="ch-ticket-grand-total"><span><b>Toplam</b><small><span data-ticket-item-count><?=count($items)+count($pending)?></span> ürün</small></span><strong data-ticket-total data-committed-total="<?=e((string)$total)?>"><?=number_format($total+waiterCartTotal($pending),2,',','.')?> ₺</strong></div>
   <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="submit_order"><input type="hidden" name="session_id" value="<?=$sessionId?>"><button class="v4-primary v4-full ch-send-order" data-send-order <?=$pending?'':'disabled'?>>Siparişi Gönder</button></form>
   <form method="post" data-clear-order-form <?=$pending?'':'hidden'?>><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="discard_pending"><input type="hidden" name="session_id" value="<?=$sessionId?>"><button class="v4-ghost v4-full ch-clear-order">Seçimleri Temizle</button></form>
  </section>
  <div class="v4-ticket-body ch-ticket-scroll">
   <h3>Gönderilenler</h3><?php if(!$items):?><p class="v4-empty-text">Henüz gönderilmiş ürün yok.</p><?php endif;?>
   <?php foreach($items as $it):?><div class="v4-line committed"><div><b><?=e($it['quantity'])?>×</b><span><strong><?=e($it['product_name'])?></strong><small><?=e($it['staff_name']??'')?><?php if($it['item_note']):?> · 📝 <?=e($it['item_note'])?><?php endif;?></small></span></div><em><?=number_format((float)$it['unit_price']*(float)$it['quantity'],2,',','.')?> ₺</em></div><?php endforeach;?>
   <h3 class="new">Yeni Sipariş <span data-pending-heading-count><?=count($pending)?></span></h3><p class="v4-empty-text" data-pending-empty <?=$pending?'hidden':''?>>Ürünlere dokunarak ekleyin.</p><div data-pending-list>
   <?php foreach($pending as $row):?><div class="v4-line pending"><div><b><?=e($row['quantity'])?>×</b><span><strong><?=e($row['product_name'])?></strong><small><?=number_format((float)$row['unit_price'],2,',','.')?> ₺<?php if($row['item_note']):?> · 📝 <?=e($row['item_note'])?><?php endif;?></small></span></div><em><?=number_format((float)$row['unit_price']*(float)$row['quantity'],2,',','.')?> ₺</em><div class="v4-line-actions"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="pending_qty"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="key" value="<?=e($row['key'])?>"><input type="hidden" name="delta" value="-1"><button>−</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="pending_qty"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="key" value="<?=e($row['key'])?>"><input type="hidden" name="delta" value="1"><button>+</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remove_pending"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="key" value="<?=e($row['key'])?>"><button class="danger">×</button></form></div></div><?php endforeach;?></div>
  </div>
  <footer class="ch-ticket-footer"><span>Mevcut <b><?=number_format($total,2,',','.')?> ₺</b></span><span>Yeni <b data-new-total><?=number_format(waiterCartTotal($pending),2,',','.')?> ₺</b></span></footer>
 </aside>
</main><?php endif;?>
<dialog id="quickNoteDialog" class="v4-dialog v4-quick-note-dialog"><form method="dialog" class="v4-dialog-card"><button type="button" class="v4-dialog-x" data-quick-note-close>×</button><span class="v4-eyebrow">HIZLI NOT</span><h2 data-quick-note-title>Ürün Notu</h2><div class="v4-note-presets" data-note-presets><button type="button">Az pişmiş</button><button type="button">Orta</button><button type="button">İyi pişmiş</button><button type="button">Acısız</button><button type="button">Az acılı</button><button type="button">Bol acılı</button><button type="button">Soğansız</button><button type="button">Sarımsaksız</button></div><label>Özel not<textarea rows="3" data-quick-note-text placeholder="Örn. sos ayrı gelsin"></textarea></label><div class="v4-dialog-actions"><button type="button" class="v4-ghost" data-quick-note-cancel>Vazgeç</button><button type="button" class="v4-primary" data-quick-note-apply>Ürünü Ekle</button></div></form></dialog>
</body></html>