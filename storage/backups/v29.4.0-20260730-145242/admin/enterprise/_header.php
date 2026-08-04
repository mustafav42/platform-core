<?php
$pageTitle = $pageTitle ?? 'Enterprise';
$currentPage = $currentPage ?? 'dashboard';
$businessName = ent_setting('business_name', 'CherryHouse');
$menuItems = [
    ['id'=>'dashboard','href'=>'./','icon'=>'⌂','label'=>'Dashboard','keywords'=>'özet istatistik ana ekran'],
    ['id'=>'media','href'=>'media.php','icon'=>'▧','label'=>'Medya Kütüphanesi','keywords'=>'görsel resim yükleme dosya'],
    ['id'=>'qrx','href'=>'../qr-experience/','icon'=>'◈','label'=>'QR Experience OS','keywords'=>'tema hero tasarım menü'],
    ['id'=>'products','href'=>'products.php','icon'=>'□','label'=>'Ürünler','keywords'=>'ürün fiyat yemek'],
    ['id'=>'categories','href'=>'categories.php','icon'=>'≡','label'=>'Kategoriler','keywords'=>'kategori menü sıralama'],
    ['id'=>'variants','href'=>'variants.php','icon'=>'◇','label'=>'Varyantlar','keywords'=>'varyant seçenek ekstra boyut fiyat'],
    ['id'=>'settings','href'=>'../?page=maintenance','icon'=>'⚙','label'=>'Ayarlar','keywords'=>'sistem restoran ayar'],
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?=ent_e($pageTitle)?> · <?=ent_e($businessName)?></title>
<link rel="stylesheet" href="assets/enterprise.css?v=701">
<link rel="stylesheet" href="assets/admin-ui.css?v=701">
<link rel="stylesheet" href="assets/media-picker.css?v=2930">
</head>
<body class="ent-ui" data-page="<?=ent_e($currentPage)?>">
<div class="enterprise-shell" data-admin-shell>
<aside class="enterprise-sidebar" data-admin-sidebar>
    <div class="sidebar-head">
        <a class="enterprise-brand" href="./"><span>CH</span><div><strong><?=ent_e($businessName)?></strong><small>Enterprise v6.1</small></div></a>
        <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Menüyü daralt" title="Menüyü daralt">⇤</button>
    </div>
    <button class="command-trigger" type="button" data-command-open><span>⌕</span><b>Hızlı ara</b><kbd>⌘ K</kbd></button>
    <nav class="enterprise-nav" aria-label="Yönetim menüsü">
        <small class="nav-label">YÖNETİM</small>
        <?php foreach($menuItems as $item):?>
        <a class="<?=$currentPage===$item['id']?'active':''?>" href="<?=ent_e($item['href'])?>" data-nav-item data-keywords="<?=ent_e($item['keywords'])?>"><span><?=ent_e($item['icon'])?></span><b><?=ent_e($item['label'])?></b><button type="button" class="favorite-toggle" data-favorite="<?=ent_e($item['id'])?>" aria-label="Favoriye ekle">☆</button></a>
        <?php endforeach;?>
        <a href="../"><span>←</span><b>Ana Yönetim Paneli</b></a>
    </nav>
    <div class="sidebar-spacer"></div>
    <div class="enterprise-version"><strong>v6.1 Admin UI</strong><span>Modern panel altyapısı</span></div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
<div class="enterprise-main">
<header class="enterprise-topbar">
    <button class="mobile-menu" type="button" data-sidebar-toggle aria-label="Menüyü aç">☰</button>
    <div class="page-heading"><small>YÖNETİM MERKEZİ</small><h1><?=ent_e($pageTitle)?></h1></div>
    <div class="topbar-actions">
        <button class="icon-button" type="button" data-command-open aria-label="Hızlı ara" title="Hızlı ara">⌕</button>
        <button class="icon-button" type="button" data-theme-toggle aria-label="Tema değiştir" title="Tema değiştir">◐</button>
        <details class="notification-menu"><summary class="icon-button" aria-label="Bildirimler">♢<i></i></summary><div><strong>Bildirim Merkezi</strong><p>Sistem sağlığı ve eksik içerik uyarıları Dashboard üzerinden takip edilir.</p><a href="./">Dashboard’u aç</a></div></details>
        <a class="preview-button" href="../../" target="_blank" rel="noopener">Canlı Menü <span>↗</span></a>
    </div>
</header>
<main class="enterprise-content">
<div class="command-palette" data-command-palette hidden>
    <button class="command-backdrop" type="button" data-command-close aria-label="Aramayı kapat"></button>
    <section role="dialog" aria-modal="true" aria-label="Hızlı menü araması">
        <header><span>⌕</span><input type="search" data-command-input placeholder="Menü veya işlem ara…" autocomplete="off"><kbd>ESC</kbd></header>
        <div class="command-results" data-command-results>
            <?php foreach($menuItems as $item):?><a href="<?=ent_e($item['href'])?>" data-command-item data-search="<?=ent_e(mb_strtolower($item['label'].' '.$item['keywords'],'UTF-8'))?>"><span><?=ent_e($item['icon'])?></span><div><strong><?=ent_e($item['label'])?></strong><small><?=ent_e($item['keywords'])?></small></div><b>↵</b></a><?php endforeach;?>
            <a href="../../" target="_blank" data-command-item data-search="canlı menü önizleme"><span>↗</span><div><strong>Canlı Menüyü Aç</strong><small>Yeni sekmede görüntüle</small></div><b>↵</b></a>
        </div>
        <footer><span>↑↓ gezin</span><span>Enter aç</span><span>Esc kapat</span></footer>
    </section>
</div>
