<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ent_media_upgrade();
$error='';
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        ent_verify_csrf();
        if((string)($_POST['action']??'')==='upload'){
            $files=$_FILES['media_files']??null;
            if(!$files||!is_array($files['name']??null)) throw new RuntimeException('Bir görsel seçin.');
            foreach($files['name'] as $i=>$name){
                if((int)($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
                ent_media_upload([
                    'name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'',
                    'error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0,
                ],(string)($_POST['folder']??'Genel'),(string)($_POST['alt_text']??''));
            }
            ent_redirect('media-picker.php?uploaded=1');
        }
    }
}catch(Throwable $e){$error=$e->getMessage();}
$q=trim((string)($_GET['q']??''));
$sql='SELECT * FROM enterprise_media';$params=[];
if($q!==''){$sql.=' WHERE original_name LIKE ? OR alt_text LIKE ? OR folder LIKE ?';$like='%'.$q.'%';$params=[$like,$like,$like];}
$sql.=' ORDER BY is_favorite DESC,id DESC LIMIT 500';$st=ent_db()->prepare($sql);$st->execute($params);$items=$st->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medya Seç</title><link rel="stylesheet" href="assets/media-picker.css?v=720"></head><body class="mp-page">
<header class="mp-top"><div><strong>Medya Kütüphanesi</strong><span>Bir görsel seçin veya yenisini yükleyin.</span></div><button type="button" onclick="window.close()">Kapat</button></header>
<?php if($error):?><div class="mp-alert"><?=ent_e($error)?></div><?php endif;?>
<nav class="mp-tabs"><button class="active" data-tab="library">Kütüphane</button><button data-tab="upload">Yeni Yükle</button></nav>
<section data-panel="library"><form class="mp-search"><input type="search" name="q" value="<?=ent_e($q)?>" placeholder="Görsel ara…"><button>Ara</button></form>
<div class="mp-grid"><?php foreach($items as $item):?><button class="mp-item" type="button" data-select-path="<?=ent_e($item['relative_path'])?>" data-select-url="<?=ent_e(ent_media_url($item['relative_path']))?>" data-select-alt="<?=ent_e($item['alt_text'])?>"><img src="<?=ent_e(ent_media_url($item['relative_path']))?>" alt=""><span><b><?=ent_e($item['original_name'])?></b><small><?=ent_e($item['folder'])?> · <?=(int)$item['width']?>×<?=(int)$item['height']?></small></span></button><?php endforeach;?><?php if(!$items):?><div class="mp-empty">Henüz görsel bulunmuyor.</div><?php endif;?></div></section>
<section data-panel="upload" hidden><form method="post" enctype="multipart/form-data" class="mp-upload"><input type="hidden" name="csrf_token" value="<?=ent_e($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="upload"><label>Görseller<input type="file" name="media_files[]" accept="image/jpeg,image/png,image/webp" multiple required></label><label>Klasör<input name="folder" value="Genel"></label><label>Alternatif metin<input name="alt_text"></label><button>Yükle ve Kütüphaneye Ekle</button></form></section>
<script>document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('[data-panel]').forEach(p=>p.hidden=p.dataset.panel!==b.dataset.tab)});document.querySelectorAll('[data-select-path]').forEach(b=>b.onclick=()=>{const payload={type:'cherryhouse-media-selected',path:b.dataset.selectPath,url:b.dataset.selectUrl,alt:b.dataset.selectAlt||''};if(window.opener){window.opener.postMessage(payload,location.origin);window.close();}else if(window.parent!==window){window.parent.postMessage(payload,location.origin);}});</script></body></html>
