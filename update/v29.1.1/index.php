<?php
declare(strict_types=1);
$bootstrapFile=dirname(__DIR__,2).'/app/bootstrap.php';
if(function_exists('opcache_invalidate')) @opcache_invalidate($bootstrapFile,true);
clearstatcache(true,$bootstrapFile);
require_once $bootstrapFile;
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../../admin/');
if(function_exists('require_permission')) require_permission('maintenance.manage');

$pdo=db();$messages=[];$error='';
$keys=[
'qrx_theme_preset','qr_menu_enabled','qr_show_search','qr_ui_first_category_open','qr_ui_show_product_images','qr_ui_show_descriptions','qr_ui_single_category','qr_ui_show_category_count','qr_ui_show_category_numbers','qr_ui_show_row_arrow',
'qr_hero_title','qr_hero_subtitle','qr_hero_image','qr_logo_image','qr_hero_height','qr_hero_overlay','qr_show_hero','qr_show_status','qr_show_readonly_note',
'qr_accent_color','qr_ui_background','qr_ui_surface','qr_ui_text','qr_ui_muted','qr_ui_category_style','qr_ui_product_density','qr_font_family','qr_card_radius','qr_card_shadow','qr_spacing_scale',
'qr_detail_variant','qr_detail_image_height','qr_ui_detail_note','qr_show_detail_category','qr_show_detail_badges','qr_show_detail_close_text',
'qr_show_promo_bar','qr_promo_text','qr_show_featured','qr_feature_title','qr_feature_text','qr_show_chef_note','qr_chef_note_title','qr_chef_note_text','qr_show_story','qr_story_title','qr_story_text','qr_show_social','qr_social_title','qr_social_text','qr_price_note',
'qr_section_order','qr_section_categories_enabled'
];
try{
    $pdo->beginTransaction();
    $read=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
    $write=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $delete=$pdo->prepare('DELETE FROM settings WHERE setting_key=?');
    $migrated=0;
    foreach($keys as $key){
        $draftKey='qrx_draft_'.$key;
        $read->execute([$draftKey]);
        $draft=$read->fetchColumn();
        if($draft!==false){$write->execute([$key,(string)$draft]);$migrated++;}
        $delete->execute([$draftKey]);
    }
    $delete->execute(['qrx_draft_updated_at']);
    $now=date('Y-m-d H:i:s');
    $write->execute(['qrx_last_published_at',$now]);
    $write->execute(['qrx_publish_revision',date('YmdHis').'-'.bin2hex(random_bytes(3))]);
    $write->execute(['qr_menu_enabled','1']);
    $write->execute(['app_version','29.1.1']);
    if(function_exists('db_table_exists') && db_table_exists($pdo,'app_migrations')){
        $q=$pdo->prepare("INSERT IGNORE INTO app_migrations(migration_key,version,description,executed_at) VALUES('20260729_2911_qrx_live_editor','29.1.1','QR Experience taslak sistemi kaldırıldı; canlı önizleme ve tek adımlı yayınlama etkinleştirildi.',NOW())");
        $q->execute();
    }
    $pdo->commit();
    if(class_exists('QrExperience') && method_exists('QrExperience','clearCache')) QrExperience::clearCache();
    if(function_exists('audit_log')) audit_log('system_update','CherryHouse v29.1.1 QR Experience Live Editor kuruldu.',['migrated_drafts'=>$migrated]);
    $messages=[
        'QR Experience taslak sistemi kaldırıldı.',
        $migrated.' mevcut taslak ayarı canlı ayarlara aktarıldı.',
        'Renk, tipografi, Hero ve bileşen ayarları tek veri kaynağına bağlandı.',
        'Kaydet ve Yayınla akışı etkinleştirildi.',
        'Eski qrx_draft_* kayıtları temizlendi.'
    ];
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();if(function_exists('app_log'))app_log($e,['update'=>'29.1.1']);}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CherryHouse v29.1.1 Güncelleme</title><style>body{font-family:Inter,system-ui;background:#f4f6f8;padding:30px;color:#172033}.box{max-width:820px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 15px 50px #0001}.ok{padding:13px;background:#dcfce7;color:#166534;border-radius:11px;margin:10px 0}.err{padding:13px;background:#fee2e2;color:#991b1b;border-radius:11px}a{display:inline-block;margin:16px 8px 0 0;padding:12px 16px;background:#8b1e2d;color:#fff;text-decoration:none;border-radius:10px}</style></head><body><div class="box"><h1>CherryHouse v29.1.1 LTS</h1><h2>QR Experience Live Editor</h2><?php if($error):?><div class="err"><?=e($error)?></div><?php else:foreach($messages as $m):?><div class="ok">✓ <?=e($m)?></div><?php endforeach;?><a href="../../admin/qr-experience/">QR Experience’ı Aç</a><a href="../../">Canlı Menüyü Aç</a><?php endif;?></div></body></html>
