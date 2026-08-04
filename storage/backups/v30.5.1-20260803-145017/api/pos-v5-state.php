<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$allowed = !empty($_SESSION['cashier_id']) || !empty($_SESSION['staff_id']);
if (!$allowed) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Oturum gerekli'], JSON_UNESCAPED_UNICODE); exit; }
try {
    $pdo=db();
    $sql="SELECT t.id,t.status,ts.id session_id,ts.opened_at,ts.guest_count,
      GREATEST(0,
        COALESCE((SELECT SUM(CASE WHEN oi.status='active' THEN oi.unit_price*oi.quantity ELSE 0 END) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.session_id=ts.id),0)
        - COALESCE(ts.discount_amount,0)
        - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.table_session_id=ts.id),0)
      ) remaining
      FROM restaurant_tables t
      LEFT JOIN table_sessions ts ON ts.table_id=t.id AND ts.status='open' AND EXISTS (SELECT 1 FROM orders ox JOIN order_items oix ON oix.order_id=ox.id WHERE ox.session_id=ts.id AND oix.status IN ('active','complimentary') AND oix.quantity>0)
      WHERE t.is_active=1 ORDER BY t.id";
    $tables=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($tables as &$table){$table['id']=(int)$table['id'];$table['session_id']=(int)($table['session_id']??0);$table['guest_count']=(int)($table['guest_count']??0);$table['remaining']=(float)($table['remaining']??0);} unset($table);
    echo json_encode(['ok'=>true,'tables'=>$tables,'server_time'=>date(DATE_ATOM)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Masa bilgileri alınamadı'], JSON_UNESCAPED_UNICODE);}
