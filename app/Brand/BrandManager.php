<?php
declare(strict_types=1);

final class BrandManager
{
    public static function defaults(): array
    {
        return [
            'business_name' => 'CherryHouse',
            'brand_short_name' => 'CherryHouse',
            'brand_welcome_text' => 'PIN GİRİNİZ',
            'brand_footer_text' => 'Restaurant Management System',
            'brand_version_text' => 'v27.1',
            'brand_primary_color' => '#7c3aed',
            'brand_secondary_color' => '#e11d2e',
            'brand_surface_color' => '#ffffff',
            'brand_login_background_color' => '#f3f5f8',
            'brand_login_logo' => '',
            'brand_admin_logo' => '',
            'brand_favicon' => '',
            'brand_login_background' => '',
            'brand_login_logo_width' => '520',
            'brand_show_license' => '1',
            'brand_license_label' => 'Lisans Sahibi',
            'brand_license_owner' => 'CHERRY HOUSE',
        ];
    }

    public static function get(string $key): string
    {
        $defaults = self::defaults();
        return setting($key, $defaults[$key] ?? '');
    }

    public static function asset(string $key): string
    {
        $value = trim(self::get($key));
        if ($value === '') return '';
        if (preg_match('~^https?://~i', $value)) return $value;
        return '../'.ltrim($value, '/');
    }

    public static function cssVars(): string
    {
        $primary = self::safeColor(self::get('brand_primary_color'), '#7c3aed');
        $secondary = self::safeColor(self::get('brand_secondary_color'), '#e11d2e');
        $surface = self::safeColor(self::get('brand_surface_color'), '#ffffff');
        $loginBg = self::safeColor(self::get('brand_login_background_color'), '#f3f5f8');
        return '--brand-primary:'.$primary.';--brand-secondary:'.$secondary.';--brand-surface:'.$surface.';--brand-login-bg:'.$loginBg.';';
    }

    public static function safeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }
}
