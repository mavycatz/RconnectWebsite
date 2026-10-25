<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/GetArchivedAdmins.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/GetArchivedAdmins.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/GetArchivedAdmins.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new GetArchivedAdminsModel(
        $conn
    );

$service =
    new GetArchivedAdminsService(
        $model
    );

$controller =
    new GetArchivedAdminsController(
        $service
    );

$controller
    ->getArchivedAdmins();