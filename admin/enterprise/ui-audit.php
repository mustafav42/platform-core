<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_permission('modules.manage');
require_once dirname(__DIR__, 2) . '/app/ControlCenter/UIAuditService.php';

$service = new UIAuditService(dirname(__DIR__, 2));
$report = $service->report();

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($export === 'json') {
    $filename = 'cherryhouse-ui-audit-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'product' => 'CherryHouse',
        'report_type' => 'UI Audit & Component Registry',
        'schema_version' => '1.1',
        'generated_at' => $report['generated_at'],
        'summary' => $report['summary'],
        'pages' => $report['pages'],
        'components' => $report['components'],
        'notes' => [
            'Bu dosya CherryHouse Control Center UI Denetimi ekranından üretilmiştir.',
            'Destek veya analiz için dosyayı doğrudan paylaşabilirsiniz.',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($export === 'csv') {
    $filename = 'cherryhouse-ui-pages-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Ekran', 'Durum', 'Kabuklar', 'Asset Sayısı', 'Inline Style', 'Inline Script', 'Buton Token Sayısı', 'Risk Puanı'], ';');
    foreach ($report['pages'] as $page) {
        fputcsv($out, [
            $page['path'],
            $page['status'],
            implode(', ', (array)$page['shells']),
            $page['asset_count'],
            $page['inline_style'],
            $page['inline_script'],
            $page['button_token_count'],
            $page['risk_score'],
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'UI Denetimi';
$currentPage = 'ui-audit';
$s = $report['summary'];
require __DIR__ . '/_header.php';
?>
<link rel="stylesheet" href="assets/ui-audit.css?v=30801">
<section class="audit-hero">
  <div>
    <span class="audit-kicker">CHERRYHOUSE UI CONSOLIDATION</span>
    <h2>Arayüz Denetimi ve Bileşen Kayıtları</h2>
    <p>Canlı kurulumdaki yönetim dosyalarını tarar; çakışan kabukları, sayfa içi stilleri ve standardizasyon önceliklerini görünür hâle getirir. Raporu dışa aktarıp destek veya geliştirme incelemesi için doğrudan paylaşabilirsiniz.</p>
  </div>
  <div class="audit-date">
    <small>Son tarama</small>
    <strong><?=ent_e($report['generated_at'])?></strong>
    <div class="audit-actions">
      <a class="ch-button ch-button--secondary" href="ui-audit.php">Yeniden Tara</a>
      <a class="ch-button ch-button--primary" href="ui-audit.php?export=json">JSON Dışa Aktar</a>
      <a class="ch-button ch-button--ghost" href="ui-audit.php?export=csv">CSV Dışa Aktar</a>
    </div>
  </div>
</section>
<div class="audit-export-note">
  <strong>Paylaşım için önerilen dosya: JSON</strong>
  <span>Özet, tüm ekranlar, risk puanları, kullanılan kabuklar ve Component Registry tek dosyada bulunur.</span>
</div>
<div class="audit-stats">
<?php foreach ([['PHP ekranı',$s['php_pages']],['CSS dosyası',$s['css_files']],['JavaScript',$s['js_files']],['Sayfa içi stil',$s['inline_style_pages']],['Eski kabuk',$s['legacy_shell_pages']],['Karışık kabuk',$s['mixed_shell_pages']]] as [$label,$value]):?>
  <article><small><?=ent_e($label)?></small><strong><?=number_format((int)$value,0,',','.')?></strong></article>
<?php endforeach;?>
</div>
<div class="audit-grid">
<section class="audit-card"><header><div><span class="audit-kicker">ÖNCELİK LİSTESİ</span><h3>En yüksek riskli ekranlar</h3></div><span class="audit-badge warning">İlk 30</span></header>
<div class="audit-table-wrap"><table class="audit-table"><thead><tr><th>Ekran</th><th>Kabuk</th><th>Asset</th><th>Inline</th><th>Buton dili</th><th>Risk</th></tr></thead><tbody>
<?php foreach(array_slice($report['pages'],0,30) as $page):?><tr><td><code><?=ent_e($page['path'])?></code></td><td><span class="audit-status <?=str_contains($page['status'],'Control')?'good':(str_contains($page['status'],'Eski')?'bad':'neutral')?>"><?=ent_e($page['status'])?></span></td><td><?=number_format((int)$page['asset_count'])?></td><td><?=number_format((int)$page['inline_style']+(int)$page['inline_script'])?></td><td><?=number_format((int)$page['button_token_count'])?></td><td><b class="risk-score"><?=number_format((int)$page['risk_score'])?></b></td></tr><?php endforeach;?>
</tbody></table></div></section>
<section class="audit-card"><header><div><span class="audit-kicker">UI KIT 1.0</span><h3>Component Registry</h3></div><span class="audit-badge"><?=count($report['components'])?> bileşen</span></header><div class="component-list">
<?php foreach($report['components'] as $c):?><article><div><code><?=ent_e($c['code'])?></code><span><?=ent_e($c['status'])?></span></div><p><?=ent_e($c['rule'])?></p></article><?php endforeach;?>
</div></section>
</div>
<section class="audit-card audit-roadmap"><header><div><span class="audit-kicker">KONSOLİDASYON PLANI</span><h3>Önerilen uygulama sırası</h3></div></header><div class="roadmap-steps">
<article><b>01</b><div><strong>UI Kit Core</strong><p>Token, buton, form, kart, tablo, drawer ve modal standartları.</p></div></article>
<article><b>02</b><div><strong>Tek Layout</strong><p>Eski admin, admin-v2 ve Enterprise kabuklarının tek Control Center katmanına alınması.</p></div></article>
<article><b>03</b><div><strong>Workspace Geçişleri</strong><p>Menü, Yönetim, Operasyon ve Finans alanlarının sırayla standardize edilmesi.</p></div></article>
<article><b>04</b><div><strong>Legacy Cleanup</strong><p>Feature parity doğrulandıktan sonra kullanılmayan CSS, JS ve layout dosyalarının kaldırılması.</p></div></article>
</div></section>
<?php require __DIR__ . '/_footer.php'; ?>
