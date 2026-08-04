<?php
/**
 * CherryHouse Admin Panel 2.0 dashboard.
 * This file is loaded only by admin/index.php after authentication and permission checks.
 */
if (!defined('BASE_PATH')) { http_response_code(403); exit('Doğrudan erişim engellendi.'); }
$pdo = db();

function v2_money(float $value): string {
    return number_format($value, 2, ',', '.') . ' ₺';
}
function v2_scalar(PDO $pdo, string $sql, array $params = [], mixed $fallback = 0): mixed {
    try {
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $value = $q->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        app_log($e, ['admin_v2_query' => $sql]);
        return $fallback;
    }
}
function v2_rows(PDO $pdo, string $sql, array $params = []): array {
    try {
        $q = $pdo->prepare($sql);
        $q->execute($params);
        return $q->fetchAll() ?: [];
    } catch (Throwable $e) {
        app_log($e, ['admin_v2_query' => $sql]);
        return [];
    }
}
function v2_setting(string $key, string $default = ''): string {
    try { return setting($key, $default); } catch (Throwable) { return $default; }
}

$brandName = v2_setting('brand_business_name', v2_setting('restaurant_name', 'CherryHouse'));
$brandShort = v2_setting('brand_short_name', 'CH');
$brandPrimary = v2_setting('brand_primary_color', '#f05b32');
$brandLogo = v2_setting('brand_admin_logo', '');
if ($brandLogo !== '' && !str_starts_with($brandLogo, 'http') && !str_starts_with($brandLogo, '/')) {
    $brandLogo = '../' . ltrim($brandLogo, './');
}

$todayStart = date('Y-m-d 00:00:00');
$tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
$weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));

$todayRevenue = (float)v2_scalar($pdo, 'SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=? AND paid_at<?', [$todayStart, $tomorrowStart], 0);
$ticketCount = (int)v2_scalar($pdo, 'SELECT COUNT(DISTINCT table_session_id) FROM payments WHERE paid_at>=? AND paid_at<?', [$todayStart, $tomorrowStart], 0);
$openTables = (int)v2_scalar($pdo, "SELECT COUNT(*) FROM restaurant_tables WHERE status='open' AND is_active=1", [], 0);
$activeOrders = (int)v2_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('open','pending','preparing')", [], 0);
$activeStaff = (int)v2_scalar($pdo, 'SELECT COUNT(*) FROM staff_users WHERE is_active=1 AND deleted_at IS NULL', [], 0);
$openCash = (int)v2_scalar($pdo, "SELECT COUNT(*) FROM cash_sessions WHERE status='open'", [], 0);
$activeProducts = (int)v2_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active=1', [], 0);
$averageTicket = $ticketCount > 0 ? $todayRevenue / $ticketCount : 0.0;

$weekRows = v2_rows($pdo, 'SELECT DATE(paid_at) day_date, COALESCE(SUM(amount),0) total FROM payments WHERE paid_at>=? AND paid_at<? GROUP BY DATE(paid_at) ORDER BY day_date', [$weekStart, $tomorrowStart]);
$weekMap = [];
foreach ($weekRows as $row) $weekMap[(string)$row['day_date']] = (float)$row['total'];
$week = [];
$weekMax = 1.0;
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $total = $weekMap[$date] ?? 0.0;
    $weekMax = max($weekMax, $total);
    $week[] = ['date' => $date, 'total' => $total];
}

