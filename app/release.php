<?php
declare(strict_types=1);
function app_release_info(): array { return ['version'=>'30.4.2','channel'=>'LTS','name'=>'QR Experience Final QA']; }
function app_release_label(): string { $r=app_release_info(); return 'v'.$r['version'].' '.$r['channel'].' — '.$r['name']; }
