<?php
declare(strict_types=1);

function qrx_db(?PDO $pdo = null): PDO { return $pdo instanceof PDO ? $pdo : db(); }
function qrx_setting(string $key, ?string $default = null, string $scope = 'published', ?PDO $pdo = null): ?string {
    try { $q=qrx_db($pdo)->prepare('SELECT setting_value FROM qr_experience_settings WHERE setting_key=? AND setting_scope=? LIMIT 1');$q->execute([$key,$scope]);$v=$q->fetchColumn();return $v===false?$default:(string)$v; }
    catch(Throwable $e){ app_log($e,['qrx_setting'=>$key,'scope'=>$scope]);return $default; }
}
function qrx_save_setting(string $key,string $value,string $scope='published',?PDO $pdo=null): void {
    $q=qrx_db($pdo)->prepare('INSERT INTO qr_experience_settings(setting_key,setting_value,setting_scope,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');$q->execute([$key,$value,$scope]);
}
function qrx_all_settings(string $scope='published',?PDO $pdo=null): array {
    try{$q=qrx_db($pdo)->prepare('SELECT setting_key,setting_value FROM qr_experience_settings WHERE setting_scope=?');$q->execute([$scope]);return array_column($q->fetchAll(PDO::FETCH_ASSOC),'setting_value','setting_key');}
    catch(Throwable $e){app_log($e,['qrx_settings_scope'=>$scope]);return [];}
}
function qrx_publish(?PDO $pdo=null): void {
    $pdo=qrx_db($pdo);$pdo->beginTransaction();try{$pdo->exec("DELETE FROM qr_experience_settings WHERE setting_scope='published'");$pdo->exec("INSERT INTO qr_experience_settings(setting_key,setting_value,setting_scope,updated_at) SELECT setting_key,setting_value,'published',NOW() FROM qr_experience_settings WHERE setting_scope='draft'");$pdo->commit();audit_log('qr_experience_published','QR Experience tasarımı yayınlandı.');}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function qrx_require_admin(): void {if(empty($_SESSION['admin_id'])&&(($_SESSION['staff_role']??'')!=='manager')){header('Location: /admin/');exit;}require_permission('dashboard.view');}
function qrx_campaigns(bool $onlyActive=false,?PDO $pdo=null): array {
    $sql='SELECT * FROM qr_experience_campaigns';if($onlyActive)$sql.=" WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())";$sql.=' ORDER BY sort_order ASC,id DESC';
    try{return qrx_db($pdo)->query($sql)->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){app_log($e,['qrx_campaigns'=>true]);return [];}
}
function qrx_badges(?PDO $pdo=null): array {try{return qrx_db($pdo)->query('SELECT b.*,p.name product_name FROM qr_product_badges b LEFT JOIN products p ON p.id=b.product_id ORDER BY b.id DESC')->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){app_log($e,['qrx_badges'=>true]);return [];}}
function qrx_default_sections(): array {return ['hero','announcement','categories','featured','menu','chef','story','social','footer'];}
function qrx_section_labels(): array {return ['hero'=>'Hero / Karşılama','announcement'=>'Kampanya Bannerı','categories'=>'Kategoriler','featured'=>'Öne Çıkanlar','menu'=>'Ürün Menüsü','chef'=>'Şef Seçimleri','story'=>'Hikâyemiz','social'=>'Sosyal Medya','footer'=>'Alt Bilgi'];}
function qrx_layout(string $scope='published',?PDO $pdo=null): array {
    $defaults=qrx_default_sections();$raw=qrx_setting('layout_config','',$scope,$pdo);$decoded=$raw?json_decode($raw,true):null;
    if(!is_array($decoded)){$legacy=json_decode((string)qrx_setting('layout_order',json_encode($defaults),$scope,$pdo),true);$legacy=is_array($legacy)?$legacy:$defaults;$decoded=array_map(fn($id)=>['id'=>(string)$id,'enabled'=>true],$legacy);}
    $seen=[];$out=[];foreach($decoded as $row){$id=(string)($row['id']??'');if(!in_array($id,$defaults,true)||isset($seen[$id]))continue;$seen[$id]=true;$out[]=['id'=>$id,'enabled'=>(bool)($row['enabled']??true)];}
    foreach($defaults as $id)if(!isset($seen[$id]))$out[]=['id'=>$id,'enabled'=>true];return $out;
}


function qrx_product_badges_map(?PDO $pdo=null): array {
    try {
        $pdo=qrx_db($pdo);
        if (!db_table_exists($pdo,'qr_product_badges')) return [];

        // Güncel CherryHouse şeması label/color kullanır. Eski paketlerle uyumluluk için
        // çıktı anahtarları badge_text/badge_color olarak normalize edilir.
        $textColumn=db_column_exists($pdo,'qr_product_badges','label') ? 'label' : 'badge_text';
        $colorColumn=db_column_exists($pdo,'qr_product_badges','color') ? 'color' : 'badge_color';
        if (!db_column_exists($pdo,'qr_product_badges',$textColumn)
            || !db_column_exists($pdo,'qr_product_badges',$colorColumn)) return [];

        $sql="SELECT product_id, `{$textColumn}` AS badge_text, `{$colorColumn}` AS badge_color "
            ."FROM qr_product_badges WHERE is_active=1 ORDER BY id ASC";
        $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $map=[];
        foreach($rows as $row){$map[(int)$row['product_id']][]=$row;}
        return $map;
    } catch(Throwable $e){ app_log($e,['qrx_product_badges_map'=>true]); return []; }
}
function qrx_enabled_sections(string $scope='published',?PDO $pdo=null): array {
    $out=[]; foreach(qrx_layout($scope,$pdo) as $row){ if(!empty($row['enabled'])) $out[]=(string)$row['id']; } return $out;
}
