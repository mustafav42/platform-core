<div class="section-head"><span>THEME STUDIO</span><h2>Görsel kimlik</h2><p>Hazır bir stil seçin veya tüm ayrıntıları markanıza göre düzenleyin.</p></div>
<input type="hidden" name="qrx_theme_preset" value="<?=qrxos_e(qrxos_get('qrx_theme_preset','editorial'))?>">
<div class="theme-library">
<?php
$themes=[
 'editorial'=>['Editorial B','Krem · editoryal · premium',['#f4efe6','#fffdf8','#191814','#a77931']],
 'minimal'=>['Modern Light','Beyaz · net · hafif',['#f7f7f5','#ffffff','#151515','#232323']],
 'cherry'=>['Cherry Accent','Krem · bordo vurgu',['#f6eee9','#fffaf6','#211516','#8b1e2d']],
 'coffee'=>['Coffee House','Sıcak · doğal · kahve',['#eee5d8','#fffaf0','#2a1d17','#8a5a36']],
 'steak'=>['Steak House','Koyu · güçlü · bakır',['#171511','#24201b','#f5eadc','#bd7446']],
 'pizza'=>['Pizza House','Canlı · sıcak · iştah açıcı',['#fff1df','#fffaf1','#251813','#d45a2a']],
 'sushi'=>['Sushi Bar','Sade · koyu · Japon esintisi',['#111614','#1c2421','#f4f1e8','#c94c4c']],
 'breakfast'=>['Breakfast','Aydınlık · taze · sabah',['#fff8df','#ffffff','#2e2a1f','#e3a326']],
 'dessert'=>['Dessert Boutique','Pastel · zarif · tatlı',['#fff0f5','#fffafd','#2d2027','#c85b8d']],
];
foreach($themes as $id=>$t): ?>
<button type="button" class="theme-card <?=qrxos_get('qrx_theme_preset','editorial')===$id?'selected':''?>" data-preset="<?=$id?>">
 <span class="theme-swatches"><?php foreach($t[2] as $c):?><i style="background:<?=$c?>"></i><?php endforeach;?></span>
 <b><?=$t[0]?></b><small><?=$t[1]?></small><em>Uygula</em>
</button>
<?php endforeach;?>
</div>
<div class="form-grid"><section class="panel"><h3>Renk Paleti</h3><div class="color-grid"><?php foreach(['qr_ui_background'=>'Sayfa zemini','qr_ui_surface'=>'Kart zemini','qr_ui_text'=>'Ana yazı','qr_ui_muted'=>'İkincil yazı','qr_accent_color'=>'Vurgu'] as $k=>$l):?><label class="color-field"><input type="color" name="<?=$k?>" value="<?=qrxos_e(qrxos_get($k,['qr_ui_background'=>'#f4efe6','qr_ui_surface'=>'#fffdf8','qr_ui_text'=>'#191814','qr_ui_muted'=>'#716c63','qr_accent_color'=>'#a77931'][$k]))?>"><span><?=$l?></span></label><?php endforeach;?></div></section><section class="panel"><h3>Tipografi ve Form</h3><label>Yazı tipi<select name="qr_font_family"><?php foreach(['dm-sans'=>'DM Sans','inter'=>'Inter','manrope'=>'Manrope','poppins'=>'Poppins','playfair'=>'Playfair Display','cormorant'=>'Cormorant Garamond'] as $v=>$l):?><option value="<?=$v?>" <?=qrxos_get('qr_font_family','dm-sans')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></label><label>Köşe yuvarlaklığı <output data-output="qr_card_radius"><?=qrxos_e(qrxos_get('qr_card_radius','16'))?> px</output><input type="range" min="0" max="36" name="qr_card_radius" value="<?=qrxos_e(qrxos_get('qr_card_radius','16'))?>"></label><label>Kart gölgesi<select name="qr_card_shadow"><option value="none" <?=qrxos_get('qr_card_shadow')==='none'?'selected':''?>>Yok</option><option value="soft" <?=qrxos_get('qr_card_shadow','soft')==='soft'?'selected':''?>>Yumuşak</option><option value="medium" <?=qrxos_get('qr_card_shadow')==='medium'?'selected':''?>>Belirgin</option></select></label><label>Boşluk sistemi<select name="qr_spacing_scale"><option value="compact" <?=qrxos_get('qr_spacing_scale')==='compact'?'selected':''?>>Kompakt</option><option value="balanced" <?=qrxos_get('qr_spacing_scale','balanced')==='balanced'?'selected':''?>>Dengeli</option><option value="airy" <?=qrxos_get('qr_spacing_scale')==='airy'?'selected':''?>>Ferah</option></select></label></section></div>
