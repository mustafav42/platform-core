<?php
declare(strict_types=1);
if(!is_file(__DIR__.'/storage/installed.lock')){header('Location: install/');exit;}
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/qr/ThemeRegistry.php';
require_once __DIR__.'/app/qr/QrExperience.php';
require_once __DIR__.'/app/media/QrImage.php';
require_once __DIR__.'/app/Modules/QRExperience/helpers.php';
require_once __DIR__.'/app/Modules/RestaurantCMS/helpers.php';
$preview=isset($_GET['preview']);
// QR Experience v29.1.2: yayın sonrası eski HTML/CSS görünümünü engelle.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if($preview){
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
function qrx_public_setting(string $key, mixed $default=''): mixed {
    global $preview;
    return QrExperience::setting($key,(string)$default,(bool)$preview);
}
function qr_product_allergen_labels(?string $codes): array {
    $map=['gluten'=>'Gluten','milk'=>'Süt','egg'=>'Yumurta','peanut'=>'Yer fıstığı','nuts'=>'Sert kabuklu yemişler','soy'=>'Soya','sesame'=>'Susam','fish'=>'Balık','crustaceans'=>'Kabuklu deniz ürünleri','molluscs'=>'Yumuşakçalar','mustard'=>'Hardal','celery'=>'Kereviz','lupin'=>'Acı bakla','sulphites'=>'Sülfit'];
    $out=[];foreach(array_filter(explode(',',(string)$codes)) as $code){$code=trim($code);if(isset($map[$code]))$out[]=$map[$code];}return $out;
}
if(qrx_public_setting('qr_menu_enabled','1')!=='1'&&!$preview){?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Menü geçici olarak kapalı</title><link rel="stylesheet" href="app/assets/qr-menu.css?v=<?=e($qrxRevision)?>"></head><body><main class="maintenance"><div><small><?=e(setting('business_name','Restoran'))?></small><h1>Menümüz yenileniyor</h1><p>Size daha iyi bir deneyim sunmak için QR menümüz üzerinde çalışıyoruz. Lütfen kısa süre sonra tekrar deneyin.</p></div></main></body></html><?php exit;}
$themeKey=QrThemeRegistry::normalize((string)qrx_public_setting('qr_theme','cherry'));$theme=QrThemeRegistry::get($themeKey);$components=array_fill_keys($theme['components'],true);$accent=(string)qrx_public_setting('qr_accent_color','#8b1e2d');$font=QrExperience::fontStack(qrx_public_setting('qr_font_family','inter'));$radius=max(0,min(36,(int)qrx_public_setting('qr_card_radius','16')));$heroHeight=max(45,min(100,(int)qrx_public_setting('qr_hero_height','78')));$cardStyle=preg_replace('/[^a-z-]/','',qrx_public_setting('qr_card_style','elevated'));
$qrxRevision=preg_replace('/[^a-zA-Z0-9_-]/','',(string)QrExperience::setting('qrx_publish_revision',(string)time(),false));
$uiBackground=qrx_public_setting('qr_ui_background','#f4efe6');$uiSurface=qrx_public_setting('qr_ui_surface','#fffdf8');$uiText=qrx_public_setting('qr_ui_text','#191814');$uiMuted=qrx_public_setting('qr_ui_muted','#716c63');$uiCategoryStyle=preg_replace('/[^a-z-]/','',qrx_public_setting('qr_ui_category_style','pastel'));$uiDensity=preg_replace('/[^a-z-]/','',qrx_public_setting('qr_ui_product_density','compact'));$uiShowImages=true;$uiShowDescriptions=true;$uiFirstOpen=qrx_public_setting('qr_ui_first_category_open','0')==='1';$uiDetailNote=qrx_public_setting('qr_ui_detail_note','İçerik ve alerjen bilgileri için personelimize danışabilirsiniz.');$uiShowCategoryCount=qrx_public_setting('qr_ui_show_category_count','1')==='1';$uiShowCategoryNumbers=qrx_public_setting('qr_ui_show_category_numbers','1')==='1';$uiShowRowArrow=qrx_public_setting('qr_ui_show_row_arrow','1')==='1';$detailVariant=preg_replace('/[^a-z-]/','',qrx_public_setting('qr_detail_variant','sheet'));$detailImageHeight=max(180,min(620,(int)qrx_public_setting('qr_detail_image_height','360')));

