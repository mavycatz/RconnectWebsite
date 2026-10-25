<?php
declare(strict_types=1);

/**
 * R-Connect Admin Generate Report registry.
 *
 * One authoritative definition per report type drives the configuration UI,
 * preview, generation, PDF/CSV export, and validation. It has no side effects
 * so it can be required by views and endpoints alike.
 *
 * Timestamps are handled in Asia/Manila; DB datetimes are treated as
 * Manila wall-clock values (matching the reports library).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/Driver/driver-perf-metrics-core.php';

function rgen_period_modes(): array
{
    return [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semi_annually' => 'Semi-Annual',
        'annually' => 'Annual',
        'custom' => 'Custom range',
    ];
}

function rgen_registry(): array
{
    return [
        'drivers' => [
            'label' => 'Driver Masterlist',
            'description' => 'Complete driver directory with license, contact, account, and bus assignment details.',
            'category' => 'drivers',
            'requires' => [],
            'filters' => ['period', 'status', 'search'],
            'status_source' => ['table' => 'drivers', 'column' => 'status'],
            'date_column' => 'd.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'full_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'license_number', 'label' => 'License No.', 'type' => 'text'],
                ['key' => 'license_type', 'label' => 'License Type', 'type' => 'text'],
                ['key' => 'license_expiry', 'label' => 'License Expiry', 'type' => 'date'],
                ['key' => 'experience_years', 'label' => 'Experience (yrs)', 'type' => 'int'],
                ['key' => 'assigned_buses', 'label' => 'Assigned Buses', 'type' => 'text'],
                ['key' => 'routes', 'label' => 'Routes', 'type' => 'text'],
                ['key' => 'username', 'label' => 'Account', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'contact_number', 'label' => 'Contact', 'type' => 'text'],
                ['key' => 'total_bus_assignments', 'label' => 'Bus Assignments', 'type' => 'int'],
                ['key' => 'created_at', 'label' => 'Recorded', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'driver_performance' => [
            'label' => 'Driver Performance',
            'description' => 'One driver\'s performance for a period: attendance, trips, passengers, cargo, income, fixed costs, fuel, net income, occupancy, and top routes.',
            'category' => 'driver_report',
            'requires' => ['driver'],
            'filters' => ['period', 'driver'],
            'date_column' => null,
            'date_key' => null,
            'columns' => [
                ['key' => 'day', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'trips', 'label' => 'Trips', 'type' => 'int'],
                ['key' => 'passengers', 'label' => 'Passengers', 'type' => 'int'],
                ['key' => 'fare', 'label' => 'Fare', 'type' => 'money'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'driver_accounts' => [
            'label' => 'Driver Accounts',
            'description' => 'Driver login accounts with the linked driver profile and status.',
            'category' => 'driver_accounts',
            'requires' => [],
            'filters' => ['period', 'status', 'search'],
            'status_source' => ['table' => 'drivers', 'column' => 'status'],
            'date_column' => 'da.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'driver_id', 'label' => 'Driver ID', 'type' => 'text'],
                ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
                ['key' => 'full_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'license_number', 'label' => 'License No.', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'contact_number', 'label' => 'Contact', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'driver_bus_assignments' => [
            'label' => 'Driver Bus Assignments',
            'description' => 'Driver-to-bus assignments with the linked account and driver details.',
            'category' => 'driver_bus_assignments',
            'requires' => [],
            'filters' => ['period', 'bus', 'status', 'search'],
            'status_source' => ['table' => 'drivers', 'column' => 'status'],
            'date_column' => 'dba.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'driver_id', 'label' => 'Driver ID', 'type' => 'text'],
                ['key' => 'full_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'bus_id', 'label' => 'Bus', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'Assigned', 'type' => 'datetime'],
                ['key' => 'status', 'label' => 'Driver Status', 'type' => 'status'],
                ['key' => 'username', 'label' => 'Account', 'type' => 'text'],
                ['key' => 'assigned_buses', 'label' => 'Assigned Buses', 'type' => 'text'],
                ['key' => 'routes', 'label' => 'Routes', 'type' => 'text'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'passengers' => [
            'label' => 'Passenger Directory',
            'description' => 'Passenger directory with discount information, trip history count, and fare paid.',
            'category' => 'passengers',
            'requires' => [],
            'filters' => ['period', 'search'],
            'date_column' => 'p.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'full_name', 'label' => 'Passenger', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'discount_type', 'label' => 'Discount Type', 'type' => 'text'],
                ['key' => 'discount_status', 'label' => 'Discount Status', 'type' => 'status'],
                ['key' => 'student_id', 'label' => 'Student ID', 'type' => 'text'],
                ['key' => 'total_trip_history', 'label' => 'Trips', 'type' => 'int'],
                ['key' => 'total_fare_paid', 'label' => 'Total Fare Paid', 'type' => 'money'],
                ['key' => 'created_at', 'label' => 'Registered', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'passenger_trips' => [
            'label' => 'Passenger Trips',
            'description' => 'Live passenger trip records with payment, remittance, and discrepancy details.',
            'category' => 'passenger_trips',
            'requires' => [],
            'filters' => ['period', 'driver', 'bus', 'status', 'search'],
            'status_source' => ['table' => 'passenger_trips', 'column' => 'payment_status'],
            'date_column' => 'pt.boarding_time',
            'date_key' => 'boarding_time',
            'columns' => [
                ['key' => 'boarding_time', 'label' => 'Boarding Time', 'type' => 'datetime'],
                ['key' => 'trip_no', 'label' => 'Trip No.', 'type' => 'text'],
                ['key' => 'driver_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'bus_no', 'label' => 'Bus', 'type' => 'text'],
                ['key' => 'full_name', 'label' => 'Passenger', 'type' => 'text'],
                ['key' => 'discount_type', 'label' => 'Discount', 'type' => 'text'],
                ['key' => 'boarding_point', 'label' => 'Boarding', 'type' => 'text'],
                ['key' => 'destination', 'label' => 'Destination', 'type' => 'text'],
                ['key' => 'fare', 'label' => 'Fare', 'type' => 'money'],
                ['key' => 'payment_method', 'label' => 'Payment Method', 'type' => 'text'],
                ['key' => 'payment_status', 'label' => 'Payment Status', 'type' => 'status'],
                ['key' => 'remittance_status', 'label' => 'Remittance', 'type' => 'status'],
                ['key' => 'discrepancy_amount', 'label' => 'Discrepancy', 'type' => 'money'],
                ['key' => 'dropoff_time', 'label' => 'Drop-off Time', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'passenger_trip_history' => [
            'label' => 'Passenger Trip History',
            'description' => 'Archived passenger trips with passenger type, payment method, and fare details.',
            'category' => 'passenger_trip_history',
            'requires' => [],
            'filters' => ['period', 'driver', 'bus', 'status', 'search'],
            'status_source' => ['table' => 'passenger_trip_history', 'column' => 'passenger_type'],
            'date_column' => 'pth.trip_date',
            'date_key' => 'trip_date',
            'columns' => [
                ['key' => 'trip_date', 'label' => 'Trip Date', 'type' => 'date'],
                ['key' => 'passenger_name', 'label' => 'Passenger', 'type' => 'text'],
                ['key' => 'passenger_type', 'label' => 'Passenger Type', 'type' => 'status'],
                ['key' => 'driver_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'bus_number', 'label' => 'Bus', 'type' => 'text'],
                ['key' => 'boarding_point', 'label' => 'Boarding', 'type' => 'text'],
                ['key' => 'destination', 'label' => 'Destination', 'type' => 'text'],
                ['key' => 'fare', 'label' => 'Fare', 'type' => 'money'],
                ['key' => 'payment_method', 'label' => 'Payment Method', 'type' => 'text'],
                ['key' => 'date_booked', 'label' => 'Date Booked', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'trip_operations_reports' => [
            'label' => 'Trip Operations',
            'description' => 'Trip operations with route, driver, inspector, passenger count, revenue, expenses, and net income.',
            'category' => 'trip_operations_reports',
            'requires' => [],
            'filters' => ['period', 'driver', 'bus', 'status', 'search'],
            'status_options' => ['Profitable Operation', 'Low Revenue Operation'],
            'date_column' => 'tor.trip_date',
            'date_key' => 'trip_date',
            'columns' => [
                ['key' => 'trip_date', 'label' => 'Trip Date', 'type' => 'date'],
                ['key' => 'trip_group_code', 'label' => 'Trip Code', 'type' => 'text'],
                ['key' => 'driver_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'inspector_name', 'label' => 'Inspector', 'type' => 'text'],
                ['key' => 'bus_id', 'label' => 'Bus', 'type' => 'text'],
                ['key' => 'route_id', 'label' => 'Route', 'type' => 'text'],
                ['key' => 'passenger_count', 'label' => 'Passengers', 'type' => 'int'],
                ['key' => 'total_revenue', 'label' => 'Revenue', 'type' => 'money'],
                ['key' => 'fuel_cost', 'label' => 'Fuel Cost', 'type' => 'money'],
                ['key' => 'other_expenses', 'label' => 'Other Expenses', 'type' => 'money'],
                ['key' => 'net_income', 'label' => 'Net Income', 'type' => 'money'],
                ['key' => 'operation_status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'created_at', 'label' => 'Recorded', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'trip_operation_evidences' => [
            'label' => 'Trip Evidences',
            'description' => 'Trip operation evidence files with upload status and the linked operation summary.',
            'category' => 'trip_operation_evidences',
            'requires' => [],
            'filters' => ['period', 'driver', 'status', 'search'],
            'status_options' => ['Evidence Available', 'No Evidence Uploaded'],
            'date_column' => 'toe.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'created_at', 'label' => 'Uploaded', 'type' => 'datetime'],
                ['key' => 'trip_group_code', 'label' => 'Trip Code', 'type' => 'text'],
                ['key' => 'trip_date', 'label' => 'Trip Date', 'type' => 'date'],
                ['key' => 'driver_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'evidence_type', 'label' => 'Evidence Type', 'type' => 'text'],
                ['key' => 'evidence_file', 'label' => 'File', 'type' => 'text'],
                ['key' => 'evidence_status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'passenger_count', 'label' => 'Passengers', 'type' => 'int'],
                ['key' => 'total_revenue', 'label' => 'Revenue', 'type' => 'money'],
                ['key' => 'net_income', 'label' => 'Net Income', 'type' => 'money'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'ai_trip_operations_reports' => [
            'label' => 'AI Operations Report',
            'description' => 'AI-assisted trip operations reports with title, category, and generation time.',
            'category' => 'ai_trip_operations_reports',
            'requires' => [],
            'filters' => ['period', 'search'],
            'date_column' => 'generated_at',
            'date_key' => 'generated_at',
            'columns' => [
                ['key' => 'report_title', 'label' => 'Report Title', 'type' => 'text'],
                ['key' => 'report_category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'generated_at', 'label' => 'Generated', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'route_stops' => [
            'label' => 'Route Stops',
            'description' => 'Route stop sequences with coordinates and stop category.',
            'category' => 'route_stops',
            'requires' => [],
            'filters' => ['route', 'search'],
            'date_column' => null,
            'date_key' => null,
            'columns' => [
                ['key' => 'route_name', 'label' => 'Route', 'type' => 'text'],
                ['key' => 'stop_name', 'label' => 'Stop', 'type' => 'text'],
                ['key' => 'sequence_order', 'label' => 'Sequence', 'type' => 'int'],
                ['key' => 'stop_category', 'label' => 'Category', 'type' => 'status'],
                ['key' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
                ['key' => 'longitude', 'label' => 'Longitude', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'Recorded', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'fares' => [
            'label' => 'Fare Matrix',
            'description' => 'Fare matrix with routes and regular, student, and special fares.',
            'category' => 'fares',
            'requires' => [],
            'filters' => ['period', 'search'],
            'date_column' => 'created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'route_from', 'label' => 'From', 'type' => 'text'],
                ['key' => 'route_to', 'label' => 'To', 'type' => 'text'],
                ['key' => 'fare_regular', 'label' => 'Regular', 'type' => 'money'],
                ['key' => 'fare_student', 'label' => 'Student', 'type' => 'money'],
                ['key' => 'fare_special', 'label' => 'Special', 'type' => 'money'],
                ['key' => 'created_at', 'label' => 'Recorded', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'remittance_logs' => [
            'label' => 'Remittance Logs',
            'description' => 'Driver remittances with expected versus actual cash and discrepancy details.',
            'category' => 'remittance_logs',
            'requires' => [],
            'filters' => ['period', 'driver', 'bus', 'status', 'search'],
            'status_source' => ['table' => 'remittance_logs', 'column' => 'status'],
            'date_column' => 'rl.created_at',
            'date_key' => 'created_at',
            'columns' => [
                ['key' => 'created_at', 'label' => 'Submitted', 'type' => 'datetime'],
                ['key' => 'finance_code', 'label' => 'Finance Code', 'type' => 'text'],
                ['key' => 'trip_no', 'label' => 'Trip No.', 'type' => 'text'],
                ['key' => 'full_name', 'label' => 'Driver', 'type' => 'text'],
                ['key' => 'bus_no', 'label' => 'Bus', 'type' => 'text'],
                ['key' => 'total_passengers', 'label' => 'Passengers', 'type' => 'int'],
                ['key' => 'total_collection', 'label' => 'Collection', 'type' => 'money'],
                ['key' => 'expected_cash', 'label' => 'Expected', 'type' => 'money'],
                ['key' => 'actual_cash', 'label' => 'Actual', 'type' => 'money'],
                ['key' => 'discrepancy_amount', 'label' => 'Discrepancy', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'financial_condition', 'label' => 'Condition', 'type' => 'status'],
                ['key' => 'verified_at', 'label' => 'Verified', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],

        'inspectors' => [
            'label' => 'Inspector Activity',
            'description' => 'Inspector profiles with department, assignments, and account status.',
            'category' => 'inspectors',
            'requires' => [],
            'filters' => ['period', 'status', 'search'],
            'status_source' => ['table' => 'inspector_accounts', 'column' => 'status'],
            'date_column' => 'ia.created_at',
            'date_key' => 'account_created_at',
            'columns' => [
                ['key' => 'inspector_name', 'label' => 'Inspector', 'type' => 'text'],
                ['key' => 'department', 'label' => 'Department', 'type' => 'text'],
                ['key' => 'experience_years', 'label' => 'Experience (yrs)', 'type' => 'int'],
                ['key' => 'operational_assignment', 'label' => 'Assignment', 'type' => 'text'],
                ['key' => 'assigned_routes', 'label' => 'Assigned Routes', 'type' => 'text'],
                ['key' => 'contact_number', 'label' => 'Contact', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'account_created_at', 'label' => 'Account Created', 'type' => 'datetime'],
            ],
            'formats' => ['pdf', 'csv'],
        ],
    ];
}

function rgen_get_type(string $type): ?array
{
    $registry = rgen_registry();
    return $registry[$type] ?? null;
}

/**
 * Presentation grouping for the report type selector.
 */
