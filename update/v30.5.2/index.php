<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
$done=false;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();
  $pdo=db();
  if(db_table_exists($pdo,'settings')){
   $q=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('app_version','30.5.2') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
   $q->execute();
  }
  audit_log('waiter_workspace_ui_updated','Garson sipariş çalışma alanı UI v2 düzenine geçirildi.');
  if(function_exists('opcache_reset')) @opcache_reset();
  $done=true;
 }catch(Throwable $e){$error=$e->getMessage();if(function_exists('app_log'))app_log($e,['update'=>'30.5.2']);}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.5.2</title><style>body{margin:0;background:#f4f6f8;font-family:Inter,system-ui;color:#172033}.wrap{max-width:760px;margin:60px auto;padding:24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:32px;box-shadow:0 20px 60px #0f172a10}h1{margin:0 0 8px}p{color:#667085;line-height:1.65}.items{display:grid;gap:10px;margin:24px 0}.item{padding:13px 15px;background:#f8fafc;border-radius:12px}.ok{padding:16px;background:#ecfdf3;color:#067647;border-radius:14px}.err{padding:16px;background:#fef3f2;color:#b42318;border-radius:14px}button,a.btn{display:inline-block;border:0;border-radius:13px;padding:14px 18px;background:#e86828;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}</style></head><body><main class="wrap"><section class="card"><small>CHERRYHOUSE POS 5.0</small><h1>v30.5.2 — Garson Workspace UI v2</h1><p>Garson sipariş ekranındaki kategori ve adisyon kaydırma sorunlarını giderir.</p><div class="items"><div class="item">Kategori listesi bağımsız ve kesintisiz kaydırılır.</div><div class="item">Toplam, Siparişi Gönder ve Temizle işlemleri üstte sabit kalır.</div><div class="item">Yalnızca sipariş ürünleri listesi kaydırılır.</div><div class="item">Masaüstü ve tablet ekranları için ayrı taşma kuralları uygulanır.</div></div><?php if($error):?><div class="err"><?=e($error)?></div><?php elseif($done):?><div class="ok">Güncelleme tamamlandı.</div><p><a class="btn" href="../../staff/">Garson ekranını aç</a></p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button>Garson Workspace UI v2’yi Uygula</button></form><?php endif;?></section></main></body></html>
