<?php
declare(strict_types=1);

final class BackupService
{
    private PDO $pdo;
    private string $backupDir;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->backupDir = BASE_PATH . '/storage/backups';
        $this->ensureStorage();
        $this->install();
    }

    public function install(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS enterprise_backup_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            backup_type VARCHAR(30) NOT NULL DEFAULT 'database',
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'completed',
            created_by BIGINT UNSIGNED NULL,
            created_by_name VARCHAR(190) NOT NULL DEFAULT '',
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_enterprise_backup_file (file_name),
            KEY idx_enterprise_backup_created (created_at),
            KEY idx_enterprise_backup_type (backup_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function backupDirectory(): string
    {
        return $this->backupDir;
    }

    public function supportsFullBackup(): bool
    {
        return class_exists('ZipArchive');
    }

    public function createDatabaseBackup(string $prefix = 'database', string $type = 'database'): array
    {
        $stamp = date('Ymd-His');
        $sqlName = $prefix . '-' . $stamp . '.sql';
        $sqlPath = $this->backupDir . '/' . $sqlName;

        $this->writeDatabaseDump($sqlPath);

        $finalName = $sqlName;
        $finalPath = $sqlPath;

        if (function_exists('gzencode')) {
            $gzName = $sqlName . '.gz';
            $gzPath = $this->backupDir . '/' . $gzName;
            $raw = file_get_contents($sqlPath);
            if ($raw === false) {
                throw new RuntimeException('Yedek dosyası okunamadı.');
            }
            $encoded = gzencode($raw, 9);
            if ($encoded === false || file_put_contents($gzPath, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('GZIP yedeği oluşturulamadı.');
            }
            @unlink($sqlPath);
            $finalName = $gzName;
            $finalPath = $gzPath;
        }

        $size = (int)filesize($finalPath);
        $this->record($type, $finalName, $size, [
            'database' => $this->databaseName(),
            'format' => str_ends_with($finalName, '.gz') ? 'sql.gz' : 'sql',
        ]);
        $this->recordLegacyHistory($finalName, $size);

        if (function_exists('audit_log')) {
            audit_log('database_backup_created', 'Veritabanı yedeği oluşturuldu.', [
                'file' => $finalName,
                'size' => $size,
                'type' => $type,
            ]);
        }

        return [
            'type' => $type,
            'file_name' => $finalName,
            'path' => $finalPath,
            'file_size' => $size,
        ];
    }

    public function createFullBackup(string $prefix = 'full-system'): array
    {
        if (!$this->supportsFullBackup()) {
            throw new RuntimeException('Tam sistem yedeği için sunucuda PHP ZIP uzantısı etkin olmalıdır.');
        }

        $stamp = date('Ymd-His');
        $fileName = $prefix . '-' . $stamp . '.zip';
        $filePath = $this->backupDir . '/' . $fileName;
        $tempSql = $this->backupDir . '/.backup-db-' . bin2hex(random_bytes(6)) . '.sql';

        $this->writeDatabaseDump($tempSql);

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempSql);
            throw new RuntimeException('ZIP yedek dosyası oluşturulamadı.');
        }

        $manifest = [
            'product' => 'CherryHouse Business Operating Platform',
            'backup_version' => 2,
            'backup_type' => 'full-system',
            'created_at' => date(DATE_ATOM),
            'database' => 'database.sql',
            'included_paths' => [
                'uploads/',
                'storage/uploads/',
                'storage/branding/',
                'storage/floor-designer/',
            ],
            'excluded' => [
                'application source code (GitHub repository is source of truth)',
                'config/database.php and secrets',
                'logs, cache and previous backups',
            ],
        ];

        $zip->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFile($tempSql, 'database.sql');

        foreach ([
            'uploads' => BASE_PATH . '/uploads',
            'storage/uploads' => BASE_PATH . '/storage/uploads',
            'storage/branding' => BASE_PATH . '/storage/branding',
            'storage/floor-designer' => BASE_PATH . '/storage/floor-designer',
        ] as $archiveRoot => $localRoot) {
            $this->addDirectoryToZip($zip, $localRoot, 'files/' . $archiveRoot);
        }

        $zip->close();
        @unlink($tempSql);

        if (!is_file($filePath)) {
            throw new RuntimeException('Tam sistem yedeği oluşturulamadı.');
        }

        $size = (int)filesize($filePath);
        $this->record('full-system', $fileName, $size, $manifest);
        $this->recordLegacyHistory($fileName, $size);

        if (function_exists('audit_log')) {
            audit_log('full_backup_created', 'Tam sistem verisi yedeği oluşturuldu.', [
                'file' => $fileName,
                'size' => $size,
            ]);
        }

        return [
            'type' => 'full-system',
            'file_name' => $fileName,
            'path' => $filePath,
            'file_size' => $size,
        ];
    }

    public function importUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Yedek dosyası yüklenemedi. Hata kodu: ' . (int)($file['error'] ?? -1));
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Geçersiz yedek yüklemesi.');
        }

        $original = basename((string)($file['name'] ?? 'backup'));
        $lower = strtolower($original);
        $extension = null;
        foreach (['.sql.gz', '.sql', '.zip'] as $candidate) {
            if (str_ends_with($lower, $candidate)) {
                $extension = $candidate;
                break;
            }
        }
        if ($extension === null) {
            throw new RuntimeException('Yalnızca .sql, .sql.gz veya .zip yedekleri kabul edilir.');
        }

        $name = 'import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . $extension;
        $target = $this->backupDir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Yedek dosyası sunucuya kaydedilemedi.');
        }

        $type = $extension === '.zip' ? 'full-system' : 'database';
        $size = (int)filesize($target);
        $this->record('imported-' . $type, $name, $size, ['original_name' => $original]);

        if (function_exists('audit_log')) {
            audit_log('backup_uploaded', 'Harici yedek sisteme yüklendi.', [
                'file' => $name,
                'original_name' => $original,
            ]);
        }

        return [
            'type' => 'imported-' . $type,
            'file_name' => $name,
            'path' => $target,
            'file_size' => $size,
        ];
    }

    public function listBackups(int $limit = 100): array
    {
        $rows = $this->pdo
            ->query('SELECT * FROM enterprise_backup_records ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)))
            ->fetchAll(PDO::FETCH_ASSOC);

        $byName = [];
        foreach ($rows as $row) {
            $byName[(string)$row['file_name']] = $row;
        }

        // Include older backups that predate Backup & Restore Center v2.
        $legacy = [];
        foreach (glob($this->backupDir . '/*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $name = basename($path);
            if (isset($byName[$name]) || str_starts_with($name, '.')) {
                continue;
            }
            $lower = strtolower($name);
            if (!(str_ends_with($lower, '.sql') || str_ends_with($lower, '.sql.gz') || str_ends_with($lower, '.zip'))) {
                continue;
            }
            $legacy[] = [
                'id' => 0,
                'backup_type' => $this->inferType($name),
                'file_name' => $name,
                'file_size' => (int)filesize($path),
                'status' => 'legacy',
                'created_by' => null,
                'created_by_name' => '',
                'meta_json' => null,
                'created_at' => date('Y-m-d H:i:s', (int)filemtime($path)),
            ];
        }

        $all = array_merge($rows, $legacy);
        usort($all, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return array_slice($all, 0, $limit);
    }

    public function resolveBackupPath(string $fileName): string
    {
        $safe = basename($fileName);
        if ($safe === '' || $safe !== $fileName) {
            throw new RuntimeException('Geçersiz yedek dosyası.');
        }
        $path = $this->backupDir . '/' . $safe;
        if (!is_file($path)) {
            throw new RuntimeException('Yedek dosyası bulunamadı.');
        }
        return $path;
    }

    public function deleteBackup(string $fileName): void
    {
        $path = $this->resolveBackupPath($fileName);
        if (!@unlink($path)) {
            throw new RuntimeException('Yedek dosyası silinemedi.');
        }
        $q = $this->pdo->prepare('DELETE FROM enterprise_backup_records WHERE file_name=?');
        $q->execute([$fileName]);

        if (db_table_exists($this->pdo, 'backup_history')) {
            try {
                $q = $this->pdo->prepare('DELETE FROM backup_history WHERE file_name=?');
                $q->execute([$fileName]);
            } catch (Throwable) {
                // Legacy table differences must not block deletion.
            }
        }

        if (function_exists('audit_log')) {
            audit_log('backup_deleted', 'Yedek dosyası silindi.', ['file' => $fileName]);
        }
    }

    public function verifyCurrentActorPin(string $pin): bool
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';
        if (strlen($pin) !== 4) {
            return false;
        }

        if (!empty($_SESSION['admin_id'])) {
            $columns = $this->tableColumns('admins');
            if (!in_array('pin_hash', $columns, true)) {
                return false;
            }
            $q = $this->pdo->prepare('SELECT pin_hash FROM admins WHERE id=? AND is_active=1 LIMIT 1');
            $q->execute([(int)$_SESSION['admin_id']]);
            $hash = (string)$q->fetchColumn();
            return $hash !== '' && password_verify($pin, $hash);
        }

        if (!empty($_SESSION['staff_id']) && (string)($_SESSION['staff_role'] ?? '') === 'manager') {
            $columns = $this->tableColumns('staff_users');
            if (!in_array('pin_hash', $columns, true)) {
                return false;
            }
            $q = $this->pdo->prepare("SELECT pin_hash FROM staff_users WHERE id=? AND is_active=1 LIMIT 1");
            $q->execute([(int)$_SESSION['staff_id']]);
            $hash = (string)$q->fetchColumn();
            return $hash !== '' && password_verify($pin, $hash);
        }

        return false;
    }

    public function restoreBackup(string $fileName): array
    {
        $path = $this->resolveBackupPath($fileName);

        // Always create an emergency point before restore.
        $emergency = $this->supportsFullBackup()
            ? $this->createFullBackup('restore-before')
            : $this->createDatabaseBackup('restore-before', 'pre-restore');

        $lower = strtolower($fileName);
        if (str_ends_with($lower, '.zip')) {
            $this->restoreFullBackup($path);
            $type = 'full-system';
        } elseif (str_ends_with($lower, '.sql.gz') || str_ends_with($lower, '.sql')) {
            $this->restoreDatabaseFile($path);
            $type = 'database';
        } else {
            throw new RuntimeException('Desteklenmeyen yedek biçimi.');
        }

        // A restore can replace/drop the v2 table if an old DB is loaded.
        $this->install();

        if (function_exists('audit_log')) {
            audit_log('backup_restored', 'Sistem yedekten geri yüklendi.', [
                'file' => $fileName,
                'type' => $type,
                'emergency_backup' => $emergency['file_name'],
            ]);
        }

        return [
            'restored' => $fileName,
            'type' => $type,
            'emergency_backup' => $emergency['file_name'],
        ];
    }

    private function restoreFullBackup(string $zipPath): void
    {
        if (!$this->supportsFullBackup()) {
            throw new RuntimeException('ZIP yedeğini geri yüklemek için PHP ZIP uzantısı gereklidir.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP yedeği açılamadı.');
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $dbSql = $zip->getFromName('database.sql');
        if ($manifestRaw === false || $dbSql === false) {
            $zip->close();
            throw new RuntimeException('Bu ZIP geçerli bir CherryHouse tam sistem yedeği değil.');
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || (int)($manifest['backup_version'] ?? 0) < 2) {
            $zip->close();
            throw new RuntimeException('Yedek manifesti geçersiz veya desteklenmiyor.');
        }

        $this->restoreSqlString($dbSql);

        $allowed = [
            'files/uploads/' => BASE_PATH . '/uploads/',
            'files/storage/uploads/' => BASE_PATH . '/storage/uploads/',
            'files/storage/branding/' => BASE_PATH . '/storage/branding/',
            'files/storage/floor-designer/' => BASE_PATH . '/storage/floor-designer/',
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if ($entry === '' || str_contains($entry, "\0") || str_contains($entry, '../') || str_starts_with($entry, '/')) {
                continue;
            }
            if (str_ends_with($entry, '/')) {
                continue;
            }

            foreach ($allowed as $prefix => $targetRoot) {
                if (!str_starts_with($entry, $prefix)) {
                    continue;
                }
                $relative = substr($entry, strlen($prefix));
                if ($relative === '' || str_contains($relative, '../')) {
                    break;
                }
                $target = $targetRoot . str_replace('\\', '/', $relative);
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                    $zip->close();
                    throw new RuntimeException('Dosya geri yükleme klasörü oluşturulamadı.');
                }
                $contents = $zip->getFromIndex($i);
                if ($contents === false || file_put_contents($target, $contents, LOCK_EX) === false) {
                    $zip->close();
                    throw new RuntimeException('Yedek içindeki dosyalardan biri geri yüklenemedi.');
                }
                break;
            }
        }

        $zip->close();
    }

    private function restoreDatabaseFile(string $path): void
    {
        $lower = strtolower($path);
        if (str_ends_with($lower, '.gz')) {
            if (!function_exists('gzdecode')) {
                throw new RuntimeException('GZIP yedeğini açmak için gzip desteği gereklidir.');
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new RuntimeException('Yedek dosyası okunamadı.');
            }
            $sql = gzdecode($raw);
            if ($sql === false) {
                throw new RuntimeException('GZIP yedeği açılamadı.');
            }
        } else {
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException('Yedek dosyası okunamadı.');
            }
        }
        $this->restoreSqlString($sql);
    }

    private function restoreSqlString(string $sql): void
    {
        $statements = $this->splitSqlStatements($sql);
        if (!$statements) {
            throw new RuntimeException('Yedek dosyasında çalıştırılabilir SQL bulunamadı.');
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }
                $this->pdo->exec($trimmed);
            }
        } finally {
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
        }
    }

    /**
     * Split generated MySQL dumps without breaking on semicolons inside quoted values.
     *
     * @return array<int,string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $out = [];
        $buffer = '';
        $len = strlen($sql);
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $n = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($c === "\n") {
                    $lineComment = false;
                    $buffer .= $c;
                }
                continue;
            }

            if ($blockComment) {
                if ($c === '*' && $n === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $c;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($c === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($c === $quote) {
                    if (($quote === "'" || $quote === '"') && $n === $quote) {
                        $buffer .= $n;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($c === '-' && $n === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($c === '#') {
                $lineComment = true;
                continue;
            }
            if ($c === '/' && $n === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buffer .= $c;
                continue;
            }
            if ($c === ';') {
                if (trim($buffer) !== '') {
                    $out[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $c;
        }

        if (trim($buffer) !== '') {
            $out[] = trim($buffer);
        }

        return $out;
    }

    private function writeDatabaseDump(string $sqlPath): void
    {
        $fh = fopen($sqlPath, 'wb');
        if (!$fh) {
            throw new RuntimeException('Yedek dosyası oluşturulamadı.');
        }

        fwrite($fh, "-- CherryHouse Business Operating Platform database backup\n");
        fwrite($fh, "-- Created: " . date(DATE_ATOM) . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $this->pdo
            ->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
            ->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $row) {
            $table = (string)$row[0];
            $escapedTable = str_replace('`', '``', $table);
            $create = $this->pdo->query('SHOW CREATE TABLE `' . $escapedTable . '`')->fetch(PDO::FETCH_NUM);
            if (!$create || !isset($create[1])) {
                fclose($fh);
                throw new RuntimeException('Tablo şeması okunamadı: ' . $table);
            }

            fwrite($fh, "DROP TABLE IF EXISTS `{$escapedTable}`;\n");
            fwrite($fh, (string)$create[1] . ";\n");

            $q = $this->pdo->query('SELECT * FROM `' . $escapedTable . '`');
            while ($data = $q->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_map(
                    static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`',
                    array_keys($data)
                );
                $vals = [];
                foreach ($data as $v) {
                    $vals[] = $v === null ? 'NULL' : $this->pdo->quote((string)$v);
                }
                fwrite(
                    $fh,
                    'INSERT INTO `' . $escapedTable . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n"
                );
            }
            fwrite($fh, "\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $localRoot, string $archiveRoot): void
    {
        if (!is_dir($localRoot)) {
            return;
        }

        $localRoot = rtrim(str_replace('\\', '/', realpath($localRoot) ?: $localRoot), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $real = str_replace('\\', '/', $file->getRealPath() ?: '');
            if ($real === '' || !str_starts_with($real, $localRoot . '/')) {
                continue;
            }
            $relative = substr($real, strlen($localRoot) + 1);
            $zip->addFile($real, rtrim($archiveRoot, '/') . '/' . $relative);
        }
    }

    private function record(string $type, string $fileName, int $size, array $meta = []): void
    {
        $actorId = !empty($_SESSION['admin_id'])
            ? (int)$_SESSION['admin_id']
            : (!empty($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null);
        $actorName = (string)($_SESSION['admin_name'] ?? $_SESSION['staff_name'] ?? 'Sistem');

        $q = $this->pdo->prepare(
            'INSERT INTO enterprise_backup_records
             (backup_type,file_name,file_size,status,created_by,created_by_name,meta_json,created_at)
             VALUES(?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
                backup_type=VALUES(backup_type),
                file_size=VALUES(file_size),
                status=VALUES(status),
                meta_json=VALUES(meta_json)'
        );
        $q->execute([
            $type,
            $fileName,
            $size,
            'completed',
            $actorId,
            $actorName,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function recordLegacyHistory(string $fileName, int $size): void
    {
        if (!db_table_exists($this->pdo, 'backup_history')) {
            return;
        }
        try {
            $columns = $this->tableColumns('backup_history');
            if (!in_array('file_name', $columns, true)) {
                return;
            }
            $createdBy = !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
            $fields = ['file_name'];
            $values = [$fileName];
            if (in_array('file_size', $columns, true)) {
                $fields[] = 'file_size';
                $values[] = $size;
            }
            if (in_array('status', $columns, true)) {
                $fields[] = 'status';
                $values[] = 'completed';
            }
            if (in_array('created_by', $columns, true)) {
                $fields[] = 'created_by';
                $values[] = $createdBy;
            }
            $placeholders = array_fill(0, count($fields), '?');
            $sql = 'INSERT INTO backup_history(' . implode(',', $fields);
            if (in_array('created_at', $columns, true)) {
                $sql .= ',created_at';
                $placeholders[] = 'NOW()';
            }
            $sql .= ') VALUES(' . implode(',', $placeholders) . ')';
            $this->pdo->prepare($sql)->execute($values);
        } catch (Throwable) {
            // Legacy history must never block a valid backup.
        }
    }

    private function ensureStorage(): void
    {
        if (!is_dir($this->backupDir) && !mkdir($this->backupDir, 0755, true) && !is_dir($this->backupDir)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı.');
        }
        if (!is_writable($this->backupDir)) {
            throw new RuntimeException('Yedek klasörü yazılabilir değil.');
        }
    }

    private function inferType(string $fileName): string
    {
        $lower = strtolower($fileName);
        if (str_ends_with($lower, '.zip')) {
            return 'full-system';
        }
        return 'database';
    }

    private function tableColumns(string $table): array
    {
        try {
            $rows = $this->pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows, 'Field');
        } catch (Throwable) {
            return [];
        }
    }

    private function databaseName(): string
    {
        try {
            return (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable) {
            return '';
        }
    }
}
