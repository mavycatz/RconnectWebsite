<?php
declare(strict_types=1);

/**
 * In-memory SQLite fixture for FinancialIntelligenceService tests.
 *
 * Mirrors the real column names for the tables the service reads so the same
 * SQL runs against the fixture and the live MySQL database. Dates and amounts
 * are deterministic; see financial_intelligence_test.php for the expected
 * arithmetic.
 */
final class FinancialIntelligenceFixture
{
    public static function pdo(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::schema($db);
        self::seed($db);
        return $db;
    }

    public static function schema(PDO $db): void
    {
        $db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY, bus_id TEXT, plate_number TEXT)');
        $db->exec('CREATE TABLE passengers (id INTEGER PRIMARY KEY, discount_type TEXT)');
        $db->exec('CREATE TABLE bus_trips (
            id INTEGER PRIMARY KEY, bus_id TEXT, driver_id TEXT, route_name TEXT,
            trip_date TEXT, status TEXT, total_revenue REAL
        )');
        $db->exec('CREATE TABLE passenger_trips (
            id INTEGER PRIMARY KEY, passenger_id INTEGER, bus_id TEXT, driver_id TEXT,
            trip_no TEXT, fare REAL, payment_status TEXT, boarding_time TEXT,
            payment_method TEXT, archived_status TEXT, passenger_type TEXT
        )');
        $db->exec('CREATE TABLE passenger_trip_history (
            history_id INTEGER PRIMARY KEY, passenger_name TEXT, passenger_type TEXT,
            type TEXT, fare REAL, trip_date TEXT, date_booked TEXT, bus_id TEXT,
            bus_number TEXT, driver_id TEXT, trip_id TEXT, payment_method TEXT,
            boarding_point TEXT, destination TEXT, payment_status TEXT
        )');
        $db->exec('CREATE TABLE expenses (
            id INTEGER PRIMARY KEY, bus_id TEXT, trip_id TEXT, category TEXT,
            amount REAL, description TEXT, created_at TEXT
        )');
        $db->exec('CREATE TABLE financial_expenses (
            id INTEGER PRIMARY KEY, category TEXT, amount REAL, expense_date TEXT,
            bus_id INTEGER, description TEXT
        )');
        $db->exec('CREATE TABLE fuel_logs (
            id INTEGER PRIMARY KEY, fuel_log_id INTEGER, bus_id TEXT, driver_id TEXT,
            trip_id TEXT, liters REAL, cost REAL, price_per_liter REAL,
            distance_traveled REAL, distance_km REAL, kpl REAL, receipt_number TEXT, created_at TEXT
        )');
        $db->exec('CREATE TABLE fuel_refuels (
            refuel_id INTEGER PRIMARY KEY, bus_id TEXT, driver_id TEXT, trip_id TEXT,
            refuel_odo REAL, liters REAL, price_per_liter REAL, total_cost REAL,
            is_full_tank INTEGER, created_at TEXT
        )');
        $db->exec('CREATE TABLE fuel_prices (
            id INTEGER PRIMARY KEY, fuel_type TEXT, price_per_liter REAL, updated_at TEXT
        )');
        $db->exec('CREATE TABLE fare_pricing (
            id INTEGER PRIMARY KEY, route_name TEXT, destination TEXT,
            regular_fare REAL, student_fare REAL, special_fare REAL,
            display_order INTEGER, updated_at TEXT
        )');
        $db->exec('CREATE TABLE financial_cost_settings (
            id INTEGER PRIMARY KEY, cost_key TEXT, label TEXT, amount REAL, rate REAL,
            calc_method TEXT, unit TEXT, effective_date TEXT, status TEXT, notes TEXT,
            updated_by TEXT, created_at TEXT, updated_at TEXT
        )');
        $db->exec('CREATE TABLE remittance_logs (
            id INTEGER PRIMARY KEY, driver_id TEXT, bus_no TEXT, total_collection REAL,
            expected_cash REAL, actual_cash REAL, status TEXT, discrepancy_amount REAL,
            business_date TEXT, created_at TEXT
        )');
        $db->exec('CREATE TABLE discrepancy_cases (
            id INTEGER PRIMARY KEY, remittance_log_id INTEGER, case_ref TEXT,
            driver_id TEXT, business_date TEXT, case_type TEXT, status TEXT,
            expected_amount REAL, actual_amount REAL, discrepancy_amount REAL,
            excess_amount REAL, opened_at TEXT, created_at TEXT
        )');
        $db->exec('CREATE TABLE discrepancy_ledger_entries (
            id INTEGER PRIMARY KEY, case_id INTEGER, entry_type TEXT,
            recovery_class TEXT, amount REAL, direction TEXT, state TEXT
        )');
    }

    public static function seed(PDO $db): void
    {
        $insert = static function (PDO $db, string $table, array $row): void {
            $columns = array_keys($row);
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_map(static fn($c) => ':' . $c, $columns)) . ')';
            $stmt = $db->prepare($sql);
            foreach ($row as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();
        };

        $insert($db, 'buses', ['id' => 1, 'bus_id' => 'B1', 'plate_number' => 'ABC-1001']);
        $insert($db, 'buses', ['id' => 2, 'bus_id' => 'B2', 'plate_number' => 'ABC-1002']);
        $insert($db, 'passengers', ['id' => 1, 'discount_type' => 'Regular']);
        $insert($db, 'passengers', ['id' => 2, 'discount_type' => 'Student']);

        $trips = [
            [101, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-10-05'],
            [102, 'B2', 'D2', 'Cabanatuan-Kalikid', '2026-10-05'],
            [103, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-10-06'],
            [104, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-10-02'],
            [105, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-09-12'],
            [106, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-07-20'],
            [107, 'B2', 'D2', 'Cabanatuan-Kalikid', '2026-04-21'],
        ];
        foreach ($trips as [$id, $bus, $driver, $route, $date]) {
            $insert($db, 'bus_trips', [
                'id' => $id, 'bus_id' => $bus, 'driver_id' => $driver, 'route_name' => $route,
                'trip_date' => $date, 'status' => 'Completed', 'total_revenue' => 0,
            ]);
        }

        $live = [
            [1, 1, 'B1', 'D1', '101', 100.00, 'paid', '2026-10-05 08:00:00', 'cash', 'no'],
            [2, 2, 'B1', 'D1', '101', 80.00, 'paid', '2026-10-05 08:30:00', 'cash', 'no'],
            [3, 1, 'B1', 'D1', '103', 100.00, 'paid', '2026-10-06 09:00:00', 'gcash', 'no'],
            [4, 1, 'B1', 'D1', '103', 100.00, 'pending', '2026-10-06 09:30:00', 'cash', 'no'],
            [5, 1, 'B1', 'D1', '103', 100.00, 'paid', '2026-10-06 10:00:00', 'cash', 'yes'],
        ];
        foreach ($live as [$id, $passenger, $bus, $driver, $trip, $fare, $status, $time, $method, $archived]) {
            $insert($db, 'passenger_trips', [
                'id' => $id, 'passenger_id' => $passenger, 'bus_id' => $bus, 'driver_id' => $driver,
                'trip_no' => $trip, 'fare' => $fare, 'payment_status' => $status,
                'boarding_time' => $time, 'payment_method' => $method, 'archived_status' => $archived,
            ]);
        }

        $history = [
            [1, 'Regular', 'passenger', 120.00, '2026-10-05', 'B2', 'D2', '102', 'Rizal'],
            [2, 'Senior Citizen', 'passenger', 96.00, '2026-10-05', 'B2', 'D2', '102', 'Rizal'],
            [3, 'PWD', 'passenger', 96.00, '2026-10-05', 'B2', 'D2', '102', 'Rizal'],
            [4, 'Baggage', 'other', 50.00, '2026-10-05', 'B2', 'D2', '102', 'Rizal'],
            [5, 'Regular', 'passenger', 100.00, '2026-09-12', 'B1', 'D1', '105', 'Rizal'],
            [6, 'Regular', 'passenger', 90.00, '2026-07-20', 'B1', 'D1', '106', 'Rizal'],
            [7, 'Regular', 'passenger', 85.00, '2026-04-21', 'B2', 'D2', '107', 'Kalikid'],
            [8, 'Student', 'passenger', 80.00, '2026-10-02', 'B1', 'D1', '104', 'Rizal'],
        ];
        foreach ($history as [$id, $type, $kind, $fare, $date, $bus, $driver, $trip, $destination]) {
            $insert($db, 'passenger_trip_history', [
                'history_id' => $id, 'passenger_name' => 'P' . $id, 'passenger_type' => $type,
                'type' => $kind, 'fare' => $fare, 'trip_date' => $date, 'date_booked' => $date . ' 07:00:00',
                'bus_id' => $bus, 'driver_id' => $driver, 'trip_id' => $trip,
                'payment_method' => 'cash', 'boarding_point' => 'Cabanatuan', 'destination' => $destination,
            ]);
        }

        $insert($db, 'expenses', [
            'id' => 1, 'bus_id' => 'B1', 'trip_id' => '101', 'category' => 'maintenance',
            'amount' => 200.00, 'description' => 'Brake pads', 'created_at' => '2026-10-05 12:00:00',
        ]);
        $insert($db, 'financial_expenses', [
            'id' => 1, 'category' => 'toll', 'amount' => 50.00, 'expense_date' => '2026-10-06',
            'bus_id' => 1, 'description' => 'NLEX toll',
        ]);

        $refuels = [
            [1, 'B1', 'D1', '101', 10000, 40.0, 15.0, 600.00, '2026-10-05 06:00:00'],
            [2, 'B2', 'D2', '102', 5000, 30.0, 15.0, 450.00, '2026-10-05 06:30:00'],
        ];
        foreach ($refuels as [$id, $bus, $driver, $trip, $odo, $liters, $price, $total, $created]) {
            $insert($db, 'fuel_refuels', [
                'refuel_id' => $id, 'bus_id' => $bus, 'driver_id' => $driver, 'trip_id' => $trip,
                'refuel_odo' => $odo, 'liters' => $liters, 'price_per_liter' => $price,
                'total_cost' => $total, 'is_full_tank' => 1, 'created_at' => $created,
            ]);
        }
        $insert($db, 'fuel_logs', [
            'id' => 1, 'fuel_log_id' => 1, 'bus_id' => 'B1', 'driver_id' => 'D1', 'trip_id' => '101',
            'liters' => 0.0, 'cost' => 0.0, 'price_per_liter' => 15.0, 'distance_traveled' => 200.0,
            'kpl' => 5.0, 'receipt_number' => null, 'created_at' => '2026-10-05 18:00:00',
        ]);
        $insert($db, 'fuel_logs', [
            'id' => 2, 'fuel_log_id' => 2, 'bus_id' => 'B1', 'driver_id' => 'D1', 'trip_id' => '103',
            'liters' => 0.0, 'cost' => 0.0, 'price_per_liter' => 15.0, 'distance_traveled' => 150.0,
            'kpl' => 5.0, 'receipt_number' => null, 'created_at' => '2026-10-06 18:00:00',
        ]);
        $insert($db, 'fuel_logs', [
            'id' => 3, 'fuel_log_id' => 3, 'bus_id' => 'B1', 'driver_id' => '', 'trip_id' => '103',
            'liters' => 10.0, 'cost' => 150.0, 'price_per_liter' => 15.0, 'distance_traveled' => 0.0,
            'kpl' => 0.0, 'receipt_number' => null, 'created_at' => '2026-10-07 08:00:00',
        ]);

        $insert($db, 'fuel_prices', ['id' => 1, 'fuel_type' => 'Diesel', 'price_per_liter' => 14.0, 'updated_at' => '2026-09-01 00:00:00']);
        $insert($db, 'fuel_prices', ['id' => 2, 'fuel_type' => 'Diesel', 'price_per_liter' => 15.0, 'updated_at' => '2026-10-01 00:00:00']);

        $costSettings = [
            [1, 'amortization', 'Amortization', 1000.00, 0.0, 'fixed_monthly', 'PHP/month', 'Active'],
            [2, 'insurance', 'Insurance', 100.00, 0.0, 'fixed_monthly', 'PHP/month', 'Active'],
            [3, 'gps', 'GPS', 10.00, 0.0, 'fixed_monthly', 'PHP/month', 'Active'],
            [4, 'optr', 'OPTR', 200.00, 0.0, 'fixed_monthly', 'PHP/month', 'Active'],
            [5, 'optr', 'OPTR (old rate)', 999.00, 0.0, 'fixed_monthly', 'PHP/month', 'Inactive'],
            [6, 'gas', 'Gas (Fuel)', 15.00, 0.0, 'actual_fuel', 'PHP/liter', 'Active'],
        ];
        foreach ($costSettings as [$id, $key, $label, $amount, $rate, $method, $unit, $status]) {
            $insert($db, 'financial_cost_settings', [
                'id' => $id, 'cost_key' => $key, 'label' => $label, 'amount' => $amount,
                'rate' => $rate, 'calc_method' => $method, 'unit' => $unit,
                'effective_date' => '2026-01-01', 'status' => $status, 'notes' => null,
                'updated_by' => 'seed', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ]);
        }

        $fares = [
            [1, 'Cabanatuan-Rizal', 'Cabanatuan', 15.00, 15.00, 15.00, 1],
            [2, 'Cabanatuan-Rizal', 'Rizal', 120.00, 96.00, 96.00, 2],
            [3, 'Cabanatuan-Kalikid', 'Kalikid', 100.00, 80.00, 80.00, 2],
        ];
        foreach ($fares as [$id, $route, $destination, $regular, $student, $special, $order]) {
            $insert($db, 'fare_pricing', [
                'id' => $id, 'route_name' => $route, 'destination' => $destination,
                'regular_fare' => $regular, 'student_fare' => $student, 'special_fare' => $special,
                'display_order' => $order, 'updated_at' => '2026-10-03 08:00:00',
            ]);
        }

        $remittances = [
            [1, 'D1', 'B1', 180.00, 180.00, 150.00, 'Discrepancy', 30.00, '2026-10-05'],
            [2, 'D2', 'B2', 100.00, 100.00, 120.00, 'Discrepancy', 0.00, '2026-10-06'],
            [3, 'D1', 'B1', 50.00, 50.00, 50.00, 'Completed', 0.00, '2026-10-10'],
        ];
        foreach ($remittances as [$id, $driver, $bus, $collection, $expected, $actual, $status, $discrepancy, $date]) {
            $insert($db, 'remittance_logs', [
                'id' => $id, 'driver_id' => $driver, 'bus_no' => $bus, 'total_collection' => $collection,
                'expected_cash' => $expected, 'actual_cash' => $actual, 'status' => $status,
                'discrepancy_amount' => $discrepancy, 'business_date' => $date, 'created_at' => $date . ' 23:00:00',
            ]);
        }

        $insert($db, 'discrepancy_cases', [
            'id' => 1, 'remittance_log_id' => 1, 'case_ref' => 'DIS-00001', 'driver_id' => 'D1',
            'business_date' => '2026-10-05', 'case_type' => 'Cash Shortage', 'status' => 'Open',
            'expected_amount' => 180.00, 'actual_amount' => 150.00, 'discrepancy_amount' => 30.00,
            'excess_amount' => 0.00, 'opened_at' => '2026-10-05 23:30:00', 'created_at' => '2026-10-05 23:30:00',
        ]);
        $insert($db, 'discrepancy_cases', [
            'id' => 2, 'remittance_log_id' => 2, 'case_ref' => 'DIS-00002', 'driver_id' => 'D2',
            'business_date' => '2026-10-06', 'case_type' => 'Excess Remittance', 'status' => 'Open',
            'expected_amount' => 100.00, 'actual_amount' => 120.00, 'discrepancy_amount' => 0.00,
            'excess_amount' => 20.00, 'opened_at' => '2026-10-06 23:30:00', 'created_at' => '2026-10-06 23:30:00',
        ]);
        $insert($db, 'discrepancy_ledger_entries', [
            'id' => 1, 'case_id' => 1, 'entry_type' => 'additional_payment', 'recovery_class' => 'cash',
            'amount' => 10.00, 'direction' => 'reduce', 'state' => 'posted',
        ]);
    }

    /**
     * Additional deterministic rows for the data-integrity cases: cross-midnight
     * attribution, archived bus fallback, archived unmapped references,
     * configuration-excluded routes, live/history twins, and fuel de-dup/KPL
     * derivation. Confined to November 2026 so the October expectations in
     * financial_intelligence_test.php are untouched.
     */
    public static function seedIntegrityCases(PDO $db): void
    {
        $insert = static function (PDO $db, string $table, array $row): void {
            $columns = array_keys($row);
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_map(static fn($c) => ':' . $c, $columns)) . ')';
            $stmt = $db->prepare($sql);
            foreach ($row as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();
        };

        /* Cross-midnight trip: departs 2026-10-31, boarding after midnight. */
        $insert($db, 'bus_trips', [
            'id' => 108, 'bus_id' => 'B1', 'driver_id' => 'D1', 'route_name' => 'Cabanatuan-Rizal',
            'trip_date' => '2026-10-31', 'status' => 'Completed', 'total_revenue' => 0,
        ]);
        /* Bus ran exactly one canonical route that day (archived fallback). */
        $insert($db, 'bus_trips', [
            'id' => 109, 'bus_id' => 'B2', 'driver_id' => 'D2', 'route_name' => 'Cabanatuan-Kalikid',
            'trip_date' => '2026-11-02', 'status' => 'Completed', 'total_revenue' => 0,
        ]);
        /* Configuration-excluded route label. */
        $insert($db, 'bus_trips', [
            'id' => 110, 'bus_id' => 'B1', 'driver_id' => 'D1',
            'route_name' => 'Cabanatuan Central Terminal - Rizal / Poblacion Terminal',
            'trip_date' => '2026-11-04', 'status' => 'Completed', 'total_revenue' => 0,
        ]);
        /* Recorded route with no fare matrix row. */
        $insert($db, 'bus_trips', [
            'id' => 111, 'bus_id' => 'B1', 'driver_id' => 'D1', 'route_name' => 'Cabanatuan-Gapan',
            'trip_date' => '2026-11-05', 'status' => 'Completed', 'total_revenue' => 0,
        ]);

        $live = [
            [101, 1, 'B1', 'D1', '108', 100.00, 'paid', '2026-11-01 00:30:00', 'cash', 'no'],
            [102, 1, 'B1', 'D1', '110', 50.00, 'paid', '2026-11-04 08:00:00', 'cash', 'no'],
            [103, 1, 'B1', 'D1', '111', 60.00, 'paid', '2026-11-05 08:00:00', 'cash', 'no'],
        ];
        foreach ($live as [$id, $passenger, $bus, $driver, $trip, $fare, $status, $time, $method, $archived]) {
            $insert($db, 'passenger_trips', [
                'id' => $id, 'passenger_id' => $passenger, 'bus_id' => $bus, 'driver_id' => $driver,
                'trip_no' => $trip, 'fare' => $fare, 'payment_status' => $status,
                'boarding_time' => $time, 'payment_method' => $method, 'archived_status' => $archived,
            ]);
        }

        /* history_id 9 is the archived twin of live row 101 (trip_id = live id). */
        $insert($db, 'passenger_trip_history', [
            'history_id' => 9, 'passenger_name' => 'P101', 'passenger_type' => 'Regular',
            'type' => 'passenger', 'fare' => 100.00, 'trip_date' => '2026-11-01',
            'date_booked' => '2026-11-01 00:30:00', 'bus_id' => null, 'bus_number' => 'B1',
            'driver_id' => 'D1', 'trip_id' => '101', 'payment_method' => 'cash',
            'boarding_point' => 'Cabanatuan', 'destination' => 'Rizal',
        ]);
        /* history_id 10: unmapped TRIP- reference attributed via the bus. */
        $insert($db, 'passenger_trip_history', [
            'history_id' => 10, 'passenger_name' => 'P10', 'passenger_type' => 'Regular',
            'type' => 'passenger', 'fare' => 90.00, 'trip_date' => '2026-11-02',
            'date_booked' => '2026-11-02 07:00:00', 'bus_id' => null, 'bus_number' => 'B2',
            'driver_id' => 'D2', 'trip_id' => 'TRIP-9999', 'payment_method' => 'cash',
            'boarding_point' => 'Cabanatuan', 'destination' => 'Kalikid',
        ]);
        /* history_id 11: unmapped TRIP- reference with no bus/day basis. */
        $insert($db, 'passenger_trip_history', [
            'history_id' => 11, 'passenger_name' => 'P11', 'passenger_type' => 'Regular',
            'type' => 'passenger', 'fare' => 70.00, 'trip_date' => '2026-11-03',
            'date_booked' => '2026-11-03 07:00:00', 'bus_id' => null, 'bus_number' => 'B1',
            'driver_id' => 'D1', 'trip_id' => 'TRIP-9998', 'payment_method' => 'cash',
            'boarding_point' => 'Cabanatuan', 'destination' => 'Rizal',
        ]);
        /* history_id 12: ARCV prefix resolves through bus_trips.id 109. */
        $insert($db, 'passenger_trip_history', [
            'history_id' => 12, 'passenger_name' => 'P12', 'passenger_type' => 'Student',
            'type' => 'passenger', 'fare' => 80.00, 'trip_date' => '2026-11-02',
            'date_booked' => '2026-11-02 08:00:00', 'bus_id' => null, 'bus_number' => 'B2',
            'driver_id' => 'D2', 'trip_id' => 'ARCV-109', 'payment_method' => 'cash',
            'boarding_point' => 'Cabanatuan', 'destination' => 'Kalikid',
        ]);

        /* Fuel: refuel 3 and legacy log 4 describe the same purchase. */
        $insert($db, 'fuel_refuels', [
            'refuel_id' => 3, 'bus_id' => 'B1', 'driver_id' => 'D1', 'trip_id' => '108',
            'refuel_odo' => 12000, 'liters' => 10.0, 'price_per_liter' => 15.0,
            'total_cost' => 150.00, 'is_full_tank' => 1, 'created_at' => '2026-11-01 06:00:00',
        ]);
        $insert($db, 'fuel_logs', [
            'id' => 4, 'fuel_log_id' => 4, 'bus_id' => 'B1', 'driver_id' => 'D1', 'trip_id' => '108',
            'liters' => 10.0, 'cost' => 150.0, 'price_per_liter' => 15.0, 'distance_traveled' => 0.0,
            'kpl' => 0.0, 'receipt_number' => null, 'created_at' => '2026-11-01 06:00:00',
        ]);
        /* Operational measurement whose KPL must be derived from distance/liters. */
        $insert($db, 'fuel_logs', [
            'id' => 5, 'fuel_log_id' => 5, 'bus_id' => 'B1', 'driver_id' => 'D1', 'trip_id' => '108',
            'liters' => 20.0, 'cost' => 300.0, 'price_per_liter' => 15.0, 'distance_traveled' => 170.0,
            'kpl' => 0.0, 'receipt_number' => null, 'created_at' => '2026-11-01 07:00:00',
        ]);
        /* Measured distance but no liters and no recorded KPL: missing measurement. */
        $insert($db, 'fuel_logs', [
            'id' => 6, 'fuel_log_id' => 6, 'bus_id' => 'B1', 'driver_id' => '', 'trip_id' => '',
            'liters' => 5.0, 'cost' => 0.0, 'price_per_liter' => 15.0, 'distance_traveled' => 0.0,
            'kpl' => 0.0, 'receipt_number' => null, 'created_at' => '2026-11-06 08:00:00',
        ]);
        /* Genuine zero record: nothing measured, nothing claimed. */
        $insert($db, 'fuel_logs', [
            'id' => 7, 'fuel_log_id' => 7, 'bus_id' => 'B1', 'driver_id' => '', 'trip_id' => '',
            'liters' => 0.0, 'cost' => 0.0, 'price_per_liter' => 0.0, 'distance_traveled' => 0.0,
            'kpl' => 0.0, 'receipt_number' => null, 'created_at' => '2026-11-07 08:00:00',
        ]);
        /* Operational-only month: December has no purchases but has measurements. */
        $insert($db, 'fuel_logs', [
            'id' => 8, 'fuel_log_id' => 8, 'bus_id' => 'B1', 'driver_id' => '', 'trip_id' => '',
            'liters' => 0.0, 'cost' => 0.0, 'price_per_liter' => 0.0, 'distance_traveled' => 120.0,
            'kpl' => 6.0, 'receipt_number' => null, 'created_at' => '2026-12-01 08:00:00',
        ]);

        /* Remittance with a null business_date: the created_at fallback must
           still place it in the period. */
        $insert($db, 'remittance_logs', [
            'id' => 4, 'driver_id' => 'D1', 'bus_no' => 'B1', 'total_collection' => 100.00,
            'expected_cash' => 80.00, 'actual_cash' => 80.00, 'status' => 'Completed',
            'discrepancy_amount' => 0.00, 'business_date' => null, 'created_at' => '2026-11-01 23:00:00',
        ]);
        /* Remittance with no usable date at all: reported as undated. */
        $insert($db, 'remittance_logs', [
            'id' => 5, 'driver_id' => 'D1', 'bus_no' => 'B1', 'total_collection' => 50.00,
            'expected_cash' => 50.00, 'actual_cash' => 50.00, 'status' => 'Completed',
            'discrepancy_amount' => 0.00, 'business_date' => null, 'created_at' => null,
        ]);

        /* Completed trip with no fare collections: its route must still be
           reported as operating. */
        $insert($db, 'bus_trips', [
            'id' => 112, 'bus_id' => 'B1', 'driver_id' => 'D1', 'route_name' => 'Cabanatuan-Rizal',
            'trip_date' => '2026-10-10', 'status' => 'Completed', 'total_revenue' => 0,
        ]);
    }
}
