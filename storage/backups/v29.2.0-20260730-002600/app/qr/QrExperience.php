<?php
declare(strict_types=1);
final class QrExperience
{
 private static array $cache=[];
 public static function adminAllowed(): bool{$role=(string)($_SESSION['admin_role']??$_SESSION['staff_role']??'guest');return !empty($_SESSION['admin_id'])||(!empty($_SESSION['staff_id'])&&$role==='manager');}
 public static function setting(string $key,string $default='',bool $draft=false): string{
  if(array_key_exists($key,self::$cache))return self::$cache[$key];
  $q=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();
  return self::$cache[$key]=$v===false?$default:(string)$v;
 }
 public static function clearCache(): void{self::$cache=[];}
 public static function sections(): array{return [
  ['key'=>'hero','label'=>'Karşılama / Hero','description'=>'Logo, kapak ve ana mesaj'],
  ['key'=>'promo','label'=>'Kampanya Bandı','description'=>'Kısa duyuru ve kampanya metni'],
  ['key'=>'featured','label'=>'Öne Çıkan Alan','description'=>'Günün seçimi veya özel mesaj'],
  ['key'=>'chef_note','label'=>'Şef Notu','description'=>'Şefin yaklaşımı ve önerisi'],
  ['key'=>'story','label'=>'Restoran Hikâyesi','description'=>'Marka ve mutfak hikâyesi'],
  ['key'=>'social','label'=>'Sosyal Medya','description'=>'Instagram ve sosyal çağrı'],
  ['key'=>'search','label'=>'Canlı Arama','description'=>'Ürün ve kategori araması'],
  ['key'=>'categories','label'=>'Menü Kategorileri','description'=>'Kategori navigasyonu ve ürünler'],
 ];}
 public static function sectionOrder(bool $draft=false): array{$raw=self::setting('qr_section_order','hero,promo,featured,chef_note,story,social,search,categories');$allowed=array_column(self::sections(),'key');$out=[];foreach(explode(',',$raw) as $key){$key=trim($key);if(in_array($key,$allowed,true)&&!in_array($key,$out,true))$out[]=$key;}foreach($allowed as $key)if(!in_array($key,$out,true))$out[]=$key;return $out;}
 public static function enabled(string $key,string $default='1',bool $draft=false): bool{$map=['hero'=>'qr_show_hero','promo'=>'qr_show_promo_bar','featured'=>'qr_show_featured','chef_note'=>'qr_show_chef_note','story'=>'qr_show_story','social'=>'qr_show_social','search'=>'qr_show_search','categories'=>'qr_section_categories_enabled'];return self::setting($map[$key]??('qr_section_'.$key.'_enabled'),$default)==='1';}
 public static function layout(bool $draft=false): array{$off=['chef_note','story','social'];$out=[];foreach(self::sectionOrder() as $key)$out[]=['id'=>$key,'enabled'=>self::enabled($key,in_array($key,$off,true)?'0':'1')];return $out;}
 public static function fontStack(string $font): string{return match($font){'manrope'=>'Manrope,Arial,sans-serif','poppins'=>'Poppins,Arial,sans-serif','playfair'=>'Playfair Display,Georgia,serif','cormorant'=>'Cormorant Garamond,Georgia,serif','dm-sans'=>'DM Sans,Arial,sans-serif',default=>'Inter,Arial,sans-serif'};}
}
