<?php
declare(strict_types=1);

final class ModuleManager
{
    private string $modulesPath;
    /** @var array<string,array<string,mixed>>|null */
    private ?array $registry = null;

    public function __construct(?string $modulesPath = null)
    {
        $this->modulesPath = $modulesPath ?: BASE_PATH . '/modules';
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        if ($this->registry !== null) return $this->registry;
        $items = [];
        foreach (glob($this->modulesPath . '/*/module.php') ?: [] as $manifest) {
            $data = require $manifest;
            if (!is_array($data) || empty($data['id'])) continue;
            $id = preg_replace('/[^a-z0-9._-]/i', '', (string)$data['id']);
            if ($id === '') continue;
            $data['id'] = $id;
            $data['enabled'] = $this->isEnabled($id, (bool)($data['default_enabled'] ?? false));
            $data['available'] = $this->dependenciesSatisfied($data);
            $items[$id] = $data;
        }
        uasort($items, fn(array $a, array $b): int => (($a['order'] ?? 100) <=> ($b['order'] ?? 100)) ?: strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
        return $this->registry = $items;
    }

    public function get(string $id): ?array { return $this->all()[$id] ?? null; }

    public function isEnabled(string $id, bool $default = false): bool
    {
        try { return setting('module.' . $id . '.enabled', $default ? '1' : '0') === '1'; }
        catch (Throwable) { return $default; }
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $module = $this->get($id);
        if (!$module) throw new InvalidArgumentException('Modül bulunamadı.');
        if (!empty($module['locked']) && !$enabled) throw new RuntimeException('Çekirdek modül kapatılamaz.');
        if ($enabled && !$this->dependenciesSatisfied($module)) throw new RuntimeException('Modül bağımlılıkları karşılanmıyor.');
        db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute(['module.' . $id . '.enabled', $enabled ? '1' : '0']);
        $this->registry = null;
        if (class_exists('EventDispatcher')) EventDispatcher::dispatch($enabled ? 'module.enabled' : 'module.disabled', ['module' => $id]);
        if (function_exists('audit_log')) audit_log('module_state_changed', 'Modül durumu değiştirildi.', ['module' => $id, 'enabled' => $enabled]);
    }

    public function bootEnabledModules(): void
    {
        foreach ($this->all() as $module) {
            if (empty($module['enabled']) || empty($module['available'])) continue;
            $bootstrap = $module['bootstrap'] ?? null;
            if (is_string($bootstrap) && is_file(BASE_PATH . '/' . ltrim($bootstrap, '/'))) require_once BASE_PATH . '/' . ltrim($bootstrap, '/');
        }
    }

    private function dependenciesSatisfied(array $module): bool
    {
        foreach ((array)($module['requires'] ?? []) as $required) {
            if (!$this->isEnabled((string)$required, false)) return false;
        }
        return true;
    }
}

function modules(): ModuleManager
{
    static $manager;
    return $manager ??= new ModuleManager();
}

function module_enabled(string $id, bool $default = false): bool
{
    return modules()->isEnabled($id, $default);
}
