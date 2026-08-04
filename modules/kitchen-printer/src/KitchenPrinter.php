<?php
declare(strict_types=1);

final class KitchenPrinter
{
    public static function ensureSchema(): void
    {
        static $done=false; if($done) return; $done=true;
        $pdo=db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS kitchen_printers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            station_name VARCHAR(120) NOT NULL DEFAULT 'Ana Mutfak',
            connection_type ENUM('network','browser','windows_share') NOT NULL DEFAULT 'browser',
            host VARCHAR(190) NULL,
            port INT UNSIGNED NOT NULL DEFAULT 9100,
            share_path VARCHAR(255) NULL,
            paper_width TINYINT UNSIGNED NOT NULL DEFAULT 80,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_kp_active (is_active), INDEX idx_kp_default (is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kitchen_print_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_type ENUM('product','category') NOT NULL,
            reference_id INT UNSIGNED NOT NULL,
            printer_id INT UNSIGNED NOT NULL,
            priority INT NOT NULL DEFAULT 100,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_kpr_rule (rule_type,reference_id),
            INDEX idx_kpr_printer (printer_id), INDEX idx_kpr_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kitchen_print_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NULL,
            printer_id INT UNSIGNED NOT NULL,
            job_type ENUM('order','test','reprint') NOT NULL DEFAULT 'order',
            title VARCHAR(190) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            printable_text LONGTEXT NOT NULL,
            status ENUM('pending','printing','printed','failed','cancelled') NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            printed_at DATETIME NULL,
            INDEX idx_kpj_status (status,created_at), INDEX idx_kpj_order (order_id), INDEX idx_kpj_printer (printer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function queueOrder(int $orderId): array
    {
        self::ensureSchema();
        if(!module_enabled('kitchen-printer',true)) return [];
        $pdo=db();
        $q=$pdo->prepare("SELECT o.id,o.created_at,t.name table_name,a.name area_name,s.name staff_name
            FROM orders o JOIN table_sessions ts ON ts.id=o.session_id JOIN restaurant_tables t ON t.id=ts.table_id
            JOIN dining_areas a ON a.id=t.area_id LEFT JOIN staff_users s ON s.id=o.staff_id WHERE o.id=? LIMIT 1");
        $q->execute([$orderId]); $order=$q->fetch(); if(!$order) return [];
        $q=$pdo->prepare("SELECT oi.id,oi.product_id,oi.product_name,oi.quantity,oi.item_note,p.category_id
            FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? AND oi.status='active' ORDER BY oi.id");
        $q->execute([$orderId]); $items=$q->fetchAll(); if(!$items) return [];
        $printers=$pdo->query("SELECT * FROM kitchen_printers WHERE is_active=1 ORDER BY is_default DESC,id")->fetchAll();
        if(!$printers) return [];
        $byId=[]; $default=null; foreach($printers as $p){$byId[(int)$p['id']]=$p;if(!$default||!empty($p['is_default']))$default=$p;}
        $rules=$pdo->query("SELECT * FROM kitchen_print_rules WHERE is_active=1 ORDER BY priority,id")->fetchAll();
        $productRules=[];$categoryRules=[];foreach($rules as $r){if($r['rule_type']==='product')$productRules[(int)$r['reference_id']]=(int)$r['printer_id'];else $categoryRules[(int)$r['reference_id']]=(int)$r['printer_id'];}
        $groups=[];
        foreach($items as $item){
            $pid=(int)$item['product_id'];$cid=(int)($item['category_id']??0);
            $printerId=$productRules[$pid]??$categoryRules[$cid]??(int)$default['id'];
            if(!isset($byId[$printerId]))$printerId=(int)$default['id'];
            $groups[$printerId][]=$item;
        }
        $jobs=[];
        foreach($groups as $printerId=>$rows){
            $printer=$byId[$printerId];
            $payload=['order'=>$order,'printer'=>['id'=>$printerId,'name'=>$printer['name'],'station'=>$printer['station_name']],'items'=>$rows];
            $text=self::renderTicket($payload,(int)$printer['paper_width']);
            $st=$pdo->prepare("INSERT INTO kitchen_print_jobs(order_id,printer_id,job_type,title,payload_json,printable_text,status,created_at) VALUES(?,?,'order',?,?,?,'pending',NOW())");
            $st->execute([$orderId,$printerId,'Mutfak Siparişi #'.$orderId,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$text]);
            $jobId=(int)$pdo->lastInsertId();$jobs[]=$jobId;
            self::attempt($jobId);
        }
        return $jobs;
    }

    public static function createTestJob(int $printerId): int
    {
        self::ensureSchema();$pdo=db();$q=$pdo->prepare('SELECT * FROM kitchen_printers WHERE id=?');$q->execute([$printerId]);$p=$q->fetch();if(!$p)throw new RuntimeException('Yazıcı bulunamadı.');
        $text="CHERRYHOUSE YAZICI TESTI\n".str_repeat('=',32)."\nYazici: {$p['name']}\nIstasyon: {$p['station_name']}\nTarih: ".date('d.m.Y H:i:s')."\n\nTest basariliysa baglanti hazirdir.\n\n\n";
        $st=$pdo->prepare("INSERT INTO kitchen_print_jobs(printer_id,job_type,title,payload_json,printable_text,status,created_at) VALUES(?,'test','Yazıcı Testi','{}',?,'pending',NOW())");$st->execute([$printerId,$text]);$id=(int)$pdo->lastInsertId();self::attempt($id);return $id;
    }

    public static function attempt(int $jobId): bool
    {
        self::ensureSchema();$pdo=db();$q=$pdo->prepare("SELECT j.*,p.connection_type,p.host,p.port,p.share_path,p.is_active FROM kitchen_print_jobs j JOIN kitchen_printers p ON p.id=j.printer_id WHERE j.id=?");$q->execute([$jobId]);$job=$q->fetch();if(!$job)throw new RuntimeException('Baskı işi bulunamadı.');
        if(!$job['is_active']){self::fail($jobId,'Yazıcı pasif.');return false;}
        $pdo->prepare("UPDATE kitchen_print_jobs SET status='printing',attempts=attempts+1,last_error=NULL WHERE id=?")->execute([$jobId]);
        try{
            if($job['connection_type']==='network'){
                $host=trim((string)$job['host']);$port=(int)$job['port'];if($host==='')throw new RuntimeException('Ağ yazıcısı IP/host bilgisi boş.');
                $errno=0;$errstr='';$fp=@fsockopen($host,$port,$errno,$errstr,3);if(!$fp)throw new RuntimeException('Yazıcı bağlantısı kurulamadı: '.$errstr.' ('.$errno.')');
                stream_set_timeout($fp,3);$data="\x1B\x40".$job['printable_text']."\n\n\n\x1D\x56\x00";$written=fwrite($fp,$data);fclose($fp);if($written===false)throw new RuntimeException('Veri yazıcıya gönderilemedi.');
                self::markPrinted($jobId);return true;
            }
            // Browser and Windows share require local bridge/browser confirmation.
            $pdo->prepare("UPDATE kitchen_print_jobs SET status='pending',last_error=? WHERE id=?")->execute([$job['connection_type']==='browser'?'Tarayıcıdan yazdırma bekleniyor.':'Windows yazdırma köprüsü bekleniyor.',$jobId]);
            return false;
        }catch(Throwable $e){self::fail($jobId,$e->getMessage());return false;}
    }
    public static function markPrinted(int $jobId): void {db()->prepare("UPDATE kitchen_print_jobs SET status='printed',printed_at=NOW(),last_error=NULL WHERE id=?")->execute([$jobId]);}
    private static function fail(int $jobId,string $error): void {db()->prepare("UPDATE kitchen_print_jobs SET status='failed',last_error=? WHERE id=?")->execute([mb_substr($error,0,500),$jobId]);app_log('Printer job failed',['job_id'=>$jobId,'error'=>$error]);}
    private static function renderTicket(array $payload,int $width): string
    {
        $cols=$width===58?32:48;$o=$payload['order'];$lines=[];$lines[]=self::center(mb_strtoupper((string)$payload['printer']['station']),$cols);$lines[]=str_repeat('=',$cols);$lines[]='MASA: '.($o['table_name']??'-');$lines[]='SALON: '.($o['area_name']??'-');$lines[]='SIPARIS: #'.($o['id']??'-');$lines[]='GARSON: '.($o['staff_name']??'-');$lines[]='SAAT: '.date('d.m.Y H:i',strtotime((string)$o['created_at']));$lines[]=str_repeat('-',$cols);
        foreach($payload['items'] as $it){$qty=rtrim(rtrim(number_format((float)$it['quantity'],2,'.',''),'0'),'.');$lines[]=$qty.' x '.mb_strtoupper((string)$it['product_name']);if(trim((string)$it['item_note'])!=='')$lines[]='  !! '.mb_strtoupper(trim((string)$it['item_note']));}
        $lines[]=str_repeat('=',$cols);$lines[]='MUTFAK CIKTISI';$lines[]='';$lines[]='';return implode("\n",$lines)."\n";
    }
    private static function center(string $text,int $width): string {$len=mb_strlen($text);return str_repeat(' ',max(0,(int)(($width-$len)/2))).$text;}
}
