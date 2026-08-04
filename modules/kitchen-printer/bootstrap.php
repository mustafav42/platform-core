<?php
declare(strict_types=1);
require_once __DIR__.'/src/KitchenPrinter.php';
KitchenPrinter::ensureSchema();
EventDispatcher::listen('order.created', static function(array $payload): void {
    $orderId=(int)($payload['order_id']??0);
    if($orderId>0) KitchenPrinter::queueOrder($orderId);
});
