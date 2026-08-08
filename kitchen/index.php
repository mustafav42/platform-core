<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-CherryHouse-KDS-Version: RC1.2');

/*
 * HARD KDS MODULE GATE
 *
 * Module Center is the single source of truth. We intentionally read the
 * exact persisted value and fail closed. The kitchen board renders ONLY
 * when module.kds.enabled === "1".
 */
$kdsSetting = null;
try {
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
    $stmt->execute(['module.kds.enabled']);
    $value = $stmt->fetchColumn();
    $kdsSetting = ($value === false) ? null : trim((string)$value);
} catch (Throwable) {
    $kdsSetting = null;
}

if ($kdsSetting !== '1') {
    header('Location: ../admin/enterprise/?notice=' . rawurlencode('KDS / Mutfak modülü kapalı.'), true, 303);
    exit;
}
$role=(string)($_SESSION['admin_role'] ?? $_SESSION['cashier_role'] ?? $_SESSION['staff_role'] ?? 'guest');
$logged=!empty($_SESSION['admin_id']) || !empty($_SESSION['cashier_id']) || (!empty($_SESSION['staff_id']) && $role==='manager');
if(!$logged) redirect('../admin/');
$pdo=db();
$error='';
$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();
  $orderId=(int)($_POST['order_id']??0);
  $next=(string)($_POST['kitchen_status']??'');
  if(!in_array($next,['new','preparing','ready','delivered'],true)) throw new RuntimeException('Geçersiz mutfak durumu.');
  $sql="UPDATE orders o JOIN table_sessions ts ON ts.id=o.session_id
        SET o.kitchen_status=?,
            o.kitchen_started_at=CASE WHEN ?='preparing' AND o.kitchen_started_at IS NULL THEN NOW() ELSE o.kitchen_started_at END,
            o.kitchen_ready_at=CASE WHEN ?='ready' THEN NOW() ELSE o.kitchen_ready_at END
        WHERE o.id=? AND o.status='submitted' AND ts.status='open'";
  $updateParams=[$next,$next,$next,$orderId];
  try{
      $liveDay=business_day_service()->currentOpenDay();
      $liveDayId=(int)($liveDay['id']??0);
      if($liveDayId>0){$sql.=" AND (ts.business_day_id=? OR ts.business_day_id IS NULL)";$updateParams[]=$liveDayId;}
  }catch(Throwable){}
  $q=$pdo->prepare($sql);
  $q->execute($updateParams);
  if(!$q->rowCount()) throw new RuntimeException('Sipariş bulunamadı veya güncellenemedi.');
  audit_log('kitchen_status_changed','Mutfak sipariş durumu değiştirildi.',['order_id'=>$orderId,'status'=>$next]);
  redirect('./?status='.urlencode((string)($_GET['status']??'active')));
 }catch(Throwable $e){$error=$e->getMessage();}
}
$filter=(string)($_GET['status']??'active');

/*
 * Live KDS must never show historical/closed tickets.
 * It is limited to:
 *   - submitted orders
 *   - an OPEN table session
 *   - the currently OPEN business day when one exists
 *   - active / complimentary order items
 */
$currentBusinessDayId = 0;
try {
    $day = business_day_service()->currentOpenDay();
    $currentBusinessDayId = (int)($day['id'] ?? 0);
} catch (Throwable) {
    $currentBusinessDayId = 0;
}

