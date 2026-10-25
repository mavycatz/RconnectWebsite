<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

echo 'PHP WORKING <br>';

require_once $_SERVER['DOCUMENT_ROOT']
. '/vendor/autoload.php';

echo 'AUTOLOAD OK <br>';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/DownloadReportsModel.php';

echo 'MODEL OK <br>';

use Dompdf\Dompdf;

echo 'DOMPDF OK <br>';

$dompdf = new Dompdf();

echo 'INSTANCE OK <br>';