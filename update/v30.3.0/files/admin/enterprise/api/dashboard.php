<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ent_platform_install();
$data=['active_tables'=>0,'open_orders'=>0,'today_sales'=>0.0,'business_day'=>'Kapalı','notifications'=>0];
try {
    $table=ent_table_first_existing(['restaurant_tables','tables','pos_tables']);
    if($table){$cols=ent_columns($table);foreach(['status','is_occupied','occupied'] as $c){if(in_array($c,$cols,true)){$where=$c==='status'?"`{$c}` IN ('occupied','open','busy','dolu')":"`{$c}`=1";$data['active_tables']=ent_count($table,$where);break;}}}
    $orders=ent_table_first_existing(['orders','tickets','pos_orders']);
    if($orders){$cols=ent_columns($orders);$where='1=1';if(in_array('status',$cols,true))$where="status IN ('open','pending','active')";elseif(in_array('is_closed',$cols,true))$where='is_closed=0';$data['open_orders']=ent_count($orders,$where);}
    $payments=ent_table_first_existing(['payments','pos_payments','order_payments']);
    if($payments){$cols=ent_columns($payments);$amount=null;foreach(['amount','paid_amount','total'] as $c)if(in_array($c,$cols,true)){$amount=$c;break;}$date=null;foreach(['created_at','paid_at','payment_date'] as $c)if(in_array($c,$cols,true)){$date=$c;break;}if($amount){$sql="SELECT COALESCE(SUM(`{$amount}`),0) FROM `{$payments}`".($date?" WHERE DATE(`{$date}`)=CURDATE()":'');$data['today_sales']=(float)ent_db()->query($sql)->fetchColumn();}}
    $bd=ent_table_first_existing(['business_days','business_day_sessions']);
    if($bd){$cols=ent_columns($bd);$where=in_array('status',$cols,true)?"status IN ('open','active')":(in_array('closed_at',$cols,true)?'closed_at IS NULL':'1=0');$data['business_day']=ent_count($bd,$where)>0?'Açık':'Kapalı';}
    $data['notifications']=(int)ent_db()->query('SELECT COUNT(*) FROM enterprise_notifications WHERE is_read=0')->fetchColumn();
} catch(Throwable $e){}
echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
