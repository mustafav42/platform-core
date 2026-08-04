<?php
declare(strict_types=1);

if (!function_exists('permission_catalog')) {
    function permission_catalog(): array {
        return [
            'dashboard.view'=>'Dashboard görüntüleme',
            'catalog.manage'=>'Kategori ve ürün yönetimi',
            'tables.manage'=>'Salon ve masa yönetimi',
            'staff.manage'=>'Personel yönetimi',
            'reports.view'=>'Satış raporlarını görüntüleme',
            'security.manage'=>'Güvenlik ve işlem kayıtları',
            'maintenance.manage'=>'Bakım ve yedekleme',
            'permissions.manage'=>'Rol ve yetki yönetimi',
            'modules.manage'=>'Modül merkezi yönetimi',
            'pos.access'=>'POS / kasa erişimi',
            'discount.apply'=>'İndirim uygulama',
            'complimentary.apply'=>'İkram uygulama',
            'table.transfer'=>'Masa taşıma',
            'payment.receive'=>'Ödeme alma',
            'cash.close'=>'Kasa kapatma',
        ];
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string {
        return ['owner'=>'İşletme Sahibi','admin'=>'Tam Yetkili Yönetici','manager'=>'Müdür','cashier'=>'Kasiyer','waiter'=>'Garson'][$role] ?? ucfirst($role);
    }
}

if (!function_exists('permission_table_available')) {
    function permission_table_available(): bool {
        static $ok=null;
        if ($ok!==null) return $ok;
        try { $ok=(bool)db()->query("SHOW TABLES LIKE 'role_permissions'")->fetchColumn(); }
        catch(Throwable){ $ok=false; }
        return $ok;
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $permission, ?string $role=null): bool {
        $role=$role ?: (string)($_SESSION['admin_role'] ?? $_SESSION['staff_role'] ?? $_SESSION['cashier_role'] ?? 'guest');
        if (in_array($role,['owner','admin','superadmin'],true)) return true;
        if (!permission_table_available()) return $role==='manager';
        static $cache=[];
        $key=$role.'|'.$permission;
        if(array_key_exists($key,$cache)) return $cache[$key];
        $q=db()->prepare('SELECT is_allowed FROM role_permissions WHERE role_key=? AND permission_key=? LIMIT 1');
        $q->execute([$role,$permission]);
        $value=$q->fetchColumn();
        return $cache[$key]=$value!==false && (bool)$value;
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $permission): void {
        if (has_permission($permission)) return;
        if (function_exists('audit_log')) audit_log('permission_denied','Yetkisiz işlem engellendi.',['permission'=>$permission]);
        http_response_code(403);
        throw new RuntimeException('Bu işlem için yetkiniz bulunmuyor.');
    }
}
