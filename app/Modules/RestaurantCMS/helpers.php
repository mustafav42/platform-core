<?php
declare(strict_types=1);

function cms_db(?PDO $pdo=null): PDO { return $pdo instanceof PDO ? $pdo : db(); }
function cms_require_admin(): void {
    if (empty($_SESSION['admin_id']) && (($_SESSION['staff_role'] ?? '') !== 'manager')) { header('Location: /admin/'); exit; }
    require_permission('dashboard.view');
}
function cms_blocks(string $scope='published', bool $onlyActive=true, ?PDO $pdo=null): array {
    try {
        $sql='SELECT * FROM cms_content_blocks WHERE publish_scope=?';
        if($onlyActive)$sql.=' AND is_active=1';
        $sql.=' ORDER BY sort_order,id';
        $q=cms_db($pdo)->prepare($sql);$q->execute([$scope]);return $q->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e){ app_log($e,['cms_blocks'=>$scope]); return []; }
}
function cms_media(?PDO $pdo=null): array {
    try{return cms_db($pdo)->query('SELECT * FROM cms_media_library ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){app_log($e,['cms_media'=>true]);return [];}
}
function cms_setting(string $key,string $default='',?PDO $pdo=null): string {
    try{$q=cms_db($pdo)->prepare('SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable $e){return $default;}
}
function cms_save_setting(string $key,string $value,?PDO $pdo=null): void {
    $q=cms_db($pdo)->prepare('INSERT INTO cms_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');$q->execute([$key,$value]);
}
function cms_publish(?PDO $pdo=null): void {
    $pdo=cms_db($pdo);$pdo->beginTransaction();try{
        $pdo->exec("DELETE FROM cms_content_blocks WHERE publish_scope='published'");
        $pdo->exec("INSERT INTO cms_content_blocks(block_key,block_type,title,subtitle,body,media_url,button_text,button_url,background_color,text_color,is_active,sort_order,publish_scope,created_at,updated_at) SELECT block_key,block_type,title,subtitle,body,media_url,button_text,button_url,background_color,text_color,is_active,sort_order,'published',NOW(),NOW() FROM cms_content_blocks WHERE publish_scope='draft'");
        cms_save_setting('last_published_at',date('Y-m-d H:i:s'),$pdo);
        $pdo->commit(); audit_log('restaurant_cms_published','Restaurant CMS içerikleri yayınlandı.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function cms_safe_url(string $url): string {
    $url=trim($url); if($url==='')return '';
    if(str_starts_with($url,'/'))return $url;
    return filter_var($url,FILTER_VALIDATE_URL)?$url:'';
}
function cms_upload_media(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi.');
    if(($file['size']??0)>15*1024*1024) throw new RuntimeException('Dosya en fazla 15 MB olabilir.');
    $tmp=(string)$file['tmp_name']; $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($tmp);
    $allowed=['image/jpeg'=>['jpg','image'],'image/png'=>['png','image'],'image/webp'=>['webp','image'],'image/gif'=>['gif','image'],'video/mp4'=>['mp4','video'],'application/pdf'=>['pdf','pdf']];
    if(!isset($allowed[$mime])) throw new RuntimeException('Desteklenmeyen dosya türü. JPG, PNG, WEBP, GIF, MP4 veya PDF yükleyin.');
    [$ext,$type]=$allowed[$mime];
    $root=dirname(__DIR__,3); $dir=$root.'/uploads/cms'; if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Medya klasörü oluşturulamadı.');
    $name=date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.'.$ext; $target=$dir.'/'.$name;
    if(!move_uploaded_file($tmp,$target))throw new RuntimeException('Dosya sunucuya taşınamadı.');
    return ['url'=>'/uploads/cms/'.$name,'type'=>$type,'name'=>pathinfo((string)($file['name']??$name),PATHINFO_FILENAME)];
}
