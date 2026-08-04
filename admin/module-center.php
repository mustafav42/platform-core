<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file(BASE_PATH . '/storage/installed.lock')) redirect('../install/');
if (empty($_SESSION['admin_id'])) redirect('./');
require_permission('modules.manage');
$notice = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $id = (string)($_POST['module_id'] ?? '');
        $enabled = (string)($_POST['enabled'] ?? '0') === '1';
        modules()->setEnabled($id, $enabled);
        $notice = $enabled ? 'Modül etkinleştirildi.' : 'Modül devre dışı bırakıldı.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$moduleList = modules()->all();
$enabledCount = count(array_filter($moduleList, fn(array $m): bool => !empty($m['enabled'])));
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Modül Merkezi</title><link rel="stylesheet" href="assets/module-center.css?v=500"></head><body>
<div class="shell"><aside><a class="brand" href="./">✦ <span><?=e(setting('business_name','CherryHouse'))?></span></a><nav><a href="./">Dashboard</a><a class="active" href="module-center.php">Modül Merkezi</a><a href="system-center.php">Sistem Merkezi</a><a href="./?page=staff">Personel Merkezi</a></nav><a class="back" href="enterprise/">← Control Center’a dön</a></aside>
<main><header><div><span class="eyebrow">ENTERPRISE CORE v5.0</span><h1>Modül Merkezi</h1><p>İşletmenin kullanacağı özellikleri tek noktadan yönetin. Çekirdek modüller sistem güvenliği için kilitlidir.</p></div><div class="score"><strong><?=$enabledCount?></strong><span>/ <?=count($moduleList)?> aktif</span></div></header>
<?php if($notice):?><div class="notice ok"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="notice err"><?=e($error)?></div><?php endif;?>
<section class="summary"><article><span>Çekirdek</span><strong><?=count(array_filter($moduleList,fn($m)=>($m['tier']??'')==='core'))?></strong></article><article><span>Opsiyonel</span><strong><?=count(array_filter($moduleList,fn($m)=>($m['tier']??'')!=='core'))?></strong></article><article><span>Etkin</span><strong><?=$enabledCount?></strong></article></section>
<section class="modules">
<?php foreach($moduleList as $m): $enabled=!empty($m['enabled']);$locked=!empty($m['locked']);?>
<article class="module <?=$enabled?'is-on':'is-off'?>"><div class="icon"><?=e($m['icon']??'◫')?></div><div class="content"><div class="line"><h2><?=e($m['name'])?></h2><span class="tier"><?=e(strtoupper((string)($m['tier']??'core')))?></span></div><p><?=e($m['description']??'')?></p><div class="meta"><span>v<?=e($m['version']??'1.0.0')?></span><span><?=$enabled?'Etkin':'Kapalı'?></span><?=$locked?'<span>Çekirdek kilidi</span>':''?></div></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="module_id" value="<?=e($m['id'])?>"><input type="hidden" name="enabled" value="<?=$enabled?'0':'1'?>"><button <?=$locked&&$enabled?'disabled':''?> class="toggle" aria-label="Modül durumunu değiştir"><span></span></button></form></article>
<?php endforeach;?></section>
<section class="architecture"><h2>Olay tabanlı altyapı hazır</h2><p><code>OrderCreated</code> olayı üzerinden yazıcı, KDS, stok ve raporlama modülleri birbirinden bağımsız çalışabilir. Bir modül kapalı olduğunda ana sipariş akışı kesilmez.</p></section>
</main></div></body></html>
