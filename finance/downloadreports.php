<?php
define('APP_LOADED', true);

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/downloadreports.php';

(new \Finance\Controllers\downloadreports())->index();