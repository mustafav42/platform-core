<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// CherryHouse Core Loader: sınıf dosyaları eksik require nedeniyle kaybolmasın.
spl_autoload_register(static function (string $class): void {
    // PHP 8.x: preg_replace() hata durumunda null döndürebilir. Sınıfın
    // namespace son parçasını doğrudan alarak str_contains(null) hatasını önle.
    $safe = trim($class, "\\");
    $separator = strrpos($safe, "\\");
    $short = $separator === false ? $safe : substr($safe, $separator + 1);
    foreach (['Core', 'System', 'Brand'] as $directory) {
        $file = BASE_PATH.'/app/'.$directory.'/'.$short.'.php';
        if (is_file($file)) { require_once $file; return; }
    }
});
require_once BASE_PATH.'/app/release.php';
require_once BASE_PATH.'/app/System/MaintenanceMode.php';
require_once BASE_PATH.'/app/System/SystemHealth.php';
require_once BASE_PATH.'/app/System/PerformanceCache.php';
require_once BASE_PATH.'/app/System/SecurityHeaders.php';
require_once BASE_PATH.'/app/Core/EventDispatcher.php';
require_once BASE_PATH.'/app/Core/ModuleManager.php';
require_once BASE_PATH.'/app/Core/BusinessDayService.php';
require_once BASE_PATH.'/app/Core/TableLifecycleService.php';
require_once BASE_PATH.'/app/ControlCenter/ControlCenterRegistry.php';
require_once BASE_PATH.'/app/Brand/BrandManager.php';

function env_load(string $file): array {
    $out=[];
    if (!is_file($file)) return $out;
    foreach (file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line=trim($line);
        if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$k,$v]=explode('=',$line,2);
        $out[trim($k)]=trim($v);
    }
    return $out;
}
$GLOBALS['APP_ENV_VARS']=env_load(BASE_PATH.'/.env');
function envv(string $key, ?string $default=null): ?string { return $GLOBALS['APP_ENV_VARS'][$key] ?? $default; }
function app_log(Throwable|string $error, array $context=[]): void {
    $msg=$error instanceof Throwable ? ($error::class.': '.$error->getMessage().' @ '.$error->getFile().':'.$error->getLine()) : $error;
    @file_put_contents(BASE_PATH.'/storage/logs/app.log','['.date('c').'] '.$msg.' '.json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
}
function db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    $cfg=BASE_PATH.'/config/database.php';
    if (!is_file($cfg)) throw new RuntimeException('Veritabanı yapılandırması bulunamadı.');
    $c=require $cfg;
    $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'].';charset=utf8mb4',$c['user'],$c['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }

function db_table_exists(PDO $pdo, string $table): bool {
    static $cache=[];
    $key=spl_object_id($pdo).'|'.$table;
    if (array_key_exists($key,$cache)) return $cache[$key];
    try {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]);
        return $cache[$key]=(bool)$q->fetchColumn();
    } catch (Throwable) { return $cache[$key]=false; }
}
function db_column_exists(PDO $pdo, string $table, string $column): bool {
    static $cache=[];
    $key=spl_object_id($pdo).'|'.$table.'|'.$column;
    if (array_key_exists($key,$cache)) return $cache[$key];
    try {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$table,$column]);
        return $cache[$key]=(bool)$q->fetchColumn();
    } catch (Throwable) { return $cache[$key]=false; }
}
function db_index_exists(PDO $pdo, string $table, string $index): bool {
    try {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $q->execute([$table,$index]);
        return (bool)$q->fetchColumn();
    } catch (Throwable) { return false; }
}
function setting(string $key,string $default=''): string {
    static $cache=[];
    if (array_key_exists($key,$cache)) return $cache[$key];
    $q=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
    $q->execute([$key]);
    return $cache[$key]=(string)($q->fetchColumn() ?: $default);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))) throw new RuntimeException('Oturum doğrulaması başarısız.');
}
function redirect(string $url): never { header('Location: '.$url,true,303); exit; }

