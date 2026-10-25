<?php

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/FinancialIntelligenceController.php';

(new \Finance\Controllers\FinancialIntelligenceController())->index();
