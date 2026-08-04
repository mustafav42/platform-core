<?php
declare(strict_types=1);require_once dirname(__DIR__).'/app/bootstrap.php';if(empty($_SESSION['admin_id'])&&($_SESSION['staff_role']??'')!=='manager'){header('Location: ./');exit;}header('Location: enterprise/permissions.php');exit;
