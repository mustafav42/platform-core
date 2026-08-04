<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$source=__DIR__.'/files';$ok=[];$errors=[];$dbChanges=[];
$files=['admin/catalog.php','admin/enterprise/products.php','admin/assets/catalog.css','index.php','app/assets/qrx-premium-menu.js','app/assets/qrx-premium-menu.css','app/release.php','install/schema.sql'];
function ch3035_column_exists(PDO $pdo,string $table,string $column): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table,$column]);return (int)$q->fetchColumn()>0;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $backup=$root.'/storage/backups/v30.3.5-'.date('Ymd-His');
  if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
  foreach($files as $rel){$src=$source.'/'.$rel;$dst=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Paket dosyası eksik: '.$rel);
   if(is_file($dst)){$bf=$backup.'/'.$rel;if(!is_dir(dirname($bf))&&!mkdir(dirname($bf),0775,true)&&!is_dir(dirname($bf)))throw new RuntimeException('Yedek alt klasörü oluşturulamadı.');if(!copy($dst,$bf))throw new RuntimeException('Dosya yedeklenemedi: '.$rel);}
   if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('Hedef klasör oluşturulamadı.');if(!copy($src,$dst))throw new RuntimeException('Dosya yazılamadı: '.$rel);$ok[]=$rel;
  }
  require_once $root.'/app/bootstrap.php';$pdo=db();
  $columns=[
   'calories_kcal'=>'ALTER TABLE products ADD COLUMN calories_kcal SMALLINT UNSIGNED NULL AFTER image_path',
   'prep_time_min'=>'ALTER TABLE products ADD COLUMN prep_time_min SMALLINT UNSIGNED NULL AFTER calories_kcal',
   'allergen_codes'=>'ALTER TABLE products ADD COLUMN allergen_codes VARCHAR(500) NULL AFTER prep_time_min'
  ];
  foreach($columns as $name=>$sql){if(!ch3035_column_exists($pdo,'products',$name)){$pdo->exec($sql);$dbChanges[]=$name;}else{$dbChanges[]=$name.' (mevcut)';}}
  try{$pdo->exec("UPDATE settings SET setting_value='".date('YmdHis')."' WHERE setting_key='qrx_publish_revision'");}catch(Throwable $ignore){}
  if(function_exists('audit_log')){try{audit_log('system_update','Product Information System kuruldu.',['version'=>'30.3.5']);}catch(Throwable $ignore){}}
  if(function_exists('opcache_reset'))@opcache_reset();
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.5</title><style>body{font-family:Inter,Arial,sans-serif;background:#f4f5f8;color:#171a22;padding:30px}.box{max-width:850px;margin:auto;background:#fff;border:1px solid #e2e5ec;border-radius:20px;padding:28px;box-shadow:0 18px 60px #19202a12}button,a{display:inline-block;border:0;border-radius:11px;padding:12px 16px;background:#201719;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.ok{background:#eaf8f1;color:#237553;padding:14px;border-radius:11px}.err{background:#fff0f0;color:#a02d39;padding:14px;border-radius:11px}.note{background:#fff7e8;color:#805d18;padding:14px;border-radius:11px;margin:16px 0}li{margin:6px 0}</style></head><body><div class="box"><h1>v30.3.5 Product Information System</h1><p>Ürünlere alerjen, kalori ve hazırlama süresi ekler. QR ürün detayının sade tasarımı korunur.</p><div class="note">Bilgi girilmeyen ürünlerde QR penceresinde hiçbir boş başlık veya alan görünmez.</div><?php foreach($errors as $e):?><div class="err"><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?><?php if($ok&&!$errors):?><div class="ok"><b>Güncelleme başarılı.</b><br>Veritabanı alanları: <?=htmlspecialchars(implode(', ',$dbChanges),ENT_QUOTES,'UTF-8')?></div><p><a href="../../admin/catalog.php?tab=products">Ürünleri Düzenle</a> <a href="../../" target="_blank">QR Menüyü Aç</a></p><?php else:?><ul><li>Alerjen seçimleri</li><li>Kalori bilgisi</li><li>Hazırlama süresi</li><li>Sade QR ürün bilgi alanı</li></ul><form method="post"><button type="submit">Product Information System’i Kur</button></form><?php endif;?></div></body></html>
