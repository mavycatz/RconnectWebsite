<?php

declare(strict_types=1);

require_once __DIR__ . '/context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/DashboardModel.php';

use App\Core\Database;
use Admin\Models\DashboardModel;

try {
    $filters = dashboard_request_filters();
    $range = trim((string)($_GET['range'] ?? ''));

    $today = new DateTimeImmutable('today');

    if ($range === '') {
        if (!empty($filters['fromDate']) && !empty($filters['toDate'])) {
            $range = 'custom';
        } elseif (!empty($filters['month']) && !empty($filters['year'])) {
            $range = 'month';
        } elseif (!empty($filters['year'])) {
            $range = 'year';
        } else {
            $range = 'today';
        }
    }

    $granularity = 'hour';
    $currentStart = $today;
    $currentEnd = $today->modify('+1 day');
    $previousStart = $today->modify('-1 day');
    $previousEnd = $today;

    if ($range === 'week') {
        $granularity = 'day';
        $currentStart = $today->modify('-6 days');
        $currentEnd = $today->modify('+1 day');
        $previousStart = $currentStart->modify('-7 days');
        $previousEnd = $currentEnd->modify('-7 days');
    } elseif ($range === 'month') {
        $granularity = 'day';
        $currentStart = $today->modify('first day of this month');
        $currentEnd = $currentStart->modify('+1 month');
        $previousStart = $currentStart->modify('-1 month');
        $previousEnd = $currentStart;
    } elseif ($range === 'year') {
        $granularity = 'month';
        $currentStart = $today->modify('first day of January');
        $currentEnd = $currentStart->modify('+1 year');
        $previousStart = $currentStart->modify('-1 year');
        $previousEnd = $currentStart;
    } elseif ($range === 'custom') {
        $from = !empty($filters['fromDate'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $filters['fromDate'])
            : false;
        $to = !empty($filters['toDate'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $filters['toDate'])
            : false;

        if ($from === false || $to === false) {
            $from = $today;
            $to = $today;
        }

        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        if ($to < $from) {
            $swap = $from;
            $from = $to;
            $to = $swap;
        }

        $currentStart = $from;
        $currentEnd = $to->modify('+1 day');

        $days = (int)$from->diff($to)->days + 1;

        if ($days <= 1) {
            $granularity = 'hour';
            $previousStart = $from->modify('-1 day');
            $previousEnd = $from;
        } elseif ($days <= 62) {
            $granularity = 'day';
            $previousStart = $from->modify("-{$days} days");
            $previousEnd = $to->modify("-{$days} days")->modify('+1 day');
        } else {
            $granularity = 'month';
            $months = ((int)$currentEnd->format('Y') - (int)$currentStart->format('Y')) * 12
                + ((int)$currentEnd->format('n') - (int)$currentStart->format('n'));
            $months = max(1, $months);
            $previousStart = $from->modify("-{$months} months");
            $previousEnd = $from;
        }
    }

    $currentKeys = [];
    $labels = [];

    if ($granularity === 'hour') {
        $cursor = $currentStart->setTime(0, 0);
        for ($i = 0; $i < 24; $i++) {
            $currentKeys[] = $cursor->format('Y-m-d H');
            $labels[] = $cursor->format('H:00');
            $cursor = $cursor->modify('+1 hour');
        }
    } elseif ($granularity === 'day') {
        $cursor = $currentStart;
        while ($cursor < $currentEnd) {
            $currentKeys[] = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $cursor = $cursor->modify('+1 day');
        }
    } else {
        $cursor = $currentStart;
        while ($cursor < $currentEnd) {
            $currentKeys[] = $cursor->format('Y-m');
            $labels[] = $cursor->format('M');
            $cursor = $cursor->modify('+1 month');
        }
    }

    $previousKeys = [];

    if ($granularity === 'hour') {
        $cursor = $previousStart->setTime(0, 0);
        for ($i = 0; $i < 24; $i++) {
            $previousKeys[] = $cursor->format('Y-m-d H');
            $cursor = $cursor->modify('+1 hour');
        }
    } elseif ($granularity === 'day') {
        $cursor = $previousStart;
        while ($cursor < $previousEnd) {
            $previousKeys[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
    } else {
        $cursor = $previousStart;
        while ($cursor < $previousEnd) {
            $previousKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }
    }

    $model = new DashboardModel(Database::get());

    $currentCounts = $model->tripCountsByBucket(
        $currentStart->format('Y-m-d H:i:s'),
        $currentEnd->format('Y-m-d H:i:s'),
        $granularity,
        $filters
    );

    $previousCounts = $model->tripCountsByBucket(
        $previousStart->format('Y-m-d H:i:s'),
        $previousEnd->format('Y-m-d H:i:s'),
        $granularity,
        $filters
    );

    $buckets = [];
    $currentTotal = 0;
    $previousTotal = 0;

    foreach ($currentKeys as $i => $key) {
        $current = (int)($currentCounts[$key] ?? 0);
        $previousKey = $previousKeys[$i] ?? '';
        $previous = $previousKey !== '' ? (int)($previousCounts[$previousKey] ?? 0) : 0;

        $buckets[] = [
            'label'    => $labels[$i],
            'current'  => $current,
            'previous' => $previous,
        ];

        $currentTotal += $current;
        $previousTotal += $previous;
    }

    dashboard_json([
        'success'     => true,
        'range'       => $range,
        'granularity' => $granularity,
        'buckets'     => $buckets,
        'totals'      => [
            'current'  => $currentTotal,
            'previous' => $previousTotal,
        ],
    ]);
} catch (\Throwable $e) {
    dashboard_json([
        'success' => false,
        'message' => 'Unable to load trip activity.',
    ]);
}
