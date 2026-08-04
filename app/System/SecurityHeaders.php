<?php
declare(strict_types=1);

final class SecurityHeaders
{
    public static function apply(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
