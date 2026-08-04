<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ent_platform_install();
$pageTitle='İşlem Geçmişi';$currentPage='audit';
$q=trim((string)($_GET['q']??''));$event=trim((string)($_GET['event']??''));
$where=[];$args=[];
if($q!==''){$where[]='(summary LIKE ? OR actor_name LIKE ? OR module_name LIKE ?)';$like='%'.$q.'%';array_push($args,$like,$like,$like);}
if($event!==''){$where[]='event_key=?';$args[]=$event;}
$sql='SELECT * FROM enterprise_audit_logs'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 250';
$stmt=ent_db()->prepare($sql);$stmt->execute($args);$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__.'/_header.php';
?>
<section class="panel-card audit-panel">
<header><div><span>GÜVENLİK VE İZLENEBİLİRLİK</span><h3>İşlem geçmişi</h3></div></header>
<form class="audit-filter" method="get"><input type="search" name="q" value="<?=ent_e($q)?>" placeholder="İşlem, kullanıcı veya modül ara"><button class="primary-action">Ara</button><a class="secondary-action" href="audit.php">Temizle</a></form>
<?php if($logs):?><div class="audit-table-wrap"><table class="audit-table"><thead><tr><th>Tarih</th><th>Kullanıcı</th><th>İşlem</th><th>Modül</th><th>Detay</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td><?=ent_e(date('d.m.Y H:i:s',strtotime((string)$log['created_at'])))?></td><td><strong><?=ent_e($log['actor_name'])?></strong><small><?=ent_e($log['ip_address'])?></small></td><td><code><?=ent_e($log['event_key'])?></code></td><td><?=ent_e($log['module_name'] ?: 'Sistem')?></td><td><strong><?=ent_e($log['summary'])?></strong><?php if($log['old_values']||$log['new_values']):?><details><summary>Değişiklikleri göster</summary><div class="audit-diff"><pre><?=ent_e($log['old_values'] ?: '{}')?></pre><span>→</span><pre><?=ent_e($log['new_values'] ?: '{}')?></pre></div></details><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="empty-panel">Filtreye uygun işlem kaydı bulunamadı.</div><?php endif;?>
</section>
<?php require __DIR__.'/_footer.php'; ?>
