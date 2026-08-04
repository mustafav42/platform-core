<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');
if (empty($_SESSION['admin_id']) && (($_SESSION['staff_role'] ?? '') !== 'manager')) redirect('./');
require_permission('maintenance.manage');
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $cleared=PerformanceCache::clear();
        audit_log('performance_cache_cleared','Dosya önbelleği temizlendi.',['file_count'=>$cleared]);
        $message=$cleared.' önbellek dosyası temizlendi.';
    }catch(Throwable $e){$error=$e->getMessage();app_log($e,['page'=>'performance']);}
}
$stats=PerformanceCache::stats();
$opcache=function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
$memory=memory_get_usage(true);
$peak=memory_get_peak_usage(true);
function v5_bytes(int $bytes): string { $units=['B','KB','MB','GB'];$i=0;$n=$bytes;while($n>=1024&&$i<count($units)-1){$n/=1024;$i++;}return number_format($n,$i?1:0,',','.').' '.$units[$i]; }
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Performans Merkezi</title><link rel="stylesheet" href="assets/admin-premium.css?v=500"><style>
body{background:#f4f6fb;color:#172033;font-family:Inter,system-ui,sans-serif}.shell{max-width:1100px;margin:auto;padding:28px}.hero{padding:28px;border-radius:24px;background:linear-gradient(135deg,#111827,#334155);color:#fff}.hero h1{margin:0 0 8px}.hero p{margin:0;color:#d2dae6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin:18px 0}.card{background:#fff;border:1px solid #e5e9f1;border-radius:18px;padding:20px;box-shadow:0 10px 32px #1f293708}.metric{font-size:26px;font-weight:850}.muted{color:#697386;font-size:13px}.ok,.err{padding:12px 14px;border-radius:12px;margin:14px 0}.ok{background:#dcfce7;color:#166534}.err{background:#fee2e2;color:#991b1b}.btn{border:0;border-radius:12px;padding:12px 17px;background:#dc2626;color:#fff;font-weight:800;cursor:pointer}.back{display:inline-block;margin-bottom:16px;text-decoration:none;font-weight:800;color:#5b4ee5}@media(max-width:800px){.grid{grid-template-columns:1fr 1fr}.shell{padding:16px}}@media(max-width:500px){.grid{grid-template-columns:1fr}}
</style></head><body><main class="shell"><a class="back" href="./?page=dashboard">← Yönetim paneline dön</a><section class="hero"><h1>Performans Merkezi</h1><p>Önbellek, PHP çalışma zamanı ve sunucu kapasitesini güvenli biçimde izle.</p></section><?php if($message):?><div class="ok">✓ <?=e($message)?></div><?php endif;?><?php if($error):?><div class="err"><?=e($error)?></div><?php endif;?><section class="grid"><div class="card"><div class="metric"><?=e((string)$stats['files'])?></div><div class="muted">Önbellek dosyası</div></div><div class="card"><div class="metric"><?=e(v5_bytes((int)$stats['bytes']))?></div><div class="muted">Önbellek boyutu</div></div><div class="card"><div class="metric"><?=e(v5_bytes($peak))?></div><div class="muted">PHP tepe bellek kullanımı</div></div><div class="card"><div class="metric"><?=$opcache?'Aktif':'Kapalı'?></div><div class="muted">OPcache durumu</div></div></section><section class="card"><h2>Dosya önbelleği</h2><p class="muted">Tema, CMS ve sık okunan ayarlar için kullanılabilecek güvenli dosya tabanlı önbellek altyapısı hazır. Temizleme işlemi veritabanı veya yüklenen medyaları silmez.</p><p><strong>Klasör yazılabilir:</strong> <?=$stats['writable']?'Evet':'Hayır'?> · <strong>Süresi dolan:</strong> <?=e((string)$stats['expired'])?> · <strong>Anlık bellek:</strong> <?=e(v5_bytes($memory))?></p><form method="post" onsubmit="return confirm('Önbellek dosyaları temizlensin mi?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="btn" type="submit">Önbelleği Temizle</button></form></section></main></body></html>
