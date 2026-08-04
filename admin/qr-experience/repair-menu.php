<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$pdo=db();
$stmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
$pdo->beginTransaction();
try{
    $stmt->execute(['qr_menu_enabled','1']);
    $stmt->execute(['qrx_draft_qr_menu_enabled','1']);
    $stmt->execute(['qrx_hotfix_403_menu_recovered_at',date('Y-m-d H:i:s')]);
    $pdo->commit();
    if(function_exists('audit_log')) audit_log('qrxos_menu_recovered','QR menü v4.0.3 hotfix ile yeniden etkinleştirildi.');
    header('Location: index.php?tab=products&recovered=1');
    exit;
}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'Menü etkinleştirilemedi: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');
}
