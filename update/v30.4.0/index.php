<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$payload=__DIR__.'/payload';
$message='';$error='';
function copy_tree(string $src,string $dst,array &$changed): void{
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as $item){$rel=substr($item->getPathname(),strlen($src)+1);$target=$dst.'/'.$rel;if($item->isDir()){if(!is_dir($target)&&!mkdir($target,0755,true)&&!is_dir($target))throw new RuntimeException('Klasör oluşturulamadı: '.$rel);}else{if(!is_dir(dirname($target))&&!mkdir(dirname($target),0755,true)&&!is_dir(dirname($target)))throw new RuntimeException('Klasör oluşturulamadı: '.dirname($rel));if(!copy($item->getPathname(),$target))throw new RuntimeException('Dosya kopyalanamadı: '.$rel);$changed[]=$rel;}}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_dir($payload))throw new RuntimeException('Güncelleme dosyaları bulunamadı.');
  $backup=$root.'/storage/backups/v30.4.0-'.date('Ymd-His');
  $files=['admin/enterprise/_header.php','admin/enterprise/menu.php','admin/menu-center.php','admin/index.php','admin/qr-experience/index.php','admin/qr-experience/assets/unified-admin.css'];
  foreach($files as $rel){$src=$root.'/'.$rel;if(is_file($src)){$dst=$backup.'/'.$rel;if(!is_dir(dirname($dst)))mkdir(dirname($dst),0755,true);copy($src,$dst);}}
  $changed=[];copy_tree($payload,$root,$changed);
  if(function_exists('opcache_reset'))@opcache_reset();
  $message='Birleşik Yönetim Merkezi kuruldu. '.count($changed).' dosya güncellendi.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.4.0</title><style>*{box-sizing:border-box}body{margin:0;background:#f3efeb;color:#211a17;font-family:Inter,system-ui;padding:40px}.card{max-width:760px;margin:auto;background:#fff;border:1px solid #e4dcd6;border-radius:28px;padding:34px;box-shadow:0 24px 70px #2c1d1714}small{letter-spacing:.16em;color:#9d2942;font-weight:900}h1{font-size:40px;margin:10px 0 12px}p{color:#726660;line-height:1.65}.items{display:grid;gap:9px;margin:22px 0}.items div{background:#f8f4f1;border-radius:14px;padding:13px 15px}.ok,.err{padding:14px;border-radius:14px;margin:18px 0}.ok{background:#eaf8ef;color:#176333}.err{background:#fff0f1;color:#9d1f35}button,a{display:inline-flex;min-height:48px;align-items:center;justify-content:center;border:0;border-radius:14px;padding:0 20px;font-weight:800;text-decoration:none}button{background:#9d2942;color:#fff;cursor:pointer}a{background:#231b18;color:#fff;margin-left:8px}</style></head><body><main class="card"><small>CHERRYHOUSE PLATFORM UPDATE</small><h1>v30.4.0 — Unified Admin</h1><p>Menü Merkezi, QR Experience Studio ve Enterprise panelini tek yönetim çatısı altında birleştirir.</p><?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><a href="../../../admin/enterprise/">Yönetim Merkezini Aç</a><?php elseif($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><div class="items"><div>Tek ana giriş: <b>/admin/enterprise/</b></div><div>Birleşik sol menü ve modül navigasyonu</div><div>Eski Menü Merkezi adresinden otomatik yönlendirme</div><div>QR Studio içinde aynı platform navigasyonu</div><div>POS, Garson ve KDS’ye tek menüden erişim</div></div><?php if(!$message):?><form method="post"><button>Birleşik Yönetim Merkezini Kur</button></form><?php endif;?></main></body></html>
