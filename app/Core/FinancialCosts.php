<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Financial cost settings — single source of truth for driver deductions.
 *
 * Reads editable rows from the `financial_cost_settings` table (created and
 * seeded by Finance\Models\FinancialCostSettingsModel). When the table is
 * missing/empty the legacy hardcoded values are used, so every existing
 * formula keeps producing the exact same numbers until an authorized user
 * changes a setting.
 *
 * Supported calculation methods:
 *   fixed_monthly — amount is charged per driver per month (prorated by period months)
 *   fixed_daily   — amount is charged per driver per day  (amount × 30.4375 → monthly)
 *   per_trip      — amount is charged per trip for the period
 *   percent_gross — rate (%) of the period gross income
 *   actual_fuel   — gas only: actual liters consumed × latest fuel price
 */
class FinancialCosts
{
    /** Average days per month used by the existing proration formulas. */
    public const DAYS_PER_MONTH = 30.4375;

    /** Legacy fallback monthly diesel price (₱/L). */
    public const DEFAULT_GAS_PRICE = 65.55;

    /** Fixed per-driver deduction keys (order matters for UI/reporting). */
    public const FIXED_KEYS = ['amortization', 'insurance', 'gps', 'optr'];

    /**
     * Defaults mirror the values previously hardcoded across the codebase
     * (driver-perf-occupancy.php, DashboardController, financial_reports_api,
     * analytics_dashboard): ₱1,797.14 per driver per month in total.
     */
    public const DEFAULTS = [
        'amortization' => [
            'label' => 'Amortization',
            'amount' => 1098.00,
            'rate' => 0.0,
            'method' => 'fixed_monthly',
            'unit' => 'PHP/month',
        ],
        'insurance' => [
            'label' => 'Insurance',
            'amount' => 192.89,
            'rate' => 0.0,
            'method' => 'fixed_monthly',
            'unit' => 'PHP/month',
        ],
        'gps' => [
            'label' => 'GPS',
            'amount' => 6.25,
            'rate' => 0.0,
            'method' => 'fixed_monthly',
            'unit' => 'PHP/month',
        ],
        'optr' => [
            'label' => 'OPTR',
            'amount' => 500.00,
            'rate' => 0.0,
            'method' => 'fixed_monthly',
            'unit' => 'PHP/month',
        ],
        'gas' => [
            'label' => 'Gas (Fuel)',
            'amount' => self::DEFAULT_GAS_PRICE,
            'rate' => 0.0,
            'method' => 'actual_fuel',
            'unit' => 'PHP/liter',
        ],
    ];

    /** @var array<string, array>|null */
    private static ?array $cache = null;

    private static ?float $priceCache = null;

