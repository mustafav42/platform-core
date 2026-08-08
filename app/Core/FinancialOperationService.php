<?php
declare(strict_types=1);

final class FinancialOperationService
{
    public function __construct(private PDO $pdo)
    {
        $this->install();
    }

    public function install(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS financial_operations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            table_session_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NULL,
            operation_type VARCHAR(40) NOT NULL,
            calculation_type VARCHAR(20) NULL,
            input_value DECIMAL(12,4) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            quantity DECIMAL(8,2) NULL,
            reason VARCHAR(190) NULL,
            note VARCHAR(255) NULL,
            actor_type VARCHAR(30) NOT NULL DEFAULT 'cashier',
            actor_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(190) NOT NULL DEFAULT '',
            business_day_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            KEY idx_finop_session(table_session_id,created_at),
            KEY idx_finop_item(order_item_id),
            KEY idx_finop_type(operation_type,created_at),
            KEY idx_finop_day(business_day_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureColumn('table_sessions', 'discount_type',
            "ALTER TABLE table_sessions ADD COLUMN discount_type VARCHAR(20) NULL AFTER discount_amount");
        $this->ensureColumn('table_sessions', 'discount_value',
            "ALTER TABLE table_sessions ADD COLUMN discount_value DECIMAL(12,4) NULL AFTER discount_type");
    }

    public function applyDiscount(
        int $sessionId,
        string $type,
        float $value,
        string $reason = '',
        string $note = ''
    ): array {
        require_permission('discount.apply');

        $type = in_array($type, ['percent','amount'], true) ? $type : 'amount';
        $value = max(0, $value);
        if ($type === 'percent' && $value > 100) {
            throw new RuntimeException('Yüzdelik iskonto %100 üzerinde olamaz.');
        }

        $subtotal = $this->activeSubtotal($sessionId);
        $amount = $type === 'percent'
            ? round($subtotal * ($value / 100), 2)
            : round($value, 2);

        if ($amount > $subtotal + .009) {
            throw new RuntimeException('İskonto, ücretli ürünler toplamından yüksek olamaz.');
        }

        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare(
                "UPDATE table_sessions
                 SET discount_amount=?, discount_type=?, discount_value=?, discount_note=?
                 WHERE id=? AND status='open'"
            );
            $q->execute([$amount, $type, $value, $note, $sessionId]);
            if ($q->rowCount() < 1) {
                throw new RuntimeException('Açık adisyon bulunamadı.');
            }

            $this->log(
                $sessionId, null, 'discount', $type, $value, $amount, null, $reason, $note
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        audit_log('discount_applied', 'Adisyona iskonto uygulandı.', [
            'session_id'=>$sessionId,
            'discount_type'=>$type,
            'input_value'=>$value,
            'amount'=>$amount,
            'reason'=>$reason,
        ]);

        return compact('subtotal','amount','type','value');
    }

    /**
     * Complimentary selected OPEN quantities.
     * Partial complimentary safely splits an order_item row so paid/remaining quantities
     * and reports stay mathematically correct.
     *
     * @param array<int,float> $selection item_id => quantity
     */
    public function complimentarySelected(
        int $sessionId,
        array $selection,
        string $reason = '',
        string $note = ''
    ): array {
        require_permission('complimentary.apply');
        if (!$selection) throw new RuntimeException('İkram için en az bir ürün seçin.');

        $paid = $this->paidQuantities($sessionId);
        $gifted = [];
        $totalAmount = 0.0;

        $this->pdo->beginTransaction();
        try {
            foreach ($selection as $itemId => $requestedQty) {
                $itemId = (int)$itemId;
                $requestedQty = round(max(0, (float)$requestedQty), 2);
                if ($itemId < 1 || $requestedQty <= 0) continue;

                $q = $this->pdo->prepare(
                    "SELECT oi.*, o.session_id
                     FROM order_items oi
                     JOIN orders o ON o.id=oi.order_id
                     WHERE oi.id=? AND o.session_id=? AND oi.status='active'
                     FOR UPDATE"
                );
                // Avoid alias issue across schemas by using explicit select fallback below.
                try {
                    $q->execute([$itemId,$sessionId]);
                    $item = $q->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable) {
                    $q = $this->pdo->prepare(
                        "SELECT oi.*, o.session_id
                         FROM order_items oi
                         JOIN orders o ON o.id=oi.order_id
                         WHERE oi.id=? AND o.session_id=? AND oi.status='active'
                         FOR UPDATE"
                    );
                    $q->execute([$itemId,$sessionId]);
                    $item = $q->fetch(PDO::FETCH_ASSOC);
                }
                if (!$item) continue;

                $quantity = (float)$item['quantity'];
                $paidQty = (float)($paid[$itemId] ?? 0);
                $openQty = max(0, $quantity - $paidQty);
                $giftQty = min($requestedQty, $openQty);
                if ($giftQty <= .009) continue;

                $unitPrice = (float)$item['unit_price'];
                $giftAmount = round($unitPrice * $giftQty, 2);

                // If nothing from the row has been paid and the full row is gifted,
                // status can be changed in-place. Otherwise split the row.
                if ($paidQty <= .009 && abs($giftQty - $quantity) <= .009) {
                    $u = $this->pdo->prepare("UPDATE order_items SET status='complimentary' WHERE id=?");
                    $u->execute([$itemId]);
                    $giftItemId = $itemId;
                } else {
                    $remainingQty = round($quantity - $giftQty, 2);
                    if ($remainingQty <= .009) {
                        // This can only occur with a paid part on the original row.
                        // Keep paid quantity on original row; move only open part to the gift clone.
                        $remainingQty = $paidQty;
                    }
                    $u = $this->pdo->prepare("UPDATE order_items SET quantity=? WHERE id=?");
                    $u->execute([$remainingQty,$itemId]);

                    $columns = $this->columns('order_items');
                    $insertCols = ['order_id','product_id','product_name','unit_price','quantity','item_note','status'];
                    $insertVals = [
                        (int)$item['order_id'],
                        (int)$item['product_id'],
                        (string)$item['product_name'],
                        $unitPrice,
                        $giftQty,
                        (string)($item['item_note'] ?? ''),
                        'complimentary',
                    ];
                    if (in_array('created_at',$columns,true)) {
                        $insertCols[]='created_at';
                    }
                    $sql = "INSERT INTO order_items(".implode(',',$insertCols).") VALUES(".
                        implode(',', array_fill(0, count($insertVals), '?')).
                        (in_array('created_at',$columns,true) ? ',NOW()' : '') . ")";
                    $this->pdo->prepare($sql)->execute($insertVals);
                    $giftItemId = (int)$this->pdo->lastInsertId();
                }

                $this->log(
                    $sessionId, $giftItemId, 'complimentary', 'quantity',
                    $giftQty, $giftAmount, $giftQty, $reason, $note
                );
                $gifted[] = ['item_id'=>$giftItemId,'quantity'=>$giftQty,'amount'=>$giftAmount];
                $totalAmount += $giftAmount;
            }

            if (!$gifted) throw new RuntimeException('İkram yapılabilecek seçili ürün bulunamadı.');
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        audit_log('complimentary_applied', 'Seçili ürünler ikram olarak işaretlendi.', [
            'session_id'=>$sessionId,
            'item_count'=>count($gifted),
            'amount'=>round($totalAmount,2),
            'reason'=>$reason,
        ]);

        return ['items'=>$gifted,'amount'=>round($totalAmount,2)];
    }

    public function sessionOperations(int $sessionId): array
    {
        $q=$this->pdo->prepare(
            "SELECT * FROM financial_operations WHERE table_session_id=? ORDER BY id"
        );
        $q->execute([$sessionId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function activeSubtotal(int $sessionId): float
    {
        $q=$this->pdo->prepare(
            "SELECT COALESCE(SUM(oi.unit_price*oi.quantity),0)
             FROM order_items oi JOIN orders o ON o.id=oi.order_id
             WHERE o.session_id=? AND oi.status='active'"
        );
        $q->execute([$sessionId]);
        return (float)$q->fetchColumn();
    }

    private function paidQuantities(int $sessionId): array
    {
        try {
            $q=$this->pdo->prepare(
                "SELECT a.order_item_id,COALESCE(SUM(a.quantity),0) qty
                 FROM cashier_payment_allocations a
                 JOIN cashier_payment_groups g ON g.id=a.payment_group_id
                 WHERE g.table_session_id=? GROUP BY a.order_item_id"
            );
            $q->execute([$sessionId]);
            $out=[];
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['order_item_id']] = (float)$r['qty'];
            }
            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    private function log(
        int $sessionId, ?int $itemId, string $operationType,
        ?string $calculationType, ?float $inputValue, float $amount,
        ?float $quantity, string $reason, string $note
    ): void {
        $actorId=(int)($_SESSION['cashier_id'] ?? $_SESSION['admin_id'] ?? 0);
        $actorType=!empty($_SESSION['cashier_id'])?'cashier':'admin';
        $actorName=(string)($_SESSION['cashier_name'] ?? $_SESSION['admin_name'] ?? 'Sistem');
        $businessDayId=null;
        try {
            $day=business_day_service()->currentOpenDay();
            $businessDayId=(int)($day['id']??0) ?: null;
        } catch (Throwable) {}

        $q=$this->pdo->prepare(
            "INSERT INTO financial_operations
             (table_session_id,order_item_id,operation_type,calculation_type,input_value,amount,quantity,
              reason,note,actor_type,actor_id,actor_name,business_day_id,created_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
        );
        $q->execute([
            $sessionId,$itemId,$operationType,$calculationType,$inputValue,$amount,$quantity,
            $reason,$note,$actorType,$actorId,$actorName,$businessDayId
        ]);
    }

    private function ensureColumn(string $table,string $column,string $alterSql): void
    {
        if (!in_array($column,$this->columns($table),true)) {
            $this->pdo->exec($alterSql);
        }
    }

    private function columns(string $table): array
    {
        try {
            $rows=$this->pdo->query("SHOW COLUMNS FROM `".str_replace('`','``',$table)."`")
                ->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows,'Field');
        } catch (Throwable) {
            return [];
        }
    }
}
