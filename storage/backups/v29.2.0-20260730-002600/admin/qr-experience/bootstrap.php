<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/qr/ThemeRegistry.php';
require_once dirname(__DIR__,2).'/app/qr/QrExperience.php';
if(!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../../install/');
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')) redirect('../');
if(function_exists('require_permission')){try{require_permission('catalog.manage');}catch(Throwable){redirect('../');}}
function qrs_e(mixed $v): string{return e((string)$v);} 
function qrs_get(string $key,string $default=''): string{return QrExperience::setting($key,$default);} 
function qrs_put(PDO $pdo,string $key,string $value): void{$q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$key,$value]);}
function qrs_hex(string $value,string $fallback): string{return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;}
function qrs_choice(string $value,array $allowed,string $fallback): string{return in_array($value,$allowed,true)?$value:$fallback;}
function qrs_bool(string $key): string{return isset($_POST[$key])?'1':'0';}
function qrs_save(array $values): void{
 $pdo=db();$pdo->beginTransaction();
 try{
  foreach($values as $key=>$value)qrs_put($pdo,(string)$key,(string)$value);
  qrs_put($pdo,'qr_menu_enabled','1');
  qrs_put($pdo,'qrx_last_published_at',date('Y-m-d H:i:s'));
  qrs_put($pdo,'qrx_publish_revision',date('YmdHis').'-'.bin2hex(random_bytes(3)));
  $pdo->prepare("DELETE FROM settings WHERE setting_key LIKE 'qrx_draft_%'")->execute();
  $pdo->commit();QrExperience::clearCache();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
