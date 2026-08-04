<?php
declare(strict_types=1);
return [
    'id' => 'kitchen-printer',
    'name' => 'Mutfak Yazıcısı',
    'description' => 'Çoklu yazıcı, istasyon yönlendirme ve güvenli baskı kuyruğu.',
    'version' => '5.1.0',
    'tier' => 'core',
    'default_enabled' => 1,
    'locked' => 0,
    'order' => 50,
    'icon' => '▤',
    'requires' => [],
    'bootstrap' => 'modules/kitchen-printer/bootstrap.php',
];
