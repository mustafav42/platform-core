<?php
declare(strict_types=1);
if (!function_exists('app_release')) {
    function app_release(): array
    {
        return ['version'=>'30.3.0','channel'=>'LTS','name'=>'Aurora — Enterprise Experience'];
    }
}
if (!function_exists('app_release_version')) { function app_release_version(): string { return (string)(app_release()['version']??'0.0.0'); } }
if (!function_exists('app_release_label')) { function app_release_label(): string { $r=app_release(); return trim('v'.($r['version']??'0.0.0').' '.($r['channel']??'').' — '.($r['name']??'')); } }
return app_release();
