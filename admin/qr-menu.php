<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/qr/QrExperience.php';
if(!is_file(BASE_PATH.'/storage/installed.lock')) redirect('../install/');if(!QrExperience::adminAllowed()) redirect('./');redirect('qr-experience/appearance.php');
