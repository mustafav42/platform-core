<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['ok'=>true,'results'=>[]], JSON_UNESCAPED_UNICODE); exit;
}
$like = '%' . $q . '%';
$results = [];
$push = static function(string $type, string $title, string $subtitle, string $url, string $icon='⌕') use (&$results): void {
    if (count($results) < 20) $results[] = compact('type','title','subtitle','url','icon');
};
try {
    if (ent_table_exists('products')) {
        $cols = ent_columns('products');
        $name = in_array('name',$cols,true) ? 'name' : null;
        if ($name) {
            $st=ent_db()->prepare("SELECT id, `{$name}` AS title FROM products WHERE `{$name}` LIKE ? ORDER BY `{$name}` LIMIT 8");
            $st->execute([$like]); foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r) $push('Ürün',(string)$r['title'],'Ürün düzenleme','products.php?edit='.(int)$r['id'],'□');
        }
    }
    if (ent_table_exists('categories')) {
        $cols=ent_columns('categories'); $name=in_array('name',$cols,true)?'name':null;
        if($name){$st=ent_db()->prepare("SELECT id, `{$name}` AS title FROM categories WHERE `{$name}` LIKE ? ORDER BY `{$name}` LIMIT 6");$st->execute([$like]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$push('Kategori',(string)$r['title'],'Kategori düzenleme','categories.php?edit='.(int)$r['id'],'≡');}
    }
    $table = ent_table_first_existing(['restaurant_tables','tables','pos_tables']);
    if ($table) {
        $cols=ent_columns($table); $name=null; foreach(['name','table_name','title','number'] as $c) if(in_array($c,$cols,true)){$name=$c;break;}
        if($name){$st=ent_db()->prepare("SELECT id, `{$name}` AS title FROM `{$table}` WHERE CAST(`{$name}` AS CHAR) LIKE ? ORDER BY id LIMIT 6");$st->execute([$like]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$push('Masa','Masa '.(string)$r['title'],'POS çalışma ekranı','../../cashier/?table_id='.(int)$r['id'],'▦');}
    }
} catch (Throwable $e) {}
echo json_encode(['ok'=>true,'results'=>$results], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
