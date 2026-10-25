<?php

namespace Finance\Models;

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;

class FinancialReportModel
{
    /*
    |--------------------------------------------------------------------------
    | INIT — CREATE TABLES IF NOT EXISTS
    |--------------------------------------------------------------------------
    */

    public function initTables(): void
    {
        try {
            $db = Database::get();
            $db->exec("
                CREATE TABLE IF NOT EXISTS financial_expenses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category VARCHAR(100) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    expense_date DATE NOT NULL,
                    bus_id INT NULL,
                    driver_id INT NULL,
                    description TEXT NULL,
                    reference_file VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_category (category),
                    INDEX idx_expense_date (expense_date),
                    INDEX idx_bus_id (bus_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $db->exec("
                CREATE TABLE IF NOT EXISTS financial_reports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    report_data LONGTEXT NOT NULL,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_generated_at (generated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // Add columns if they don't exist (safe migration)
            try { $db->exec("ALTER TABLE financial_reports ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER report_data"); } catch(\Exception $e) {}
            try { $db->exec("ALTER TABLE financial_reports ADD COLUMN report_category VARCHAR(100) DEFAULT NULL AFTER archived"); } catch(\Exception $e) {}
            try { $db->exec("ALTER TABLE financial_reports ADD COLUMN generated_by VARCHAR(255) DEFAULT NULL AFTER report_category"); } catch(\Exception $e) {}
            try { $db->exec("ALTER TABLE financial_reports ADD COLUMN generated_by_role VARCHAR(100) DEFAULT NULL AFTER generated_by"); } catch(\Exception $e) {}
        } catch(\Exception $e) {
            // silently pass — tables may already exist
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT SINGLE EXPENSE
    |--------------------------------------------------------------------------
    */

    public function insertExpense(
        string $category,
        float $amount,
        string $expenseDate,
        ?int $busId = null,
        ?int $driverId = null,
        ?string $description = null,
        ?string $referenceFile = null
    ): int {
        $db = Database::get();
        $sql = "INSERT INTO financial_expenses (category, amount, expense_date, bus_id, driver_id, description, reference_file)
                VALUES (:category, :amount, :expense_date, :bus_id, :driver_id, :description, :reference_file)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':category' => $category,
            ':amount' => $amount,
            ':expense_date' => $expenseDate,
            ':bus_id' => $busId,
            ':driver_id' => $driverId,
            ':description' => $description,
            ':reference_file' => $referenceFile,
        ]);
        return (int)$db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | BULK INSERT EXPENSES (CSV IMPORT)
    |--------------------------------------------------------------------------
    */

    public function bulkInsertExpenses(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        $db = Database::get();
        $count = 0;
        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];
            $i = 0;
            foreach ($chunk as $row) {
                $placeholders[] = "(:cat_$i, :amt_$i, :dt_$i, :bus_$i, :drv_$i, :desc_$i, :ref_$i)";
                $values[":cat_$i"] = $row['category'] ?? 'Other';
                $values[":amt_$i"] = (float)($row['amount'] ?? 0);
                $values[":dt_$i"] = $row['expense_date'] ?? date('Y-m-d');
                $values[":bus_$i"] = !empty($row['bus_id']) ? (int)$row['bus_id'] : null;
                $values[":drv_$i"] = !empty($row['driver_id']) ? (int)$row['driver_id'] : null;
                $values[":desc_$i"] = $row['description'] ?? null;
                $values[":ref_$i"] = $row['reference_file'] ?? null;
                $i++;
            }
            $sql = "INSERT INTO financial_expenses (category, amount, expense_date, bus_id, driver_id, description, reference_file)
                    VALUES " . implode(',', $placeholders);
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            $count += count($chunk);
        }
        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | GET EXPENSES BY DATE RANGE
    |--------------------------------------------------------------------------
    */

    public function getExpensesByDateRange(?string $from = null, ?string $to = null): array
    {
        $db = Database::get();
        $conditions = [];
        $params = [];
        if ($from) {
            $conditions[] = "fe.expense_date >= :from";
            $params[':from'] = $from;
        }
        if ($to) {
            $conditions[] = "fe.expense_date <= :to";
            $params[':to'] = $to;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT fe.*, b.bus_id AS bus_number, d.full_name AS driver_name
                FROM financial_expenses fe
                LEFT JOIN buses b ON b.bus_id = fe.bus_id
                LEFT JOIN drivers d ON d.id = fe.driver_id
                $where
                ORDER BY fe.expense_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE AI REPORT
    |--------------------------------------------------------------------------
    */

    public function saveReport(string $title, string $reportData, string $category = '', ?string $generatedBy = null, ?string $generatedByRole = null): int
    {
        $db = Database::get();
        $sql = "INSERT INTO financial_reports (title, report_data, report_category, generated_by, generated_by_role) VALUES (:title, :report_data, :category, :generated_by, :generated_by_role)";
        $stmt = $db->prepare($sql);
        $stmt->execute([':title' => $title, ':report_data' => $reportData, ':category' => $category ?: null, ':generated_by' => $generatedBy, ':generated_by_role' => $generatedByRole]);
        return (int)$db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL REPORTS (with optional filters)
    |--------------------------------------------------------------------------
    */

    public function getReports(bool $archived = false, string $search = ''): array
    {
        $db = Database::get();
        $conditions = ["archived = " . ($archived ? 1 : 0)];
        $params = [];
        if (!empty($search)) {
            $conditions[] = "(title LIKE :search OR report_data LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $stmt = $db->prepare("SELECT id, title, generated_at, report_category, generated_by, generated_by_role FROM financial_reports $where ORDER BY generated_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORT BY ID
    |--------------------------------------------------------------------------
    */

    public function getReportById(int $id): ?array
    {
        $db = Database::get();
        $stmt = $db->prepare("SELECT * FROM financial_reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | ARCHIVE / UNARCHIVE REPORT
    |--------------------------------------------------------------------------
    */

    public function setArchiveStatus(int $id, bool $archived): bool
    {
        $db = Database::get();
        $stmt = $db->prepare("UPDATE financial_reports SET archived = :archived WHERE id = :id");
        $stmt->execute([':archived' => $archived ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | GET FINANCIAL OVERVIEW
    |--------------------------------------------------------------------------
    */

    public function getFinancialOverview(): array
    {
        try{

            $db = Database::get();

            $sql = "
                SELECT
                    COALESCE(
                        (SELECT SUM(actual_cash) FROM remittances), 0
                    ) AS total_revenue,
                    COALESCE(
                        (SELECT SUM(amount) FROM expenses), 0
                    ) AS total_expenses
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if(!$result){
                return [
                    'total_revenue' => 0,
                    'total_expenses' => 0
                ];
            }

            return [
                'total_revenue' => (float)($result['total_revenue'] ?? 0),
                'total_expenses' => (float)($result['total_expenses'] ?? 0)
            ];

        }catch(\Exception $e){
            return [
                'total_revenue' => 0,
                'total_expenses' => 0
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXPENSE BREAKDOWN BY CATEGORY
    |--------------------------------------------------------------------------
    */

    public function getExpenseBreakdown(): array
    {
        try{

            $db = Database::get();

            $sql = "
                SELECT
                    category,
                    SUM(amount) AS total,
                    ROUND(
                        SUM(amount) * 100.0 / (
                            SELECT SUM(amount) FROM expenses
                        ), 2
                    ) AS percentage
                FROM expenses
                GROUP BY category
                ORDER BY total DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }catch(\Exception $e){
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXPENSE TRENDS (MONTHLY)
    |--------------------------------------------------------------------------
    */

    public function getExpenseTrends(): array
    {
        try{

            $db = Database::get();

            $sql = "
                SELECT
                    DATE_FORMAT(e.created_at, '%Y-%m') AS month,
                    DATE_FORMAT(e.created_at, '%b %Y') AS label,
                    COALESCE(SUM(e.amount), 0) AS total
                FROM expenses e
                WHERE e.created_at IS NOT NULL
                GROUP BY DATE_FORMAT(e.created_at, '%Y-%m')
                ORDER BY month ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }catch(\Exception $e){
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COST PER BUS
    |--------------------------------------------------------------------------
    */

    public function getCostPerBus(): array
    {
        try{

            $db = Database::get();

            $sql = "
                SELECT
                    e.bus_id AS bus,
                    COALESCE(SUM(e.amount), 0) AS total_cost
                FROM expenses e
                WHERE e.bus_id IS NOT NULL
                GROUP BY e.bus_id
                ORDER BY total_cost DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }catch(\Exception $e){
            return [];
        }
    }

}
