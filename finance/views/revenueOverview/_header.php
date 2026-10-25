<?php if (!defined('APP_LOADED')) exit; ?>

<?php
if (!class_exists(\Finance\Services\DateFilterService::class)) {
    require_once __DIR__ . '/../../services/DateFilterService.php';
}
$financePeriodResolved = \Finance\Services\DateFilterService::normalizeRequest($_GET, null, \Finance\Services\DateFilterService::TODAY);
$financePeriodConfig = \Finance\Services\DateFilterService::config(
    $financePeriodResolved['period'],
    $financePeriodResolved['from'],
    $financePeriodResolved['to']
);
?>

<div class="ro-header">
    <div class="ro-header-title">
        <h1 class="ro-title">Profit Overview</h1>
        <p class="ro-subtitle">Track income, expenses, and performance across routes and buses</p>
    </div>

    <div class="ro-toolbar" id="roToolbar">
        <?php
        $financePeriodActions = [
            ['id' => 'roRefresh', 'label' => 'Refresh', 'icon' => 'bi-arrow-clockwise'],
            ['id' => 'roClear', 'label' => 'Clear filters', 'icon' => 'bi-x-lg'],
        ];
        require __DIR__ . '/../layout/_period_filter.php';
        unset($financePeriodActions);
        ?>
    </div>
</div>