function rgen_type_groups(): array
{
    return [
        'drivers' => 'Drivers',
        'driver_performance' => 'Drivers',
        'driver_accounts' => 'Drivers',
        'driver_bus_assignments' => 'Drivers',
        'passengers' => 'Passengers',
        'passenger_trips' => 'Passengers',
        'passenger_trip_history' => 'Passengers',
        'trip_operations_reports' => 'Trips & Routes',
        'trip_operation_evidences' => 'Trips & Routes',
        'ai_trip_operations_reports' => 'Trips & Routes',
        'route_stops' => 'Trips & Routes',
        'fares' => 'Finance',
        'remittance_logs' => 'Finance',
        'inspectors' => 'Inspectors',
    ];
}

/**
 * Resolve report periods and validate user-supplied dates.
 * Custom ranges must be two valid YYYY-MM-DD dates with start <= end.
 */
function rgen_resolve_period(string $mode, string $start, string $end): array
{
    $mode = strtolower(trim($mode));
    if ($mode === '' || !isset(rgen_period_modes()[$mode])) {
        $mode = 'monthly';
    }

    if ($mode === 'custom') {
        $okStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1;
        $okEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) === 1;
        if (!$okStart || !$okEnd) {
            throw new InvalidArgumentException('Enter a valid Start Date and End Date for the custom range.');
        }
        if ($start > $end) {
            throw new InvalidArgumentException('Start Date must not be after End Date.');
        }
    }

    date_default_timezone_set('Asia/Manila');
    [$rangeStart, $rangeEnd, $resolved] = dpm_period_range([
        'period' => $mode,
        'start' => $start,
        'end' => $end,
    ]);

    return [
        'mode' => $resolved,
        'start' => $rangeStart,
        'end' => $rangeEnd,
        'start_date' => substr($rangeStart, 0, 10),
        'end_date' => substr($rangeEnd, 0, 10),
        'label' => dpm_period_label($resolved, $rangeStart, $rangeEnd),
    ];
}

/**
 * Normalize an incoming filter payload for a report type.
 * Only filters the registry declares for the type are kept.
 */
function rgen_normalize_filters(string $type, array $input): array
{
    $def = rgen_get_type($type);
    if ($def === null) {
        throw new InvalidArgumentException('Unknown report type.');
    }

    $allowed = $def['filters'] ?? [];
    $filters = [];

    if (in_array('period', $allowed, true)) {
        $filters['period'] = rgen_resolve_period(
            (string)($input['period'] ?? 'monthly'),
            (string)($input['start'] ?? ''),
            (string)($input['end'] ?? '')
        );
    }

    foreach (['driver' => 'driver_id', 'bus' => 'bus_no', 'status' => 'status', 'search' => 'search', 'route' => 'route'] as $filterKey => $field) {
        if (!in_array($filterKey, $allowed, true)) {
            continue;
        }
        $value = trim((string)($input[$field] ?? $input[$filterKey] ?? ''));
        if ($value !== '') {
            $filters[$field] = $value;
        }
    }

    return $filters;
}
