<?php
declare(strict_types=1);
namespace Finance\Services;

final class FinancialReportPresentationService
{
    public static function type(string $value): string
    {
        $aliases = ['comprehensive' => 'full', 'revenue_analysis' => 'revenue', 'operating_expenses' => 'expenses', 'fleet_performance' => 'fleet', 'route_performance' => 'routes', 'driver_performance' => 'drivers', 'financial_summary' => 'summary', 'risk_assessment' => 'risk'];
        $value = $aliases[$value] ?? $value;
        return in_array($value, ['full', 'revenue', 'expenses', 'fleet', 'routes', 'drivers', 'summary', 'risk'], true) ? $value : 'full';
    }

    public static function apply(array $snapshot, ?string $type = null): array
    {
        $type = self::type($type ?? $snapshot['report_type'] ?? 'full');
        $days = (new \DateTimeImmutable($snapshot['filters']['from']))->diff(new \DateTimeImmutable($snapshot['filters']['to']))->days + 1;
        $bucket = $days <= 31 ? 'daily' : 'monthly';
        $configs = [
            'full' => ['charts' => ['comparison', 'performance', 'stacked', 'drivers', 'expenses'], 'tables' => [$bucket, 'buses', 'drivers', 'categories', 'payments'], 'kinds' => ['revenue', 'expense', 'settlement'], 'cards' => [0,1,2,3,4,5,6,7], 'profit' => true, 'payments' => true],
            'revenue' => ['charts' => ['revenue', 'buses'], 'tables' => [$bucket, 'payments'], 'kinds' => ['revenue'], 'cards' => [0,5,6,7], 'profit' => false, 'payments' => true],
            'expenses' => ['charts' => ['expenses'], 'tables' => [$bucket, 'categories'], 'kinds' => ['expense'], 'cards' => [1,3,4], 'profit' => false, 'payments' => false],
            'fleet' => ['charts' => ['stacked', 'buses'], 'tables' => ['buses'], 'kinds' => [], 'cards' => [0,1,2], 'profit' => false, 'payments' => false],
            'drivers' => ['charts' => ['drivers'], 'tables' => ['drivers'], 'kinds' => ['settlement'], 'cards' => [0,1,2], 'profit' => false, 'payments' => false],
            'routes' => ['charts' => [], 'tables' => [], 'kinds' => [], 'cards' => [], 'profit' => false, 'payments' => false],
            'summary' => ['charts' => ['comparison'], 'tables' => [], 'kinds' => [], 'cards' => [0,1,2,7], 'profit' => true, 'payments' => false],
            'risk' => ['charts' => ['performance', 'expenses'], 'tables' => [], 'kinds' => [], 'cards' => [0,1,2,3], 'profit' => true, 'payments' => false],
        ];
        $config = $configs[$type];
        $config['metrics'] = $type === 'expenses' ? ['expenses'] : ($type === 'revenue' ? ['revenue'] : ['revenue', 'expenses', 'profit']);
        $config['bucket'] = $bucket;
        $config['records'] = (bool) $config['kinds'];
        $snapshot['report_type'] = $type;
        $snapshot['presentation'] = $config;
        $snapshot['records'] = array_values(array_filter($snapshot['records'], static fn($row) => in_array($row['kind'], $config['kinds'], true)));
        $t = $snapshot['totals'];
        $money = static fn($v) => '₱' . number_format((float) $v, 2);
        if ($type === 'expenses') {
            $snapshot['insights'] = ['Recorded expenses: ' . $money($t['expenses']) . '.', 'Fuel costs: ' . $money($t['fuel']) . '.', 'Non-fuel operating costs: ' . $money($t['operational_costs']) . '.'];
        } elseif ($type === 'revenue') {
            $snapshot['insights'] = ['Paid fare revenue: ' . $money($t['revenue']) . '.', number_format($t['passengers']) . ' passenger fare records across ' . number_format($t['trips']) . ' trips with collections.'];
        } elseif ($type === 'drivers') {
            $snapshot['insights'] = [count($snapshot['breakdowns']['drivers']) . ' driver allocations with recorded financial activity.', 'Remitted cash: ' . $money($t['collections']) . ' (settlement, not additional revenue).'];
        } elseif ($type === 'fleet') {
            $snapshot['insights'] = [count($snapshot['breakdowns']['buses']) . ' bus allocations with recorded financial activity.', 'Rankings use recorded bus attribution; missing assignments remain Unassigned.'];
        } elseif ($type === 'routes') {
            $snapshot['insights'] = ['This saved financial snapshot does not contain a verified route breakdown. Route figures are not inferred from bus or driver performance.'];
        } elseif (in_array($type, ['summary', 'risk'], true)) {
            $snapshot['insights'] = array_slice($snapshot['insights'], 0, 4);
        }
        return $snapshot;
    }
}
