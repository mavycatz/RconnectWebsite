<?php declare(strict_types=1);

/**
 * BusAvailability checks: the boarding gate used by the passenger scanning
 * flow. No database or session is required.
 */

require_once __DIR__ . '/../services/BusAvailability.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(BusAvailability::boardingAllowed('Active', null), 'Active with no operational state boards');
check(BusAvailability::boardingAllowed('Active', ''), 'Active with empty operational state boards');
check(BusAvailability::boardingAllowed('active', 'Available'), 'Case-insensitive Active with Available boards');
check(BusAvailability::boardingAllowed('Active', 'On Trip'), 'On-Trip buses stay usable for active-trip operations');

check(!BusAvailability::boardingAllowed('Active', 'Under Maintenance'), 'Under Maintenance is blocked');
check(!BusAvailability::boardingAllowed('Active', 'Out of Service'), 'Out of Service is blocked');
check(!BusAvailability::boardingAllowed('Active', 'under maintenance'), 'Operational checks are case-insensitive');

check(!BusAvailability::boardingAllowed('Archived', null), 'Archived buses are blocked');
check(!BusAvailability::boardingAllowed('Archived', 'Available'), 'Archived stays blocked regardless of operational state');
check(!BusAvailability::boardingAllowed('Inactive', null), 'Inactive buses are blocked');
check(!BusAvailability::boardingAllowed('Pending QR', null), 'Pending buses are blocked');
check(!BusAvailability::boardingAllowed(null, null), 'A missing status is blocked');
check(!BusAvailability::boardingAllowed('', null), 'An empty status is blocked');

check(strpos(BusAvailability::reason('Active', 'Under Maintenance'), 'maintenance') !== false, 'Maintenance reason explains the refusal');
check(strpos(BusAvailability::reason('Active', 'Out of Service'), 'out of service') !== false, 'Out-of-service reason explains the refusal');
check(strpos(BusAvailability::reason('Archived', null), 'not available') !== false, 'Generic reason for lifecycle blocks');

echo "$checks bus availability checks passed.\n";
