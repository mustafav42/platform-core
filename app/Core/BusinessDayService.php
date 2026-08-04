<?php
declare(strict_types=1);

final class BusinessDayService
{
    public function __construct(private PDO $pdo) {}

    public static function install(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS business_days (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_no BIGINT UNSIGNED NOT NULL UNIQUE,
            business_date DATE NOT NULL,
            status ENUM('open','closing','closed','forced_closed') NOT NULL DEFAULT 'open',
            opened_at DATETIME NOT NULL,
            opened_by_type ENUM('admin','staff','system') NOT NULL DEFAULT 'system',
            opened_by_id BIGINT UNSIGNED NULL,
            opened_by_name VARCHAR(150) NOT NULL DEFAULT 'Sistem',
            opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
            opening_note VARCHAR(500) NULL,
            handover_note VARCHAR(1000) NULL,
            closing_started_at DATETIME NULL,
            closed_at DATETIME NULL,
            closed_by_type ENUM('admin','staff','system') NULL,
            closed_by_id BIGINT UNSIGNED NULL,
            closed_by_name VARCHAR(150) NULL,
            expected_cash DECIMAL(12,2) NULL,
            counted_cash DECIMAL(12,2) NULL,
            cash_difference DECIMAL(12,2) NULL,
            closing_note VARCHAR(1000) NULL,
            forced_close TINYINT(1) NOT NULL DEFAULT 0,
            forced_close_reason VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business_days_status(status),
            INDEX idx_business_days_date(business_date),
            INDEX idx_business_days_opened(opened_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS business_day_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_day_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            actor_type ENUM('admin','staff','system') NOT NULL DEFAULT 'system',
            actor_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(150) NOT NULL DEFAULT 'Sistem',
            description VARCHAR(500) NOT NULL DEFAULT '',
            context_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bde_day(business_day_id,created_at),
            INDEX idx_bde_type(event_type,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS business_day_cash_counts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_day_id BIGINT UNSIGNED NOT NULL,
            count_type ENUM('opening','interim','closing','handover') NOT NULL,
            expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            counted_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            difference_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            actor_type ENUM('admin','staff','system') NOT NULL DEFAULT 'system',
            actor_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(150) NOT NULL DEFAULT 'Sistem',
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bdcc_day(business_day_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS business_day_exceptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_day_id BIGINT UNSIGNED NOT NULL,
            exception_type VARCHAR(100) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            reference_type VARCHAR(100) NULL,
            reference_id BIGINT UNSIGNED NULL,
            description VARCHAR(500) NOT NULL,
            resolved_at DATETIME NULL,
            resolved_by_type ENUM('admin','staff','system') NULL,
            resolved_by_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bdex_day(business_day_id,resolved_at),
            INDEX idx_bdex_ref(reference_type,reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach (['table_sessions','orders','payments','cash_sessions','cashier_payment_groups','kitchen_print_jobs','audit_logs'] as $table) {
            if (function_exists('db_table_exists') && !db_table_exists($pdo,$table)) continue;
            if (function_exists('db_column_exists') && db_column_exists($pdo,$table,'business_day_id')) continue;
            try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN business_day_id BIGINT UNSIGNED NULL"); } catch (Throwable) {}
            try { $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_business_day` (business_day_id)"); } catch (Throwable) {}
        }
    }

    public function current(bool $forUpdate=false): ?array
    {
        $sql="SELECT * FROM business_days WHERE status IN ('open','closing') ORDER BY id DESC LIMIT 1".($forUpdate?' FOR UPDATE':'');
        $row=$this->pdo->query($sql)->fetch();
        return $row ?: null;
    }

    public function requireOpen(bool $allowClosing=false): array
    {
        $day=$this->current();
        if (!$day) throw new RuntimeException('İş günü açık değil. Satış işlemi için önce Gün Başı yapılmalıdır.');
        if (!$allowClosing && $day['status']!=='open') throw new RuntimeException('Gün Sonu işlemi devam ediyor. Satış işlemleri geçici olarak kilitlendi.');
        return $day;
    }

    public function open(float $openingCash=0, string $note='', ?string $businessDate=null): array
    {
        $this->pdo->beginTransaction();
        try {
            if ($this->current(true)) throw new RuntimeException('Zaten açık bir iş günü bulunuyor.');
            $next=(int)$this->pdo->query('SELECT COALESCE(MAX(business_no),0)+1 FROM business_days')->fetchColumn();
            [$type,$id,$name]=$this->actor();
            $date=$businessDate && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$businessDate) ? $businessDate : date('Y-m-d');
            $q=$this->pdo->prepare("INSERT INTO business_days(business_no,business_date,status,opened_at,opened_by_type,opened_by_id,opened_by_name,opening_cash,opening_note) VALUES(?,?,'open',NOW(),?,?,?,?,?)");
            $q->execute([$next,$date,$type,$id,$name,max(0,$openingCash),mb_substr(trim($note),0,500)]);
            $dayId=(int)$this->pdo->lastInsertId();
            $this->event($dayId,'business_day_opened','Gün Başı yapıldı.',['opening_cash'=>max(0,$openingCash),'business_date'=>$date]);
            $this->pdo->prepare("INSERT INTO business_day_cash_counts(business_day_id,count_type,expected_amount,counted_amount,difference_amount,actor_type,actor_id,actor_name,note) VALUES(?,'opening',?,?,?,?,?,?,?)")
                ->execute([$dayId,max(0,$openingCash),max(0,$openingCash),0,$type,$id,$name,mb_substr(trim($note),0,500)]);
            $this->pdo->commit();
            audit_log('business_day_opened','Gün Başı yapıldı.',['business_day_id'=>$dayId,'business_no'=>$next]);
            return $this->current() ?? [];
        } catch(Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    public function checks(int $dayId): array
    {
        $checks=[];
        $openTables=(int)$this->pdo->query("SELECT COUNT(*) FROM table_sessions WHERE status='open' AND (business_day_id=".$dayId." OR business_day_id IS NULL)")->fetchColumn();
        $checks[]=['key'=>'open_tables','label'=>'Açık masa / adisyon','count'=>$openTables,'blocking'=>$openTables>0];
        $pending=(int)$this->pdo->query("SELECT COUNT(*) FROM table_sessions ts WHERE ts.status='open' AND (ts.business_day_id=".$dayId." OR ts.business_day_id IS NULL) AND GREATEST(0,(SELECT COALESCE(SUM(CASE WHEN oi.status='active' THEN oi.unit_price*oi.quantity ELSE 0 END),0) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.session_id=ts.id)-ts.discount_amount-(SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.table_session_id=ts.id))>0.009")->fetchColumn();
        $checks[]=['key'=>'remaining_balance','label'=>'Kalan bakiyeli adisyon','count'=>$pending,'blocking'=>$pending>0];
        $cashSessions=db_table_exists($this->pdo,'cash_sessions')?(int)$this->pdo->query("SELECT COUNT(*) FROM cash_sessions WHERE status='open' AND (business_day_id=".$dayId." OR business_day_id IS NULL)")->fetchColumn():0;
        $checks[]=['key'=>'open_cash_sessions','label'=>'Açık kasa oturumu','count'=>$cashSessions,'blocking'=>$cashSessions>0];
        return $checks;
    }

    public function summary(int $dayId): array
    {
        $q=$this->pdo->prepare("SELECT method,COALESCE(SUM(amount),0) total FROM payments WHERE business_day_id=? GROUP BY method");
        $q->execute([$dayId]); $methods=[];$total=0.0;$cash=0.0;
        foreach($q->fetchAll() as $r){$methods[$r['method']]=(float)$r['total'];$total+=(float)$r['total'];if($r['method']==='cash')$cash+=(float)$r['total'];}
        $q=$this->pdo->prepare('SELECT opening_cash FROM business_days WHERE id=?');$q->execute([$dayId]);$opening=(float)$q->fetchColumn();
        return ['total'=>$total,'cash'=>$cash,'opening_cash'=>$opening,'expected_cash'=>$opening+$cash,'methods'=>$methods];
    }

    public function close(float $countedCash, string $note='', bool $force=false, string $forceReason=''): array
    {
        $this->pdo->beginTransaction();
        try {
            $day=$this->current(true); if(!$day)throw new RuntimeException('Açık iş günü bulunamadı.');
            $checks=$this->checks((int)$day['id']);$blocking=array_values(array_filter($checks,fn($c)=>$c['blocking']));
            if($blocking && !$force) throw new RuntimeException('Gün Sonu için tamamlanması gereken işlemler bulunuyor.');
            if($force && trim($forceReason)==='') throw new RuntimeException('Zorunlu kapanış nedeni yazılmalıdır.');
            [$type,$id,$name]=$this->actor();$sum=$this->summary((int)$day['id']);$difference=round($countedCash-$sum['expected_cash'],2);
            $status=$force?'forced_closed':'closed';
            $q=$this->pdo->prepare("UPDATE business_days SET status=?,closing_started_at=COALESCE(closing_started_at,NOW()),closed_at=NOW(),closed_by_type=?,closed_by_id=?,closed_by_name=?,expected_cash=?,counted_cash=?,cash_difference=?,closing_note=?,forced_close=?,forced_close_reason=? WHERE id=?");
            $q->execute([$status,$type,$id,$name,$sum['expected_cash'],$countedCash,$difference,mb_substr(trim($note),0,1000),$force?1:0,$force?mb_substr(trim($forceReason),0,1000):null,(int)$day['id']]);
            $this->pdo->prepare("INSERT INTO business_day_cash_counts(business_day_id,count_type,expected_amount,counted_amount,difference_amount,actor_type,actor_id,actor_name,note) VALUES(?,'closing',?,?,?,?,?,?,?)")
                ->execute([(int)$day['id'],$sum['expected_cash'],$countedCash,$difference,$type,$id,$name,mb_substr(trim($note),0,500)]);
            if($force){foreach($blocking as $c){$this->pdo->prepare("INSERT INTO business_day_exceptions(business_day_id,exception_type,severity,description) VALUES(?,?,'critical',?)")->execute([(int)$day['id'],$c['key'],$c['label'].': '.$c['count']]);}}
            $this->event((int)$day['id'],$force?'business_day_forced_closed':'business_day_closed',$force?'Zorunlu Gün Sonu yapıldı.':'Gün Sonu yapıldı.',['checks'=>$checks,'summary'=>$sum,'difference'=>$difference]);
            $this->pdo->commit();
            audit_log($force?'business_day_forced_closed':'business_day_closed','İş günü kapatıldı.',['business_day_id'=>$day['id'],'business_no'=>$day['business_no'],'difference'=>$difference]);
            return ['day'=>$day,'checks'=>$checks,'summary'=>$sum,'difference'=>$difference];
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function event(int $dayId,string $event,string $description,array $context=[]): void
    {
        [$type,$id,$name]=$this->actor();
        $q=$this->pdo->prepare('INSERT INTO business_day_events(business_day_id,event_type,actor_type,actor_id,actor_name,description,context_json) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$dayId,mb_substr($event,0,100),$type,$id,$name,mb_substr($description,0,500),json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }

    private function actor(): array
    {
        if(!empty($_SESSION['admin_id']))return ['admin',(int)$_SESSION['admin_id'],(string)($_SESSION['admin_name']??'Yönetici')];
        if(!empty($_SESSION['cashier_id']))return ['staff',(int)$_SESSION['cashier_id'],(string)($_SESSION['cashier_name']??'Kasiyer')];
        if(!empty($_SESSION['staff_id']))return ['staff',(int)$_SESSION['staff_id'],(string)($_SESSION['staff_name']??'Personel')];
        return ['system',null,'Sistem'];
    }
}

function business_day_service(): BusinessDayService
{
    static $service=null;
    if(!$service){$service=new BusinessDayService(db());}
    return $service;
}
