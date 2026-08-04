<div class="section-head"><span>VISUAL LAYOUT BUILDER</span><h2>Menü akışını düzenle</h2><p>Bölümleri tutup sürükleyin. Anahtarları kullanarak görünürlüğü taslakta yönetin.</p></div>
<?php
$sectionMap=[];foreach(QrExperience::sections() as $section)$sectionMap[$section['key']]=$section;
$order=QrExperience::sectionOrder(true);
$defaultOff=['chef_note','story','social'];
?>
<input type="hidden" id="qr_section_order" name="qr_section_order" value="<?=qrxos_e(implode(',',$order))?>">
<section class="panel layout-builder-panel">
 <div class="layout-builder-help"><span>☰</span><div><b>Sürükle-bırak aktif</b><small>Mobil cihazlarda ok düğmeleriyle de sıralayabilirsiniz.</small></div></div>
 <ol class="visual-sortable" data-layout-sortable>
 <?php foreach($order as $index=>$key):$section=$sectionMap[$key];$enabled=QrExperience::enabled($key,in_array($key,$defaultOff,true)?'0':'1',true);?>
  <li draggable="true" data-section-key="<?=qrxos_e($key)?>">
   <button class="drag-handle" type="button" aria-label="<?=qrxos_e($section['label'])?> bölümünü sürükle">☰</button>
   <span class="layout-index"><?=str_pad((string)($index+1),2,'0',STR_PAD_LEFT)?></span>
   <div class="layout-copy"><b><?=qrxos_e($section['label'])?></b><small><?=qrxos_e($section['description'])?></small></div>
   <div class="layout-move-buttons"><button type="button" data-move="up" aria-label="Yukarı taşı">↑</button><button type="button" data-move="down" aria-label="Aşağı taşı">↓</button></div>
   <label class="compact-switch"><input type="checkbox" name="enabled_<?=qrxos_e($key)?>" <?=$enabled?'checked':''?>><span></span><b><?=$enabled?'Açık':'Kapalı'?></b></label>
  </li>
 <?php endforeach;?>
 </ol>
</section>
