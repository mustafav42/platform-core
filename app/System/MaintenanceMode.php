<?php
declare(strict_types=1);

final class MaintenanceMode
{
    public static function enabled(): bool
    {
        try { return setting('maintenance_mode_enabled','0') === '1'; }
        catch (Throwable) { return false; }
    }

    public static function message(): string
    {
        try { return trim(setting('maintenance_mode_message','Sistem kısa süreli bakım çalışması nedeniyle kapalıdır.')); }
        catch (Throwable) { return 'Sistem kısa süreli bakım çalışması nedeniyle kapalıdır.'; }
    }

    public static function requestIsExempt(): bool
    {
        $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH) ?: '/');
        foreach(['/admin','/update','/install'] as $prefix) {
            if ($path===$prefix || str_starts_with($path,$prefix.'/')) return true;
        }
        return !empty($_SESSION['admin_id']);
    }

    public static function enforce(): void
    {
        if (!is_file(BASE_PATH.'/storage/installed.lock')) return;
        if (!self::enabled() || self::requestIsExempt()) return;
        http_response_code(503);
        header('Retry-After: 1800');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $business='Restoran';
        try { $business=setting('business_name','Restoran'); } catch(Throwable) {}
        $message=self::message();
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bakım Çalışması</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e5e7eb;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px}.box{width:min(620px,100%);padding:38px;border:1px solid #334155;border-radius:24px;background:#111c31;box-shadow:0 30px 80px #0005}.icon{font-size:42px}.muted{color:#9fb0c6;line-height:1.65}.badge{display:inline-block;margin-top:20px;padding:8px 12px;border-radius:999px;background:#1e293b;color:#c4b5fd;font-weight:800}</style></head><body><main class="box"><div class="icon">🛠️</div><h1>'.e($business).' bakımda</h1><p class="muted">'.e($message).'</p><span class="badge">Lütfen kısa süre sonra yeniden deneyin</span></main></body></html>';
        exit;
    }
}
