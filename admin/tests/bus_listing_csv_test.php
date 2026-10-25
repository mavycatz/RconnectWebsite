<?php declare(strict_types=1);

/**
 * Pure-logic checks for the Bus Management CSV export shaping.
 * No database or session is required.
 */

require_once __DIR__ . '/../services/Bus/BusListingCsv.php';

use Admin\Services\Bus\BusListingCsv;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(BusListingCsv::headers() === [
    'Bus ID', 'Plate Number', 'Unit Number', 'Bus Type', 'Manufacturer', 'Model', 'Year Model',
    'Seats', 'Standing Capacity', 'Assigned Driver', 'Assigned Route', 'Terminal', 'Schedule',
    'Status', 'Registration Month', 'Registration Expiry', 'Insurance Expiry', 'Next Inspection',
    'Date Registered',
], 'Exported headers match the documented columns');

$row = BusListingCsv::row([
    'bus_id' => 'BUS-004',
    'plate' => 'ABC 1234',
    'unit_number' => '1201',
    'bus_type' => 'Coach',
    'manufacturer' => 'Yutong',
    'model' => 'ZK6122',
    'year_model' => '2023',
    'seats' => 49,
    'standing_capacity' => 10,
    'driver_name' => 'Juan Dela Cruz',
    'route_name' => 'Cebu - Danao',
    'terminal' => 'Cebu South',
    'schedule' => '06:00 AM - 06:00 PM',
    'classification_label' => 'Active',
    'operational_status' => 'On Trip',
    'registration_month' => 'February',
    'registration_expiry' => '2027-05-01',
    'insurance_expiry' => '2026-12-31',
    'next_inspection_date' => '2026-11-15',
    'created_at' => '2026-01-05 09:30:00',
]);
check($row === [
    'BUS-004', 'ABC 1234', '1201', 'Coach', 'Yutong', 'ZK6122', '2023',
    '49', '10', 'Juan Dela Cruz', 'Cebu - Danao', 'Cebu South', '06:00 AM - 06:00 PM',
    'Active', 'February', '2027-05-01', '2026-12-31', '2026-11-15', '2026-01-05 09:30:00',
], 'Row maps the actual record values in column order and exports the lifecycle status, not the operational state');

check(BusListingCsv::neutralize('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'Leading = is neutralized');
check(BusListingCsv::neutralize('+639171234567') === "'+639171234567", 'Leading + is neutralized');
check(BusListingCsv::neutralize('-formula') === "'-formula", 'Leading - is neutralized');
check(BusListingCsv::neutralize('@command') === "'@command", 'Leading @ is neutralized');
check(BusListingCsv::neutralize("\tTabbed") === "'\tTabbed", 'Leading tab is neutralized');
check(BusListingCsv::neutralize("\rReturn") === "'\rReturn", 'Leading CR is neutralized');
check(BusListingCsv::neutralize('ABC 1234') === 'ABC 1234', 'Normal text is unchanged');
check(BusListingCsv::neutralize('') === '', 'Empty values stay empty');
check(BusListingCsv::neutralize('safe=value') === 'safe=value', 'A formula character inside a value is not altered');

$formulaRow = BusListingCsv::row(['bus_id' => '=cmd|calc', 'plate' => '@plate', 'classification_label' => 'Active']);
check($formulaRow[0] === "'=cmd|calc" && $formulaRow[1] === "'@plate", 'Every cell of a row is neutralized');

$operationalRow = BusListingCsv::row([
    'bus_id' => 'BUS-009',
    'classification_label' => 'Inactive',
    'operational_status' => 'Available',
]);
check($operationalRow[13] === 'Inactive', 'Status exports the lifecycle label even with an operational state set');
check(array_search('Available', $operationalRow, true) === false, 'Operational states never leak into export cells');

echo "$checks bus management CSV checks passed.\n";
