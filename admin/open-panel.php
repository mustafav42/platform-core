<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(empty($_SESSION['admin_id']) && (string)($_SESSION['staff_role']??'')!=='manager') redirect('./');
$target=(string)($_GET['target']??'');
$pdo=db();
if($target==='cashier'){
    $q=$pdo->query("SELECT id,name,role FROM staff_users WHERE is_active=1 AND deleted_at IS NULL AND role IN ('cashier','manager') ORDER BY role='cashier' DESC,id LIMIT 1");
    $u=$q->fetch();
    if(!$u) throw new RuntimeException('Aktif kasiyer veya yönetici personel bulunamadı.');
    $_SESSION['cashier_id']=(int)$u['id'];$_SESSION['cashier_name']=$u['name'];$_SESSION['cashier_role']=$u['role'];$_SESSION['cashier_last_activity']=time();
    audit_log('admin_panel_bridge','Yönetici kasiyer panelini açtı.',['staff_id'=>(int)$u['id']]);
    redirect('../cashier/');
}
if($target==='staff'){
    $q=$pdo->query("SELECT id,name,role FROM staff_users WHERE is_active=1 AND deleted_at IS NULL AND role='waiter' ORDER BY id LIMIT 1");
    $u=$q->fetch();
    if(!$u) throw new RuntimeException('Aktif garson bulunamadı.');
    $_SESSION['staff_id']=(int)$u['id'];$_SESSION['staff_name']=$u['name'];$_SESSION['staff_role']='waiter';$_SESSION['staff_last_activity']=time();
    audit_log('admin_panel_bridge','Yönetici garson panelini açtı.',['staff_id'=>(int)$u['id']]);
    redirect('../staff/');
}
redirect('./');
