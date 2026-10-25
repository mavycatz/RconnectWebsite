<?php
declare(strict_types=1);
// Pure-logic checks for the passenger account rules. No database connection
// is opened: only static helpers over in-memory arrays are exercised.
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/PassengerCodeService.php';
require_once __DIR__ . '/../services/PassengerAccountService.php';

$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}

// ---- Passenger ID prefixes (STD / SEN / STU / PWD) ----
$prefixes = [
    'Regular' => 'STD', 'Standard' => 'STD', 'Student' => 'STU', 'Senior' => 'SEN',
    'Senior Citizen' => 'SEN', 'PWD' => 'PWD', 'Person With Disability' => 'PWD',
    'pwd' => 'PWD', 'teacher' => 'TCH', 'nurse' => 'NUR',
];
foreach ($prefixes as $type => $expected) {
    check(PassengerCodeService::prefixForType($type) === $expected, "Prefix for $type must be $expected");
}
check(PassengerCodeService::prefixForType('Unknown Type') === 'STD', 'Unknown classifications fall back to the Standard prefix');

// ---- Verification gates discounted classifications ----
check(PassengerCodeService::effectiveType(['discount_type' => 'Student', 'discount_status' => 'Pending']) === 'Regular', 'Unverified Student remains Standard');
check(PassengerCodeService::effectiveType(['discount_type' => 'Student', 'discount_status' => 'approved']) === 'Student', 'Approved Student is Student');
check(PassengerCodeService::effectiveType(['discount_type' => 'Senior', 'discount_status' => 'Verified']) === 'Senior', 'Verified Senior is Senior');
check(PassengerCodeService::effectiveType(['discount_type' => 'PWD', 'discount_status' => 'pending']) === 'Regular', 'Pending PWD remains Standard');
check(PassengerCodeService::effectiveType(['discount_type' => '']) === 'Regular', 'Empty type is Regular');
check(PassengerCodeService::effectiveType(['type' => 'student', 'discount_type' => 'Regular']) === 'Regular', 'Explicit Regular type wins over a legacy student hint');

// ---- Effective account status and automatic suspension expiry ----
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$future = fn(int $days) => $now->modify("+$days days")->format('Y-m-d H:i:s');
$past = fn(int $days) => $now->modify("-$days days")->format('Y-m-d H:i:s');

check(PassengerAccountService::effectiveStatus([]) === 'ACTIVE', 'Missing status defaults to Active');
check(PassengerAccountService::effectiveStatus(['account_status' => 'BLOCKED']) === 'BLOCKED', 'Blocked stays Blocked');
check(PassengerAccountService::effectiveStatus(['status' => 'Archived']) === 'BLOCKED', 'Archived legacy rows are treated as blocked restrictions');
check(PassengerAccountService::effectiveStatus(['status' => 'Blocked']) === 'BLOCKED', 'Legacy Blocked stays Blocked');
check(PassengerAccountService::effectiveStatus(['account_status' => 'SUSPENDED', 'suspension_starts_at' => $past(1), 'suspension_ends_at' => $future(3)]) === 'SUSPENDED', 'Active suspension window is Suspended');
check(PassengerAccountService::effectiveStatus(['account_status' => 'SUSPENDED', 'suspension_starts_at' => $past(5), 'suspension_ends_at' => $past(1)]) === 'ACTIVE', 'Expired suspension automatically returns to Active');
check(PassengerAccountService::effectiveStatus(['account_status' => 'SUSPENDED', 'suspension_starts_at' => $future(1), 'suspension_ends_at' => $future(3)]) === 'ACTIVE', 'Scheduled future suspension is not yet active');
check(PassengerAccountService::normalizeStatus('suspended') === 'SUSPENDED', 'normalizeStatus maps suspended');
check(PassengerAccountService::normalizeStatus('archived') === 'BLOCKED', 'normalizeStatus maps archived to blocked');

// ---- Verification status vocabulary ----
foreach ([
    'Pending' => 'pending', 'pending' => 'pending',
    'Verified' => 'verified', 'approved' => 'verified',
    'Needs_Review' => 'needs_review', 'needs review' => 'needs_review', 'review' => 'needs_review',
    'Expired' => 'expired', 'REJECTED' => 'rejected',
] as $input => $expected) {
    check(PassengerAccountService::normalizeVerification((string) $input) === $expected, "normalizeVerification('$input') must be $expected");
}
check(PassengerAccountService::normalizeVerification('unknown-state') === null, 'Unknown verification values are rejected');
check(PassengerAccountService::normalizeVerification('') === null, 'Empty verification values are rejected');
// Only verified/approved unlock discounted fares; every review state stays Regular.
foreach (['pending', 'needs_review', 'expired', 'rejected', ''] as $state) {
    check(PassengerCodeService::effectiveType(['discount_type' => 'Student', 'discount_status' => $state]) === 'Regular', "Student with '$state' verification stays Standard");
}
check(PassengerCodeService::effectiveType(['discount_type' => 'PWD', 'discount_status' => 'verified']) === 'PWD', 'Verified PWD is PWD');

// ---- Exact notification wording ----
$end = $future(2);
$suspend = PassengerAccountService::notice(['account_status' => 'SUSPENDED', 'suspension_starts_at' => $past(1), 'suspension_ends_at' => $end, 'status_reason' => 'Fare discrepancy']);
check(str_starts_with($suspend, 'Your Rconnect account has been temporarily suspended until '), 'Suspension notice opens with the required sentence');
check(str_contains($suspend, 'Reason: Fare discrepancy') && str_contains($suspend, 'Please contact the cooperative administrator if you believe this is a mistake.'), 'Suspension notice contains the reason and appeal sentence');
$suspendUntil = (new DateTimeImmutable($end))->format('F j, Y g:i A');
check(str_contains($suspend, $suspendUntil), 'Suspension notice contains the exact end date');
$blocked = PassengerAccountService::notice(['account_status' => 'BLOCKED']);
check($blocked === 'Your Rconnect account has been blocked. Please contact the cooperative administrator for further assistance.', 'Blocked notice matches the required wording exactly');
$active = PassengerAccountService::notice(['account_status' => 'ACTIVE']);
check($active === 'Your Rconnect account has been successfully reactivated. You can now access Rconnect services.', 'Reactivation notice matches the required wording exactly');

echo "$checks passenger account checks passed.\n";
