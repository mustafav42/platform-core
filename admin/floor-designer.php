<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
$pdo=db();
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('./');
require_permission('tables.manage');
$_SESSION['last_activity']=time();

function floor_json(array $payload,int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function floor_store_dir(): string {
    $dir=BASE_PATH.'/storage/floor-designer';
    if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Floor Designer depolama dizini oluşturulamadı.');
    return $dir;
}
function floor_read_json(string $file,array $fallback=[]): array {
    if(!is_file($file)) return $fallback;
    $data=json_decode((string)file_get_contents($file),true);
    return is_array($data)?$data:$fallback;
}
function floor_write_json(string $file,array $data): void {
    $tmp=$file.'.tmp';
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if($json===false || file_put_contents($tmp,$json,LOCK_EX)===false || !@rename($tmp,$file)) throw new RuntimeException('Taslak kaydedilemedi. Depolama izinlerini kontrol edin.');
}
function floor_sanitize_tables(array $tables): array {
    $out=[];
    foreach($tables as $row){
        $id=(int)($row['id']??0); if($id<1) continue;
        $out[]=[
            'id'=>$id,'x'=>max(0,min(4000,(int)($row['x']??0))),'y'=>max(0,min(3000,(int)($row['y']??0))),
            'w'=>max(60,min(600,(int)($row['w']??120))),'h'=>max(60,min(600,(int)($row['h']??100))),
            'shape'=>in_array((string)($row['shape']??''),['round','square','rectangle'],true)?(string)$row['shape']:'rectangle',
            'name'=>mb_substr(trim((string)($row['name']??'')),0,80),'capacity'=>max(1,min(99,(int)($row['capacity']??4)))
        ];
    }
    return $out;
}
function floor_sanitize_objects(array $objects): array {
    $types=['wall','door','window','column','plant','cashier','service','wc','kitchen','stairs','elevator','text']; $out=[];
    foreach($objects as $row){
        $type=(string)($row['type']??''); if(!in_array($type,$types,true)) continue;
        $out[]=['uid'=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($row['uid']??uniqid('o',true))),'type'=>$type,
            'x'=>max(0,min(4000,(int)($row['x']??0))),'y'=>max(0,min(3000,(int)($row['y']??0))),
            'w'=>max(30,min(1200,(int)($row['w']??120))),'h'=>max(30,min(1200,(int)($row['h']??80))),
            'rotation'=>max(-180,min(180,(int)($row['rotation']??0))),'label'=>mb_substr(trim((string)($row['label']??'')),0,100),
            'layer'=>in_array((string)($row['layer']??''),['architecture','decor','labels'],true)?(string)$row['layer']:'decor'];
    }
    return $out;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $raw=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        if(!hash_equals((string)($_SESSION['csrf']??''),(string)($raw['csrf']??''))) throw new RuntimeException('Oturum doğrulaması başarısız.');
        $action=(string)($raw['action']??''); $dir=floor_store_dir(); $draftFile=$dir.'/draft.json'; $publishedFile=$dir.'/published-objects.json';
        if($action==='save_draft' || $action==='publish_layout'){
            $payload=['version'=>431,'saved_at'=>date(DATE_ATOM),'saved_by'=>(int)($_SESSION['admin_id']??0),
                'tables'=>floor_sanitize_tables(is_array($raw['tables']??null)?$raw['tables']:[]),
                'objects'=>floor_sanitize_objects(is_array($raw['objects']??null)?$raw['objects']:[]),
                'layers'=>is_array($raw['layers']??null)?$raw['layers']:[]];
            floor_write_json($draftFile,$payload);
            if($action==='publish_layout'){
                $pdo->beginTransaction();
                $q=$pdo->prepare("UPDATE restaurant_tables SET position_x=?,position_y=?,width_px=?,height_px=?,shape=?,name=?,capacity=? WHERE id=?");
                foreach($payload['tables'] as $row) $q->execute([$row['x'],$row['y'],$row['w'],$row['h'],$row['shape'],$row['name'],$row['capacity'],$row['id']]);
                $pdo->commit(); floor_write_json($publishedFile,['published_at'=>date(DATE_ATOM),'objects'=>$payload['objects'],'layers'=>$payload['layers']]);
                $history=$dir.'/history'; if(!is_dir($history)) @mkdir($history,0775,true); floor_write_json($history.'/'.date('Ymd-His').'.json',$payload);
                audit_log('floor_layout_published','Salon yerleşimi yayınlandı.',['tables'=>count($payload['tables']),'objects'=>count($payload['objects'])]);
                floor_json(['ok'=>true,'message'=>'Salon planı yayınlandı.','saved_at'=>date('H:i')]);
            }
            floor_json(['ok'=>true,'message'=>'Taslak kaydedildi.','saved_at'=>date('H:i')]);
        }
        if($action==='update_table'){
            $id=(int)($raw['id']??0); $name=mb_substr(trim((string)($raw['name']??'')),0,80); $capacity=max(1,min(99,(int)($raw['capacity']??4)));
            if($id<1||$name==='') throw new RuntimeException('Masa bilgileri eksik.');
            floor_json(['ok'=>true]);
        }
        throw new RuntimeException('Geçersiz işlem.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();floor_json(['ok'=>false,'message'=>$e->getMessage()],422);}
}
$areas=$pdo->query("SELECT id,name,sort_order FROM dining_areas WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
$tables=$pdo->query("SELECT t.id,t.area_id,t.name,t.capacity,t.status,t.position_x,t.position_y,t.width_px,t.height_px,t.shape,a.name area_name FROM restaurant_tables t JOIN dining_areas a ON a.id=t.area_id WHERE t.is_active=1 ORDER BY a.sort_order,t.id")->fetchAll();
$draft=[]; $draftObjects=[]; $draftLayers=[];
try{ $draft=floor_read_json(floor_store_dir().'/draft.json',[]); $draftObjects=is_array($draft['objects']??null)?$draft['objects']:[]; $draftLayers=is_array($draft['layers']??null)?$draft['layers']:[]; if(!empty($draft['tables'])){ $byId=[]; foreach($draft['tables'] as $r)$byId[(int)$r['id']]=$r; foreach($tables as &$t){$id=(int)$t['id'];if(isset($byId[$id])){$r=$byId[$id];$t['position_x']=$r['x'];$t['position_y']=$r['y'];$t['width_px']=$r['w'];$t['height_px']=$r['h'];$t['shape']=$r['shape'];$t['name']=$r['name']?:$t['name'];$t['capacity']=$r['capacity'];}} unset($t); } }catch(Throwable $e){}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse Studio</title><link rel="stylesheet" href="assets/floor-designer.css?v=431"></head><body>
<div class="fd-app" data-floor-designer data-csrf="<?=e(csrf_token())?>">
<header class="fd-top"><a href="./?page=tables" class="fd-back">‹ Yönetim Paneli</a><div class="fd-brand"><b>CherryHouse Studio</b><span>Salon taslağı ve yayın yönetimi</span></div><div class="fd-save-state"><span class="fd-dot"></span><span data-save-status>Taslak hazır</span></div><div class="fd-actions"><button data-undo disabled>↶</button><button data-redo disabled>↷</button><button data-copy>Kopyala</button><button data-delete>Sil</button><button data-grid class="active"># Izgara</button><button data-save>Taslağı Kaydet</button><button data-publish class="primary">Yayınla</button></div></header>
<aside class="fd-left"><h3>SALONLAR</h3><button class="fd-area active" data-area="all">Tüm Salonlar</button><?php foreach($areas as $a):?><button class="fd-area" data-area="<?=(int)$a['id']?>"><?=e($a['name'])?></button><?php endforeach;?><hr><h3>MASA ŞEKLİ</h3><button data-shape="round">○ Yuvarlak</button><button data-shape="square">□ Kare</button><button data-shape="rectangle">▭ Dikdörtgen</button><hr><h3>MİMARİ</h3><div class="fd-tool-grid"><button data-add-object="wall">Duvar</button><button data-add-object="door">Kapı</button><button data-add-object="window">Pencere</button><button data-add-object="column">Kolon</button></div><h3>ALAN & DEKOR</h3><div class="fd-tool-grid"><button data-add-object="plant">Bitki</button><button data-add-object="cashier">Kasa</button><button data-add-object="service">Servis</button><button data-add-object="wc">WC</button><button data-add-object="kitchen">Mutfak</button><button data-add-object="stairs">Merdiven</button></div><p class="fd-help">Öğe eklemek için araca tıklayın. Ctrl/Cmd ile çoklu seçim yapabilirsiniz.</p></aside>
<main class="fd-stage-wrap"><div class="fd-stage-tools"><button data-zoom-out>−</button><span data-zoom-label>%100</span><button data-zoom-in>+</button><button data-fit>Sığdır</button><span class="fd-separator"></span><button data-align="left">Sola</button><button data-align="top">Üste</button><button data-align="hcenter">Yatay Ortala</button><button data-align="vcenter">Dikey Ortala</button></div><div class="fd-viewport"><div class="fd-ruler fd-ruler-x"></div><div class="fd-ruler fd-ruler-y"></div><div class="fd-stage" data-stage><?php foreach($tables as $t):?><article class="fd-item fd-table shape-<?=e((string)$t['shape'])?> status-<?=e((string)$t['status'])?>" tabindex="0" data-kind="table" data-id="<?=(int)$t['id']?>" data-area="<?=(int)$t['area_id']?>" data-name="<?=e((string)$t['name'])?>" data-capacity="<?=(int)$t['capacity']?>" data-shape="<?=e((string)$t['shape'])?>" style="left:<?=(int)$t['position_x']?>px;top:<?=(int)$t['position_y']?>px;width:<?=(int)$t['width_px']?>px;height:<?=(int)$t['height_px']?>px"><span class="fd-status"></span><strong><?=e($t['name'])?></strong><small><?=e($t['area_name'])?> · <?=(int)$t['capacity']?> kişi</small><i class="fd-resize"></i></article><?php endforeach;?><?php foreach($draftObjects as $o):?><article class="fd-item fd-object type-<?=e($o['type'])?>" tabindex="0" data-kind="object" data-uid="<?=e($o['uid'])?>" data-type="<?=e($o['type'])?>" data-layer="<?=e($o['layer'])?>" data-label="<?=e($o['label'])?>" data-rotation="<?=(int)$o['rotation']?>" style="left:<?=(int)$o['x']?>px;top:<?=(int)$o['y']?>px;width:<?=(int)$o['w']?>px;height:<?=(int)$o['h']?>px;transform:rotate(<?=(int)$o['rotation']?>deg)"><strong><?=e($o['label']?:ucfirst($o['type']))?></strong><i class="fd-resize"></i></article><?php endforeach;?></div></div></main>
<aside class="fd-right"><section class="fd-layers"><h3>KATMANLAR</h3><label><input type="checkbox" data-layer-visible="tables" checked> Masalar</label><label><input type="checkbox" data-layer-visible="architecture" checked> Mimari</label><label><input type="checkbox" data-layer-visible="decor" checked> Dekor</label><label><input type="checkbox" data-layer-visible="labels" checked> Yazılar</label><hr><label><input type="checkbox" data-layer-lock="architecture"> Mimariyi kilitle</label></section><div class="fd-empty" data-empty><b>Öğe seçilmedi</b><p>Düzenlemek için bir öğeye tıklayın.</p></div><form data-properties hidden><h3>ÖZELLİKLER</h3><label>Ad / Etiket<input name="name" maxlength="100"></label><label data-capacity-row>Kapasite<input name="capacity" type="number" min="1" max="99"></label><div class="fd-two"><label>X<input name="x" type="number" min="0"></label><label>Y<input name="y" type="number" min="0"></label></div><div class="fd-two"><label>Genişlik<input name="w" type="number" min="30"></label><label>Yükseklik<input name="h" type="number" min="30"></label></div><label data-rotation-row>Döndürme<input name="rotation" type="number" min="-180" max="180"></label><button type="submit" class="primary">Uygula</button></form><div class="fd-shortcuts"><h3>KISAYOLLAR</h3><p><kbd>Ctrl</kbd> + <kbd>S</kbd> Taslak</p><p><kbd>Ctrl</kbd> + <kbd>C/V</kbd> Kopyala</p><p><kbd>Delete</kbd> Objeyi sil</p><p><kbd>Oklar</kbd> Hassas taşı</p></div></aside>
<div class="fd-toast" data-toast></div></div><script src="assets/floor-designer.js?v=431"></script></body></html>