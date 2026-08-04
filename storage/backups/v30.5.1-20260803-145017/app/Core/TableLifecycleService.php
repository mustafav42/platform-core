<?php
declare(strict_types=1);

final class TableLifecycleService
{
    public function __construct(private PDO $pdo) {}

    public function sessionHasCommittedItems(int $sessionId): bool
    {
        $q=$this->pdo->prepare("SELECT EXISTS(
            SELECT 1 FROM orders o
            JOIN order_items oi ON oi.order_id=o.id
            WHERE o.session_id=? AND oi.status IN ('active','complimentary') AND oi.quantity>0
        )");
        $q->execute([$sessionId]);
        return (bool)$q->fetchColumn();
    }

    public function sessionHasPayments(int $sessionId): bool
    {
        $q=$this->pdo->prepare("SELECT EXISTS(SELECT 1 FROM payments WHERE table_session_id=? AND amount>0)");
        $q->execute([$sessionId]);
        return (bool)$q->fetchColumn();
    }

    public function activateSession(int $sessionId): void
    {
        $q=$this->pdo->prepare("SELECT table_id FROM table_sessions WHERE id=? AND status='open' LIMIT 1");
        $q->execute([$sessionId]);
        $tableId=(int)($q->fetchColumn()?:0);
        if($tableId>0){
            $this->pdo->prepare("UPDATE restaurant_tables SET status='open' WHERE id=?")->execute([$tableId]);
        }
    }

    public function reconcileSession(int $sessionId): bool
    {
        if($sessionId<=0 || $this->sessionHasCommittedItems($sessionId) || $this->sessionHasPayments($sessionId)){
            if($sessionId>0 && $this->sessionHasCommittedItems($sessionId)) $this->activateSession($sessionId);
            return false;
        }
        $q=$this->pdo->prepare("SELECT table_id FROM table_sessions WHERE id=? AND status='open' LIMIT 1");
        $q->execute([$sessionId]);
        $tableId=(int)($q->fetchColumn()?:0);
        if($tableId<=0) return false;
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("UPDATE table_sessions SET status='closed',closed_at=COALESCE(closed_at,NOW()) WHERE id=? AND status='open'")->execute([$sessionId]);
            $this->pdo->prepare("UPDATE restaurant_tables SET status='empty' WHERE id=?")->execute([$tableId]);
            $this->pdo->commit();
            return true;
        }catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    public function cleanupEmptyOpenSessions(?int $staffId=null): int
    {
        $sql="SELECT ts.id FROM table_sessions ts
              WHERE ts.status='open'
                AND NOT EXISTS(SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.session_id=ts.id AND oi.status IN ('active','complimentary') AND oi.quantity>0)
                AND NOT EXISTS(SELECT 1 FROM payments p WHERE p.table_session_id=ts.id AND p.amount>0)";
        $params=[];
        if($staffId!==null){$sql.=" AND ts.opened_by_staff_id=?";$params[]=$staffId;}
        $q=$this->pdo->prepare($sql);$q->execute($params);
        $count=0;
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $sid){if($this->reconcileSession((int)$sid))$count++;}
        return $count;
    }
}
