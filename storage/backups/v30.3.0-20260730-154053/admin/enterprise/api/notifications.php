<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ent_platform_install();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['id']??0);
    if($id>0) ent_db()->prepare('UPDATE enterprise_notifications SET is_read=1, read_at=? WHERE id=?')->execute([date('Y-m-d H:i:s'),$id]);
}
echo json_encode(['ok'=>true,'items'=>ent_notifications(12)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
