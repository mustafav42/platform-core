<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (empty($_SESSION['admin_id']) && (($_SESSION['staff_role'] ?? '') !== 'manager')) {
    redirect('./');
}

require_permission('maintenance.manage');
redirect('./enterprise/backup.php');
