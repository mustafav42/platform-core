<?php
declare(strict_types=1);

final class ControlCenterRegistry
{
    private const REPAIR_VERSION = '30.8.2.2';
    /** @return array<int,array<string,mixed>> */
    public static function navigation(): array
    {
        return [
            self::group('general', 'Genel', [
                self::item('dashboard', 'Genel Bakış', './', '⌂', 'dashboard.view', null, false, ['ana sayfa','dashboard','özet']),
            ]),
            self::group('operations', 'Operasyon', [
                self::item('business-day', 'İş Günü', 'compatibility.php?tool=business-day', '◷', 'business_day.view', null, false, ['gün başı','gün sonu']),
                self::item('tables', 'Salon ve Masalar', 'compatibility.php?tool=tables', '▦', 'tables.manage', 'tables', false, ['masa','salon']),
                self::item('staff-panel', 'Garson Paneli', '../../staff/', '♨', 'pos.access', 'waiter', true, ['sipariş','garson']),
                self::item('cashier-panel', 'POS / Kasa', '../../cashier/', '▣', 'pos.access', 'cashier', true, ['ödeme','tahsilat','kasa']),
                self::item('audit', 'İşlem Geçmişi', 'audit.php', '◎', 'security.manage', null, false, ['log','hareketler']),
            ], null, ['tables','waiter','cashier']),
            self::group('menu', 'Menü Yönetimi', [
                self::item('menu', 'Genel Bakış', 'menu.php', '▤', 'catalog.manage', null, false, ['menü','genel bakış','workspace']),
                self::item('products', 'Ürünler', 'products.php', '□', 'catalog.manage', null, false, ['ürün','fiyat']),
                self::item('categories', 'Kategoriler', 'categories.php', '≡', 'catalog.manage', null, false, ['kategori']),
                self::item('variants', 'Varyantlar', 'variants.php', '◇', 'catalog.manage', null, false, ['varyant','seçenek']),
                self::item('media', 'Medya Merkezi', 'media.php', '▧', 'catalog.manage', null, false, ['görsel','fotoğraf','ortam']),
            ]),
            self::group('qr', 'QR Yönetimi', [
                self::item('qrx', 'QR Experience Studio', 'compatibility.php?tool=qrx', '◈', 'catalog.manage', 'qr-menu', false, ['qr','tema','studio']),
                self::item('qr-inspector', 'QR Kalite Kontrolü', 'qr-inspector.php', '✓', 'catalog.manage', 'qr-menu', false, ['qr kalite','inspector']),
                self::item('qr-preview', 'Canlı QR Menü', '../../', '↗', 'dashboard.view', 'qr-menu', true, ['canlı menü','önizleme']),
            ], 'qr-menu'),
            self::group('kitchen', 'Mutfak', [
                self::item('kds', 'KDS / Mutfak', '../../kitchen/', '◫', 'pos.access', 'kds', true, ['mutfak','kds']),
                self::item('printer-center', 'Yazıcı Merkezi', 'compatibility.php?tool=printer-center', '▣', 'maintenance.manage', 'kitchen-printer', false, ['yazıcı']),
                self::item('print-queue', 'Yazdırma Kuyruğu', 'compatibility.php?tool=print-queue', '▤', 'maintenance.manage', 'kitchen-printer', false, ['kuyruk','baskı']),
            ], null, ['kds','kitchen-printer']),
            self::group('finance', 'Finans ve Raporlar', [
                self::item('reports', 'Rapor Merkezi', 'compatibility.php?tool=reports', '▥', 'reports.view', 'reports', false, ['rapor','ciro','satış']),
                self::item('backup', 'Yedekleme Merkezi', 'compatibility.php?tool=backup', '⇩', 'maintenance.manage', null, false, ['yedek']),
            ]),
            self::group('management', 'Yönetim', [
                self::item('staff', 'Personel', 'personnel.php', '♙', 'staff.manage', null, false, ['personel','kullanıcı','garson']),
                self::item('permissions', 'Roller ve Yetkiler', 'permissions.php', '◈', 'permissions.manage', null, false, ['rol','yetki']),
                self::item('modules', 'Modül Merkezi', 'compatibility.php?tool=modules', '◫', 'modules.manage', null, false, ['modül','lisans']),
                self::item('brand', 'Marka Merkezi', 'compatibility.php?tool=brand', '✦', 'maintenance.manage', null, false, ['logo','marka']),
                self::item('system', 'Sistem Merkezi', 'compatibility.php?tool=system', '●', 'maintenance.manage', null, false, ['sistem','sağlık']),
                self::item('maintenance', 'Bakım ve Yedekleme', 'compatibility.php?tool=maintenance', '⚙', 'maintenance.manage', null, false, ['bakım']),
                self::item('security', 'Güvenlik ve Kayıtlar', 'compatibility.php?tool=security', '◆', 'security.manage', null, false, ['güvenlik','kayıt']),
                self::item('inventory', 'Özellik Envanteri', 'feature-inventory.php', '☷', 'modules.manage', null, false, ['envanter','özellik']),
                self::item('design-system', 'Tasarım Sistemi', 'design-system.php', '✦', 'dashboard.view', null, false, ['ui kit','tasarım']),
            ]),
        ];
    }

    /** @return array<string,mixed> */
    private static function group(string $id, string $label, array $items, ?string $module=null, array $moduleAny=[]): array
    {
        return ['id'=>$id,'group'=>$label,'items'=>$items,'module'=>$module,'module_any'=>$moduleAny];
    }

