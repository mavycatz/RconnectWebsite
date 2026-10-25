<?php

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/RevenueController.php';

(new \Finance\Controllers\RevenueController())->index();