<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/GetAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/GetAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/GetAdmin.php';

use App\Core\Database;

header('Content-Type: application/json');

$conn = Database::get();

$model =
    new AdminModel($conn);

$service =
    new AdminService($model);

$controller =
    new AdminController($service);

$controller->getAdmin();