    /** @return array<string,mixed> */
    private static function item(string $id, string $label, string $href, string $icon, ?string $permission=null, ?string $module=null, bool $external=false, array $keywords=[]): array
    {
        $permission = trim((string)$permission);
        if ($permission === '') {
            $permission = 'dashboard.view';
        }
        $search = mb_strtolower(implode(' ', array_merge([$label], $keywords)), 'UTF-8');
        return compact('id','label','href','icon','permission','module','external','keywords') + ['search'=>$search];
    }

    /** @return array<int,array<string,mixed>> */
    public static function visibleNavigation(): array
    {
        $groups=[];
        foreach(self::navigation() as $group){
            if(!empty($group['module']) && !module_enabled((string)$group['module'])) continue;
            if(!empty($group['module_any'])){
                $ok=false;
                foreach((array)$group['module_any'] as $m){ if(module_enabled((string)$m)){ $ok=true; break; } }
                if(!$ok) continue;
            }
            $items=[];
            foreach((array)$group['items'] as $item){
                if(!empty($item['module']) && !module_enabled((string)$item['module'])) continue;
                if(!has_permission((string)$item['permission'])) continue;
                $items[]=$item;
            }
            if($items){$group['items']=$items;$groups[]=$group;}
        }
        return $groups;
    }

    /** @return array<int,array<string,mixed>> */
    public static function quickActions(): array
    {
        $actions=[
            self::item('quick-product','Yeni Ürün','products.php?action=create','＋','catalog.manage',null,false,['ürün ekle']),
            self::item('quick-category','Yeni Kategori','categories.php?action=create','＋','catalog.manage',null,false,['kategori ekle']),
            self::item('quick-media','Medya Yükle','media.php?action=upload','↑','catalog.manage',null,false,['görsel yükle']),
            self::item('quick-staff','Personel Ekle','personnel.php?action=create','＋','staff.manage',null,false,['personel ekle']),
            self::item('quick-day','İş Günü','compatibility.php?tool=business-day','◷','business_day.view',null,false,['gün başı gün sonu']),
            self::item('quick-backup','Yedek Al','compatibility.php?tool=backup','⇩','maintenance.manage',null,false,['yedek al']),
            self::item('quick-modules','Modüller','compatibility.php?tool=modules','◫','modules.manage',null,false,['modül merkezi']),
        ];
        return array_values(array_filter($actions, static fn(array $a):bool => has_permission((string)$a['permission']) && (empty($a['module']) || module_enabled((string)$a['module']))));
    }

    /** @return array<string,mixed>|null */
    public static function currentItem(string $currentPage): ?array
    {
        foreach(self::visibleNavigation() as $group){
            foreach($group['items'] as $item){
                if($item['id']===$currentPage) return $item + ['group_label'=>$group['group'],'group_id'=>$group['id']];
            }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function searchableEntries(): array
    {
        $entries=[];
        foreach(self::visibleNavigation() as $group){
            foreach($group['items'] as $item){$entries[]=$item+['kind'=>'Sayfa','group_label'=>$group['group']];}
        }
        foreach(self::quickActions() as $item){$entries[]=$item+['kind'=>'Hızlı İşlem','group_label'=>'İşlemler'];}
        return $entries;
    }

    /** @return array<string,int> */
    public static function moduleSummary(): array
    {
        $all=modules()->all();$active=0;$optional=0;
        foreach($all as $m){if(!empty($m['enabled']))$active++;if(($m['tier']??'core')!=='core')$optional++;}
        return ['total'=>count($all),'active'=>$active,'optional'=>$optional];
    }

    /** @return array<int,array<string,mixed>> */
    public static function featureInventory(): array
    {
        $features=[];
        foreach(modules()->all() as $id=>$m){
            $features[]=['type'=>'module','id'=>$id,'name'=>$m['name']??$id,'group'=>'Modül','status'=>!empty($m['enabled'])?'Etkin':'Kapalı','tier'=>$m['tier']??'core','version'=>$m['version']??'1.0.0','source'=>'modules/'.$id.'/module.php'];
        }
        $pages=[
            ['QR Experience Studio','QR Menü','admin/qr-experience/','qr-menu'],['Hero Builder','QR Menü','admin/qr-experience/partials/hero.php','qr-menu'],['Tema Sistemi','QR Menü','app/assets/qr-themes/','qr-menu'],['QR Inspector','QR Menü','admin/enterprise/qr-inspector.php','qr-menu'],
            ['Menü Merkezi','Menü','admin/enterprise/menu.php',null],['Ürün Yönetimi','Menü','admin/enterprise/products.php',null],['Kategori Yönetimi','Menü','admin/enterprise/categories.php',null],['Varyant Sistemi','Menü','admin/enterprise/variants.php',null],
            ['Medya Merkezi 2.0','Medya','admin/enterprise/media.php',null],['Image Studio','Medya','admin/enterprise/image-studio.php',null],['Business Day Engine','Operasyon','app/Core/BusinessDayService.php',null],['Table Lifecycle Engine','POS','app/Core/TableLifecycleService.php','tables'],
            ['Garson Workspace','POS','staff/index.php','waiter'],['Payment Workspace','POS','cashier/index.php','cashier'],['KDS','Mutfak','kitchen/index.php','kds'],['Mutfak Yazıcısı','Mutfak','modules/kitchen-printer/','kitchen-printer'],
            ['Sistem Sağlığı','Sistem','admin/system-center.php',null],['Yedekleme Merkezi','Sistem','admin/backup.php',null],['Modül Merkezi','Sistem','admin/module-center.php',null],['Rol ve Yetkiler','Sistem','admin/permissions.php',null],
        ];
        foreach($pages as [$name,$group,$source,$module]){$features[]=['type'=>'feature','id'=>strtolower(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$name)?:$name)),'name'=>$name,'group'=>$group,'status'=>$module && !module_enabled($module)?'Modül kapalı':'Hazır','tier'=>'feature','version'=>'','source'=>$source];}
        return $features;
    }
}