$openTableRows = v2_rows($pdo, "SELECT t.name, a.name area_name, ts.opened_at,
    COALESCE(SUM(CASE WHEN oi.status='active' THEN oi.quantity*oi.unit_price ELSE 0 END),0) total
    FROM table_sessions ts
    JOIN restaurant_tables t ON t.id=ts.table_id
    JOIN dining_areas a ON a.id=t.area_id
    LEFT JOIN orders o ON o.session_id=ts.id
    LEFT JOIN order_items oi ON oi.order_id=o.id
    WHERE ts.status='open'
    GROUP BY ts.id,t.name,a.name,ts.opened_at
    ORDER BY ts.opened_at ASC LIMIT 6");

$recentPayments = v2_rows($pdo, "SELECT p.amount,p.method,p.paid_at,t.name table_name,COALESCE(s.name,'Sistem') staff_name
    FROM payments p
    LEFT JOIN table_sessions ts ON ts.id=p.table_session_id
    LEFT JOIN restaurant_tables t ON t.id=ts.table_id
    LEFT JOIN staff_users s ON s.id=p.staff_id
    ORDER BY p.paid_at DESC LIMIT 7");

$topProducts = v2_rows($pdo, "SELECT oi.product_name,SUM(oi.quantity) quantity,SUM(oi.quantity*oi.unit_price) gross
    FROM order_items oi JOIN orders o ON o.id=oi.order_id
    WHERE o.created_at>=? AND o.created_at<? AND oi.status='active'
    GROUP BY oi.product_name ORDER BY quantity DESC,gross DESC LIMIT 5", [$todayStart, $tomorrowStart]);

$methodLabels = ['cash'=>'Nakit','card'=>'Kart','credit_card'=>'Kredi Kartı','meal_card'=>'Yemek Kartı','transfer'=>'Havale','other'=>'Diğer'];
$adminName = (string)($_SESSION['admin_name'] ?? $_SESSION['staff_name'] ?? 'Yönetici');
$systemHealthy = extension_loaded('pdo_mysql') && is_writable(BASE_PATH.'/storage');
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="<?=e($brandPrimary)?>">
<title><?=e($brandName)?> — Yönetim Merkezi</title>
<link rel="stylesheet" href="assets/admin-v2.css?v=27.2.2">
<style>:root{--brand:<?=e($brandPrimary)?>}</style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand-block">
            <?php if ($brandLogo !== ''): ?><img class="brand-logo" src="<?=e($brandLogo)?>" alt="<?=e($brandName)?>"><?php else: ?><div class="brand-mark"><?=e(mb_substr($brandShort,0,2))?></div><?php endif; ?>
            <div><strong><?=e($brandName)?></strong><span>Yönetim Merkezi</span></div>
        </div>
        <nav class="nav-list" aria-label="Yönetim menüsü">
            <span class="nav-section">GENEL</span>
            <a class="nav-item active" href="?page=dashboard"><span>⌂</span><b>Dashboard</b></a>
            <a class="nav-item" href="?page=reports"><span>▥</span><b>Raporlar</b></a>
            <span class="nav-section">OPERASYON</span>
            <a class="nav-item" href="../cashier/" target="_blank"><span>▣</span><b>POS / Kasa</b></a>
            <a class="nav-item" href="../staff/" target="_blank"><span>♨</span><b>Garson Paneli</b></a>
            <span class="nav-section">KATALOG VE QR</span>
            <a class="nav-item" href="?page=categories"><span>◫</span><b>Kategoriler</b></a>
            <a class="nav-item" href="?page=products"><span>◇</span><b>Ürünler</b></a>
            <a class="nav-item" href="enterprise/media.php"><span>▧</span><b>Medya Merkezi</b></a>
            <a class="nav-item" href="qr-experience/"><span>✦</span><b>QR Experience</b></a>
            <span class="nav-section">İŞLETME</span>
            <a class="nav-item" href="?page=tables"><span>▦</span><b>Salon ve Masalar</b></a>
            <a class="nav-item" href="?page=staff"><span>♙</span><b>Personel</b></a>
            <span class="nav-section">SİSTEM</span>
            <a class="nav-item" href="brand-center.php"><span>✦</span><b>Marka Merkezi</b></a>
            <?php if (has_permission('modules.manage')): ?><a class="nav-item" href="module-center.php"><span>◫</span><b>Modül Merkezi</b></a><?php endif; ?>
            <a class="nav-item" href="?page=security"><span>◈</span><b>Güvenlik</b></a>
            <a class="nav-item" href="?page=maintenance"><span>⚙</span><b>Bakım ve Yedekleme</b></a>
            <?php if (has_permission('maintenance.manage')): ?><a class="nav-item" href="system-center.php"><span>●</span><b>Sistem Merkezi</b></a><?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="health-pill"><i class="<?= $systemHealthy ? 'ok' : 'warn' ?>"></i><?= $systemHealthy ? 'Sistem hazır' : 'Kontrol gerekli' ?></div>
            
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="icon-button mobile-only" id="menuButton" aria-label="Menüyü aç">☰</button>
            <div class="page-title"><small><?=e(date('d F Y'))?></small><h1>Günaydın, <?=e($adminName)?></h1></div>
            <button class="search-trigger" id="commandButton"><span>⌕</span><b>Hızlı ara veya komut çalıştır</b><kbd>⌘ K</kbd></button>
            <div class="top-actions"><a class="icon-button" href="brand-center.php" title="Marka Merkezi">✦</a><a class="avatar" href="?logout=1" title="Çıkış"><?=e(mb_strtoupper(mb_substr($adminName,0,1)))?></a></div>
        </header>

        <section class="hero-row">
            <div><span class="eyebrow">CANLI İŞLETME ÖZETİ</span><h2>Bugünün kontrolü tek ekranda.</h2><p>Satış, masa, personel ve sistem durumunu hızlıca takip edin.</p></div>
            <div class="hero-actions"><a class="button secondary" href="?page=reports">Raporları aç</a><a class="button primary" href="../cashier/">Kasaya git</a></div>
        </section>

        <section class="metric-grid">
            <article class="metric-card emphasis"><div class="metric-icon">₺</div><div><span>Bugünkü satış</span><strong><?=v2_money($todayRevenue)?></strong><small><?=$ticketCount?> tahsilat alınan adisyon</small></div></article>
            <article class="metric-card"><div class="metric-icon">▦</div><div><span>Açık masalar</span><strong><?=$openTables?></strong><small>Şu anda servis verilen masa</small></div></article>
            <article class="metric-card"><div class="metric-icon">▤</div><div><span>Aktif sipariş</span><strong><?=$activeOrders?></strong><small>Devam eden sipariş kaydı</small></div></article>
            <article class="metric-card"><div class="metric-icon">Ø</div><div><span>Ortalama adisyon</span><strong><?=v2_money($averageTicket)?></strong><small>Bugünkü tahsilat ortalaması</small></div></article>
        </section>

        <section class="quick-actions card">
            <div class="section-head"><div><span class="eyebrow">HIZLI İŞLEMLER</span><h3>Sık kullanılanlar</h3></div><span class="muted">Dokunmatik kullanım için optimize edildi</span></div>
            <div class="action-grid">
                <a href="?page=products" class="action-tile"><i>＋</i><div><b>Yeni ürün</b><span>Ürün kataloğunu yönet</span></div></a>
                <a href="?page=tables" class="action-tile"><i>▦</i><div><b>Masa düzeni</b><span>Salon ve masaları aç</span></div></a>
                <a href="?page=staff" class="action-tile"><i>♙</i><div><b>Yeni personel</b><span>PIN ve yetki tanımla</span></div></a>
                <a href="qr-experience/" class="action-tile"><i>⌁</i><div><b>QR Menü</b><span>Dijital menüyü yönet</span></div></a>
                <a href="enterprise/media.php" class="action-tile"><i>▧</i><div><b>Medya Merkezi</b><span>Görselleri seç, düzenle ve optimize et</span></div></a>
                <a href="brand-center.php" class="action-tile"><i>✦</i><div><b>Marka Merkezi</b><span>Logo ve renkleri değiştir</span></div></a>
            </div>
        </section>

        <section class="dashboard-grid">
            <article class="card chart-card">
                <div class="section-head"><div><span class="eyebrow">SON 7 GÜN</span><h3>Satış hareketi</h3></div><strong><?=v2_money(array_sum(array_column($week,'total')))?></strong></div>
                <div class="bar-chart">
                    <?php foreach ($week as $day): $height=max(5,($day['total']/$weekMax)*100); ?>
                    <div class="bar-column" title="<?=e(date('d.m.Y',strtotime($day['date'])))?> — <?=e(v2_money($day['total']))?>"><div class="bar-value" style="height:<?=$height?>%"></div><span><?=e(date('D',strtotime($day['date'])))?></span></div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card status-card">
                <div class="section-head"><div><span class="eyebrow">OPERASYON</span><h3>Canlı durum</h3></div><span class="live-dot">Canlı</span></div>
                <div class="status-list">
                    <div><span><i class="status-icon">♙</i>Aktif personel</span><strong><?=$activeStaff?></strong></div>
                    <div><span><i class="status-icon">▣</i>Açık kasa</span><strong><?=$openCash?></strong></div>
                    <div><span><i class="status-icon">◇</i>Aktif ürün</span><strong><?=$activeProducts?></strong></div>
                    <div><span><i class="status-icon">●</i>Veritabanı</span><strong class="positive">Bağlı</strong></div>
                    <div><span><i class="status-icon">●</i>Depolama</span><strong class="<?=is_writable(BASE_PATH.'/storage')?'positive':'negative'?>"><?=is_writable(BASE_PATH.'/storage')?'Hazır':'Kontrol et'?></strong></div>
                </div>
            </article>
        </section>

        <section class="dashboard-grid lower-grid">
            <article class="card">
                <div class="section-head"><div><span class="eyebrow">AÇIK MASALAR</span><h3>Servis akışı</h3></div><a href="../cashier/">Tüm masalar →</a></div>
                <div class="table-list">
                    <?php foreach ($openTableRows as $row): ?>
                    <div class="table-row"><div class="table-badge">▦</div><div><b><?=e($row['name'])?></b><span><?=e($row['area_name'])?> · <?=e(date('H:i',strtotime($row['opened_at'])))?></span></div><strong><?=v2_money((float)$row['total'])?></strong></div>
                    <?php endforeach; ?>
                    <?php if (!$openTableRows): ?><div class="empty-state">Şu anda açık masa bulunmuyor.</div><?php endif; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-head"><div><span class="eyebrow">SON TAHSİLATLAR</span><h3>Ödeme hareketleri</h3></div><a href="?page=reports">Rapor →</a></div>
                <div class="payment-list">
                    <?php foreach ($recentPayments as $payment): ?>
                    <div class="payment-row"><div class="payment-symbol">₺</div><div><b><?=e($payment['table_name'] ?: 'Masa dışı')?></b><span><?=e($payment['staff_name'])?> · <?=e($methodLabels[$payment['method']] ?? $payment['method'])?> · <?=e(date('H:i',strtotime($payment['paid_at'])))?></span></div><strong><?=v2_money((float)$payment['amount'])?></strong></div>
                    <?php endforeach; ?>
                    <?php if (!$recentPayments): ?><div class="empty-state">Henüz ödeme hareketi bulunmuyor.</div><?php endif; ?>
                </div>
            </article>
        </section>

        <section class="card top-products">
            <div class="section-head"><div><span class="eyebrow">BUGÜN</span><h3>En çok satan ürünler</h3></div><a href="?page=reports">Detaylı rapor →</a></div>
            <div class="product-grid">
                <?php foreach ($topProducts as $index=>$product): ?>
                <div class="product-item"><span class="rank"><?=($index+1)?></span><div><b><?=e($product['product_name'])?></b><span><?=number_format((float)$product['quantity'],0,',','.')?> adet</span></div><strong><?=v2_money((float)$product['gross'])?></strong></div>
                <?php endforeach; ?>
                <?php if (!$topProducts): ?><div class="empty-state">Bugün için ürün satışı bulunmuyor.</div><?php endif; ?>
            </div>
        </section>
    </main>
</div>

<div class="command-overlay" id="commandOverlay" hidden>
    <div class="command-panel" role="dialog" aria-modal="true" aria-label="Hızlı komut">
        <div class="command-input"><span>⌕</span><input id="commandInput" type="search" autocomplete="off" placeholder="Bir sayfa veya işlem ara..."><kbd>ESC</kbd></div>
        <div class="command-results" id="commandResults"></div>
    </div>
</div>
<script src="assets/admin-v2.js?v=27.2.1"></script>
</body>
</html>
