<?php

declare(strict_types=1);

namespace Finance\Controllers;

require_once __DIR__ . '/../services/FinancialCostSettingsService.php';
require_once __DIR__ . '/../../app/Core/FinancialCosts.php';

use App\Auth\Auth;
use App\Core\FinancialCosts;
use Finance\Services\FinancialCostSettingsService;

class FinancialCostSettingsController
{
    /**
     * Render the Financial Cost Settings page.
     */
    public function index(): void
    {
        $user = Auth::user();
        if (!in_array($user['role'] ?? '', ['finance_manager', 'finance_officer'], true)) {
            header('Location: /public/login/index.php');
            exit;
        }

        $settings = FinancialCostSettingsService::getSettings();
        $canEdit = FinancialCostSettingsService::canEdit();
        $role = $user['role'] ?? '';
        $roleLabel = ['finance_manager' => 'Finance Manager', 'finance_officer' => 'Finance Officer'][$role] ?? ucwords(str_replace('_', ' ', (string) $role));

        $fuelPrice = FinancialCosts::fuelPrice();
        $fuelPrices = FinancialCostSettingsService::getFuelPrices();
        // Match FinancialCosts::fuelPrice(): the highest-id diesel record is the one applied.
        $applicableFuel = null;
        foreach ($fuelPrices as $record) {
            if (stripos($record['fuel_type'], 'diesel') === false) {
                continue;
            }
            if ($applicableFuel === null || $record['id'] > $applicableFuel['id']) {
                $applicableFuel = $record;
            }
        }
        if ($applicableFuel === null && $fuelPrices) {
            $applicableFuel = $fuelPrices[0];
        }
        $fixedPerDriver = FinancialCosts::fixedCosts(1.0, []);
        $monthlyTotal = (float) $fixedPerDriver['monthly_total'];
        $audit = FinancialCostSettingsService::getRecentAudit(100);
        $methodLabels = FinancialCostSettingsService::methodLabels();

        require __DIR__ . '/../views/financial_cost_settings/index.php';
    }
}
