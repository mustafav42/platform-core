<?php
declare(strict_types=1);

final class QrThemeRegistry
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            'cherry' => self::theme('CherryHouse','Restoran','Görsel kartlar ve güçlü marka vurgusu','cards.css',['hero','search','categories','featured']),
            'petrov' => self::theme('Petrov Flow','Restoran','Büyük mobil kartlar ve hızlı kategori akışı','cards.css',['hero','search','categories','featured','popular']),
            'haus' => self::theme('Haus Editorial','Fine Dining','Büyük tipografi, geniş boşluklar ve sade liste','editorial.css',['hero','categories','chef_note','story']),
            'haus_gallery' => self::theme('Haus Gallery','Fine Dining','Dönüşümlü büyük görsel ve metin galerisi','gallery.css',['hero','categories','story']),
            'bistro' => self::theme('Bistro Paper','Bistro','Krem kâğıt hissi ve klasik restoran menüsü','paper.css',['hero','search','categories']),
            'cactus' => self::theme('Cactus Social','Cafe','Kampanya bandı, renkli çipler ve enerjik kartlar','social.css',['hero','promo','search','categories','featured','social']),
            'minimal' => self::theme('Minimal','Cafe','Hızlı açılan, temiz ve görselden bağımsız liste','minimal.css',['search','categories']),
            'dark' => self::theme('Dark','Restoran','Koyu arka plan ve premium ürün kartları','dark.css',['hero','search','categories','featured']),
            'black_gold' => self::theme('Black Gold','Steakhouse','Siyah zemin, altın detaylar ve steakhouse karakteri','black-gold.css',['hero','categories','chef_note','featured']),
            'street_food' => self::theme('Street Food','Fast Food','Cesur tipografi, sticker etiketleri ve yoğun kart düzeni','street.css',['hero','promo','search','categories','featured']),
            'zen' => self::theme('Zen','Asian','Sakin boşluklar, doğal tonlar ve dengeli ürün listesi','zen.css',['hero','categories','chef_note']),
            'noir' => self::theme('Luxury Noir','Fine Dining','İnce serif yazılar ve zarif koyu görünüm','noir.css',['hero','categories','chef_note','story']),
        ];
    }

    /** @return array<string,mixed> */
    private static function theme(string $name,string $group,string $description,string $css,array $components): array
    {
        return compact('name','group','description','css','components');
    }

    /** @return array<string,mixed> */
    public static function get(string $key): array
    {
        $all=self::all();
        return $all[$key] ?? $all['cherry'];
    }

    public static function normalize(string $key): string
    {
        return array_key_exists($key,self::all()) ? $key : 'cherry';
    }
}