    /**
     * All active settings keyed by cost_key, merged over the defaults.
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $settings = [];
        foreach (self::DEFAULTS as $key => $def) {
            $settings[$key] = self::defaultRow($key);
        }

        try {
            $db = Database::tryGet();
            if ($db === null) {
                return self::$cache = $settings;
            }
            $stmt = $db->query(
                "SELECT cost_key, label, amount, rate, calc_method, unit, effective_date, status, notes, updated_by, updated_at
                 FROM financial_cost_settings
                 WHERE status = 'Active'"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = strtolower(trim((string) ($row['cost_key'] ?? '')));
                if (!isset(self::DEFAULTS[$key])) {
                    continue;
                }
                $default = self::defaultRow($key);
                $method = trim((string) ($row['calc_method'] ?? ''));
                $settings[$key] = array_merge($default, [
                    'amount' => (float) ($row['amount'] ?? $default['amount']),
                    'rate' => (float) ($row['rate'] ?? 0),
                    'method' => $method !== '' ? $method : $default['method'],
                    'unit' => trim((string) ($row['unit'] ?? '')) !== '' ? (string) $row['unit'] : $default['unit'],
                    'effective_date' => (string) ($row['effective_date'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'Active'),
                    'notes' => $row['notes'] ?? null,
                    'updated_by' => $row['updated_by'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null,
                    'label' => trim((string) ($row['label'] ?? '')) !== '' ? (string) $row['label'] : $default['label'],
                ]);
            }
        } catch (\Throwable $e) {
            // Table or database unavailable — keep the legacy defaults so
            // existing financial formulas never break.
        }

        return self::$cache = $settings;
    }

    /**
     * Clear per-request caches (called after a settings save).
     */
    public static function reset(): void
    {
        self::$cache = null;
        self::$priceCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsFor(string $key): array
    {
        $key = strtolower(trim($key));
        $all = self::all();
        return $all[$key] ?? self::defaultRow($key);
    }

    /**
     * Monthly-equivalent per-driver base amounts for the four fixed costs.
     *
     * @return array{amortization: float, insurance: float, gps: float, optr: float}
     */
    public static function monthlyBases(): array
    {
        $settings = self::all();
        $bases = [];
        foreach (self::FIXED_KEYS as $key) {
            $bases[$key] = self::monthlyBase($settings[$key]);
        }
        return $bases;
    }

    /**
     * Itemized per-driver fixed costs scaled to the requested period.
     *
     * Same return shape as the legacy dpm_fixed_costs() helper:
     *   amortization, insurance, gps, optr, total, monthly_total, months.
     *
     * @param float $months Months the costs apply to (1 = one month).
     * @param array{gross?: float, trips?: int|float} $context Period figures used by
     *        percent_gross / per_trip methods (per driver when called per driver).
     */
    public static function fixedCosts(float $months = 1.0, array $context = []): array
    {
        $settings = self::all();
        $m = max(0.0, (float) $months);
        $gross = (float) ($context['gross'] ?? 0.0);
        $trips = (int) ($context['trips'] ?? 0);

        $items = [];
        $monthlyTotal = 0.0;
        $timeBasedOnly = true;

        foreach (self::FIXED_KEYS as $key) {
            $setting = $settings[$key];
            $method = (string) $setting['method'];
            $amount = (float) $setting['amount'];
            $rate = (float) $setting['rate'];
            $monthly = self::monthlyBase($setting);

            switch ($method) {
                case 'fixed_daily':
                    // monthlyBase already converted the daily amount.
                    $monthlyTotal += $monthly;
                    $items[$key] = round($monthly * $m, 2);
                    break;

                case 'percent_gross':
                    // The gross context is already period-scoped, so no month scaling.
                    $timeBasedOnly = false;
                    $items[$key] = $gross > 0 ? round($gross * ($rate / 100.0), 2) : 0.0;
                    break;

                case 'per_trip':
                    $timeBasedOnly = false;
                    $items[$key] = round($amount * $trips, 2);
                    break;

                case 'fixed_monthly':
                default:
                    $monthlyTotal += $monthly;
                    $items[$key] = round($monthly * $m, 2);
                    break;
            }
        }

        $monthlyTotal = round($monthlyTotal, 2);

        /* Time-based only: keep the exact legacy rounding (round(total × months))
           so prorated Net Income values are unchanged. Mixed methods (percent /
           per-trip) use the sum of the itemized amounts. */
        $total = $timeBasedOnly
            ? round($monthlyTotal * $m, 2)
            : round(array_sum($items), 2);

        return [
            'amortization' => $items['amortization'],
            'insurance' => $items['insurance'],
            'gps' => $items['gps'],
            'optr' => $items['optr'],
            'total' => $total,
            'monthly_total' => $monthlyTotal,
            'months' => $m,
        ];
    }

    /**
     * Latest diesel price from `fuel_prices` (same source the Fuel Cost
     * Analytics module uses); falls back to the editable Gas setting and then
     * to the legacy default.
     */
    public static function fuelPrice(): float
    {
        if (self::$priceCache !== null) {
            return self::$priceCache;
        }

        $price = 0.0;
        try {
            $db = Database::tryGet();
            if ($db === null) {
                $price = 0.0;
            } else {
                $stmt = $db->query(
                    "SELECT price_per_liter
                     FROM fuel_prices
                     WHERE fuel_type LIKE '%diesel%'
                     ORDER BY id DESC
                     LIMIT 1"
                );
                $price = (float) ($stmt->fetchColumn() ?: 0.0);
            }
        } catch (\Throwable $e) {
            $price = 0.0;
        }

        if ($price <= 0) {
            $gas = self::all()['gas'];
            $fallback = (float) $gas['amount'];
            $price = $fallback > 0 ? $fallback : self::DEFAULT_GAS_PRICE;
        }

        return self::$priceCache = round($price, 2);
    }

    /**
     * Gas deduction for a set of fuel records:
     * recorded actual cost when available, otherwise actual liters × fuel price.
     */
    public static function gasAmount(float $liters, float $recordedCost = 0.0): float
    {
        if ($recordedCost > 0) {
            return round($recordedCost, 2);
        }
        if ($liters <= 0) {
            return 0.0;
        }
        return round($liters * self::fuelPrice(), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultRow(string $key): array
    {
        $def = self::DEFAULTS[$key] ?? [
            'label' => ucfirst($key),
            'amount' => 0.0,
            'rate' => 0.0,
            'method' => 'fixed_monthly',
            'unit' => 'PHP/month',
        ];

        return [
            'cost_key' => $key,
            'label' => $def['label'],
            'amount' => (float) $def['amount'],
            'rate' => (float) $def['rate'],
            'method' => $def['method'],
            'unit' => $def['unit'],
            'effective_date' => date('Y-m-d'),
            'status' => 'Active',
            'notes' => null,
            'updated_by' => null,
            'updated_at' => null,
            'source' => 'default',
        ];
    }

    /**
     * @param array<string, mixed> $setting
     */
    private static function monthlyBase(array $setting): float
    {
        $amount = (float) $setting['amount'];
        if ((string) $setting['method'] === 'fixed_daily') {
            return round($amount * self::DAYS_PER_MONTH, 2);
        }
        return round($amount, 2);
    }
}