$where="o.status='submitted' AND ts.status='open' AND oi.status IN ('active','complimentary')";
$params=[];
if($currentBusinessDayId>0){
    $where.=" AND (ts.business_day_id=? OR ts.business_day_id IS NULL)";
    $params[]=$currentBusinessDayId;
}
if(in_array($filter,['new','preparing','ready','delivered'],true)){$where.=' AND o.kitchen_status=?';$params[]=$filter;}
elseif($filter==='active'){$where.=" AND o.kitchen_status IN ('new','preparing','ready')";}
$q=$pdo->prepare("SELECT o.id,o.kitchen_status,o.created_at,o.kitchen_started_at,o.kitchen_ready_at,t.name table_name,a.name area_name,s.name staff_name,oi.product_name,oi.quantity,oi.item_note,oi.status item_status FROM orders o JOIN table_sessions ts ON ts.id=o.session_id JOIN restaurant_tables t ON t.id=ts.table_id JOIN dining_areas a ON a.id=t.area_id LEFT JOIN staff_users s ON s.id=o.staff_id JOIN order_items oi ON oi.order_id=o.id WHERE $where ORDER BY FIELD(o.kitchen_status,'new','preparing','ready','delivered'),o.created_at,o.id,oi.id");
$q->execute($params);
$rows=$q->fetchAll();
$orders=[];
foreach($rows as $r){$id=(int)$r['id'];if(!isset($orders[$id])){$orders[$id]=['id'=>$id,'status'=>$r['kitchen_status'],'created_at'=>$r['created_at'],'started_at'=>$r['kitchen_started_at'],'ready_at'=>$r['kitchen_ready_at'],'table'=>$r['table_name'],'area'=>$r['area_name'],'staff'=>$r['staff_name'],'items'=>[]];}$orders[$id]['items'][]=$r;}
function kdsStatusLabel(string $s): string{return ['new'=>'Yeni','preparing'=>'Hazırlanıyor','ready'=>'Hazır','delivered'=>'Teslim Edildi'][$s]??$s;}
function elapsedLabel(string $date): string{$sec=max(0,time()-strtotime($date));$m=intdiv($sec,60);return $m<60?$m.' dk':intdiv($m,60).' sa '.($m%60).' dk';}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="10"><title>Mutfak Ekranı</title><style>
:root{--bg:#f3f5f8;--ink:#172033;--muted:#667085;--line:#dfe3e8;--new:#ef4444;--prep:#f59e0b;--ready:#16a34a;--done:#64748b}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--ink)}header{height:76px;padding:0 24px;background:#101827;color:#fff;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:5}header h1{margin:0;font-size:24px}header small{color:#cbd5e1}.actions{display:flex;gap:10px;align-items:center}.actions a{color:#fff;text-decoration:none;background:#ffffff14;padding:11px 14px;border-radius:10px}.wrap{padding:20px}.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.filters a{text-decoration:none;color:#344054;background:#fff;border:1px solid var(--line);padding:10px 14px;border-radius:10px;font-weight:700}.filters a.active{background:#111827;color:#fff}.board{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}.ticket{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 8px 24px #10182810}.ticket-head{padding:14px 16px;color:#fff;display:flex;justify-content:space-between;align-items:center}.ticket.new .ticket-head{background:var(--new)}.ticket.preparing .ticket-head{background:var(--prep)}.ticket.ready .ticket-head{background:var(--ready)}.ticket.delivered .ticket-head{background:var(--done)}.ticket-title{font-size:20px;font-weight:900}.ticket-meta{font-size:12px;opacity:.95;margin-top:3px}.timer{font-size:15px;font-weight:900}.items{padding:10px 16px 6px}.item{display:grid;grid-template-columns:52px 1fr;gap:10px;padding:11px 0;border-bottom:1px solid #eef0f3}.item:last-child{border-bottom:0}.qty{font-size:22px;font-weight:900;color:#b42318}.product{font-size:17px;font-weight:800}.note{margin-top:4px;color:#9a3412;background:#fff7ed;border-radius:7px;padding:5px 7px;font-size:12px}.ticket-actions{padding:13px 16px 16px;display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.ticket-actions form{margin:0}.ticket-actions button{width:100%;border:0;border-radius:10px;padding:12px 9px;font-weight:850;cursor:pointer;background:#e9edf2;color:#172033}.ticket-actions button.primary{background:#111827;color:#fff}.empty{background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:60px 20px;text-align:center;color:var(--muted)}.err{background:#fef2f2;color:#991b1b;padding:12px 15px;border-radius:10px;margin-bottom:14px}@media(max-width:700px){header{height:auto;padding:15px;align-items:flex-start;gap:10px}.actions{flex-wrap:wrap;justify-content:flex-end}.wrap{padding:12px}.board{grid-template-columns:1fr}}
</style><link rel="stylesheet" href="../app/assets/cherryhouse-ui-3.css?v=360"></head><body class="ch3-app ch3-kitchen"><header class="ch3-kitchen-header"><div><h1>🍳 Mutfak Ekranı</h1><small>Yeni siparişler 10 saniyede bir otomatik yenilenir · KDS RC1.2</small></div><div style="display:flex;align-items:center;gap:14px"><div class="ch3-status"><span><i></i>Canlı</span></div><div class="actions"><a href="../cashier/">Kasa</a><a href="../admin/">Ana Menü</a></div></div></header><main class="wrap"><?php if($error):?><div class="err"><?=e($error)?></div><?php endif;?><nav class="filters"><?php foreach(['active'=>'Aktif','new'=>'Yeni','preparing'=>'Hazırlanıyor','ready'=>'Hazır','delivered'=>'Teslim'] as $k=>$v):?><a class="<?=$filter===$k?'active':''?>" href="?status=<?=e($k)?>"><?=e($v)?></a><?php endforeach;?></nav><?php if(!$orders):?><div class="empty"><h2>Gösterilecek sipariş yok</h2><p>Garson veya kasiyer siparişi gönderdiğinde burada görünecek.</p></div><?php else:?><section class="board"><?php foreach($orders as $o):?><article class="ticket <?=e($o['status'])?>"><div class="ticket-head"><div><div class="ticket-title"><?=e($o['table'])?></div><div class="ticket-meta"><?=e($o['area'])?> · <?=e($o['staff']?:'Personel')?> · #<?=e($o['id'])?></div></div><div class="timer"><?=e(elapsedLabel((string)$o['created_at']))?></div></div><div class="items"><?php foreach($o['items'] as $it):?><div class="item"><div class="qty"><?=e(rtrim(rtrim(number_format((float)$it['quantity'],2,'.',''),'0'),'.'))?>×</div><div><div class="product"><?=e($it['product_name'])?><?=$it['item_status']==='complimentary'?' · İkram':''?></div><?php if(trim((string)$it['item_note'])!==''):?><div class="note">Not: <?=e($it['item_note'])?></div><?php endif;?></div></div><?php endforeach;?></div><div class="ticket-actions"><?php if($o['status']==='new'):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="kitchen_status" value="preparing"><button class="primary">Hazırlamaya Başla</button></form><?php elseif($o['status']==='preparing'):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="kitchen_status" value="ready"><button class="primary">Hazır</button></form><?php elseif($o['status']==='ready'):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="kitchen_status" value="delivered"><button class="primary">Teslim Edildi</button></form><?php endif;?><?php if($o['status']!=='new'):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="kitchen_status" value="new"><button>Yeniye Döndür</button></form><?php endif;?></div></article><?php endforeach;?></section><?php endif;?></main></body></html>