function client_ip(): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'');
    return substr($ip,0,45);
}
function audit_log(string $event, string $description='', array $context=[]): void {
    try {
        $pdo=db();
        if (!db_table_exists($pdo,'audit_logs')) return;
        $actorType='system'; $actorId=null; $actorName='Sistem';
        if (!empty($_SESSION['admin_id'])) { $actorType='admin'; $actorId=(int)$_SESSION['admin_id']; $actorName=(string)($_SESSION['admin_name']??'Yönetici'); }
        elseif (!empty($_SESSION['staff_id'])) { $actorType='staff'; $actorId=(int)$_SESSION['staff_id']; $actorName=(string)($_SESSION['staff_name']??'Personel'); }
        elseif (!empty($_SESSION['cashier_id'])) { $actorType='staff'; $actorId=(int)$_SESSION['cashier_id']; $actorName=(string)($_SESSION['cashier_name']??'Kasiyer'); }

        $values=[
            'actor_type'=>$actorType,'actor_id'=>$actorId,'actor_name'=>$actorName,
            'event_type'=>substr($event,0,100),'action'=>substr($event,0,120),
            'description'=>substr($description,0,500),'route'=>substr((string)($_SERVER['REQUEST_URI']??''),0,255),
            'ip_address'=>client_ip(),'ip_hash'=>hash('sha256',client_ip()),
            'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),
            'context_json'=>json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        $columns=[];$params=[];$marks=[];
        foreach($values as $column=>$value){ if(db_column_exists($pdo,'audit_logs',$column)){ $columns[]="`$column`";$params[]=$value;$marks[]='?'; } }
        if (!$columns) return;
        $sql='INSERT INTO audit_logs('.implode(',',$columns).',created_at) VALUES('.implode(',',$marks).',NOW())';
        $pdo->prepare($sql)->execute($params);
    } catch (Throwable $e) { app_log($e,['audit_event'=>$event]); }
}

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
        'business_day.view'=>'İş günü görüntüleme',
        'business_day.open'=>'Gün Başı yapma',
        'business_day.close'=>'Gün Sonu yapma',
        'business_day.force_close'=>'Zorunlu Gün Sonu yapma',
        'business_day.archive'=>'İş günü arşivini görüntüleme',
    ];
}
function role_label(string $role): string {
    return ['owner'=>'İşletme Sahibi','admin'=>'Tam Yetkili Yönetici','manager'=>'Müdür','cashier'=>'Kasiyer','waiter'=>'Garson'][$role] ?? ucfirst($role);
}
function permission_table_available(): bool {
    static $ok=null;
    if ($ok!==null) return $ok;
    try { $ok=(bool)db()->query("SHOW TABLES LIKE 'role_permissions'")->fetchColumn(); }
    catch(Throwable){ $ok=false; }
    return $ok;
}
function has_permission(string $permission, ?string $role=null): bool {
    $role=$role ?: (string)($_SESSION['admin_role'] ?? $_SESSION['staff_role'] ?? $_SESSION['cashier_role'] ?? 'guest');
    if (in_array($role,['owner','admin','superadmin'],true)) return true;
    if (!permission_table_available()) return $role==='manager';
    static $cache=[];
    $key=$role.'|'.$permission;
    if(array_key_exists($key,$cache)) return $cache[$key];
    $q=db()->prepare('SELECT is_allowed FROM role_permissions WHERE role_key=? AND permission_key=? LIMIT 1');
    $q->execute([$role,$permission]);
    return $cache[$key]=(bool)$q->fetchColumn();
}
function require_permission(string $permission): void {
    if (has_permission($permission)) return;
    audit_log('permission_denied','Yetkisiz işlem engellendi.',['permission'=>$permission]);
    http_response_code(403);
    throw new RuntimeException('Bu işlem için yetkiniz bulunmuyor.');
}

function security_setting_int(string $key,int $default,int $min,int $max): int {
    try { $v=(int)setting($key,(string)$default); } catch(Throwable) { $v=$default; }
    return max($min,min($max,$v));
}


require_once BASE_PATH.'/app/permissions.php';

ini_set('display_errors', envv('APP_DEBUG','false')==='true' ? '1':'0');
error_reporting(E_ALL);
date_default_timezone_set(envv('APP_TIMEZONE','Europe/Istanbul') ?: 'Europe/Istanbul');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ini_set('session.cookie_secure','1');
if (session_status()!==PHP_SESSION_ACTIVE) { session_name('restaurant_session'); session_start(); }
SecurityHeaders::apply();
MaintenanceMode::enforce();
modules()->bootEnabledModules();
set_exception_handler(function(Throwable $e): void {
    app_log($e);
    http_response_code(500);
    $debug=envv('APP_DEBUG','false')==='true';
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;background:#f4f5f7;padding:40px}.box{max-width:760px;margin:auto;background:white;padding:28px;border-radius:16px}</style><div class="box"><h1>Sistem hatası</h1><p>'.($debug?e((string)$e):'İşlem tamamlanamadı. Ayrıntı storage/logs/app.log dosyasına kaydedildi.').'</p></div>';
});
