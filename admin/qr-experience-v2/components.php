<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
require_once __DIR__.'/../../app/Modules/QRExperience/helpers.php';
qrx_require_admin();
$message='';
$groups=['hero_variant'=>['fullscreen'=>'Full Screen','editorial'=>'Editorial','split'=>'Split','minimal'=>'Minimal'],'category_variant'=>['chips'=>'Chips','tabs'=>'Tabs','underline'=>'Underline','scroll'=>'Horizontal Scroll'],'product_card_variant'=>['image-focus'=>'Image Focus','editorial'=>'Editorial','compact'=>'Compact','luxury'=>'Luxury'],'banner_variant'=>['marquee'=>'Marquee','slider'=>'Slider','campaign-card'=>'Campaign Card','announcement'=>'Announcement'],'footer_variant'=>['minimal'=>'Minimal','social'=>'Social','contact'=>'Contact','luxury'=>'Luxury']];
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach(array_keys($groups) as $key){if(isset($_POST[$key])) qrx_save_setting($key,(string)$_POST[$key],'draft');}
 $message='Bileşen seçimleri taslağa kaydedildi.';
}
$s=qrx_all_settings('draft') ?: qrx_all_settings('published');
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bileşenler</title><link rel="stylesheet" href="assets/studio.css"></head><body class="page"><header class="simple"><a href="index.php">← Dashboard</a><h1>Bileşen Kütüphanesi</h1><p>QR menünün her bölümünün görünümünü seç.</p></header><main class="narrow"><?php if($message):?><div class="notice"><?=$message?></div><?php endif;?><form method="post" class="component-grid"><?php foreach($groups as $key=>$items):?><section class="panel"><h2><?=htmlspecialchars(ucwords(str_replace(['_variant','_'],' ',' '.$key)))?></h2><?php foreach($items as $value=>$label):?><label class="component-item"><span><?=htmlspecialchars($label)?></span><input type="radio" name="<?=$key?>" value="<?=$value?>" <?=($s[$key]??'')===$value?'checked':''?>></label><?php endforeach;?></section><?php endforeach;?><div><button type="submit">Seçimleri Kaydet</button></div></form></main></body></html>
