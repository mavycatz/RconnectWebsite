<?php

/**
 * OFFLINE TEST — runs the backfill core against the SQL dump data
 * (no database connection needed). Proves the assignment logic is correct
 * before running against the live DB.
 *
 * Usage: php passenger/database/backfill_offline_test.php
 */

require_once __DIR__ . '/backfill_core.php';

function extractRows($sqlFile)
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read $sqlFile\n");
        exit(1);
    }

    preg_match('/INSERT INTO `\w+` \([^)]+\) VALUES\s*(.*?);/s', $sql, $m);
    if (empty($m)) {
        fwrite(STDERR, "No INSERT found in $sqlFile\n");
        exit(1);
    }

    // Quote-aware tuple splitter: handles ',', '(', ')' and escaped quotes
    // (both '' and \') inside string values (e.g. '3Maria\'s Store').
    $tuples = [];
    $values = $m[1];
    $len = strlen($values);
    $i = 0;
    while ($i < $len) {
        if ($values[$i] !== '(') {
            $i++;
            continue;
        }
        $buf = '';
        $inStr = false;
        $i++;
        while ($i < $len) {
            $c = $values[$i];
            if ($c === '\\' && $inStr && $i + 1 < $len && ($values[$i + 1] === "'" || $values[$i + 1] === '\\')) {
                $buf .= $c . $values[$i + 1];
                $i += 2;
                continue;
            }
            if ($c === "'") {
                if ($inStr && $i + 1 < $len && $values[$i + 1] === "'") {
                    $buf .= "''";
                    $i += 2;
                    continue;
                }
                $inStr = !$inStr;
                $buf .= $c;
                $i++;
                continue;
            }
            if ($c === ')' && !$inStr) {
                $tuples[] = $buf;
                $i++;
                break;
            }
            $buf .= $c;
            $i++;
        }
    }

    $colStmt = preg_match('/`id`, `route_name`, `stop_name`, `latitude`, `longitude`, `sequence_order`, `created_at`, `km_marker`, `fare_pricing_id`/', $sql)
        ? 'stops'
        : 'fares';

    $rows = [];

    foreach ($tuples as $t) {
        $parts = [];
        $buf = '';
        $inStr = false;
        $len = strlen($t);
        for ($i = 0; $i < $len; $i++) {
            $c = $t[$i];
            if ($c === '\\' && $inStr && $i + 1 < $len && ($t[$i + 1] === "'" || $t[$i + 1] === '\\')) {
                $buf .= $c . $t[$i + 1];
                $i++;
            } elseif ($c === "'") {
                if ($inStr && $i + 1 < $len && $t[$i + 1] === "'") {
                    $buf .= "''";
                    $i++;
                } else {
                    $inStr = !$inStr;
                    $buf .= $c;
                }
            } elseif ($c === ',' && !$inStr) {
                $parts[] = trim($buf);
                $buf = '';
            } else {
                $buf .= $c;
            }
        }
        if (trim($buf) !== '') {
            $parts[] = trim($buf);
        }

        foreach ($parts as $k => $p) {
            if (strpos($p, "'") === 0) {
                $parts[$k] = str_replace(["\\'", "''"], ["'", "'"], substr($p, 1, -1));
            }
        }

        if ($colStmt === 'stops') {
            $rows[] = [
                'id' => (int)$parts[0],
                'route_name' => $parts[1],
                'stop_name' => $parts[2],
                'latitude' => (float)$parts[3],
                'longitude' => (float)$parts[4],
                'sequence_order' => (int)$parts[5],
                'created_at' => $parts[6],
                'km_marker' => (int)$parts[7],
                'fare_pricing_id' => (int)$parts[8],
            ];
        } else {
            $rows[] = [
                'id' => (int)$parts[0],
                'route_name' => $parts[1],
                'destination' => $parts[2],
                'regular_fare' => (float)$parts[3],
                'student_fare' => (float)$parts[4],
                'special_fare' => (float)$parts[5],
                'created_at' => $parts[6],
                'updated_at' => $parts[7],
                'display_order' => (int)$parts[8],
            ];
        }
    }

    return $rows;
}

