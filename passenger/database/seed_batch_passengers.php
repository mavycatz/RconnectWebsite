<?php

/**
 * SEED BATCH COLLECTION TEST DATA
 *
 * Usage: php passenger/database/seed_batch_passengers.php
 *
 * What it does:
 *   1. Picks the first ACTIVE passenger from the `passengers` table.
 *   2. Finds that passenger's most recent onboard trip
 *      (passenger_trips row with dropoff_time IS NULL), falling back
 *      to the most recent trip by id if none is onboard.
 *   3. Inserts 2 additional passenger_trips rows that are equivalent
 *      to the source row (same boarding, destination, fare, location,
 *      payment, trip_status, etc.) so the batch collection list shows
 *      3 passengers for that trip instead of 1.
 *
 * WARNING: This inserts real rows into the `passenger_trips` table.
 * Only run it against test/development databases. Rollback = delete
 * the printed inserted row ids.
 *
 * No web server or session required.
 */

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

try {
    $conn = Database::get();

    // ---------------------------------------------------------------
    // 1. First active passenger
    // ---------------------------------------------------------------
    $stmt = $conn->prepare("SELECT * FROM passengers WHERE status = 'Active' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $passenger = $stmt->fetch();

    if (!$passenger) {
        fail("No active passenger found in the `passengers` table (status = 'Active').");
    }

    $passengerId = (int)$passenger['id'];
    $passengerName = $passenger['full_name'] ?? ('#' . $passengerId);

    // ---------------------------------------------------------------
    // 2. Source trip: latest onboard row, else most recent by id
    // ---------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT * FROM passenger_trips
        WHERE passenger_id = ?
        ORDER BY (dropoff_time IS NULL) DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$passengerId]);
    $sourceTrip = $stmt->fetch();

    if (!$sourceTrip) {
        fail("Passenger {$passengerName} (id {$passengerId}) has no rows in `passenger_trips`.");
    }

    $sourceTripId = (int)$sourceTrip['id'];

    // ---------------------------------------------------------------
    // 3. Build dynamic INSERT from live table columns (schema-drift safe)
    // ---------------------------------------------------------------
    $columns = [];
    foreach ($conn->query("SHOW COLUMNS FROM passenger_trips") as $col) {
        $columns[] = $col['Field'];
    }

    $skip = ['id']; // auto-increment
    $copyable = array_values(array_diff($columns, $skip));

    $insertCols = [];
    $values = [];
    $placeholders = [];
    foreach ($copyable as $col) {
        if ($col === 'dropoff_time') {
            $value = null; // force onboard
        } else {
            $value = $sourceTrip[$col] ?? null;
        }
        $insertCols[] = "`{$col}`";
        $values[] = $value;
        $placeholders[] = '?';
    }

    $sql = "INSERT INTO passenger_trips (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";

    // ---------------------------------------------------------------
    // 4. Insert exactly 2 additional rows in one transaction
    // ---------------------------------------------------------------
    $conn->beginTransaction();
    try {
        $insertStmt = $conn->prepare($sql);
        $insertedIds = [];
        for ($i = 0; $i < 2; $i++) {
            $insertStmt->execute($values);
            $insertedIds[] = (int)$conn->lastInsertId();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        fail("Insert failed, transaction rolled back: " . $e->getMessage());
    }

    // ---------------------------------------------------------------
    // 5. Summary
    // ---------------------------------------------------------------
    echo "Seeded batch collection test data\n";
    echo "  Source passenger: {$passengerName} (id {$passengerId})\n";
    echo "  Source trip id:   {$sourceTripId}\n";
    echo "  Inserted rows:    " . implode(', ', $insertedIds) . "\n";
    echo "  Total onboard rows for trip: 3\n";
    exit(0);
} catch (Throwable $e) {
    fail($e->getMessage());
}
