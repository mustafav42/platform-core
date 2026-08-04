<?php
declare(strict_types=1);
require_once __DIR__ . '/QrImage.php';

$source = qr_image_resolve((string)($_GET['p'] ?? ''));
if ($source === null) { http_response_code(404); exit; }
$width = (int)($_GET['w'] ?? 640);
$quality = (int)($_GET['q'] ?? 82);
[$file, $mime] = qr_image_generate($source, $width, $quality);
if (!is_file($file)) { http_response_code(404); exit; }

$etag = '"' . hash('sha256', $file . '|' . filemtime($file) . '|' . filesize($file)) . '"';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
readfile($file);
