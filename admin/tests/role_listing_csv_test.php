<?php declare(strict_types=1);

/**
 * Pure-logic checks for the Role Management CSV export shaping.
 * No database or session is required.
 */

require_once __DIR__ . '/../services/RoleManagement/RoleListingCsv.php';

use Admin\Services\RoleManagement\RoleListingCsv;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(RoleListingCsv::headers() === [
    'User ID', 'Full Name', 'Role', 'Email', 'Contact', 'Status', 'Department', 'Assignment', 'Date Created',
], 'Exported headers match the documented columns');

$row = RoleListingCsv::row([
    'user_id' => 'DRV-001',
    'name' => 'Juan Dela Cruz',
    'role_label' => 'Driver',
    'email' => 'juan@example.com',
    'contact' => '09171234567',
    'status_label' => 'Active',
    'department' => 'Operations',
    'assignment' => 'Route A',
    'created_at' => '2026-01-05 09:30:00',
]);
check($row === [
    'DRV-001', 'Juan Dela Cruz', 'Driver', 'juan@example.com', '09171234567', 'Active', 'Operations', 'Route A', '2026-01-05 09:30:00',
], 'Row maps the actual record values in column order');

check(RoleListingCsv::neutralize('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'Leading = is neutralized');
check(RoleListingCsv::neutralize('+639171234567') === "'+639171234567", 'Leading + is neutralized');
check(RoleListingCsv::neutralize('-formula') === "'-formula", 'Leading - is neutralized');
check(RoleListingCsv::neutralize('@command') === "'@command", 'Leading @ is neutralized');
check(RoleListingCsv::neutralize("\tTabbed") === "'\tTabbed", 'Leading tab is neutralized');
check(RoleListingCsv::neutralize("\rReturn") === "'\rReturn", 'Leading CR is neutralized');
check(RoleListingCsv::neutralize('Juan Dela Cruz') === 'Juan Dela Cruz', 'Normal text is unchanged');
check(RoleListingCsv::neutralize('') === '', 'Empty values stay empty');
check(RoleListingCsv::neutralize('safe=value') === 'safe=value', 'A formula character inside a value is not altered');

$formulaRow = RoleListingCsv::row(['name' => '=cmd|calc', 'user_id' => '@user', 'role_label' => 'Driver']);
check($formulaRow[0] === "'@user" && $formulaRow[1] === "'=cmd|calc", 'Every cell of a row is neutralized');

echo "$checks role management CSV checks passed.\n";
