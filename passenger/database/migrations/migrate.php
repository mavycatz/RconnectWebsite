<?php

require_once __DIR__ . '/../../../app/Core/Database.php';

$db = App\Core\Database::get();

$migrationsDir = __DIR__;
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    if ($filename === 'migrate.php') continue;

    echo "Running migration: $filename\n";

    $sql = file_get_contents($file);
    if (empty(trim($sql))) {
        echo "  Skipped (empty)\n";
        continue;
    }

    try {
        $db->exec($sql);
        echo "  Done.\n";
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

echo "All migrations complete.\n";
