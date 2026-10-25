<?php
header('Content-Type: text/plain');
echo "PHP Works\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "DISpatcher dir: " . __DIR__ . "\n";

$sessionPath = __DIR__ . '/../storage/sessions';
echo "Session path: $sessionPath\n";
echo "Session dir exists: " . (is_dir($sessionPath) ? 'yes' : 'no') . "\n";
echo "Session dir writable: " . (is_writable($sessionPath) ? 'yes' : 'no') . "\n";

// Test write
$testFile = __DIR__ . '/../storage/logs/.diag_test';
@mkdir(__DIR__ . '/../storage/logs', 0777, true);
$written = @file_put_contents($testFile, date('Y-m-d H:i:s') . " - diag.php accessed\n", FILE_APPEND);
echo "Test write to logs/.diag_test: " . ($written !== false ? 'OK (' . $written . ' bytes)' : 'FAILED') . "\n";

echo "\nDone\n";
