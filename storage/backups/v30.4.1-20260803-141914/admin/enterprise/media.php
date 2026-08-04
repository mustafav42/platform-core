<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
ent_media_upgrade();
$pageTitle='Medya Merkezi';$currentPage='media';
$syncNotice='';
try{$added=ent_media_sync_storage();if($added>0)$syncNotice=$added.' mevcut görsel kütüphaneye eklendi.';}catch(Throwable $e){}
require __DIR__.'/_header.php';
?>
<link rel="stylesheet" href="assets/media-center-v2.css?v=3041">
<div class="mc2" data-media-center data-api="api/media-center.php" data-csrf="<?=ent_e($_SESSION['csrf_token'])?>">
  <section class="mc2-head">
    <div><span>CHERRYHOUSE ASSETS</span><h2>Medya Merkezi</h2><p>Görsellerinizi bulun, yükleyin, düzenleyin ve kullanıldığı yerleri tek ekrandan yönetin.</p></div>
    <div class="mc2-head-actions"><button type="button" class="mc2-secondary" data-sync>↻ Kütüphaneyi Tara</button><button type="button" class="mc2-primary" data-upload-open>＋ Yeni Medya</button></div>
  </section>
  <?php if($syncNotice):?><div class="mc2-notice"><?=ent_e($syncNotice)?></div><?php endif;?>
  <section class="mc2-stats" aria-label="Medya özeti">
    <article><span>Toplam Medya</span><strong data-stat-total>—</strong></article><article><span>Depolama</span><strong data-stat-size>—</strong></article><article><span>Görüntülenen</span><strong data-stat-visible>—</strong></article><article><span>Seçili</span><strong data-stat-selected>0</strong></article>
  </section>
  <section class="mc2-toolbar">
    <label class="mc2-search"><span>⌕</span><input type="search" placeholder="Dosya adı, etiket veya alternatif metin ara…" data-search></label>
    <div class="mc2-filters" data-filters><button class="active" data-filter="all">Tümü</button><button data-filter="favorites">Favoriler</button><button data-filter="unused">Kullanılmayan</button><button data-filter="missing">Kayıp Dosyalar</button></div>
    <select data-folder><option value="">Tüm klasörler</option></select>
    <div class="mc2-view"><button type="button" class="active" data-view="grid" aria-label="Kart görünümü">▦</button><button type="button" data-view="compact" aria-label="Kompakt görünüm">☷</button></div>
  </section>
  <section class="mc2-library"><div class="mc2-grid" data-grid><div class="mc2-loading"><i></i><span>Medya kütüphanesi hazırlanıyor…</span></div></div></section>
</div>
<div class="mc2-upload" data-upload-modal hidden><button class="mc2-overlay" data-upload-close></button><section role="dialog" aria-modal="true"><header><div><small>YENİ MEDYA</small><h3>Görselleri yükleyin</h3></div><button data-upload-close>×</button></header><label class="mc2-drop" data-drop-zone><input type="file" accept="image/jpeg,image/png,image/webp" multiple data-file-input><span class="mc2-drop-icon">⇧</span><strong>Dosyaları buraya bırakın</strong><em>veya bilgisayarınızdan seçmek için tıklayın</em><small>JPG, PNG, WebP · Dosya başına en fazla 32 MB</small></label><div class="mc2-upload-options"><label>Klasör<select data-upload-folder><option>Ürünler</option><option>Kategoriler</option><option>Hero</option><option>Logo</option><option>Bannerlar</option><option>Genel</option></select></label></div><div class="mc2-queue" data-upload-queue></div></section></div>
<div class="mc2-drawer-wrap" data-drawer-wrap hidden><button class="mc2-overlay" data-drawer-close></button><aside class="mc2-drawer" data-drawer><header><div><small>MEDYA AYRINTILARI</small><h3 data-detail-name>Görsel</h3></div><button data-drawer-close>×</button></header><div class="mc2-detail-preview"><img data-detail-image alt=""></div><div class="mc2-detail-meta"><span data-detail-dim>—</span><span data-detail-size>—</span><span data-detail-mime>—</span></div><div class="mc2-usage"><small>KULLANILDIĞI YERLER</small><div data-detail-usage></div></div><form data-detail-form><input type="hidden" name="id" data-detail-id><label>Alternatif metin<input name="alt" data-detail-alt placeholder="Görseli açıklayan kısa metin"></label><label>Klasör<input name="folder" data-detail-folder></label><label>Etiketler<input name="tags" data-detail-tags placeholder="kahvaltı, simit, ürün"></label><label class="mc2-check"><input type="checkbox" data-detail-favorite> Favorilere ekle</label><button class="mc2-primary" type="submit">Değişiklikleri Kaydet</button></form><div class="mc2-detail-actions"><a data-detail-studio>Image Studio’da Düzenle</a><a data-detail-download download>İndir</a><button data-detail-copy>URL’yi Kopyala</button><button class="danger" data-detail-delete>Sil</button></div></aside></div>
<script src="assets/media-center-v2.js?v=3041"></script>
<?php require __DIR__.'/_footer.php';
