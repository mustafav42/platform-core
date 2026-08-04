<?php
declare(strict_types=1);

final class PerformanceCache
{
    private static function directory(): string
    {
        return BASE_PATH.'/storage/cache';
    }

    private static function path(string $key): string
    {
        return self::directory().'/'.hash('sha256', $key).'.cache.php';
    }

    public static function remember(string $key, int $ttl, callable $resolver): mixed
    {
        $ttl=max(1,$ttl);
        $path=self::path($key);
        if (is_file($path)) {
            $payload=@include $path;
            if (is_array($payload) && isset($payload['expires']) && (int)$payload['expires'] >= time()) {
                return $payload['value'] ?? null;
            }
        }
        $value=$resolver();
        self::put($key,$value,$ttl);
        return $value;
    }

    public static function put(string $key, mixed $value, int $ttl=300): bool
    {
        $dir=self::directory();
        if (!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) return false;
        $payload=['expires'=>time()+max(1,$ttl),'value'=>$value];
        $content="<?php\nreturn ".var_export($payload,true).";\n";
        return @file_put_contents(self::path($key),$content,LOCK_EX)!==false;
    }

    public static function forget(string $key): bool
    {
        $path=self::path($key);
        return !is_file($path) || @unlink($path);
    }

    public static function clear(): int
    {
        $count=0;
        foreach (glob(self::directory().'/*.cache.php') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) $count++;
        }
        return $count;
    }

    public static function stats(): array
    {
        $files=glob(self::directory().'/*.cache.php') ?: [];
        $bytes=0;$expired=0;
        foreach($files as $file){
            $bytes+=(int)@filesize($file);
            $payload=@include $file;
            if(is_array($payload) && (int)($payload['expires']??0)<time()) $expired++;
        }
        return ['files'=>count($files),'bytes'=>$bytes,'expired'=>$expired,'writable'=>is_writable(self::directory())];
    }
}