$qrxCampaigns=qrx_campaigns(true);
$qrxBadges=qrx_product_badges_map();
$qrxActive=!empty($qrxCampaigns)||!empty($qrxBadges);
$cmsBlocks=cms_blocks('published',true);
$cmsSeoTitle=cms_setting('seo_title',setting('business_name','Restoran').' · Menü');
$cmsSeoDescription=cms_setting('seo_description',qrx_public_setting('qr_seo_description',qrx_public_setting('qr_hero_subtitle','Menümüzü keşfedin.')));
$cmsCanonical=cms_setting('seo_canonical','');
$cmsOgImage=cms_setting('og_image','');
$categories=db()->query('SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();$products=db()->query('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND c.is_active=1 ORDER BY c.sort_order,c.id,p.sort_order,p.id')->fetchAll();$byCat=[];foreach($products as $p)$byCat[(int)$p['category_id']][]=$p;
function menu_image_url(string $path):string{if($path==='')return '';if(preg_match('~^https?://~i',$path))return $path;return ltrim($path,'/');}function qr_component(array $components,string $name):bool{return isset($components[$name]);}
$heroOriginal=menu_image_url((string)qrx_public_setting('qr_hero_image',''));$hero=qr_image_url($heroOriginal,1600,82);$logoOriginal=menu_image_url((string)qrx_public_setting('qr_logo_image',''));$logo=qr_image_url($logoOriginal,360,84);$heroOverlay=max(0,min(90,(int)qrx_public_setting('qr_hero_overlay','35')));$showHero=qrx_public_setting('qr_show_hero','1')==='1';$showStatus=qrx_public_setting('qr_show_status','1')==='1';$showReadonly=qrx_public_setting('qr_show_readonly_note','1')==='1';$heroTitle=(string)qrx_public_setting('qr_hero_title','İyi yemek, mutlu anlar.');$heroSubtitle=(string)qrx_public_setting('qr_hero_subtitle','Taze malzemelerle, özenle hazırlanmış lezzetleri keşfedin.');$showDirectoryIntro=qrx_public_setting('qr_show_directory_intro','1')==='1';$directoryEyebrow=(string)qrx_public_setting('qr_directory_eyebrow','MENÜMÜZ');$directoryTitle=(string)qrx_public_setting('qr_directory_title','Bugün ne yemek istersiniz?');$directoryDescription=(string)qrx_public_setting('qr_directory_description','Kategoriyi aç, ürünleri incele ve ayrıntılar için ürüne dokun.');$showInfoBox=qrx_public_setting('qr_show_info_box','0')==='1';$infoEyebrow=(string)qrx_public_setting('qr_info_eyebrow','BİLGİ');$infoTitle=(string)qrx_public_setting('qr_info_title','Misafirlerimiz için');$infoText=(string)qrx_public_setting('qr_info_text','Wi-Fi ve çalışma saatleri için ekibimize danışabilirsiniz.');$showFooter=qrx_public_setting('qr_show_footer','1')==='1';
function render_qr_section(string $section,array $ctx):void{extract($ctx);switch($section){
case 'hero':if(!qr_component($components,'hero')||!$showHero)return;?><header class="hero" style="--qrx-hero-brightness:<?=e((string)max(.1,1-($heroOverlay/100)))?>;<?=$hero?'--hero-image:url(\''.e($hero).'\');':''?>"><div class="hero-noise" aria-hidden="true"></div><div class="hero-inner"><div class="hero-brand-row"><span class="hero-kicker">CHERRY HOUSE · MENÜ</span><?php if($showStatus):?><span class="hero-status"><i></i> Bugün Açık</span><?php endif;?></div><?php if($logo):?><img class="logo" src="<?=e($logo)?>" alt="<?=e(setting('business_name','Restoran'))?>"><?php else:?><div class="eyebrow"><?=e(setting('business_name','Restoran'))?></div><?php endif;?><h1><?=e($heroTitle)?></h1><p><?=e($heroSubtitle)?></p><div class="hero-actions"><a class="scroll-link" href="#menu-content">Menüyü keşfet <span aria-hidden="true">↘</span></a><?php if($showReadonly):?><span class="hero-readonly">Dijital menü · Yalnızca görüntüleme</span><?php endif;?></div><div class="hero-signals" aria-label="Menü özellikleri"><span><i aria-hidden="true">01</i> Günlük hazırlık</span><span><i aria-hidden="true">02</i> Seçkin malzeme</span><span><i aria-hidden="true">03</i> Usta dokunuşu</span></div></div></header><?php break;
case 'announcement':
case 'promo':
if($qrxActive&&$qrxCampaigns){$c=$qrxCampaigns[0];?><section class="qrx-campaign" style="--qrx-bg:<?=e($c['background_color']?:'#111827')?>;--qrx-color:<?=e($c['text_color']?:'#ffffff')?>"><?php if(($c['media_type']??'none')==='image'&&!empty($c['media_url'])){?><img src="<?=e(qr_image_url(menu_image_url((string)$c['media_url']),1280,80))?>" alt="" loading="lazy" decoding="async"><?php }elseif(($c['media_type']??'none')==='video'&&!empty($c['media_url'])){?><video src="<?=e($c['media_url'])?>" muted autoplay loop playsinline></video><?php }?><div><strong><?=e($c['title'])?></strong><?php if(!empty($c['subtitle'])){?><p><?=e($c['subtitle'])?></p><?php }?><?php if(!empty($c['button_text'])){?><a href="<?=e($c['button_url']?:'#menu')?>"><?=e($c['button_text'])?></a><?php }?></div></section><?php }elseif(qr_component($components,'promo')&&qrx_public_setting('qr_show_promo_bar','1')==='1'){?><div class="promo-marquee"><div class="promo-track"><span><?=e(qrx_public_setting('qr_promo_text','Bugüne özel fırsatları keşfet ✦ Yeni lezzetler menüde ✦'))?></span><span aria-hidden="true"><?=e(qrx_public_setting('qr_promo_text','Bugüne özel fırsatları keşfet ✦ Yeni lezzetler menüde ✦'))?></span></div></div><?php }break;
case 'featured':if(qr_component($components,'featured')&&qrx_public_setting('qr_show_featured','1')==='1'):?><section class="feature-panel"><div><span>GÜNÜN SEÇİMİ</span><h2><?=e(qrx_public_setting('qr_feature_title','Günün öne çıkan lezzeti'))?></h2><p><?=e(qrx_public_setting('qr_feature_text','Öne çıkan lezzetlerimizi keşfet.'))?></p></div></section><?php endif;break;
case 'chef_note':if(qr_component($components,'chef_note')&&qrx_public_setting('qr_show_chef_note','0')==='1'):?><section class="story-block"><span>ŞEFTEN</span><h2><?=e(qrx_public_setting('qr_chef_note_title','Mevsimin en iyi ürünleriyle'))?></h2><p><?=nl2br(e(qrx_public_setting('qr_chef_note_text','Mutfağımızda her tabak günlük ve özenle hazırlanır.')))?></p></section><?php endif;break;
case 'story':if(qr_component($components,'story')&&qrx_public_setting('qr_show_story','0')==='1'):?><section class="story-block"><span>HİKÂYEMİZ</span><h2><?=e(qrx_public_setting('qr_story_title','Sofrada başlayan bir hikâye'))?></h2><p><?=nl2br(e(qrx_public_setting('qr_story_text','Lezzeti, paylaşmayı ve iyi malzemeyi merkeze alan bir mutfak.')))?></p></section><?php endif;break;
case 'social':if(qr_component($components,'social')&&qrx_public_setting('qr_show_social','0')==='1'):?><section class="social-panel"><span>SOSYAL</span><h2><?=e(qrx_public_setting('qr_social_title','Bizi Instagram’da takip et'))?></h2><p><?=e(qrx_public_setting('qr_social_text','Yeni ürünler ve güncel duyurular için sosyal hesaplarımızdayız.'))?></p></section><?php endif;break;
case 'directory':if($showDirectoryIntro):?><header class="directory-head qrx-standalone-directory"><span><?=e($directoryEyebrow)?></span><h2 id="category-directory-title"><?=e($directoryTitle)?></h2><p><?=e($directoryDescription)?></p></header><?php endif;break;
case 'info':if($showInfoBox):?><section class="story-block qrx-info-box"><span><?=e($infoEyebrow)?></span><h2><?=e($infoTitle)?></h2><p><?=nl2br(e($infoText))?></p></section><?php endif;break;
case 'footer':if($showFooter):?><?php endif;break;
case 'search':if(qr_component($components,'search')&&qrx_public_setting('qr_show_search','1')==='1'):?><div class="search-wrap"><label class="search-shell" for="menu-search"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.15A7.5 7.5 0 1 1 4 11.5a7.5 7.5 0 0 1 15 0Z"/></svg><input id="menu-search" type="search" placeholder="Menüde ara..." autocomplete="off"><button class="search-clear" type="button" aria-label="Aramayı temizle" hidden>×</button></label><span id="search-count"></span></div><?php endif;break;
case 'categories':
?><section class="category-directory" aria-labelledby="category-directory-title"><div class="category-accordion" data-category-accordion>
  <?php $catIndex=0; foreach($categories as $c):$items=$byCat[(int)$c['id']]??[];if(!$items)continue;$catIndex++;$panelId='cat-panel-'.(int)$c['id'];$titleId='cat-title-'.(int)$c['id']; ?>
    <section class="category-group" id="cat-<?=(int)$c['id']?>" style="--cat-index:<?=$catIndex?>" data-category-group>
      <h3 class="category-heading" id="<?=$titleId?>">
        <button class="category-trigger <?=$uiShowCategoryNumbers?'has-category-number':'no-category-number'?>" type="button" aria-expanded="false" aria-controls="<?=$panelId?>">
          <?php if($uiShowCategoryNumbers):?><span class="category-number"><?=str_pad((string)$catIndex,2,'0',STR_PAD_LEFT)?></span><?php endif;?>
          <span class="category-title-wrap">
            <strong><?=e($c['name'])?></strong>
            <small><?php if($uiShowCategoryCount):?><?=count($items)?> ürün<?php endif;?><?php if($uiShowCategoryCount&&$c['description']):?> · <?php endif;?><?php if($c['description']):?><?=e($c['description'])?><?php endif;?></small>
          </span>
          <span class="category-chevron" aria-hidden="true"><i></i></span>
        </button>
      </h3>
      <div class="category-panel" id="<?=$panelId?>" role="region" aria-labelledby="<?=$titleId?>" hidden>
        <div class="category-products">
        <?php foreach($items as $i=>$p):$imgOriginal=menu_image_url((string)($p['image_path']??''));$img=qr_image_url($imgOriginal,480,78);$img2x=qr_image_url($imgOriginal,800,80);$detailImg=qr_image_url($imgOriginal,1280,84);$price=number_format((float)$p['price'],2,',','.').' '.setting('currency_symbol','₺');$allergenLabels=qr_product_allergen_labels((string)($p['allergen_codes']??'')); ?>
          <article class="product-row"
            tabindex="0" role="button" aria-haspopup="dialog"
            data-product-id="<?=(int)$p['id']?>"
            data-name="<?=e($p['name'])?>"
            data-price="<?=e($price)?>"
            data-description="<?=e((string)($p['description']??''))?>"
            data-image="<?=e($detailImg)?>"
            data-image-fallback="<?=e($img)?>"
            data-category="<?=e($c['name'])?>"
            data-allergens="<?=e(implode('|',$allergenLabels))?>"
            data-calories="<?=e((string)($p['calories_kcal']??''))?>"
            data-prep-time="<?=e((string)($p['prep_time_min']??''))?>"
            data-search="<?=e(mb_strtolower($p['name'].' '.$p['description'].' '.$c['name'],'UTF-8'))?>">
            <div class="product-thumb<?php if(!$img):?> is-placeholder<?php endif;?>">
              <?php if($img):?><img src="<?=e($img)?>" srcset="<?=e($img)?> 480w, <?=e($img2x)?> 800w" sizes="(max-width:700px) 112px, 150px" alt="<?=e($p['name'])?>" loading="lazy" decoding="async" fetchpriority="low"><?php else:?><span><?=e(mb_substr($p['name'],0,1,'UTF-8'))?></span><?php endif;?>
            </div>
            <div class="product-info">
              <h4><?=e($p['name'])?></h4>
              <?php if($p['description']):?><p><?=e($p['description'])?></p><?php else:?><p class="product-no-description">Detayları görüntülemek için dokun.</p><?php endif;?>
              <div class="product-badges"><?php foreach(($qrxBadges[(int)$p['id']]??[]) as $badge):?><span class="qrx-badge" style="--badge:<?=e($badge['badge_color']?:'#aa7a2c')?>"><?=e($badge['badge_text'])?></span><?php endforeach;?></div>
            </div>
            <strong class="product-price"><?=e($price)?></strong>
          </article>
        <?php endforeach;?>
        </div>
      </div>
    </section>
  <?php endforeach;?>
  </div>
</section><?php break;}}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?=e($accent)?>"><title><?=e($cmsSeoTitle)?></title><meta name="description" content="<?=e($cmsSeoDescription)?>"><?php if($hero):?><link rel="preload" as="image" href="<?=e($hero)?>" fetchpriority="high"><?php endif;?><?php if($cmsCanonical):?><link rel="canonical" href="<?=e($cmsCanonical)?>"><?php endif;?><?php if($cmsOgImage):?><meta property="og:image" content="<?=e($cmsOgImage)?>"><?php endif;?><link rel="stylesheet" href="app/assets/qr-menu.css?v=<?=e($qrxRevision)?>"><link rel="stylesheet" href="app/assets/qr-themes/<?=e($theme['css'])?>?v=<?=e($qrxRevision)?>"><link rel="stylesheet" href="app/assets/qrx-premium-menu.css?v=<?=e($qrxRevision)?>"><style>:root{--accent:<?=e($accent)?>;--gold:<?=e($accent)?>;--paper:<?=e($uiSurface)?>;--cream:<?=e($uiBackground)?>;--ink:<?=e($uiText)?>;--muted:<?=e($uiMuted)?>;--qr-font:<?=$font?>;--qr-radius:<?=$radius?>px;--qr-hero-height:<?=$heroHeight?>vh;--qrx-detail-image-height:<?=$detailImageHeight?>px}.qrx-campaign{margin:16px auto;max-width:1100px;border-radius:var(--qr-radius);overflow:hidden;background:var(--qrx-bg);color:var(--qrx-color);box-shadow:0 14px 36px #0002}.qrx-campaign img,.qrx-campaign video{width:100%;max-height:340px;object-fit:cover;display:block}.qrx-campaign>div{padding:18px 20px}.qrx-campaign strong{font-size:clamp(20px,5vw,32px)}.qrx-campaign p{margin:8px 0}.qrx-campaign a{display:inline-block;margin-top:6px;padding:9px 14px;background:#fff;color:#111;border-radius:999px;text-decoration:none;font-weight:700}.qrx-badge{display:inline-block;margin:5px 5px 0 0;padding:4px 8px;border-radius:999px;background:var(--badge);color:#fff;font-size:11px;font-weight:800}.qrx-standalone-directory{max-width:1100px;margin:clamp(42px,8vw,90px) auto 28px;padding:0 18px;text-align:center}.qrx-info-box{max-width:1100px;margin:24px auto}.product-top>div{min-width:0}body.qrx-runtime{background:var(--cream);color:var(--ink)}.cms-content-block{max-width:1100px;margin:24px auto;border-radius:var(--qr-radius);overflow:hidden;background:var(--cms-bg);color:var(--cms-color);display:grid;grid-template-columns:minmax(0,1fr);box-shadow:0 16px 40px #0001}.cms-block-media img{width:100%;max-height:440px;object-fit:cover;display:block}.cms-block-copy{padding:clamp(22px,5vw,54px)}.cms-block-copy span{font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;opacity:.65}.cms-block-copy h2{font-size:clamp(28px,6vw,52px);line-height:1.05;margin:10px 0}.cms-block-copy p{font-size:16px;line-height:1.7;opacity:.82}.cms-block-copy a{display:inline-block;margin-top:10px;padding:11px 16px;border-radius:999px;background:var(--accent);color:#fff;text-decoration:none;font-weight:800}@media(min-width:800px){.cms-type-image,.cms-type-banner{grid-template-columns:1.1fr 1fr;align-items:stretch}.cms-type-image .cms-block-media img,.cms-type-banner .cms-block-media img{height:100%;max-height:none}}</style><script defer src="app/assets/qr-menu.js?v=<?=e($qrxRevision)?>"></script><script defer src="app/assets/qrx-premium-menu.js?v=<?=e($qrxRevision)?>"></script></head><body class="qr-theme theme-<?=e($themeKey)?> card-<?=e($cardStyle)?> category-style-<?=e($uiCategoryStyle)?> density-<?=e($uiDensity)?> qrx-detail-<?=e($detailVariant)?>-mode show-product-images <?=$qrxActive?'qrx-runtime':''?>" data-first-category-open="<?=$uiFirstOpen?'1':'0'?>" <?=$preview?'data-preview="1"':''?>><main id="menu" class="menu-wrap"><div class="qrx-progress" aria-hidden="true"><span></span></div><div class="qrx-mini-header" aria-hidden="true"><div><strong><?=e(setting('business_name','Restoran'))?></strong><span>Kategori Menüsü</span></div><a href="#menu-content" tabindex="-1">Keşfet</a></div><div id="menu-content" class="menu-anchor" aria-hidden="true"></div><?php $ctx=compact('components','categories','byCat','hero','logo','heroOverlay','showHero','showStatus','showReadonly','heroTitle','heroSubtitle','showDirectoryIntro','directoryEyebrow','directoryTitle','directoryDescription','showInfoBox','infoEyebrow','infoTitle','infoText','showFooter','qrxActive','qrxCampaigns','qrxBadges');$runtimeOrder=QrExperience::layout((bool)$preview);foreach($runtimeOrder as $row){if(empty($row['enabled']))continue;$section=(string)$row['id'];if($section==='menu')$section='categories';elseif($section==='chef')$section='chef_note';render_qr_section($section,$ctx);}?><?php foreach($cmsBlocks as $block):?><section class="cms-content-block cms-type-<?=e($block['block_type'])?>" style="--cms-bg:<?=e($block['background_color'])?>;--cms-color:<?=e($block['text_color'])?>"><?php if(!empty($block['media_url'])):?><div class="cms-block-media"><img src="<?=e(qr_image_url(menu_image_url((string)$block['media_url']),960,80))?>" srcset="<?=e(qr_image_url(menu_image_url((string)$block['media_url']),640,78))?> 640w, <?=e(qr_image_url(menu_image_url((string)$block['media_url']),1280,82))?> 1280w" sizes="(max-width:799px) 100vw, 55vw" alt="<?=e($block['title']??'')?>" loading="lazy" decoding="async"></div><?php endif;?><div class="cms-block-copy"><?php if(!empty($block['subtitle'])):?><span><?=e($block['subtitle'])?></span><?php endif;?><?php if(!empty($block['title'])):?><h2><?=e($block['title'])?></h2><?php endif;?><?php if(!empty($block['body'])):?><p><?=nl2br(e($block['body']))?></p><?php endif;?><?php if(!empty($block['button_text'])):?><a href="<?=e($block['button_url']?:'#menu')?>"><?=e($block['button_text'])?></a><?php endif;?></div></section><?php endforeach;?>
</main><button class="to-top" aria-label="Yukarı çık">↑</button>
<div class="qrx-detail" id="qrx-product-detail" hidden>
  <button class="qrx-detail-backdrop" type="button" data-qrx-close aria-label="Ürün detayını kapat"></button>
  <section class="qrx-detail-sheet" role="dialog" aria-modal="true" aria-labelledby="qrx-detail-title">
    <div class="qrx-detail-handle" aria-hidden="true"></div>
    <button class="qrx-detail-close" type="button" data-qrx-close aria-label="Kapat">×</button>
    <div class="qrx-detail-media"></div>
    <div class="qrx-detail-body">
      <span class="qrx-detail-category"></span>
      <div class="qrx-detail-heading"><h2 id="qrx-detail-title"></h2><strong class="qrx-detail-price"></strong></div>
      <div class="qrx-detail-badges"></div>
      <p class="qrx-detail-description"></p>
      <div class="qrx-product-meta" hidden>
        <div class="qrx-allergen-meta" hidden><span class="qrx-meta-label">Alerjenler</span><div class="qrx-allergen-list"></div></div>
        <div class="qrx-nutrition-line" hidden></div>
      </div>
      <div class="qrx-detail-footer"><span><?=e($uiDetailNote)?></span><?php if(qrx_public_setting('qr_show_detail_close_text','1')==='1'):?><button type="button" data-qrx-close>Kapat</button><?php endif;?></div>
    </div>
  </section>
</div>
<div class="qrx-empty-state" id="qrx-empty-state" hidden><strong>Sonuç bulunamadı</strong><span>Aramanı veya filtrelerini değiştirerek tekrar deneyebilirsin.</span></div>
</body></html>
