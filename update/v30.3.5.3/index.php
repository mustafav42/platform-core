<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $payload=$root.'/payload';
        $files=['app/assets/qrx-premium-menu.css','app/release.php'];
        $backup=$root.'/storage/backups/v30.3.5.3-'.date('Ymd-His');
        foreach($files as $rel){
            $src=$payload.'/'.$rel;$dst=$root.'/'.$rel;
            if(!is_file($src)) throw new RuntimeException('Eksik güncelleme dosyası: '.$rel);
            if(is_file($dst)){
                $bak=$backup.'/'.$rel;
                if(!is_dir(dirname($bak))&&!mkdir(dirname($bak),0775,true)&&!is_dir(dirname($bak))) throw new RuntimeException('Yedek klasörü oluşturulamadı.');
                if(!copy($dst,$bak)) throw new RuntimeException('Dosya yedeklenemedi: '.$rel);
            }
            if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst))) throw new RuntimeException('Hedef klasör oluşturulamadı.');
            if(!copy($src,$dst)) throw new RuntimeException('Dosya güncellenemedi: '.$rel);
        }
        require_once $root.'/app/bootstrap.php';
        try{
            $revision='30353-'.date('YmdHis');
            $pdo=db();
            $stmt=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('qrx_publish_revision',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $stmt->execute([$revision]);
        }catch(Throwable $ignored){}
        if(function_exists('opcache_reset')) @opcache_reset();
        $message='Ürün detay görseli tam görünüm moduna geçirildi.';
    }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v30.3.5.3</title><style>body{font-family:Arial,sans-serif;background:#f5f3f0;color:#221f1b;margin:0;padding:32px}.box{max-width:720px;margin:auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 18px 60px #0001}button{border:0;border-radius:12px;background:#7d1930;color:#fff;padding:14px 20px;font-weight:700;cursor:pointer}.ok{background:#eaf8ef;color:#176c38;padding:14px;border-radius:10px}.err{background:#fff0f0;color:#a51d2d;padding:14px;border-radius:10px}li{margin:8px 0}</style></head><body><div class="box"><h1>CherryHouse v30.3.5.3</h1><p>Mobil ürün detayındaki aşırı yakınlaştırılmış görseli düzeltir.</p><?php if($message):?><div class="ok"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($error):?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><ul><li>Ürün detayında kırpma kaldırılır.</li><li>Fotoğrafın tamamı 4:3 vitrin alanında gösterilir.</li><li>Küçük ürün kartlarının mevcut görünümü değişmez.</li><li>Alerjen, kalori ve hazırlama süresi korunur.</li></ul><form method="post"><button type="submit">Detay Görselini Düzelt</button></form></div></body></html>
