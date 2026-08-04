<?php
declare(strict_types=1);

if (!function_exists('app_release')) {
    function app_release(): array
    {
        return [
            'version' => '5.0.4',
            'channel' => 'LTS',
            'name' => 'System Center Redeclare Fix',
        ];
    }
}

if (!function_exists('app_release_version')) {
    function app_release_version(): string
    {
        return (string) (app_release()['version'] ?? '0.0.0');
    }
}

if (!function_exists('app_release_label')) {
    function app_release_label(): string
    {
        $release = app_release();
        return trim(
            'v'.(string) ($release['version'] ?? '0.0.0').' '
            .(string) ($release['channel'] ?? '').' — '
            .(string) ($release['name'] ?? '')
        );
    }
}

return app_release();
