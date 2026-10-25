<?php

declare(strict_types=1);

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/FinancialCosts.php';

use App\Core\Database;
use App\Core\FinancialCosts;

/**
 * Persistence for the Financial Cost Settings module.
 *
 * The table is created and seeded lazily on first use (same pattern as
 * FinancialReportModel / AuditTrailModel) so the feature works on the
 * existing shared-hosting database without a manual migration step.
 * The SQL migration file finance/database/migrations/002_financial_cost_settings.sql
 * mirrors this schema for manual deployments.
 */
class FinancialCostSettingsModel
{
    private static bool $ensured = false;

    /**
     * @var array<int, array{cost_key: string, label: string, amount: float, rate: float, method: string, unit: string, notes: ?string}>
     */
    private const SEED_ROWS = [
        ['cost_key' => 'amortization', 'label' => 'Amortization', 'amount' => 1098.00, 'rate' => 0.0, 'method' => 'fixed_monthly', 'unit' => 'PHP/month', 'notes' => 'Monthly bus amortization share per driver.'],
        ['cost_key' => 'insurance',    'label' => 'Insurance',    'amount' => 192.89,  'rate' => 0.0, 'method' => 'fixed_monthly', 'unit' => 'PHP/month', 'notes' => 'Monthly fleet insurance share per driver.'],
        ['cost_key' => 'gps',          'label' => 'GPS',          'amount' => 6.25,    'rate' => 0.0, 'method' => 'fixed_monthly', 'unit' => 'PHP/month', 'notes' => 'Monthly GPS tracking subscription per driver.'],
        ['cost_key' => 'optr',         'label' => 'OPTR',         'amount' => 500.00,  'rate' => 0.0, 'method' => 'fixed_monthly', 'unit' => 'PHP/month', 'notes' => 'Other operating expenses / permit and regulatory fees.'],
        ['cost_key' => 'gas',          'label' => 'Gas (Fuel)',   'amount' => 65.55,   'rate' => 0.0, 'method' => 'actual_fuel',  'unit' => 'PHP/liter', 'notes' => 'Fallback fuel price per liter; live price comes from Fuel Prices.'],
    ];

    public function ensureSchema(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        try {
            $db = Database::get();
            $db->exec("
                CREATE TABLE IF NOT EXISTS financial_cost_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cost_key VARCHAR(50) NOT NULL UNIQUE,
                    label VARCHAR(100) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                    calc_method VARCHAR(40) NOT NULL DEFAULT 'fixed_monthly',
                    unit VARCHAR(40) NOT NULL DEFAULT 'PHP/month',
                    effective_date DATE NOT NULL,
                    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
                    notes VARCHAR(255) NULL,
                    updated_by VARCHAR(100) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $db->prepare("
                INSERT INTO financial_cost_settings
                    (cost_key, label, amount, rate, calc_method, unit, effective_date, status, notes)
                VALUES
                    (:cost_key, :label, :amount, :rate, :calc_method, :unit, CURDATE(), 'Active', :notes)
                ON DUPLICATE KEY UPDATE cost_key = cost_key
            ");

            foreach (self::SEED_ROWS as $row) {
                $stmt->execute([
                    ':cost_key' => $row['cost_key'],
                    ':label' => $row['label'],
                    ':amount' => $row['amount'],
                    ':rate' => $row['rate'],
                    ':calc_method' => $row['method'],
                    ':unit' => $row['unit'],
                    ':notes' => $row['notes'],
                ]);
            }
        } catch (\Throwable $e) {
            // Database unavailable — callers fall back to FinancialCosts defaults.
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $this->ensureSchema();

        try {
            $db = Database::get();
            $stmt = $db->query("
                SELECT id, cost_key, label, amount, rate, calc_method, unit, effective_date, status, notes, updated_by, updated_at
                FROM financial_cost_settings
                ORDER BY FIELD(cost_key, 'amortization', 'insurance', 'gps', 'optr', 'gas'), id
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array{amount: float, rate: float, method: string, unit: string, effective_date: string, status: string, notes: string, label: string} $values
     */
    public function updateRow(string $costKey, array $values, string $editor): bool
    {
        $this->ensureSchema();

        $db = Database::get();
        $stmt = $db->prepare("
            UPDATE financial_cost_settings
            SET amount = :amount,
                rate = :rate,
                calc_method = :calc_method,
                unit = :unit,
                effective_date = :effective_date,
                status = :status,
                notes = :notes,
                updated_by = :updated_by
            WHERE cost_key = :cost_key
        ");

        return $stmt->execute([
            ':amount' => $values['amount'],
            ':rate' => $values['rate'],
            ':calc_method' => $values['method'],
            ':unit' => $values['unit'],
            ':effective_date' => $values['effective_date'],
            ':status' => $values['status'],
            ':notes' => $values['notes'],
            ':updated_by' => $editor,
            ':cost_key' => $costKey,
        ]);
    }

    /**
     * Recent cost-setting changes from the shared audit trail.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentAudit(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        try {
            $db = Database::get();
            $stmt = $db->query("
                SELECT id, username, user_role, record_id, description, previous_values, new_values, status, created_at
                FROM audit_trail
                WHERE module = 'Finance' AND action IN ('CostSettingUpdated', 'FuelPriceUpdated') AND status = 'Success'
                ORDER BY id DESC
                LIMIT " . $limit
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['previous_values'] = !empty($row['previous_values'])
                ? json_decode((string) $row['previous_values'], true)
                : [];
            $row['new_values'] = !empty($row['new_values'])
                ? json_decode((string) $row['new_values'], true)
                : [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Default labels/methods used when the table has not been seeded yet.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return FinancialCosts::DEFAULTS;
    }
}
