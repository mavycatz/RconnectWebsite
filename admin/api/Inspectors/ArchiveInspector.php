<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Inspectors/ArchiveInspectorModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Inspectors/ArchiveInspector.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Inspectors/ArchiveInspector.php';

use App\Core\Database;

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/RoleManagement/_security_guard.php';

rmg_guard_require(
    'archive',
    'inspector',
    (string)($_POST['inspector_pk'] ?? '')
);

$conn =
    Database::get();

$model =
    new ArchiveInspectorModel(
        $conn
    );

$service =
    new ArchiveInspectorService(
        $model
    );

$controller =
    new ArchiveInspectorController(
        $service
    );

$controller
    ->archiveInspector();