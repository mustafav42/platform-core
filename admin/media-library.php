<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
if (empty($_SESSION['admin_id']) && (($_SESSION['staff_role'] ?? '') !== 'manager')) redirect('./');
$pdo=db();

function uml_table_exists(PDO $pdo,string $table): bool {try{$s=$pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$table]);return (bool)$s->fetchColumn();}catch(Throwable){return false;}}
function uml_install(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS enterprise_media (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,filename VARCHAR(255) NOT NULL,original_name VARCHAR(255) NOT NULL,
 relative_path VARCHAR(500) NOT NULL,mime_type VARCHAR(100) NOT NULL,file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 width INT UNSIGNED NULL,height INT UNSIGNED NULL,alt_text VARCHAR(255) NOT NULL DEFAULT '',folder VARCHAR(100) NOT NULL DEFAULT 'Genel',
 created_by BIGINT UNSIGNED NULL,is_favorite TINYINT(1) NOT NULL DEFAULT 0,tags VARCHAR(500) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY uq_enterprise_media_path(relative_path),KEY idx_enterprise_media_created(created_at),KEY idx_enterprise_media_folder(folder)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function uml_upload(PDO $pdo,array $file,string $folder,string $alt=''): array {
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi.');
 $size=(int)($file['size']??0); if($size<1||$size>32*1024*1024) throw new RuntimeException('Her görsel en fazla 32 MB olabilir.');
 $tmp=(string)($file['tmp_name']??''); if($tmp===''||!is_uploaded_file($tmp)) throw new RuntimeException('Geçersiz yükleme kaynağı.');
 $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
 if(!$ext) throw new RuntimeException('Yalnızca JPG, PNG ve WebP görseller kabul edilir.');
 $dim=@getimagesize($tmp);if(!$dim)throw new RuntimeException('Dosya geçerli bir görsel değil.');[$w,$h]=$dim;
 if($w<1||$h<1||$w>16000||$h>16000)throw new RuntimeException('Görsel boyutları desteklenmiyor.');
 $folder=trim(preg_replace('/[^\pL\pN _-]+/u','',$folder)??'')?:'Genel';$folder=mb_substr($folder,0,100,'UTF-8');
 $dir=BASE_PATH.'/storage/uploads/media';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Medya klasörü oluşturulamadı.');
 $filename=date('Ymd-His').'-'.bin2hex(random_bytes(8)).'.'.$ext;$dest=$dir.'/'.$filename;if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Görsel kaydedilemedi.');
 $path='storage/uploads/media/'.$filename;$st=$pdo->prepare('INSERT INTO enterprise_media(filename,original_name,relative_path,mime_type,file_size,width,height,alt_text,folder,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
 try{$st->execute([$filename,mb_substr((string)($file['name']??$filename),0,255,'UTF-8'),$path,$mime,$size,(int)$w,(int)$h,mb_substr(trim($alt),0,255,'UTF-8'),$folder,!empty($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null]);}catch(Throwable $e){@unlink($dest);throw $e;}
 return ['id'=>(int)$pdo->lastInsertId(),'path'=>$path,'url'=>'/'.ltrim($path,'/'),'alt'=>$alt,'name'=>(string)($file['name']??$filename),'width'=>(int)$w,'height'=>(int)$h,'folder'=>$folder];
}
uml_install($pdo);
if(($_GET['action']??'')==='upload'){
 header('Content-Type: application/json; charset=utf-8');
 try{verify_csrf();$f=$_FILES['file']??null;if(!$f)throw new RuntimeException('Görsel seçilmedi.');$item=uml_upload($pdo,$f,(string)($_POST['folder']??'Genel'),(string)($_POST['alt_text']??''));echo json_encode(['ok'=>true,'item'=>$item],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}exit;
}
$q=trim((string)($_GET['q']??''));$folder=trim((string)($_GET['folder']??''));$sql='SELECT * FROM enterprise_media WHERE 1=1';$params=[];
if($q!==''){$sql.=' AND (original_name LIKE ? OR alt_text LIKE ? OR tags LIKE ? OR folder LIKE ?)';$like='%'.$q.'%';$params=[$like,$like,$like,$like];}
if($folder!==''){$sql.=' AND folder=?';$params[]=$folder;}$sql.=' ORDER BY is_favorite DESC,id DESC LIMIT 500';$st=$pdo->prepare($sql);$st->execute($params);$items=$st->fetchAll(PDO::FETCH_ASSOC);
$folders=$pdo->query('SELECT folder,COUNT(*) total FROM enterprise_media GROUP BY folder ORDER BY folder')->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ortam Kütüphanesi</title><link rel="stylesheet" href="assets/ch-media-library-picker.css?v=3033"></head><body class="chml-body">
<header class="chml-head"><div><b>Ortam Kütüphanesi</b><span>Mevcut bir görsel seçin veya burada yeni bir görsel yükleyin.</span></div><button type="button" data-close>×</button></header>
<nav class="chml-tabs"><button class="active" data-tab="library">Kütüphane</button><button data-tab="upload">Dosya Yükle</button></nav>
<section class="chml-panel" data-panel="library"><form class="chml-filter" method="get"><input type="search" name="q" value="<?=e($q)?>" placeholder="Görsel ara…"><select name="folder"><option value="">Tüm klasörler</option><?php foreach($folders as $f):?><option value="<?=e((string)$f['folder'])?>" <?=$folder===$f['folder']?'selected':''?>><?=e((string)$f['folder'])?> (<?=(int)$f['total']?>)</option><?php endforeach;?></select><button>Filtrele</button></form>
<div class="chml-grid" id="libraryGrid"><?php foreach($items as $item):?><button type="button" class="chml-item" data-item='<?=e(json_encode(['id'=>(int)$item['id'],'path'=>(string)$item['relative_path'],'url'=>'/'.ltrim((string)$item['relative_path'],'/'),'alt'=>(string)$item['alt_text'],'name'=>(string)$item['original_name'],'width'=>(int)$item['width'],'height'=>(int)$item['height'],'folder'=>(string)$item['folder']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><img src="/<?=e(ltrim((string)$item['relative_path'],'/'))?>" alt=""><span><b><?=e((string)$item['original_name'])?></b><small><?=e((string)$item['folder'])?> · <?=(int)$item['width']?>×<?=(int)$item['height']?></small></span></button><?php endforeach;?><?php if(!$items):?><div class="chml-empty">Henüz görsel bulunmuyor.</div><?php endif;?></div></section>
<section class="chml-panel" data-panel="upload" hidden><form class="chml-upload" id="uploadForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label class="chml-drop"><input type="file" name="file" accept="image/jpeg,image/png,image/webp" required><b>Görseli sürükleyip bırak veya seç</b><span>JPG, PNG, WebP · en fazla 32 MB</span></label><div class="chml-fields"><label>Klasör<input name="folder" value="Ürünler" list="chmlFolders"></label><label>Alternatif metin<input name="alt_text" placeholder="İsteğe bağlı"></label><button type="submit">Yükle ve Seç</button><p id="uploadStatus"></p></div></form><datalist id="chmlFolders"><?php foreach(['Logo','Hero','Ürünler','Kategoriler','Bannerlar','Temalar','Galeri','Diğer'] as $f):?><option value="<?=$f?>"><?php endforeach;?></datalist></section>
<script>const send=i=>parent.postMessage({type:'cherryhouse-media-selected',item:i,path:i.path,url:i.url,alt:i.alt||''},location.origin);document.querySelector('[data-close]').onclick=()=>parent.postMessage({type:'cherryhouse-media-close'},location.origin);document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('[data-panel]').forEach(p=>p.hidden=p.dataset.panel!==b.dataset.tab)});document.querySelectorAll('[data-item]').forEach(b=>b.onclick=()=>send(JSON.parse(b.dataset.item)));document.getElementById('uploadForm').onsubmit=async e=>{e.preventDefault();const s=document.getElementById('uploadStatus');s.textContent='Yükleniyor…';const r=await fetch('?action=upload',{method:'POST',body:new FormData(e.target)});const j=await r.json().catch(()=>({ok:false,message:'Sunucu yanıtı okunamadı.'}));if(!r.ok||!j.ok){s.textContent=j.message||'Yükleme başarısız.';return}s.textContent='Yüklendi ✓';send(j.item)};</script></body></html>
