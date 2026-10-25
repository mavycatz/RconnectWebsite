<?php

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/exportandaudits.php';

(new \Finance\Controllers\ExportandAuditsController())->index();