$stops = extractRows(__DIR__ . '/../sql/route_stops (7).sql');
$fares = extractRows(__DIR__ . '/../sql/fare_pricing (3).sql');

echo "Loaded " . count($stops) . " stops, " . count($fares) . " fares\n\n";

[$matched, $unmatched, $lines] = backfillRouteStopFares($stops, $fares);

foreach ($lines as $line) {
    echo $line . "\n";
}

echo "\nDone. Matched: $matched, Unmatched: $unmatched\n";

// Regression expectations: stop id -> expected fare_pricing_id
$expectations = [
    99 => 31,   // Homestead 1 (dir 2)
    97 => 30,   // Homestead 2 (dir 2)
    43 => 89,   // San Ricardo (dir 1)
    94 => 29,   // San Ricardo (dir 2)
    44 => 90,   // General Luna (dir 1)
    45 => 90,   // General Luna - Talabutab Rd (dir 1)
    92 => 28,   // General Luna - Talabutab Rd (dir 2)
    93 => 28,   // General Luna (dir 2)
    57 => 97,   // Junction @ km 34 (dir 1)
    79 => 21,   // Kanto Sitio Kaunlaran (dir 2)
    27 => 82,   // Carmen-Cabanatuan Road (dir 1)
    28 => 82,   // Welcome Arch - Talavera Exit (dir 1)
    1  => 80,   // Cabanatuan Terminal (dir 1)
    138 => 39,  // Cabanatuan Terminal (dir 2)
    104 => 33,  // Pinagpanaan (7/11) (dir 2)
    106 => 33,  // Pinagpanaan (dir 2)
    37 => 86,   // 3Maria's Store (dir 1, km 15)
    100 => 31,  // 3Maria's Store (dir 2, km 21)
    25 => 82,   // Mayapyap (dir 1)
    56 => 97,   // Aglipay (Curva) (dir 1)
    116 => 37,  // Daang Sarile (dir 2)
    21 => 80,   // Daang Sarile (dir 1)
    120 => 38,  // Bitas (dir 2)
    38 => 87,   // Homestead 1 (dir 1)
    40 => 88,   // Homestead 2 (dir 1)
    35 => 86,   // Baluga (dir 1)
    102 => 32,  // Baluga (dir 2)
    31 => 85,   // Pinagpanaan (dir 1)
    59 => 98,   // Del Pilar (dir 1)
    62 => 99,   // New Rizal Municipal (dir 1)
    71 => 21,   // Poblacion Sur (dir 2)
    34 => 85,   // Junction Pinagpanaan @ km 13 (dir 1)
    36 => 86,   // 399 Junction @ km 15 (dir 1)
    41 => 88,   // Junction Pinagpanaan @ km 19 (dir 1)
    54 => 90,   // Junction @ km 25 (dir 1)
    61 => 98,   // Junction @ km 35 (dir 1)
    65 => 99,   // Junction @ km 39 (dir 1)
    72 => 21,   // Junction @ km 1 (dir 2)
    83 => 23,   // Junction @ km 9 (dir 2)
    96 => 29,   // Junction @ km 19 (dir 2)
    103 => 32,  // Junction @ km 24 (dir 2)
];

$assigned = [];
foreach ($lines as $line) {
    if (preg_match('/^id=(\d+) .*?-> fare_pricing_id=(\d+) /', $line, $mm)) {
        $assigned[(int)$mm[1]] = (int)$mm[2];
    }
}

$fail = 0;
foreach ($expectations as $stopId => $wantFare) {
    $got = $assigned[$stopId] ?? null;
    $status = ($got === $wantFare) ? 'PASS' : 'FAIL';
    if ($got !== $wantFare) {
        $fail++;
    }
    echo sprintf("  %-4s stop id %-4d -> expected %d, got %s\n", $status, $stopId, $wantFare, var_export($got, true));
}

echo $fail === 0 ? "\nAll expectations PASS.\n" : "\n$fail expectation(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
