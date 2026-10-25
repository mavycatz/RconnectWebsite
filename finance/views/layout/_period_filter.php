<?php

use Finance\Services\DateFilterService;

if (!class_exists(DateFilterService::class)) {
    require_once __DIR__ . '/../../services/DateFilterService.php';
}

if (!isset($financePeriodConfig) || !is_array($financePeriodConfig)) {
    $financePeriodResolved = DateFilterService::normalizeRequest($_GET);
    $financePeriodConfig = DateFilterService::config(
        $financePeriodResolved['period'],
        $financePeriodResolved['from'],
        $financePeriodResolved['to']
    );
}

$financePeriodSubmitForm = !empty($financePeriodSubmitForm);
$financePeriodHideSummary = !empty($financePeriodHideSummary);

$financePeriodIcons = [
    DateFilterService::TODAY => 'bi-calendar-day',
    DateFilterService::MONTHLY => 'bi-calendar-month',
    DateFilterService::QUARTERLY => 'bi-calendar-range',
    DateFilterService::SEMI_ANNUALLY => 'bi-calendar2-range',
    DateFilterService::ANNUALLY => 'bi-calendar-check',
    DateFilterService::CUSTOM => 'bi-sliders',
];

if (!defined('FINANCE_DATE_FILTER_CSS')) {
    define('FINANCE_DATE_FILTER_CSS', true);
    echo '<link rel="stylesheet" href="/finance/assets/css/financedatefilter.css?v=5">' . "\n";
}
if (!defined('FINANCE_DATE_FILTER_JS')) {
    define('FINANCE_DATE_FILTER_JS', true);
    echo '<script src="/finance/assets/js/financedatefilter.js?v=5" defer></script>' . "\n";
}
?>
<div class="fin-period-filter" data-fin-date-filter<?= $financePeriodSubmitForm ? ' data-fin-submit-form="1"' : '' ?>>
    <?php if (!$financePeriodHideSummary): ?>
        <div class="fin-period-summary" data-fin-summary><?= htmlspecialchars(
            (string) ($financePeriodConfig['summary'] ?? ''),
            ENT_QUOTES
        ) ?></div>
    <?php endif; ?>

    <div class="fin-period-row">
        <div class="fin-period-bar" role="group" aria-label="Date period">
            <?php foreach ($financePeriodConfig['options'] as $financePeriodOption): ?>
                <?php
                $financePeriodIsActive = $financePeriodOption['value'] === $financePeriodConfig['selected'];
                $financePeriodIcon = $financePeriodIcons[$financePeriodOption['value']] ?? 'bi-calendar3';
                ?>
                <button type="button"
                        class="fin-period-btn<?= $financePeriodIsActive ? ' active' : '' ?>"
                        data-period="<?= htmlspecialchars($financePeriodOption['value'], ENT_QUOTES) ?>"
                        aria-pressed="<?= $financePeriodIsActive ? 'true' : 'false' ?>">
                    <i class="bi <?= htmlspecialchars($financePeriodIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($financePeriodOption['label'], ENT_QUOTES) ?></span>
                </button>
            <?php endforeach; ?>
            <?php foreach (($financePeriodActions ?? []) as $financePeriodAction): ?>
                <button type="button" class="fin-period-btn"
                        id="<?= htmlspecialchars($financePeriodAction['id'], ENT_QUOTES) ?>"
                        title="<?= htmlspecialchars($financePeriodAction['label'], ENT_QUOTES) ?>"
                        aria-label="<?= htmlspecialchars($financePeriodAction['label'], ENT_QUOTES) ?>">
                    <i class="bi <?= htmlspecialchars($financePeriodAction['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="fin-custom-range" data-fin-custom<?= $financePeriodConfig['selected'] === DateFilterService::CUSTOM ? '' : ' hidden' ?>>
            <input type="date" class="fin-date-input" data-fin-from
                   value="<?= htmlspecialchars((string) ($financePeriodConfig['from'] ?? ''), ENT_QUOTES) ?>"
                   title="Start date" aria-label="Custom range start date">
            <span class="fin-custom-sep">&ndash;</span>
            <input type="date" class="fin-date-input" data-fin-to
                   value="<?= htmlspecialchars((string) ($financePeriodConfig['to'] ?? ''), ENT_QUOTES) ?>"
                   title="End date" aria-label="Custom range end date">
            <button type="button" class="fin-custom-apply" data-fin-apply>Apply</button>
        </div>

        <?php if ($financePeriodSubmitForm): ?>
            <input type="hidden" name="period" data-fin-input-period
                   value="<?= htmlspecialchars($financePeriodConfig['selected'], ENT_QUOTES) ?>">
            <input type="hidden" name="from" data-fin-input-from
                   value="<?= htmlspecialchars((string) ($financePeriodConfig['from'] ?? ''), ENT_QUOTES) ?>">
            <input type="hidden" name="to" data-fin-input-to
                   value="<?= htmlspecialchars((string) ($financePeriodConfig['to'] ?? ''), ENT_QUOTES) ?>">
        <?php endif; ?>
    </div>

    <script type="application/json" data-fin-config><?= json_encode(
        $financePeriodConfig,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>
</div>
