<?php declare(strict_types=1);

/**
 * BusUpdatePayload checks: a minimal edit (plate + bus type only) preserves
 * every other stored value, a full edit applies what is supplied, and the
 * additive columns are only written when the request provides them. Pure,
 * no database required.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/Bus/BusUpdatePayload.php';

use Admin\Services\Bus\BusUpdatePayload;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$config = require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/bus_options.php';

$current = [
    'id' => 7,
    'bus_id' => 'BUS-2001',
    'plate_number' => 'NGP-2212',
    'bus_type' => 'HINO Bus',
    'seats' => 24,
    'standing_capacity' => 11,
    'status' => 'Active',
    'driver' => 'DRV-001',
    'route_name' => 'Rizal - Cabanatuan',
    'terminal' => 'Rizal Central',
    'schedule' => '06:00 AM',
    'accessible_features' => 'Priority seats',
    'from_terminal_id' => 1,
    'to_terminal_id' => 2,
    'qr_code' => '/uploads/qr/bus2001.png',
    'operational_status' => 'Available',
    'unit_number' => '1201',
    'manufacturer' => 'SmokeCo',
    'registration_month' => 'February',
    'insurance_expiry' => '2027-01-01',
];

/* ---------------------- minimal edit (plate + bus type only) ---------------------- */

$built = BusUpdatePayload::build($current, [
    'bus_ref' => '7',
    'plate_number' => 'NGP-9999',
    'bus_type' => 'HINO Bus',
], $config);
$data = $built['data'];

check($data['plate_number'] === 'NGP-9999', 'The supplied plate is written');
check($data['bus_type'] === 'HINO Bus', 'The supplied bus type is written');
check($data['seats'] === 24 && $data['standing_capacity'] === 11, 'Capacity is preserved when not supplied');
check($data['status'] === 'Active', 'The lifecycle status is preserved');
check($data['driver'] === 'DRV-001', 'The driver assignment is preserved');
check($data['route_name'] === 'Rizal - Cabanatuan' && $data['terminal'] === 'Rizal Central', 'Route and terminal are preserved');
check($data['schedule'] === '06:00 AM' && $data['accessible_features'] === 'Priority seats', 'Schedule and features are preserved');
check($data['from_terminal_id'] === 1 && $data['to_terminal_id'] === 2, 'Terminal references are preserved');
check($data['qr_code'] === '/uploads/qr/bus2001.png', 'The QR mirror is preserved');

foreach (['unit_number', 'manufacturer', 'operational_status', 'registration_month', 'insurance_expiry', 'registration_expiry', 'assigned_inspector_id'] as $additive) {
    check(!array_key_exists($additive, $data), 'Additive column is not written by a minimal edit: ' . $additive);
}
check($built['driver_posted'] === false, 'The driver is only touched when supplied');

/* ------------------------------ full edit applies ------------------------------ */

$full = BusUpdatePayload::build($current, [
    'bus_ref' => '7',
    'plate_number' => 'NGP-2212',
    'bus_type' => 'HINO Bus',
    'seats' => '23',
    'standing_capacity' => '12',
    'status' => 'Inactive',
    'driver' => 'DRV-002',
    'route_name' => 'Rizal - Bongabon',
    'operational_status' => 'Under Maintenance',
    'unit_number' => '',
    'manufacturer' => '',
    'registration_month' => '',
    'insurance_expiry' => '2027-06-30',
], $config);
$fullData = $full['data'];

check($fullData['seats'] === 23 && $fullData['standing_capacity'] === 12, 'Supplied capacity is written');
check($fullData['status'] === 'Inactive', 'Supplied status is written');
check($fullData['driver'] === 'DRV-002' && $full['driver_posted'] === true, 'Supplied driver is written');
check($fullData['route_name'] === 'Rizal - Bongabon', 'Supplied route is written');
check(array_key_exists('operational_status', $fullData) && $fullData['operational_status'] === 'Under Maintenance', 'Supplied additive fields are written');
check(array_key_exists('unit_number', $fullData) && $fullData['unit_number'] === '', 'Explicitly cleared additive fields can be blanked');
check(array_key_exists('insurance_expiry', $fullData) && $fullData['insurance_expiry'] === '2027-06-30', 'Supplied compliance dates are written');
check(array_key_exists('registration_expiry', $fullData) && $fullData['registration_expiry'] === null, 'Date fields normalize consistently when dates are supplied');

/* ------------------------------- edge behaviors ------------------------------- */

$cleared = BusUpdatePayload::build($current, ['driver' => 'Unassigned'], $config);
check($cleared['driver'] === '' && $cleared['data']['driver'] === 'Unassigned', 'Clearing the driver normalizes to Unassigned');
check($cleared['data']['qr_code'] === '/uploads/qr/bus2001.png', 'Clearing the driver never touches the QR mirror');

$archived = BusUpdatePayload::build(array_merge($current, ['status' => 'Archived']), [
    'plate_number' => 'X',
    'bus_type' => 'HINO Bus',
    'status' => 'Active',
], $config);
check($archived['data']['status'] === 'Archived', 'Archived buses cannot be reactivated through an edit');

$typeOnly = BusUpdatePayload::build($current, ['plate_number' => 'NGP-0001'], $config);
check($typeOnly['data']['bus_type'] === 'HINO Bus', 'The stored type is preserved when the type is not supplied');

/* ------------------- capacity stays valid when the type changes ------------------- */

$emptyCapacity = array_merge($current, ['bus_type' => null, 'seats' => 0, 'standing_capacity' => 0]);
$typed = BusUpdatePayload::build($emptyCapacity, [
    'plate_number' => 'NID-6560',
    'bus_type' => 'HINO Bus',
], $config);
check($typed['data']['seats'] === 24 && $typed['data']['standing_capacity'] === 11, 'Typing an empty-capacity bus applies the HINO standard');

$switched = BusUpdatePayload::build($current, [
    'plate_number' => 'NGP-2212',
    'bus_type' => 'Higer Bus',
], $config);
check($switched['data']['seats'] === 26 && $switched['data']['standing_capacity'] === 14, 'Switching to Higer applies the Higer standard');

$inRange = BusUpdatePayload::build(array_merge($current, ['seats' => 23, 'standing_capacity' => 5]), [
    'plate_number' => 'NGP-2212',
    'bus_type' => 'HINO Bus',
], $config);
check($inRange['data']['seats'] === 23 && $inRange['data']['standing_capacity'] === 5, 'In-range stored capacity is preserved');

$capped = BusUpdatePayload::build(array_merge($current, ['seats' => 24, 'standing_capacity' => 20]), [
    'plate_number' => 'NGP-2212',
    'bus_type' => 'HINO Bus',
], $config);
check($capped['data']['seats'] === 24 && $capped['data']['standing_capacity'] === 11, 'Standing is brought within the type cap');

$explicit = BusUpdatePayload::build($current, [
    'plate_number' => 'NGP-2212',
    'bus_type' => 'HINO Bus',
    'seats' => '23',
    'standing_capacity' => '12',
], $config);
check($explicit['data']['seats'] === 23 && $explicit['data']['standing_capacity'] === 12, 'Explicitly supplied capacity is never overridden');

$noRule = BusUpdatePayload::build($emptyCapacity, [
    'plate_number' => 'NID-6560',
    'bus_type' => 'Coach',
], $config);
check($noRule['data']['seats'] === 0 && $noRule['data']['standing_capacity'] === 0, 'Types without a rule leave capacity untouched');

echo "$checks bus update payload checks passed.\n";
