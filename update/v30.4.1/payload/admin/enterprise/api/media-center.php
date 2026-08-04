<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
ent_media_upgrade();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function mc_out(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
try {
    $pdo=ent_db();
    $action=(string)($_REQUEST['action']??'list');
    if($action==='sync') { ent_verify_csrf(); $added=ent_media_sync_storage(); mc_out(['ok'=>true,'added'=>$added]); }
    if($action==='upload') {
        ent_verify_csrf();
        $files=$_FILES['files']??null;
        if(!$files || !is_array($files['name']??null)) throw new RuntimeException('Yüklenecek görsel seçilmedi.');
        $items=[];$errors=[];
        foreach($files['name'] as $i=>$name){
            if((int)($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
            try{
                ent_media_upload(['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0],(string)($_POST['folder']??'Ürünler'),(string)($_POST['alt_text']??''));
                $id=(int)$pdo->lastInsertId();$q=$pdo->prepare('SELECT * FROM enterprise_media WHERE id=?');$q->execute([$id]);$items[]=ent_media_item_payload($q->fetch(PDO::FETCH_ASSOC));
            }catch(Throwable $e){$errors[]=['name'=>(string)$name,'message'=>$e->getMessage()];}
        }
        mc_out(['ok'=>!empty($items),'items'=>$items,'errors'=>$errors],$items?200:422);
    }
    if($action==='update') {
        ent_verify_csrf();$id=(int)($_POST['id']??0);
        $folder=mb_substr(trim((string)($_POST['folder']??'Genel')),0,100,'UTF-8')?:'Genel';
        $alt=mb_substr(trim((string)($_POST['alt']??'')),0,255,'UTF-8');
        $tags=mb_substr(trim((string)($_POST['tags']??'')),0,500,'UTF-8');
        $favorite=((string)($_POST['favorite']??'0'))==='1'?1:0;
        $pdo->prepare('UPDATE enterprise_media SET folder=?,alt_text=?,tags=?,is_favorite=?,updated_at=NOW() WHERE id=?')->execute([$folder,$alt,$tags,$favorite,$id]);
        $q=$pdo->prepare('SELECT * FROM enterprise_media WHERE id=?');$q->execute([$id]);mc_out(['ok'=>true,'item'=>ent_media_item_payload($q->fetch(PDO::FETCH_ASSOC))]);
    }
    if($action==='delete') { ent_verify_csrf();ent_media_delete((int)($_POST['id']??0));mc_out(['ok'=>true]); }
    if($action==='list') {
        if(((string)($_GET['sync']??'0'))==='1') ent_media_sync_storage();
        $q=trim((string)($_GET['q']??''));$folder=trim((string)($_GET['folder']??''));$filter=(string)($_GET['filter']??'all');
        $sql='SELECT * FROM enterprise_media WHERE 1=1';$params=[];
        if($q!==''){$like='%'.$q.'%';$sql.=' AND (original_name LIKE ? OR alt_text LIKE ? OR tags LIKE ? OR folder LIKE ?)';array_push($params,$like,$like,$like,$like);}
        if($folder!==''){$sql.=' AND folder=?';$params[]=$folder;}
        if($filter==='favorites')$sql.=' AND is_favorite=1';
        if($filter==='unused')$sql.=' ORDER BY id DESC'; else $sql.=' ORDER BY is_favorite DESC,id DESC';
        $sql.=' LIMIT 1000';$st=$pdo->prepare($sql);$st->execute($params);
        $items=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){$item=ent_media_item_payload($row);if($filter==='unused'&&$item['used'])continue;if($filter==='missing'&&$item['exists'])continue;$items[]=$item;}
        $total=(int)$pdo->query('SELECT COUNT(*) FROM enterprise_media')->fetchColumn();$bytes=(int)$pdo->query('SELECT COALESCE(SUM(file_size),0) FROM enterprise_media')->fetchColumn();
        $folders=$pdo->query('SELECT folder,COUNT(*) total FROM enterprise_media GROUP BY folder ORDER BY folder')->fetchAll(PDO::FETCH_ASSOC);
        mc_out(['ok'=>true,'items'=>$items,'stats'=>['total'=>$total,'bytes'=>$bytes,'bytes_label'=>ent_human_bytes($bytes)],'folders'=>$folders]);
    }
    throw new RuntimeException('Geçersiz işlem.');
}catch(Throwable $e){mc_out(['ok'=>false,'message'=>$e->getMessage()],422);}
