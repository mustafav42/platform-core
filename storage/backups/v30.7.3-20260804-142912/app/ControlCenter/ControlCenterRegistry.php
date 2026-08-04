<?php
declare(strict_types=1);

final class ControlCenterRegistry
{
    /** @return array<int,array<string,mixed>> */
    public static function navigation(): array
    {
        return [
            ['group'=>'Genel','items'=>[
                self::item('dashboard','Genel Bakış','./','⌂','dashboard.view'),
            ]],
            ['group'=>'Operasyon','module_any'=>['tables','waiter','cashier'],'items'=>[
                self::item('business-day','İş Günü','../business-day.php','◷','business_day.view'),
                self::item('tables','Salon ve Masalar','../?page=tables','▦','tables.manage','tables'),
                self::item('staff-panel','Garson Paneli','../../staff/','♨','pos.access','waiter',true),
                self::item('cashier-panel','POS / Kasa','../../cashier/','▣','pos.access','cashier',true),
                self::item('audit','İşlem Geçmişi','audit.php','◎','security.manage'),
            ]],
            ['group'=>'Menü Yönetimi','items'=>[
                self::item('menu','Menü Merkezi','menu.php','▤','catalog.manage'),
                self::item('products','Ürünler','products.php','□','catalog.manage'),
                self::item('categories','Kategoriler','categories.php','≡','catalog.manage'),
                self::item('variants','Varyantlar','variants.php','◇','catalog.manage'),
                self::item('media','Medya Merkezi','media.php','▧','catalog.manage'),
            ]],
            ['group'=>'QR Yönetimi','module'=>'qr-menu','items'=>[
                self::item('qrx','QR Experience Studio','../qr-experience/','◈','catalog.manage','qr-menu'),
                self::item('qr-inspector','QR Kalite Kontrolü','qr-inspector.php','✓','catalog.manage','qr-menu'),
                self::item('qr-preview','Canlı QR Menü','../../','↗','dashboard.view','qr-menu',true),
            ]],
            ['group'=>'Mutfak','module_any'=>['kds','kitchen-printer'],'items'=>[
                self::item('kds','KDS / Mutfak','../../kitchen/','◫','pos.access','kds',true),
                self::item('printer-center','Yazıcı Merkezi','../printer-center.php','▣','maintenance.manage','kitchen-printer'),
                self::item('print-queue','Yazdırma Kuyruğu','../print-queue.php','▤','maintenance.manage','kitchen-printer'),
            ]],
            ['group'=>'Finans ve Raporlar','module'=>'reports','items'=>[
                self::item('reports','Rapor Merkezi','../?page=reports','▥','reports.view','reports'),
                self::item('backup','Yedekleme Merkezi','../backup.php','⇩','maintenance.manage'),
            ]],
            ['group'=>'Yönetim','items'=>[
                self::item('staff','Personel','../?page=staff','♙','staff.manage'),
                self::item('permissions','Roller ve Yetkiler','../permissions.php','◈','permissions.manage'),
                self::item('modules','Modül Merkezi','../module-center.php','◫','modules.manage'),
                self::item('brand','Marka Merkezi','../brand-center.php','✦','maintenance.manage'),
                self::item('system','Sistem Merkezi','../system-center.php','●','maintenance.manage'),
                self::item('maintenance','Bakım ve Yedekleme','../?page=maintenance','⚙','maintenance.manage'),
                self::item('security','Güvenlik ve Kayıtlar','../?page=security','◆','security.manage'),
                self::item('inventory','Özellik Envanteri','feature-inventory.php','☷','modules.manage'),
                self::item('design-system','Tasarım Sistemi','design-system.php','✦','dashboard.view'),
            ]],
        ];
    }

    /** @return array<string,mixed> */
    private static function item(string $id,string $label,string $href,string $icon,string $permission,?string $module=null,bool $external=false): array
    {
        return compact('id','label','href','icon','permission','module','external') + ['keywords'=>mb_strtolower($label,'UTF-8')];
    }

    /** @return array<int,array<string,mixed>> */
    public static function visibleNavigation(): array
    {
        $groups=[];
        foreach(self::navigation() as $group){
            if(isset($group['module']) && !module_enabled((string)$group['module'])) continue;
            if(isset($group['module_any'])){
                $ok=false; foreach((array)$group['module_any'] as $m){ if(module_enabled((string)$m)){ $ok=true; break; } }
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
    public static function featureInventory(): array
    {
        $features=[];
        foreach(modules()->all() as $id=>$m){
            $features[]=[
                'type'=>'module','id'=>$id,'name'=>$m['name']??$id,'group'=>'Modül',
                'status'=>!empty($m['enabled'])?'Etkin':'Kapalı','tier'=>$m['tier']??'core',
                'version'=>$m['version']??'1.0.0','source'=>'modules/'.$id.'/module.php'
            ];
        }
        $pages=[
            ['QR Experience Studio','QR Menü','admin/qr-experience/','qr-menu'],['Hero Builder','QR Menü','admin/qr-experience/partials/hero.php','qr-menu'],
            ['Tema Sistemi','QR Menü','app/assets/qr-themes/','qr-menu'],['QR Inspector','QR Menü','admin/enterprise/qr-inspector.php','qr-menu'],
            ['Menü Merkezi','Menü','admin/enterprise/menu.php',null],['Ürün Yönetimi','Menü','admin/enterprise/products.php',null],
            ['Kategori Yönetimi','Menü','admin/enterprise/categories.php',null],['Varyant Sistemi','Menü','admin/enterprise/variants.php',null],
            ['Medya Merkezi 2.0','Medya','admin/enterprise/media.php',null],['Image Studio','Medya','admin/enterprise/image-studio.php',null],
            ['Business Day Engine','Operasyon','app/Core/BusinessDayService.php',null],['Table Lifecycle Engine','POS','app/Core/TableLifecycleService.php','tables'],
            ['Garson Workspace','POS','staff/index.php','waiter'],['Payment Workspace','POS','cashier/index.php','cashier'],
            ['KDS','Mutfak','kitchen/index.php','kds'],['Mutfak Yazıcısı','Mutfak','modules/kitchen-printer/','kitchen-printer'],
            ['Sistem Sağlığı','Sistem','admin/system-center.php',null],['Yedekleme Merkezi','Sistem','admin/backup.php',null],
            ['Modül Merkezi','Sistem','admin/module-center.php',null],['Rol ve Yetkiler','Sistem','admin/permissions.php',null],
        ];
        foreach($pages as [$name,$group,$source,$module]){
            $features[]=['type'=>'feature','id'=>strtolower(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$name)?:$name)),'name'=>$name,'group'=>$group,'status'=>$module && !module_enabled($module)?'Modül kapalı':'Hazır','tier'=>'feature','version'=>'','source'=>$source];
        }
        return $features;
    }
}
