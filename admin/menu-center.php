<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(empty($_SESSION['admin_id']) && (($_SESSION['staff_role']??'')!=='manager')){header('Location: ./');exit;}
header('Location: enterprise/menu.php',true,302);exit;
