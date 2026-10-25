<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

$db = Database::get();

// Set all NULL/empty generated_by values to 'Legacy Report'
$stmt = $db->exec("UPDATE financial_reports SET generated_by = 'Legacy Report' WHERE generated_by IS NULL OR generated_by = ''");

echo "Updated $stmt records.\nDone.\n";
