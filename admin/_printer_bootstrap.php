<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(empty($_SESSION['admin_id'])) redirect('./');
require_permission('printers.manage');
if(!module_enabled('kitchen-printer',true)) throw new RuntimeException('Mutfak Yazıcısı modülü kapalı.');
KitchenPrinter::ensureSchema();
function printer_page_start(string $title,string $active): void { ?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><link rel="stylesheet" href="../modules/kitchen-printer/assets/printer-center.css?v=510"></head><body><div class="shell"><aside class="side no-print"><div class="brand">✦ CherryHouse</div><a href="./">← Yönetim Paneli</a><a class="<?=$active==='printers'?'active':''?>" href="printer-center.php">Yazıcı Merkezi</a><a class="<?=$active==='rules'?'active':''?>" href="printer-rules.php">İletim Kuralları</a><a class="<?=$active==='queue'?'active':''?>" href="print-queue.php">Baskı Kuyruğu</a><a href="print-settings.php">Fiş Ayarları</a><a href="module-center.php">Modül Merkezi</a></aside><main class="main"><?php }
function printer_page_end(): void { ?></main></div></body></html><?php }
