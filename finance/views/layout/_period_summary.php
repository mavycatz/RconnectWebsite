<?php

use Finance\Services\DateFilterService;

if (!class_exists(DateFilterService::class)) {
    require_once __DIR__ . '/../../services/DateFilterService.php';
}

if (!isset($financePeriodSummary) || $financePeriodSummary === '') {
    if (isset($financePeriodConfig['summary'])) {
        $financePeriodSummary = (string) $financePeriodConfig['summary'];
    } else {
        $financePeriodSummary = DateFilterService::todaySummary();
    }
}

if (!defined('FINANCE_DATE_FILTER_CSS')) {
    define('FINANCE_DATE_FILTER_CSS', true);
    echo '<link rel="stylesheet" href="/finance/assets/css/financedatefilter.css?v=5">' . "\n";
}
?>
<div class="fin-period-summary" data-fin-summary><?= htmlspecialchars($financePeriodSummary, ENT_QUOTES) ?></div>
