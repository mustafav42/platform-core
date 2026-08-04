<?php
declare(strict_types=1);

final class MigrationRunner
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureRepository(): void
    {
        // MySQL/MariaDB DDL statements cause an implicit commit. For that reason,
        // schema changes are deliberately executed outside application transactions.
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_key VARCHAR(190) NOT NULL UNIQUE,
                version VARCHAR(30) NOT NULL,
                description VARCHAR(500) NOT NULL DEFAULT '',
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_app_migrations_version (version),
                INDEX idx_app_migrations_executed (executed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function has(string $key): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1 FROM app_migrations WHERE migration_key = ? LIMIT 1'
        );
        $query->execute([$key]);
        return (bool) $query->fetchColumn();
    }

    public function record(string $key, string $version, string $description): void
    {
        $query = $this->pdo->prepare(
            'INSERT IGNORE INTO app_migrations (migration_key, version, description) VALUES (?, ?, ?)'
        );
        $query->execute([$key, $version, $description]);
    }

    public function upsertSetting(string $key, string $value): void
    {
        $query = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $query->execute([$key, $value]);
    }
}
