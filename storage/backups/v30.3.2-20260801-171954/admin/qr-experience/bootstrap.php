<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/qr/QrExperience.php';
if(!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../../install/');
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../');
if(function_exists('require_permission')){try{require_permission('catalog.manage');}catch(Throwable){redirect('../');}}

function qrxos_e(mixed $v): string{return e((string)$v);}
function qrxos_db_get(string $key,?string $fallback=null): ?string{
    $q=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return $v===false?$fallback:(string)$v;
}
function qrxos_db_put(string $key,string $value,?PDO $pdo=null): void{
    $pdo=$pdo??db();$q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$key,$value]);
}
function qrxos_get(string $key,string $default=''): string{return qrxos_db_get($key,$default)??$default;}
function qrxos_published(string $key,string $default=''): string{return qrxos_get($key,$default);}
function qrxos_save(array $values): void{
    $pdo=db();$pdo->beginTransaction();try{
        foreach($values as $key=>$value){
            $value=(string)$value;
            qrxos_db_put($key,$value,$pdo);
            $check=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
            $check->execute([$key]);
            if((string)$check->fetchColumn()!==$value){
                throw new RuntimeException('Ayar kaydı doğrulanamadı: '.$key);
            }
        }
        // Eski taslak kayıtları artık hiçbir ekranda kullanılmaz.
        $pdo->exec("DELETE FROM settings WHERE setting_key LIKE 'qrx_draft_%'");
        qrxos_db_put('qrx_last_published_at',date('Y-m-d H:i:s'),$pdo);
        qrxos_db_put('qrx_publish_revision',date('YmdHis').'-'.bin2hex(random_bytes(3)),$pdo);
        qrxos_db_put('qr_menu_enabled','1',$pdo);
        $pdo->commit();QrExperience::clearCache();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function qrxos_publish(array $keys): int{return 0;}
function qrxos_reset(array $keys): void{}
function qrxos_hex(string $v,string $fallback): string{return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$fallback;}
function qrxos_choice(string $v,array $allowed,string $fallback): string{return in_array($v,$allowed,true)?$v:$fallback;}
function qrxos_keys(): array{return [
'qrx_theme_preset','qr_menu_enabled','qr_show_search','qr_ui_first_category_open','qr_ui_show_product_images','qr_ui_show_descriptions','qr_ui_single_category','qr_ui_show_category_count','qr_ui_show_category_numbers','qr_ui_show_row_arrow',
'qr_hero_title','qr_hero_subtitle','qr_hero_image','qr_logo_image','qr_hero_height','qr_hero_overlay','qr_show_hero','qr_show_status','qr_show_readonly_note',
'qr_accent_color','qr_ui_background','qr_ui_surface','qr_ui_text','qr_ui_muted','qr_ui_category_style','qr_ui_product_density','qr_font_family','qr_card_radius','qr_card_shadow','qr_spacing_scale',
'qr_detail_variant','qr_detail_image_height','qr_ui_detail_note','qr_show_detail_category','qr_show_detail_badges','qr_show_detail_close_text',
'qr_show_promo_bar','qr_promo_text','qr_show_featured','qr_feature_title','qr_feature_text','qr_show_chef_note','qr_chef_note_title','qr_chef_note_text','qr_show_story','qr_story_title','qr_story_text','qr_show_social','qr_social_title','qr_social_text','qr_price_note',
'qr_section_order','qr_section_categories_enabled'];}

// QR Studio 2.0 compatibility helpers.
if(!function_exists('qrs_get')){function qrs_get(string $key,string $default=''): string{return QrExperience::setting($key,$default);}}
if(!function_exists('qrs_e')){function qrs_e(mixed $value): string{return e((string)$value);}}
if(!function_exists('qrs_bool')){function qrs_bool(string $key): string{return isset($_POST[$key])?'1':'0';}}
if(!function_exists('qrs_hex')){function qrs_hex(string $value,string $fallback): string{return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;}}
if(!function_exists('qrs_choice')){function qrs_choice(string $value,array $allowed,string $fallback): string{return in_array($value,$allowed,true)?$value:$fallback;}}
if(!function_exists('qrs_save')){function qrs_save(array $values): void{qrxos_save($values);}}
