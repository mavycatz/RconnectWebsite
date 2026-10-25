<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DEBUG
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');

ini_set('display_startup_errors', '1');

error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| SESSION & AUDIT
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/auth/Session.php';
\App\Auth\Session::start();

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/DownloadReportsController.php';

use Admin\Controllers\DownloadReportsController;
use Finance\Models\AuditTrailModel;

try {

    /*
    |--------------------------------------------------------------------------
    | CONTROLLER
    |--------------------------------------------------------------------------
    */

    $controller =
        new DownloadReportsController();

    /*
    |--------------------------------------------------------------------------
    | GENERATE ZIP
    |--------------------------------------------------------------------------
    */

    $zipFile =
        $controller->download();

    if(!file_exists($zipFile)){

        throw new Exception(
            'ZIP file not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR OUTPUT BUFFER
    |--------------------------------------------------------------------------
    */

    while(ob_get_level()){

        ob_end_clean();
    }

    /*
    |--------------------------------------------------------------------------
    | HEADERS
    |--------------------------------------------------------------------------
    */

    header(
        'Content-Type: application/zip'
    );

    header(
        'Content-Disposition: attachment; filename="'
        .
        basename($zipFile)
        .
        '"'
    );

    header(
        'Content-Length: '
        .
        filesize($zipFile)
    );

    header(
        'Pragma: public'
    );

    header(
        'Cache-Control: must-revalidate'
    );

    header(
        'Expires: 0'
    );

    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
    |--------------------------------------------------------------------------
    */

    $audit = new AuditTrailModel();
    $audit->log('Reports', 'Download', 'Downloaded all reports archive',
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null
    );

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    readfile($zipFile);

    exit;
}

catch(Throwable $e){

    try {
        $audit = new AuditTrailModel();
        $audit->log('Reports', 'Download', 'Failed to download reports: ' . $e->getMessage(),
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            null, null, 'Failed'
        );
    } catch (Throwable $ignore) {}

    die(
        $e->getMessage()
    );
}
