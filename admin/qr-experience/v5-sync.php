<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
try{
    $pdo=db();$pdo->beginTransaction();
    qrxos_db_put('qr_menu_enabled','1',$pdo);
    qrxos_db_put('qrx_draft_qr_menu_enabled','1',$pdo);
    // Eksik taslakları mevcut canlı değerlerden oluştur; mevcut taslakları asla ezme.
    foreach(qrxos_keys() as $key){
        $draft=qrxos_db_get('qrx_draft_'.$key,null);$live=qrxos_db_get($key,null);
        if($draft===null&&$live!==null)qrxos_db_put('qrx_draft_'.$key,$live,$pdo);
    }
    qrxos_db_put('qrx_v5_unified_at',date('Y-m-d H:i:s'),$pdo);
    $pdo->commit();QrExperience::clearCache();
    header('Location: ./?v5=ready');exit;
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(500);echo '<h1>v5 senkronizasyon hatası</h1><pre>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</pre>';}
