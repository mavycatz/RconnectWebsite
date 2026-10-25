<?php
declare(strict_types=1);

/**
 * Reusable fixture for discrepancy workflow tests.
 *
 * Builds an in-memory SQLite database with the tables the discrepancy case,
 * ledger, approval, and reporting services query, plus small seed helpers.
 * Follows the intent of FinancialPerformanceFixturePDO (no external database
 * required) while using a real SQL engine so joins, aggregates, and the
 * append-only tables behave exactly as they do in production.
 *
 * Usage:
 *   $db = DiscrepancyFixture::pdo();
 *   DiscrepancyFixture::seedDriver($db, 'D1', 'Driver One');
 *   DiscrepancyFixture::seedRemittanceLog($db, [...]);
 */

require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';

final class DiscrepancyFixture
{
    public static function pdo(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = OFF');
        self::schema($db);
        return $db;
    }

    public static function schema(PDO $db): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS drivers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NOT NULL,
                full_name TEXT NULL,
                status TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS driver_bus_assignment (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NULL,
                bus_id TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS remittance_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NULL,
                business_date TEXT NULL,
                trip_no TEXT NULL,
                total_passengers INTEGER DEFAULT 0,
                total_collection REAL DEFAULT 0,
                expected_cash REAL DEFAULT 0,
                actual_cash REAL NULL,
                discrepancy_amount REAL DEFAULT 0,
                status TEXT NULL,
                submitted_by TEXT NULL,
                verified_at TEXT NULL,
                created_at TEXT NULL,
                finance_code TEXT NULL,
                confirmed_by TEXT NULL,
                remitted_by TEXT NULL,
                reviewed_by TEXT NULL,
                reviewed_at TEXT NULL,
                resolution_type TEXT NULL,
                resolution_notes TEXT NULL,
                resolved_by TEXT NULL,
                resolved_at TEXT NULL,
                supporting_document TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS bus_trips (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NULL,
                bus_id TEXT NULL,
                trip_date TEXT NULL,
                status TEXT NULL,
                submitted INTEGER DEFAULT 0,
                total_passengers INTEGER DEFAULT 0,
                total_revenue REAL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS passenger_trips (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NULL,
                bus_no TEXT NULL,
                trip_no TEXT NULL,
                fare REAL DEFAULT 0,
                payment_method TEXT NULL,
                payment_status TEXT NULL,
                cash_amount REAL NULL,
                gcash_amount REAL NULL,
                gcash_screenshot TEXT NULL,
                paymongo_payment_id TEXT NULL,
                paymongo_history TEXT NULL,
                gcash_reference_no TEXT NULL,
                boarding_time TEXT NULL,
                remittance_status TEXT NULL,
                expected_cash REAL NULL,
                actual_cash REAL NULL,
                discrepancy_amount REAL NULL,
                finance_note TEXT NULL,
                remitted_by TEXT NULL,
                remitted_at TEXT NULL,
                reviewed_by TEXT NULL,
                reviewed_at TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS passenger_trip_history (
                history_id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver_id TEXT NULL,
                bus_number TEXT NULL,
                drivers_full_name TEXT NULL,
                trip_id TEXT NULL,
                fare REAL DEFAULT 0,
                payment_method TEXT NULL,
                payment_status TEXT NULL,
                trip_date TEXT NULL,
                date_booked TEXT NULL,
                archived INTEGER DEFAULT 0,
                remittance_status TEXT NULL,
                actual_cash REAL NULL,
                discrepancy_amount REAL NULL,
                remitted_by TEXT NULL,
                remitted_at TEXT NULL,
                expected_cash REAL NULL
            )",
            "CREATE TABLE IF NOT EXISTS system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                role TEXT NULL,
                full_name TEXT NULL,
                status TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS audit_trail (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                username TEXT NULL,
                user_role TEXT NULL,
                module TEXT NOT NULL,
                action TEXT NOT NULL,
                record_id TEXT NULL,
                description TEXT NULL,
                previous_values TEXT NULL,
                new_values TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                status TEXT NOT NULL DEFAULT 'Success',
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS discrepancy_cases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                remittance_log_id INTEGER NOT NULL UNIQUE,
                case_ref TEXT NULL,
                driver_id TEXT NULL,
                business_date TEXT NULL,
                case_type TEXT NOT NULL DEFAULT 'Cash Shortage',
                status TEXT NOT NULL DEFAULT 'Open',
                expected_amount REAL NOT NULL DEFAULT 0,
                actual_amount REAL NULL,
                discrepancy_amount REAL NOT NULL DEFAULT 0,
                excess_amount REAL NOT NULL DEFAULT 0,
                assigned_reviewer TEXT NULL,
                assigned_at TEXT NULL,
                due_date TEXT NULL,
                findings TEXT NULL,
                resolution_reason TEXT NULL,
                opened_by TEXT NULL,
                opened_at TEXT NULL,
                resolved_at TEXT NULL,
                closed_at TEXT NULL,
                findings_submitted_by TEXT NULL,
                findings_submitted_at TEXT NULL,
                manager_decision TEXT NULL,
                manager_decision_by TEXT NULL,
                manager_decision_at TEXT NULL,
                manager_decision_reason TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS discrepancy_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                case_id INTEGER NOT NULL,
                from_status TEXT NULL,
                to_status TEXT NULL,
                event_type TEXT NOT NULL DEFAULT 'status_change',
                description TEXT NULL,
                reason TEXT NULL,
                actor_id INTEGER NULL,
                actor_username TEXT NULL,
                actor_role TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS discrepancy_evidence (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                case_id INTEGER NOT NULL,
                file_path TEXT NOT NULL,
                original_name TEXT NULL,
                mime TEXT NULL,
                size INTEGER NULL,
                uploaded_by TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS discrepancy_ledger_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                case_id INTEGER NOT NULL,
                entry_type TEXT NOT NULL,
                recovery_class TEXT NOT NULL,
                amount REAL NOT NULL DEFAULT 0,
                direction TEXT NOT NULL DEFAULT 'reduce',
                state TEXT NOT NULL DEFAULT 'proposed',
                reference TEXT NULL,
                authorization_reference TEXT NULL,
                notes TEXT NULL,
                proposed_by TEXT NULL,
                proposed_at TEXT NULL,
                decided_by TEXT NULL,
                decided_at TEXT NULL,
                decision_reason TEXT NULL,
                posted_by TEXT NULL,
                posted_at TEXT NULL,
                void_reason TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS discrepancy_approvals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                case_id INTEGER NOT NULL,
                ledger_entry_id INTEGER NULL,
                action TEXT NOT NULL,
                amount REAL NULL,
                reason TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                requested_by TEXT NULL,
                requested_at TEXT NULL,
                decided_by TEXT NULL,
                decided_at TEXT NULL,
                decision_reason TEXT NULL,
                config_reference TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )",
        ];

        foreach ($statements as $sql) {
            $db->exec($sql);
        }
    }

    public static function insertRow(PDO $db, string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn($c) => ':' . $c, $columns);
        $stmt = $db->prepare(
            "INSERT INTO $table (" . implode(', ', $columns) . ")
             VALUES (" . implode(', ', $placeholders) . ")"
        );
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public static function seedDriver(PDO $db, string $driverId, string $name, string $status = 'Active'): int
    {
        return self::insertRow($db, 'drivers', [
            'driver_id' => $driverId,
            'full_name' => $name,
            'status' => $status,
        ]);
    }

    public static function seedRemittanceLog(PDO $db, array $data): int
    {
        $data += [
            'driver_id' => 'D1',
            'business_date' => '2026-10-01',
            'trip_no' => 'T1',
            'total_passengers' => 10,
            'total_collection' => 500.0,
            'expected_cash' => 500.0,
            'actual_cash' => 450.0,
            'discrepancy_amount' => 50.0,
            'status' => 'Discrepancy',
            'created_at' => '2026-10-01 21:00:00',
        ];
        return self::insertRow($db, 'remittance_logs', $data);
    }

    public static function seedCase(PDO $db, array $data): int
    {
        $model = new \Finance\Models\DiscrepancyCaseModel($db);
        $id = $model->insertCase($data);
        $model->updateCase($id, ['case_ref' => 'DIS-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        return $id;
    }

    public static function seedLedgerEntry(PDO $db, array $data): int
    {
        $data += [
            'case_id' => 1,
            'entry_type' => 'additional_payment',
            'recovery_class' => 'cash',
            'amount' => 100.0,
            'direction' => 'reduce',
            'state' => 'posted',
            'reference' => 'REF-1',
            'proposed_by' => 'fixture',
            'proposed_at' => '2026-10-02 10:00:00',
            'posted_by' => 'fixture',
            'posted_at' => '2026-10-02 10:05:00',
        ];
        return self::insertRow($db, 'discrepancy_ledger_entries', $data);
    }

    public static function seedApproval(PDO $db, array $data): int
    {
        $data += [
            'case_id' => 1,
            'ledger_entry_id' => null,
            'action' => 'waiver',
            'amount' => 50.0,
            'reason' => 'fixture',
            'status' => 'pending',
            'requested_by' => 'fixture',
            'requested_at' => '2026-10-02 10:00:00',
        ];
        return self::insertRow($db, 'discrepancy_approvals', $data);
    }

    public static function counts(PDO $db): array
    {
        $tables = [
            'drivers',
            'remittance_logs',
            'discrepancy_cases',
            'discrepancy_status_history',
            'discrepancy_evidence',
            'discrepancy_ledger_entries',
            'discrepancy_approvals',
            'audit_trail',
        ];

        $counts = [];
        foreach ($tables as $table) {
            try {
                $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            } catch (Throwable $e) {
                $counts[$table] = -1;
            }
        }
        return $counts;
    }
}
