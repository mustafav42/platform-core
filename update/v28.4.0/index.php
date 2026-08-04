<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$target=$root.'/admin/index.php';
$result='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!is_file($target)){$result='admin/index.php bulunamadı.';}
 else{
  $src=file_get_contents($target);$backup=$target.'.bak-v28.4.0-'.date('Ymd-His');
  if(str_contains($src,'menu-center.php')){$result='Menü Yönetimi bağlantısı zaten mevcut.';$ok=true;}
  else{
   copy($target,$backup);
   $link='<a href="menu-center.php">🍽 Menü Yönetimi</a>';
   $patched=null;
   foreach(['</nav>','</aside>'] as $needle){$pos=strpos($src,$needle);if($pos!==false){$patched=substr($src,0,$pos).$link.substr($src,$pos);break;}}
   if($patched!==null && file_put_contents($target,$patched)!==false){$result='Bağlantı ana panele eklendi. Yedek: '.basename($backup);$ok=true;}else{$result='Menü alanı otomatik bulunamadı. admin/index.php değiştirilmedi; yedek oluşturuldu.';}
  }
 }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>v28.4.0 Güncelleme</title><style>body{font-family:system-ui;background:#f6f2f0;margin:0;padding:30px;color:#291d1d}.box{max-width:680px;margin:40px auto;background:#fff;padding:28px;border-radius:22px;box-shadow:0 18px 60px #2b171717}button,a{display:inline-block;border:0;border-radius:12px;padding:12px 18px;text-decoration:none;cursor:pointer}button{background:#371d20;color:#fff}.msg{padding:14px;border-radius:12px;background:#f3eeec;margin:16px 0}.ok{background:#eaf7ef;color:#196b3a}</style></head><body><div class="box"><h1>CherryHouse v28.4.0</h1><p>Menü Yönetimi Merkezi bağlantısını ana yönetim paneline güvenli ve yedekli olarak ekler.</p><?php if($result):?><div class="msg <?=$ok?'ok':''?>"><?=htmlspecialchars($result,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if(!$ok):?><form method="post"><button>Entegrasyonu Uygula</button></form><?php endif;?><p><a href="../../admin/menu-center.php">Menü Merkezi'ni Aç →</a></p></div></body></html>
