<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/Modules/QRExperience/helpers.php';
qrx_require_admin();
header('Content-Type: application/json; charset=utf-8');
$allowed=['brand_name','tagline','primary_color','secondary_color','background_color','text_color','font_family','radius','shadow','motion','hero_variant','category_variant','product_card_variant'];
foreach($allowed as $key){if(isset($_POST[$key])) qrx_save_setting($key,trim((string)$_POST[$key]),'draft');}
echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);
