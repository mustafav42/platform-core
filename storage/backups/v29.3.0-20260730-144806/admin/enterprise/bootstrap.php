<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
if (!is_file(BASE_PATH . '/storage/installed.lock')) {
    header('Location: ../../install/');
    exit;
}

$enterpriseIsAdmin = !empty($_SESSION['admin_id']);
$enterpriseIsManager = (string)($_SESSION['staff_role'] ?? '') === 'manager';
if (!$enterpriseIsAdmin && !$enterpriseIsManager) {
    header('Location: ../');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ent_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ent_db(): PDO
{
    if (function_exists('db')) {
        return db();
    }
    if (class_exists('App\\Core\\Database')) {
        return App\Core\Database::connection();
    }
    throw new RuntimeException('Veritabanı bağlantısı bulunamadı.');
}

function ent_table_exists(string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    try {
        $stmt = ent_db()->query("SHOW TABLES LIKE " . ent_db()->quote($table));
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function ent_columns(string $table): array
{
    if (!ent_table_exists($table)) {
        return [];
    }
    try {
        $rows = ent_db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    } catch (Throwable) {
        return [];
    }
}

function ent_count(string $table, string $where = '1=1'): int
{
    if (!ent_table_exists($table)) {
        return 0;
    }
    try {
        return (int)ent_db()->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function ent_verify_csrf(): void
{
    $session = (string)($_SESSION['csrf_token'] ?? '');
    $posted = (string)($_POST['csrf_token'] ?? '');
    if ($session === '' || !hash_equals($session, $posted)) {
        throw new RuntimeException('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function ent_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function ent_setting(string $key, string $default = ''): string
{
    if (function_exists('setting')) {
        return (string)setting($key, $default);
    }
    if (!ent_table_exists('settings')) {
        return $default;
    }
    try {
        $stmt = ent_db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable) {
        return $default;
    }
}

function ent_media_install(): void
{
    ent_db()->exec("CREATE TABLE IF NOT EXISTS enterprise_media (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        relative_path VARCHAR(500) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
        width INT UNSIGNED NULL,
        height INT UNSIGNED NULL,
        alt_text VARCHAR(255) NOT NULL DEFAULT '',
        folder VARCHAR(100) NOT NULL DEFAULT 'Genel',
        created_by BIGINT UNSIGNED NULL,
        is_favorite TINYINT(1) NOT NULL DEFAULT 0,
        tags VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_enterprise_media_path (relative_path),
        KEY idx_enterprise_media_created (created_at),
        KEY idx_enterprise_media_folder (folder)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ent_media_upgrade(): void
{
    ent_media_install();
    $columns = ent_columns('enterprise_media');
    $changes = [
        'is_favorite' => "ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER created_by",
        'tags' => "ADD COLUMN tags VARCHAR(500) NOT NULL DEFAULT '' AFTER is_favorite",
        'updated_at' => "ADD COLUMN updated_at DATETIME NULL AFTER created_at",
    ];
    foreach ($changes as $name => $sql) {
        if (!in_array($name, $columns, true)) ent_db()->exec("ALTER TABLE enterprise_media {$sql}");
    }
}

function ent_media_usage(string $path): array
{
    $pdo = ent_db(); $used = [];
    $checks = [
        ['products','image_path','Ürün'], ['categories','image_path','Kategori'],
    ];
    foreach ($checks as [$table,$column,$label]) {
        if (ent_table_exists($table) && in_array($column, ent_columns($table), true)) {
            $q=$pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=? OR `{$column}`=?");
            $q->execute([$path,'/'.$path]); $count=(int)$q->fetchColumn();
            if ($count>0) $used[]=$label.' ('.$count.')';
        }
    }
    if (ent_table_exists('settings')) {
        $q=$pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_value=? OR setting_value=?');
        $q->execute([$path,'/'.$path]); $count=(int)$q->fetchColumn();
        if($count>0) $used[]='QR Studio / Ayarlar ('.$count.')';
    }
    return $used;
}

function ent_media_delete(int $id, bool $force=false): void
{
    $pdo=ent_db(); $q=$pdo->prepare('SELECT * FROM enterprise_media WHERE id=? LIMIT 1');$q->execute([$id]);
    $row=$q->fetch(PDO::FETCH_ASSOC); if(!$row) throw new RuntimeException('Medya kaydı bulunamadı.');
    $path=(string)$row['relative_path']; $usage=ent_media_usage($path);
    if($usage && !$force) throw new RuntimeException('Bu görsel kullanılıyor: '.implode(', ',$usage).'. Önce ilgili kayıtlardan kaldırın.');
    if(!str_starts_with($path,'storage/uploads/media/')) throw new RuntimeException('Güvenli olmayan dosya yolu.');
    $pdo->prepare('DELETE FROM enterprise_media WHERE id=?')->execute([$id]);
    foreach([$path, preg_replace('~(\.[^.]+)$~','-thumb$1',$path)??''] as $rel){$full=BASE_PATH.'/'.ltrim($rel,'/');if(is_file($full))@unlink($full);}
}

function ent_media_root(): string
{
    return BASE_PATH . '/storage/uploads/media';
}

function ent_media_url(string $relativePath): string
{
    return '../../' . ltrim($relativePath, '/');
}

function ent_media_upload(array $file, string $folder, string $altText = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yüklenemedi. Hata kodu: ' . (int)($file['error'] ?? -1));
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('Her görsel en fazla 8 MB olabilir.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Geçersiz yükleme kaynağı.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Yalnızca JPG, PNG ve WebP görseller kabul edilir.');
    }
    $dimensions = @getimagesize($tmp);
    if ($dimensions === false) {
        throw new RuntimeException('Dosya geçerli bir görsel değil.');
    }
    [$width, $height] = $dimensions;
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000) {
        throw new RuntimeException('Görsel boyutları geçersiz veya çok büyük.');
    }

    $folder = trim(preg_replace('/[^\pL\pN _-]+/u', '', $folder) ?? '');
    $folder = $folder !== '' ? mb_substr($folder, 0, 100, 'UTF-8') : 'Genel';
    $storageDir = ent_media_root();
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Medya klasörü oluşturulamadı.');
    }
    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $destination = $storageDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Görsel sunucuya kaydedilemedi.');
    }

    $relativePath = 'storage/uploads/media/' . $filename;
    $stmt = ent_db()->prepare('INSERT INTO enterprise_media
        (filename, original_name, relative_path, mime_type, file_size, width, height, alt_text, folder, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([
            $filename,
            mb_substr((string)($file['name'] ?? $filename), 0, 255, 'UTF-8'),
            $relativePath,
            $mime,
            $size,
            (int)$width,
            (int)$height,
            mb_substr(trim($altText), 0, 255, 'UTF-8'),
            $folder,
            !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        @unlink($destination);
        throw $e;
    }
    return ['path' => $relativePath, 'name' => $filename];
}

function ent_human_bytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}
