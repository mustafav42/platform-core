<?php
declare(strict_types=1);

final class SystemHealth
{
    public static function inspect(PDO $pdo): array
    {
        $requiredTables = [
            'settings', 'admins', 'staff_users', 'categories', 'products',
            'dining_areas', 'restaurant_tables', 'table_sessions', 'order_items',
            'payments', 'cash_sessions', 'audit_logs', 'role_permissions',
            'backup_history', 'app_migrations',
        ];

        $tables = [];
        foreach ($requiredTables as $table) {
            $tables[$table] = function_exists('db_table_exists')
                ? db_table_exists($pdo, $table)
                : self::tableExists($pdo, $table);
        }

        $requiredColumns = [
            'payments' => ['table_session_id', 'amount', 'method', 'paid_at'],
            'audit_logs' => ['actor_name', 'event_type', 'created_at'],
            'staff_users' => ['role', 'is_active'],
            'table_sessions' => ['status', 'opened_at'],
            'qr_product_badges' => ['product_id', 'label', 'color', 'is_active'],
        ];

        $columns = [];
        foreach ($requiredColumns as $table => $names) {
            foreach ($names as $column) {
                $tableReady = $tables[$table] ?? (
                    function_exists('db_table_exists')
                        ? db_table_exists($pdo, $table)
                        : self::tableExists($pdo, $table)
                );
                $columnReady = function_exists('db_column_exists')
                    ? db_column_exists($pdo, $table, $column)
                    : self::columnExists($pdo, $table, $column);
                $columns[$table.'.'.$column] = $tableReady && $columnReady;
            }
        }

        $paths = [
            'storage' => BASE_PATH.'/storage',
            'logs' => BASE_PATH.'/storage/logs',
            'uploads' => BASE_PATH.'/storage/uploads',
            'backups' => BASE_PATH.'/storage/backups',
            'recovery' => BASE_PATH.'/storage/recovery',
            'cache' => BASE_PATH.'/storage/cache',
        ];

        $folders = [];
        foreach ($paths as $name => $path) {
            $folders[$name] = is_dir($path) && is_writable($path);
        }

        $lastBackupAt = null;
        if ($tables['backup_history'] ?? false) {
            try {
                $lastBackupAt = $pdo->query(
                    "SELECT created_at FROM backup_history "
                    ."WHERE status='completed' ORDER BY id DESC LIMIT 1"
                )->fetchColumn() ?: null;
            } catch (Throwable $e) {
                if (function_exists('app_log')) {
                    app_log($e, ['health_backup_history' => true]);
                }
            }
        }

        $logFile = BASE_PATH.'/storage/logs/app.log';
        $logSize = is_file($logFile) ? (int) filesize($logFile) : 0;
        $diskFree = @disk_free_space(BASE_PATH);

        $healthy = !in_array(false, $tables, true)
            && !in_array(false, $columns, true)
            && !in_array(false, $folders, true)
            && extension_loaded('pdo_mysql');

        return [
            'version' => function_exists('app_release_label')
                ? app_release_label()
                : 'Sürüm bilgisi bulunamadı',
            'php' => PHP_VERSION,
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'json' => extension_loaded('json'),
            'gzip' => function_exists('gzencode'),
            'tables' => $tables,
            'columns' => $columns,
            'folders' => $folders,
            'last_backup_at' => $lastBackupAt,
            'log_size' => $logSize,
            'disk_free' => $diskFree === false ? null : (float) $diskFree,
            'maintenance' => class_exists('MaintenanceMode') && MaintenanceMode::enabled(),
            'healthy' => $healthy,
        ];
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)
            || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return false;
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('app_integrity_report')) {
    function app_integrity_report(PDO $pdo): array
    {
        return SystemHealth::inspect($pdo);
    }
}
