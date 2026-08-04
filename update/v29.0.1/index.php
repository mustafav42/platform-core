<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$message='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $targets=[
        'index.php'=>__DIR__.'/../../index.php',
        'app/assets/qrx-premium-menu.css'=>__DIR__.'/../../app/assets/qrx-premium-menu.css',
    ];
    $backupDir=$root.'/storage/backups/v29.0.1-'.date('Ymd-His');
    if(!is_dir($backupDir) && !mkdir($backupDir,0755,true)){
        $message='Yedek klasörü oluşturulamadı.';
    } else {
        try{
            foreach($targets as $label=>$dest){
                $src=$root.'/.update-payload/v29.0.1/'.$label;
                if(!is_file($src)) throw new RuntimeException('Paket dosyası eksik: '.$label);
                if(is_file($dest)){
                    $b=$backupDir.'/'.$label;
                    if(!is_dir(dirname($b))) mkdir(dirname($b),0755,true);
                    if(!copy($dest,$b)) throw new RuntimeException('Yedek alınamadı: '.$label);
                }
                if(!is_dir(dirname($dest))) mkdir(dirname($dest),0755,true);
                if(!copy($src,$dest)) throw new RuntimeException('Dosya güncellenemedi: '.$label);
            }
            $ok=true;$message='Mobil ürün listesi başarıyla güncellendi. Tarayıcı önbelleğini temizleyip QR menüyü yenileyin.';
        }catch(Throwable $e){$message=$e->getMessage();}
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.0.1</title><style>body{font-family:Arial,sans-serif;background:#f4efe6;color:#191814;margin:0;padding:32px}.box{max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:18px;box-shadow:0 18px 50px #0001}button{border:0;border-radius:12px;background:#8b1e2d;color:#fff;padding:14px 20px;font-weight:700;cursor:pointer}.msg{padding:14px;border-radius:10px;margin:16px 0;background:<?= $ok?'#e8f7ee':'#fff0ed' ?>}</style></head><body><div class="box"><h1>v29.0.1 Mobil Liste Düzeltmesi</h1><p>Ürün görsellerini ve açıklamalarını zorunlu gösterir; ürün adı, açıklama ve fiyat hizasını referans mobil düzene geçirir.</p><?php if($message):?><div class="msg"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post"><button type="submit">Düzeltmeyi Uygula</button></form></div></body></html>
