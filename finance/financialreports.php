<?php

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/financialreports.php';

(new \Finance\Controllers\FinancialReportController())->index();