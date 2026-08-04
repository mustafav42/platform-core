<?php
declare(strict_types=1);

/** CherryHouse QR responsive image helper. */
function qr_image_url(string $path, int $width = 640, int $quality = 82): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^(?:https?:)?//~i', $path) || str_starts_with($path, 'data:')) return $path;

    $path = ltrim(str_replace('\\', '/', $path), '/');
    $width = max(64, min(1920, $width));
    $quality = max(45, min(92, $quality));
    $root = dirname(__DIR__, 2);
    $file = realpath($root . '/' . $path);
    $version = ($file && is_file($file)) ? (string) filemtime($file) : '0';

    return 'app/media/image.php?p=' . rawurlencode($path) . '&w=' . $width . '&q=' . $quality . '&v=' . $version;
}

function qr_image_resolve(string $relative): ?string
{
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) return null;
    $relative = ltrim(str_replace('\\', '/', rawurldecode($relative)), '/');
    if ($relative === '' || str_contains($relative, '../') || str_contains($relative, "\0")) return null;
    $file = realpath($root . '/' . $relative);
    if ($file === false || !is_file($file)) return null;
    if (!str_starts_with($file, $root . DIRECTORY_SEPARATOR)) return null;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return null;
    return $file;
}

function qr_image_generate(string $source, int $width, int $quality): array
{
    $root = dirname(__DIR__, 2);
    $cacheDir = $root . '/storage/cache/qr-images';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);

    $width = max(64, min(1920, $width));
    $quality = max(45, min(92, $quality));
    $canWebp = function_exists('imagewebp');
    $extension = $canWebp ? 'webp' : 'jpg';
    $key = hash('sha256', $source . '|' . filemtime($source) . '|' . filesize($source) . '|' . $width . '|' . $quality . '|' . $extension);
    $target = $cacheDir . '/' . $key . '.' . $extension;
    if (is_file($target) && filesize($target) > 0) return [$target, $canWebp ? 'image/webp' : 'image/jpeg'];

    $info = @getimagesize($source);
    if (!$info || empty($info[0]) || empty($info[1])) return [$source, $info['mime'] ?? 'application/octet-stream'];
    [$srcW, $srcH] = [$info[0], $info[1]];
    $dstW = min($width, $srcW);
    $dstH = max(1, (int) round($srcH * ($dstW / $srcW)));

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
        IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
        default => false,
    };
    if (!$src) return [$source, $info['mime'] ?? 'application/octet-stream'];

    $dst = imagecreatetruecolor($dstW, $dstH);
    if (!$dst) { imagedestroy($src); return [$source, $info['mime'] ?? 'application/octet-stream']; }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
    $saved = $canWebp ? @imagewebp($dst, $tmp, $quality) : @imagejpeg($dst, $tmp, $quality);
    imagedestroy($src); imagedestroy($dst);
    if ($saved && is_file($tmp)) { @chmod($tmp, 0664); @rename($tmp, $target); }
    else @unlink($tmp);

    return (is_file($target) && filesize($target) > 0)
        ? [$target, $canWebp ? 'image/webp' : 'image/jpeg']
        : [$source, $info['mime'] ?? 'application/octet-stream'];
}
