<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/permissions.php';
if (empty($_SESSION['admin_id'])) redirect('./');
require_permission('permissions.manage');
$pdo=db();$notice='';$error='';
$roles=['manager','cashier','waiter'];$catalog=permission_catalog();
$defaults=[
 'manager'=>array_keys($catalog),
 'cashier'=>['dashboard.view','pos.access','payment.receive','discount.apply','complimentary.apply','table.transfer','cash.close'],
 'waiter'=>['pos.access','complimentary.apply','table.transfer'],
];
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$role=(string)($_POST['role']??'');
  if(!in_array($role,$roles,true)) throw new RuntimeException('Geçersiz rol.');
  $selected=array_values(array_intersect(array_keys($catalog),(array)($_POST['permissions']??[])));
  $pdo->beginTransaction();
  $q=$pdo->prepare('INSERT INTO role_permissions(role_key,permission_key,is_allowed,updated_by,updated_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_by=VALUES(updated_by),updated_at=NOW()');
  foreach(array_keys($catalog) as $perm)$q->execute([$role,$perm,in_array($perm,$selected,true)?1:0,(int)$_SESSION['admin_id']]);
  $pdo->commit();audit_log('role_permissions_updated','Rol yetkileri güncellendi.',['role'=>$role,'permissions'=>$selected]);$notice=role_label($role).' yetkileri kaydedildi.';
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
$current=[];
try {$q=$pdo->query('SELECT role_key,permission_key,is_allowed FROM role_permissions');foreach($q as $r)$current[$r['role_key']][$r['permission_key']]=(bool)$r['is_allowed'];}
catch(Throwable $e){$error=$error ?: $e->getMessage();}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Personel Merkezi · Roller ve Yetkiler</title><style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:Inter,system-ui}.wrap{max-width:1180px;margin:auto;padding:30px}.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.head a{color:#fff;background:#111827;text-decoration:none;padding:11px 15px;border-radius:10px}.notice,.error{padding:13px;border-radius:11px;margin-bottom:15px}.notice{background:#ecfdf5;color:#166534}.error{background:#fef2f2;color:#991b1b}.roles{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card{background:#fff;border:1px solid #e8ecf3;border-radius:18px;padding:20px;box-shadow:0 12px 34px #1f29350d}.role{display:flex;align-items:center;gap:12px;margin-bottom:16px}.icon{width:46px;height:46px;border-radius:14px;background:#ede9fe;display:grid;place-items:center;font-size:22px}.role h2{margin:0;font-size:18px}.role p{margin:3px 0 0;color:#778198;font-size:12px}.perm{display:flex;gap:10px;align-items:flex-start;padding:11px 0;border-bottom:1px solid #edf0f5}.perm:last-of-type{border:0}.perm input{width:18px;height:18px;margin-top:1px}.perm strong{font-size:13px}.perm small{display:block;color:#778198;margin-top:3px}.save{width:100%;margin-top:16px;border:0;border-radius:11px;padding:12px;background:linear-gradient(135deg,#6d5dfc,#7c3aed);color:#fff;font-weight:800;cursor:pointer}.note{background:#fff7ed;color:#9a5310;padding:13px;border-radius:12px;margin-bottom:18px}@media(max-width:900px){.roles{grid-template-columns:1fr}.wrap{padding:18px}}</style><link rel="stylesheet" href="assets/admin-premium.css?v=310"><link rel="stylesheet" href="assets/personnel-center.css?v=2724"></head><body><main class="wrap personnel-permissions"><div class="head"><div><h1>Personel Merkezi</h1><p>Personel rollerinin erişebileceği ekranları ve kritik işlemleri belirleyin.</p></div><a href="./?page=staff">← Personellere Dön</a></div><nav class="personnel-tabs" aria-label="Personel Merkezi"><a href="./?page=staff">👥 Personeller</a><a class="active" href="permissions.php">🛡 Roller ve Yetkiler</a></nav><?php if($notice):?><div class="notice"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="error"><?=e($error)?></div><?php endif;?><div class="note"><strong>Tam Yetkili Yönetici</strong> ve <strong>İşletme Sahibi</strong> rolleri güvenlik amacıyla her zaman tüm yetkilere sahiptir.</div><section class="roles"><?php foreach($roles as $role):?><form class="card" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="role" value="<?=e($role)?>"><div class="role"><div class="icon"><?=$role==='manager'?'◆':($role==='cashier'?'₺':'♨')?></div><div><h2><?=e(role_label($role))?></h2><p><?=e($role==='manager'?'Yönetim ve raporlama işlemleri':($role==='cashier'?'Kasa ve tahsilat işlemleri':'Masa ve sipariş işlemleri'))?></p></div></div><?php foreach($catalog as $key=>$label): $checked=$current[$role][$key]??in_array($key,$defaults[$role],true);?><label class="perm"><input type="checkbox" name="permissions[]" value="<?=e($key)?>" <?=$checked?'checked':''?>><span><strong><?=e($label)?></strong><small><?=e($key)?></small></span></label><?php endforeach;?><button class="save">Yetkileri Kaydet</button></form><?php endforeach;?></section></main><script src="assets/admin-premium.js?v=310"></script></body></html>
