<?php
declare(strict_types=1);

/**
 * QR Experience OS v5.0
 * Tek ayar kaynağı: settings tablosu.
 * v29.1.2: Tek canlı ayar kaynağı kullanılır; eski taslak anahtarları okunmaz.
 */
final class QrExperience
{
    private static array $cache=[];

    public static function adminAllowed(): bool
    {
        $role=(string)($_SESSION['admin_role']??$_SESSION['staff_role']??'guest');
        return !empty($_SESSION['admin_id']) || (!empty($_SESSION['staff_id']) && $role==='manager');
    }

    public static function setting(string $key,string $default='',bool $draft=false): string
    {
        // $draft parametresi geriye dönük uyumluluk için korunur; artık yalnızca canlı değer okunur.
        $cacheKey='live:'.$key;
        if(array_key_exists($cacheKey,self::$cache)) return self::$cache[$cacheKey];
        $pdo=db();
        $read=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
        $read->execute([$key]);
        $value=$read->fetchColumn();
        return self::$cache[$cacheKey]=$value===false?$default:(string)$value;
    }

    public static function clearCache(): void { self::$cache=[]; }

    /** @return array<int,array{key:string,label:string,description:string}> */
    public static function sections(): array
    {
        return [
            ['key'=>'hero','label'=>'Hero / Karşılama','description'=>'Logo, kapak görseli ve ana mesaj'],
            ['key'=>'directory','label'=>'Menü Başlığı','description'=>'Kategori listesinin üstündeki karşılama metni'],
            ['key'=>'search','label'=>'Canlı Arama','description'=>'Ürün ve kategori arama alanı'],
            ['key'=>'categories','label'=>'Kategori ve Ürünler','description'=>'Ana menü kataloğu'],
            ['key'=>'promo','label'=>'Kampanya Bandı','description'=>'Kısa duyuru ve kampanya metni'],
            ['key'=>'featured','label'=>'Öne Çıkan Alan','description'=>'Günün seçimi veya özel mesaj'],
            ['key'=>'info','label'=>'Bilgi Kutusu','description'=>'Wi-Fi, çalışma saati veya kısa bilgi'],
            ['key'=>'chef_note','label'=>'Şef Notu','description'=>'Şefin yaklaşımı ve önerisi'],
            ['key'=>'story','label'=>'Restoran Hikâyesi','description'=>'Marka ve mutfak hikâyesi'],
            ['key'=>'social','label'=>'Sosyal Medya','description'=>'Instagram ve sosyal çağrı'],
            ['key'=>'footer','label'=>'Alt Bilgi','description'=>'Fiyat notu ve işletme bilgisi'],
        ];
    }

    /** @return string[] */
    public static function sectionOrder(bool $draft=false): array
    {
        $raw=self::setting('qr_section_order','hero,directory,search,categories,promo,featured,info,chef_note,story,social,footer',$draft);
        $allowed=array_column(self::sections(),'key');
        $out=[];
        foreach(explode(',',$raw) as $key){
            $key=trim($key);
            if(in_array($key,$allowed,true)&&!in_array($key,$out,true)) $out[]=$key;
        }
        foreach($allowed as $key) if(!in_array($key,$out,true)) $out[]=$key;
        return $out;
    }

    public static function enabled(string $key,string $default='1',bool $draft=false): bool
    {
        // Studio bileşen anahtarları doğrudan çalışma zamanının kaynağıdır.
        $studioMap=[
            'hero'=>'qr_show_hero','directory'=>'qr_show_directory_intro','promo'=>'qr_show_promo_bar','featured'=>'qr_show_featured','info'=>'qr_show_info_box',
            'chef_note'=>'qr_show_chef_note','story'=>'qr_show_story','social'=>'qr_show_social',
            'search'=>'qr_show_search','categories'=>'qr_section_categories_enabled','footer'=>'qr_show_footer'
        ];
        $settingKey=$studioMap[$key]??('qr_section_'.$key.'_enabled');
        return self::setting($settingKey,$default,$draft)==='1';
    }

    /** @return array<int,array{id:string,enabled:bool}> */
    public static function layout(bool $draft=false): array
    {
        $defaultsOff=['promo','featured','info','chef_note','story','social'];
        $out=[];
        foreach(self::sectionOrder($draft) as $key){
            $out[]=['id'=>$key,'enabled'=>self::enabled($key,in_array($key,$defaultsOff,true)?'0':'1',$draft)];
        }
        return $out;
    }

    public static function fontStack(string $font): string
    {
        return match($font){
            'manrope'=>'Manrope,Arial,sans-serif','poppins'=>'Poppins,Arial,sans-serif',
            'playfair'=>'Playfair Display,Georgia,serif','cormorant'=>'Cormorant Garamond,Georgia,serif',
            'dm-sans'=>'DM Sans,Arial,sans-serif',default=>'Inter,Arial,sans-serif',
        };
    }
}
