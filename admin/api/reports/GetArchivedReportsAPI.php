<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/ArchiveReportsModel.php';

use Admin\Models\ArchiveReportsModel;

header('Content-Type: application/json');

$model =
    new ArchiveReportsModel();

echo json_encode([

    'success' => true,

    'files' =>
        $model->getArchivedFiles()
]);
