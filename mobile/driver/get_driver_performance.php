<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $driver_id = trim($_GET['driver_id'] ?? '');

    if (empty($driver_id)) {
        echo json_encode(["success" => false, "message" => "driver_id required"]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | METRIC DEFINITIONS (from the actual schema used by existing APIs)
    |--------------------------------------------------------------------------
    | bus_trips: id, bus_id, route_name, trip_date, departure_time,
    |            arrival_time, total_passengers, total_revenue, status,
    |            trip_type, driver_id            (get_remittance_summary.php)
    | passenger_trips: id, driver_id, trip_no, boarding_time, ...            (get_passengers.php)
    | passenger_trip_history: history_id, driver_id, trip_id (e.g. "TRIP-001"),
    |                         trip_date, passenger_name, ...                  (admin table)
    |
    | - trip_completion: completed / (completed + cancelled)
    | - on_time:         completed trips covered within 2h 30m
    |                    (arrival_time - departure_time <= 150 min)
    | - attendance:      present = days with >= 1 completed trip;
    |                    absent  = fully elapsed days of the month without a
    |                    completed trip (missed days DO count)
    | - reliability:     trips not cancelled / all trips
    | - passengers:      COUNT(passenger_trips) + COUNT(passenger_trip_history)
    |                    per driver / day / month (history rows matched by
    |                    trip_id = bus_trips.trip_number)
    |
    | NOT recorded in this system: safety incidents (no table), scheduled
    | departure times (no column) - never reported.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    $pctOf = function ($num, $den) {
        return $den > 0 ? round(($num / $den) * 100) : null;
    };

    $breakdownRating = function ($pct) {
        if ($pct === null) return null;
        if ($pct >= 90) return 'Excellent';
        if ($pct >= 80) return 'Good';
        return 'Needs Improvement';
    };

    $scoreRating = function ($score) {
        if ($score === null) return null;
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Very Good';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Needs Improvement';
        return 'Poor';
    };

    // Weights: trip completion 30, on-time 25, attendance 20, reliability 5
    $WEIGHTS = [
        'trip_completion' => 30,
        'on_time'         => 25,
        'attendance'      => 20,
        'reliability'     => 5,
    ];

    $computeScore = function ($metrics) use ($WEIGHTS, $scoreRating) {
        if (empty($metrics)) {
            return ['value' => null, 'rating' => null, 'metrics_used' => []];
        }
        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($metrics as $key => $pct) {
            if ($pct === null) continue;
            $w = $WEIGHTS[$key] ?? 0;
            $totalWeight += $w;
            $weightedSum += $pct * $w;
        }
        if ($totalWeight <= 0) {
            return ['value' => null, 'rating' => null, 'metrics_used' => []];
        }
        $value = (int) round($weightedSum / $totalWeight);
        return [
            'value' => $value,
            'rating' => $scoreRating($value),
            'metrics_used' => array_keys($metrics),
        ];
    };

    // Trip duration in minutes (arrival - departure; handles overnight)
    $durationMinutes = function ($trip) {
        $dep = strtotime($trip['trip_date'] . ' ' . $trip['departure_time']);
        $arr = strtotime($trip['trip_date'] . ' ' . $trip['arrival_time']);
        if (!$dep || !$arr) return null;
        if ($arr < $dep) $arr += 86400; // crossed midnight
        return (int) round(($arr - $dep) / 60);
    };

    // On-time limit: a trip is late only when its duration exceeds 2h 30m.
    $ON_TIME_LIMIT = 150;

    // History-trip duration in minutes (time_of_departure -> time_of_arrival)
    $historyMinutes = function ($g) {
        if (empty($g['departure']) || empty($g['arrival'])) return null;
        $dep = strtotime($g['trip_date'] . ' ' . $g['departure']);
        $arr = strtotime($g['trip_date'] . ' ' . $g['arrival']);
        if (!$dep || !$arr) return null;
        if ($arr < $dep) $arr += 86400; // crossed midnight
        return (int) round(($arr - $dep) / 60);
    };

    // Normalizes a trip identifier for matching ("TRIP-001" -> "001")
    $normTripId = function ($v) {
        return strtoupper(preg_replace('/^TRIP-/', '', (string)$v));
    };

    /*
    |--------------------------------------------------------------------------
    | DRIVER IDENTITY + CURRENT STATUS
    |--------------------------------------------------------------------------
    */
    $driverStmt = $conn->prepare("SELECT driver_id, full_name, photo, status, assigned_buses, routes FROM drivers WHERE driver_id = ? LIMIT 1");
    if (!$driverStmt) throw new Exception($conn->error);
    $driverStmt->bind_param("s", $driver_id);
    $driverStmt->execute();
    $driverRow = $driverStmt->get_result()->fetch_assoc();
    $driverStmt->close();

    if (!$driverRow) {
        echo json_encode(["success" => false, "message" => "Driver not found"]);
        exit;
    }

    // Photo is served as an absolute URL (same convention as get_profile.php);
    // prefix relative paths so React Native's <Image> can load them.
    $photoUrl = $driverRow['photo'] ?? null;
    if ($photoUrl && stripos($photoUrl, 'http') !== 0) {
        $photoUrl = 'https://rconnecttms.online' . (str_starts_with($photoUrl, '/') ? '' : '/') . $photoUrl;
    }

    // Status is taken from ACTUAL trip activity, not the account status:
    // "On Trip" only when a trip is currently in progress today.
    $today = date('Y-m-d');
    $activeTripStmt = $conn->prepare(
        "SELECT route_name FROM bus_trips
         WHERE driver_id = ? AND trip_date = ? AND status IN ('Departed','On Route','Started','Active','Ongoing')
         ORDER BY departure_time DESC LIMIT 1"
    );
    if (!$activeTripStmt) throw new Exception($conn->error);
    $activeTripStmt->bind_param("ss", $driver_id, $today);
    $activeTripStmt->execute();
    $activeTrip = $activeTripStmt->get_result()->fetch_assoc();
    $activeTripStmt->close();

    if ($activeTrip) {
        $pillStatus = 'On Trip';
        $currentRoute = $activeTrip['route_name'];
    } else {
        $pillStatus = 'Off Duty';
        $currentRoute = null;
        $dbStatus = strtolower($driverRow['status'] ?? '');
        if (in_array($dbStatus, ['scheduled', 'waiting', 'available', 'ready', 'active'], true)) {
            $pillStatus = 'Available';
        }
    }
    if (!$currentRoute && !empty($driverRow['routes'])) $currentRoute = $driverRow['routes'];

    // Assigned bus: assignment table first, then drivers.assigned_buses
    $assignedBus = null;
    $assignedBusStmt = $conn->prepare("SELECT b.plate_number FROM driver_bus_assignment dba INNER JOIN buses b ON b.bus_id = dba.bus_id WHERE dba.driver_id = ? LIMIT 1");
    if ($assignedBusStmt) {
        $assignedBusStmt->bind_param("s", $driver_id);
        $assignedBusStmt->execute();
        $assignedBusRow = $assignedBusStmt->get_result()->fetch_assoc();
        $assignedBusStmt->close();
        if ($assignedBusRow) $assignedBus = $assignedBusRow['plate_number'];
    }
    if (empty($assignedBus)) $assignedBus = $driverRow['assigned_buses'] ?? null;
    if (!empty($assignedBus) && stripos((string)$assignedBus, 'bus') === false) {
        $assignedBus = 'Bus ' . $assignedBus;
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP RECORDS (bus_trips)
    |--------------------------------------------------------------------------
    */
    $trips = [];
    $tripStmt = $conn->prepare(
        "SELECT id, route_name, trip_date, trip_number, departure_time, arrival_time, status
         FROM bus_trips
         WHERE driver_id = ? AND trip_date <= ?
         ORDER BY trip_date DESC, departure_time DESC"
    );
    if (!$tripStmt) throw new Exception($conn->error);
    $tripStmt->bind_param("ss", $driver_id, $today);
    $tripStmt->execute();
    $tripResult = $tripStmt->get_result();
    while ($row = $tripResult->fetch_assoc()) { $trips[] = $row; }
    $tripStmt->close();

    $totalTrips = count($trips);
    $completedTrips = 0;
    $cancelledTrips = 0;
    $onTimeTrips = 0;

    // Per-day aggregation: completed / cancelled / other (in-progress or
    // not-completed trips all count against the day's performance).
    $dayMap = [];
    foreach ($trips as $trip) {
        $status = strtolower($trip['status'] ?? '');
        $date = $trip['trip_date'];

        if (!isset($dayMap[$date])) $dayMap[$date] = ['completed' => 0, 'cancelled' => 0, 'other' => 0];

        if ($status === 'completed') {
            $completedTrips++;
            $dayMap[$date]['completed']++;
            $mins = $durationMinutes($trip);
            if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTimeTrips++;
        } elseif ($status === 'cancelled' || $status === 'no-show' || $status === 'no_show') {
            $cancelledTrips++;
            $dayMap[$date]['cancelled']++;
        } else {
            $dayMap[$date]['other']++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORY TRIPS (passenger_trip_history as a completed-trip source)
    |--------------------------------------------------------------------------
    | Drivers may have passenger records but no bus_trips rows. History rows
    | are grouped into completed trips (trip_id + trip_date). Groups whose
    | trip_id matches an existing bus_trips.trip_number are skipped here -
    | their passengers are already counted on that trip.
    |--------------------------------------------------------------------------
    */
    $historyRows = [];
    $hStmt = $conn->prepare(
        "SELECT history_id, trip_id, trip_date, time_of_departure, time_of_arrival,
                bus_number, boarding_point, destination
         FROM passenger_trip_history
         WHERE driver_id = ? AND trip_date <= ?
         ORDER BY trip_date DESC, time_of_departure DESC"
    );
    if ($hStmt) {
        $hStmt->bind_param("ss", $driver_id, $today);
        $hStmt->execute();
        $hRes = $hStmt->get_result();
        while ($hRow = $hRes->fetch_assoc()) { $historyRows[] = $hRow; }
        $hStmt->close();
    }

    $busTripNumbers = [];
    foreach ($trips as $t) {
        if (!empty($t['trip_number'])) $busTripNumbers[] = $normTripId($t['trip_number']);
    }

    $historyGroups = [];
    foreach ($historyRows as $row) {
        $tid = trim((string)($row['trip_id'] ?? ''));
        $date = $row['trip_date'];
        $key = ($tid !== '' ? $tid : 'date:' . $date) . '|' . $date;
        if (!isset($historyGroups[$key])) {
            $historyGroups[$key] = [
                'trip_id' => $tid,
                'trip_date' => $date,
                'bus_number' => $row['bus_number'] ?? '',
                'boarding' => $row['boarding_point'] ?? '',
                'destination' => $row['destination'] ?? '',
                'departure' => $row['time_of_departure'] ?? '',
                'arrival' => $row['time_of_arrival'] ?? '',
                'history_id' => $row['history_id'] ?? '',
                'passengers' => 0,
            ];
        }
        $historyGroups[$key]['passengers']++;
    }

    $historyTripList = [];
    foreach ($historyGroups as $g) {
        $normId = $normTripId($g['trip_id']);
        if ($normId !== '' && in_array($normId, $busTripNumbers, true)) continue;
        $historyTripList[] = $g;
    }

    // History trips count as completed trips: totals, on-time, and per-day
    // presence (attendance/trend react to them).
    $completedFromHistory = 0;
    $onTimeFromHistory = 0;
    foreach ($historyTripList as $g) {
        $completedFromHistory++;
        $date = $g['trip_date'];
        if (!isset($dayMap[$date])) $dayMap[$date] = ['completed' => 0, 'cancelled' => 0, 'other' => 0];
        $dayMap[$date]['completed']++;
        $mins = $historyMinutes($g);
        if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTimeFromHistory++;
    }
    $totalTrips += $completedFromHistory;
    $completedTrips += $completedFromHistory;
    $onTimeTrips += $onTimeFromHistory;

    /*
    |--------------------------------------------------------------------------
    | PASSENGER COUNTS (passenger_trips + passenger_trip_history)
    |--------------------------------------------------------------------------
    */
    $passengerCount = function ($from, $to) use ($conn, $driver_id) {
        $total = 0;
        $fromDT = "$from 00:00:00";
        $toDT = "$to 23:59:59";

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM passenger_trips
             WHERE driver_id = ? AND boarding_time >= ? AND boarding_time <= ?"
        );
        if ($stmt) {
            $stmt->bind_param("sss", $driver_id, $fromDT, $toDT);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $total += (int)($row['total'] ?? 0);
        }

        $hist = $conn->prepare(
            "SELECT COUNT(*) AS total FROM passenger_trip_history
             WHERE driver_id = ? AND trip_date >= ? AND trip_date <= ?"
        );
        if ($hist) {
            $hist->bind_param("sss", $driver_id, $from, $to);
            $hist->execute();
            $hRow = $hist->get_result()->fetch_assoc();
            $hist->close();
            $total += (int)($hRow['total'] ?? 0);
        }

        return $total;
    };

    $passengerTotal = $passengerCount('2000-01-01', $today);

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE (present = completed-trip days, absent = missed elapsed days)
    |--------------------------------------------------------------------------
    */
    $monthFrom = date('Y-m-01');
    $monthTo = date('Y-m-t');
    $prevMonthFrom = date('Y-m-01', strtotime('first day of last month'));
    $prevMonthTo = date('Y-m-t', strtotime('last day of last month'));

    $attendanceFor = function ($from, $to, $includeFuture) use ($dayMap, $today) {
        $present = 0;
        $absent = 0;
        $calendar = [];
        $cursor = new DateTime($from);
        $end = new DateTime($to);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            if ($date > $today) {
                $calendar[] = ['date' => $date, 'status' => 'future'];
            } elseif ($date === $today) {
                $completed = $dayMap[$date]['completed'] ?? 0;
                if ($completed > 0) {
                    $calendar[] = ['date' => $date, 'status' => 'present'];
                    $present++;
                } else {
                    // Today is not complete yet - never marked absent.
                    $calendar[] = ['date' => $date, 'status' => 'today'];
                }
            } else {
                $completed = $dayMap[$date]['completed'] ?? 0;
                $status = $completed > 0 ? 'present' : 'absent';
                $calendar[] = ['date' => $date, 'status' => $status];
                if ($status === 'present') $present++;
                else $absent++;
            }
            $cursor->modify('+1 day');
        }
        return ['present' => $present, 'absent' => $absent, 'calendar' => $calendar];
    };

    $curAtt = $attendanceFor($monthFrom, $monthTo, false);
    $attendance = [
        'available' => true,
        'rate_pct' => $pctOf($curAtt['present'], $curAtt['present'] + $curAtt['absent']),
        'present_days' => $curAtt['present'],
        'absent_days' => $curAtt['absent'],
        'calendar' => $curAtt['calendar'],
    ];
    $attendancePct = $attendance['rate_pct'];

    $prevAtt = $attendanceFor($prevMonthFrom, $prevMonthTo, false);

    /*
    |--------------------------------------------------------------------------
    | METRIC PERCENTAGES + WEIGHTED SCORE
    |--------------------------------------------------------------------------
    */
    $completionPct = $pctOf($completedTrips, $completedTrips + $cancelledTrips);
    $onTimePct = $pctOf($onTimeTrips, $completedTrips);
    $reliabilityPct = $pctOf($totalTrips - $cancelledTrips, $totalTrips);

    $metrics = [
        'trip_completion' => $completionPct,
        'on_time'         => $onTimePct,
        'attendance'      => $attendancePct,
        'reliability'     => $reliabilityPct,
    ];
    $score = $computeScore($metrics);

    // Month-over-month score delta for the dynamic message
    $scoreMessage = null;
    $monthScore = function ($from, $to) use ($dayMap, $attendanceFor, $WEIGHTS, $computeScore, $onTimePct, $trips, $historyTripList, $durationMinutes, $historyMinutes, $ON_TIME_LIMIT) {
        $completed = 0; $cancelled = 0; $onTime = 0;
        foreach ($trips as $trip) {
            if ($trip['trip_date'] < $from || $trip['trip_date'] > $to) continue;
            $status = strtolower($trip['status'] ?? '');
            if ($status === 'completed') {
                $completed++;
                $mins = $durationMinutes($trip);
                if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTime++;
            } elseif ($status === 'cancelled' || $status === 'no-show' || $status === 'no_show') {
                $cancelled++;
            }
        }
        foreach ($historyTripList as $g) {
            if ($g['trip_date'] < $from || $g['trip_date'] > $to) continue;
            $completed++;
            $mins = $historyMinutes($g);
            if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTime++;
        }
        $att = $attendanceFor($from, $to, false);
        $m = [
            'trip_completion' => $completed + $cancelled > 0 ? round(($completed / ($completed + $cancelled)) * 100) : null,
            'on_time' => $completed > 0 ? round(($onTime / $completed) * 100) : null,
            'attendance' => $att['present'] + $att['absent'] > 0 ? round(($att['present'] / ($att['present'] + $att['absent'])) * 100) : null,
            'reliability' => null, // not part of the monthly score message
        ];
        unset($m['reliability']);
        return $computeScore($m);
    };

    $curScore = $monthScore($monthFrom, $monthTo);
    $prevScore = $monthScore($prevMonthFrom, $prevMonthTo);
    if ($score['value'] !== null && $prevScore['value'] !== null && $prevScore['value'] > 0) {
        $delta = round((($curScore['value'] - $prevScore['value']) / $prevScore['value']) * 100);
        if ($delta >= 1) $scoreMessage = "Great work! Your performance improved by {$delta}% this month.";
        elseif ($delta <= -1) $scoreMessage = "Your performance dipped by " . abs($delta) . "% this month. Keep pushing!";
        else $scoreMessage = 'Steady performance. Keep up the good work!';
    } elseif ($score['value'] !== null && $score['value'] >= 70) {
        $scoreMessage = 'You are performing well. Keep up the consistency!';
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK STATS
    |--------------------------------------------------------------------------
    */
    $quickStats = [
        'trips' => $totalTrips,
        'completed' => $completedTrips,
        'passengers' => $passengerTotal,
        'on_time_pct' => $onTimePct,
    ];

    /*
    |--------------------------------------------------------------------------
    | MONTHLY STATS + DELTAS
    |--------------------------------------------------------------------------
    */
    $monthlyTripStats = function ($from, $to) use ($trips, $historyTripList, $durationMinutes, $historyMinutes, $dayMap, $ON_TIME_LIMIT) {
        $completed = 0; $onTime = 0;
        foreach ($trips as $trip) {
            if ($trip['trip_date'] < $from || $trip['trip_date'] > $to) continue;
            $status = strtolower($trip['status'] ?? '');
            if ($status === 'completed') {
                $completed++;
                $mins = $durationMinutes($trip);
                if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTime++;
            }
        }
        foreach ($historyTripList as $g) {
            if ($g['trip_date'] < $from || $g['trip_date'] > $to) continue;
            $completed++;
            $mins = $historyMinutes($g);
            if ($mins !== null && $mins <= $ON_TIME_LIMIT) $onTime++;
        }
        return [
            'trips_completed' => $completed,
            'on_time_pct' => $completed > 0 ? round(($onTime / $completed) * 100) : null,
        ];
    };

    $curTrips = $monthlyTripStats($monthFrom, $monthTo);
    $prevTrips = $monthlyTripStats($prevMonthFrom, $prevMonthTo);
    $curPassengers = $passengerCount($monthFrom, $monthTo);
    $prevPassengers = $passengerCount($prevMonthFrom, $prevMonthTo);

    $deltaOf = function ($curVal, $prevVal) {
        if ($curVal === null || $prevVal === null || $prevVal == 0) return null;
        return (int) round((($curVal - $prevVal) / $prevVal) * 100);
    };

    $monthlyOut = [
        'trips_completed' => $curTrips['trips_completed'],
        'passengers' => $curPassengers,
        'attendance_pct' => $attendance['rate_pct'],
        'on_time_pct' => $curTrips['on_time_pct'],
        'deltas' => [
            'trips_completed' => $deltaOf($curTrips['trips_completed'], $prevTrips['trips_completed']),
            'passengers' => $deltaOf($curPassengers, $prevPassengers),
            'attendance_pct' => $deltaOf($attendance['rate_pct'], $pctOf($prevAtt['present'], $prevAtt['present'] + $prevAtt['absent'])),
            'on_time_pct' => $deltaOf($curTrips['on_time_pct'], $prevTrips['on_time_pct']),
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | BREAKDOWN
    |--------------------------------------------------------------------------
    */
    $breakdown = [];
    $labels = [
        'trip_completion' => 'Trip Completion',
        'on_time' => 'On-Time Performance',
        'attendance' => 'Attendance',
        'reliability' => 'Reliability',
    ];
    foreach ($labels as $key => $label) {
        $val = $metrics[$key];
        if ($val === null) continue;
        $breakdown[] = [
            'key' => $key,
            'label' => $label,
            'pct' => $val,
            'rating' => $breakdownRating($val),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TREND SERIES (passengers and trips per period)
    |--------------------------------------------------------------------------
    | 7d/30d: daily buckets; 3m: weekly (Monday-bucketed) buckets. Every day
    | in the window is included (0 when the driver had no activity).
    | passengers = COUNT(passenger_trips) that day/week
    | trips      = COUNT(bus_trips) that day/week (all statuses)
    |--------------------------------------------------------------------------
    */
    $trendFrom = date('Y-m-d', strtotime('-89 days'));

    $tripsByDay = [];
    foreach ($trips as $trip) {
        if ($trip['trip_date'] < $trendFrom) continue;
        $d = $trip['trip_date'];
        $tripsByDay[$d] = ($tripsByDay[$d] ?? 0) + 1;
    }
    foreach ($historyTripList as $g) {
        if ($g['trip_date'] < $trendFrom) continue;
        $d = $g['trip_date'];
        $tripsByDay[$d] = ($tripsByDay[$d] ?? 0) + 1;
    }

    $paxByDay = [];
    $paxStmt = $conn->prepare(
        "SELECT DATE(boarding_time) AS d, COUNT(*) AS c FROM passenger_trips
         WHERE driver_id = ? AND boarding_time >= ?
         GROUP BY DATE(boarding_time)"
    );
    if ($paxStmt) {
        $trendFromDT = "$trendFrom 00:00:00";
        $paxStmt->bind_param("ss", $driver_id, $trendFromDT);
        $paxStmt->execute();
        $paxRes = $paxStmt->get_result();
        while ($paxRow = $paxRes->fetch_assoc()) {
            $paxByDay[$paxRow['d']] = (int)$paxRow['c'];
        }
        $paxStmt->close();
    }

    $histStmt = $conn->prepare(
        "SELECT trip_date AS d, COUNT(*) AS c FROM passenger_trip_history
         WHERE driver_id = ? AND trip_date >= ?
         GROUP BY trip_date"
    );
    if ($histStmt) {
        $histStmt->bind_param("ss", $driver_id, $trendFrom);
        $histStmt->execute();
        $histRes = $histStmt->get_result();
        while ($histRow = $histRes->fetch_assoc()) {
            $d = $histRow['d'];
            $paxByDay[$d] = ($paxByDay[$d] ?? 0) + (int)$histRow['c'];
        }
        $histStmt->close();
    }

    $weeklyBucket = function ($date) {
        $ts = strtotime($date);
        $dow = (int) date('N', $ts); // 1 = Monday .. 7 = Sunday
        return date('Y-m-d', $ts - ($dow - 1) * 86400);
    };

    $buildCountSeries = function ($daysBack, $bucketFn) use ($tripsByDay, $paxByDay, $today) {
        $series = [];
        $cursor = new DateTime(date('Y-m-d', strtotime("-$daysBack days")));
        $end = new DateTime($today);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $bucket = $bucketFn($date);
            if (!isset($series[$bucket])) {
                $series[$bucket] = ['date' => $bucket, 'passengers' => 0, 'trips' => 0];
            }
            $series[$bucket]['passengers'] += $paxByDay[$date] ?? 0;
            $series[$bucket]['trips'] += $tripsByDay[$date] ?? 0;
            $cursor->modify('+1 day');
        }
        return array_values($series);
    };

    $series7 = $buildCountSeries(6, function ($date) { return $date; });
    $series30 = $buildCountSeries(29, function ($date) { return $date; });
    $series3m = $buildCountSeries(89, $weeklyBucket);

    $hasActivity = function ($series) {
        foreach ($series as $p) {
            if ($p['passengers'] > 0 || $p['trips'] > 0) return true;
        }
        return false;
    };

    $trend = [
        'periods' => ['7d' => $series7, '30d' => $series30, '3m' => $series3m],
        'insufficient' => [
            '7d' => !$hasActivity($series7),
            '30d' => !$hasActivity($series30),
            '3m' => !$hasActivity($series3m),
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | RECENT TRIPS (bus_trips + passenger_trip_history groups, newest first)
    |--------------------------------------------------------------------------
    */
    $recentCandidates = [];
    foreach ($trips as $trip) {
        $status = $trip['status'] ?? '';
        $mins = $durationMinutes($trip);
        $isOnTime = null;
        $lateMinutes = null;
        if ($mins !== null) {
            $isOnTime = $mins <= $ON_TIME_LIMIT;
            $lateMinutes = $mins > $ON_TIME_LIMIT ? $mins - $ON_TIME_LIMIT : 0;
        }

        $passengerStmt = $conn->prepare("SELECT COUNT(*) AS total FROM passenger_trips WHERE trip_no = ?");
        $tripPassengers = 0;
        if ($passengerStmt) {
            $passengerStmt->bind_param("i", $trip['id']);
            $passengerStmt->execute();
            $pRow = $passengerStmt->get_result()->fetch_assoc();
            $passengerStmt->close();
            $tripPassengers = (int)($pRow['total'] ?? 0);
        }

        // History rows match by trip_id = trip_number (possibly prefixed "TRIP-").
        // Both forms are precomputed in PHP and bound as params - a SQL-level
        // CONCAT literal would cause an "Illegal mix of collations" error.
        if (!empty($trip['trip_number'])) {
            $tripNo = $trip['trip_number'];
            $tripPrefixed = 'TRIP-' . $tripNo;
            $historyStmt = $conn->prepare(
                "SELECT COUNT(*) AS total FROM passenger_trip_history
                 WHERE driver_id = ? AND trip_date = ? AND trip_id IN (?, ?)"
            );
            if ($historyStmt) {
                $historyStmt->bind_param("ssss", $driver_id, $trip['trip_date'], $tripNo, $tripPrefixed);
                $historyStmt->execute();
                $hRow = $historyStmt->get_result()->fetch_assoc();
                $historyStmt->close();
                $tripPassengers += (int)($hRow['total'] ?? 0);
            }
        }

        $recentCandidates[] = [
            'sort' => [$trip['trip_date'], $trip['departure_time'] ?? ''],
            'trip' => [
                'id' => $trip['id'],
                'route_name' => $trip['route_name'] ?? 'Untitled Route',
                'trip_date' => $trip['trip_date'],
                'bus' => $assignedBus,
                'status' => $status,
                'passengers' => $tripPassengers,
                'late_minutes' => $lateMinutes,
                'on_time' => $isOnTime,
            ],
        ];
    }

    foreach ($historyTripList as $g) {
        $mins = $historyMinutes($g);
        $isOnTime = null;
        $lateMinutes = null;
        if ($mins !== null) {
            $isOnTime = $mins <= $ON_TIME_LIMIT;
            $lateMinutes = $mins > $ON_TIME_LIMIT ? $mins - $ON_TIME_LIMIT : 0;
        }
        $routeParts = array_values(array_filter([$g['boarding'], $g['destination']]));
        $recentCandidates[] = [
            'sort' => [$g['trip_date'], $g['departure'] ?? ''],
            'trip' => [
                'id' => $g['trip_id'] !== '' ? $g['trip_id'] : ('H' . $g['history_id']),
                'route_name' => !empty($routeParts) ? implode(' → ', $routeParts) : 'Unknown Route',
                'trip_date' => $g['trip_date'],
                'bus' => !empty($g['bus_number']) ? 'Bus ' . $g['bus_number'] : $assignedBus,
                'status' => 'Completed',
                'passengers' => $g['passengers'],
                'late_minutes' => $lateMinutes,
                'on_time' => $isOnTime,
            ],
        ];
    }

    usort($recentCandidates, function ($a, $b) {
        return [$b['sort'][0], $b['sort'][1]] <=> [$a['sort'][0], $a['sort'][1]];
    });
    $recentTrips = array_map(function ($c) { return $c['trip']; }, array_slice($recentCandidates, 0, 5));

    /*
    |--------------------------------------------------------------------------
    | ACHIEVEMENTS (record-derived only)
    |--------------------------------------------------------------------------
    */
    $achievements = [
        ['key' => 'trips_100', 'label' => '100 Trips', 'icon' => 'bus', 'unlocked' => $completedTrips >= 100],
        ['key' => 'perfect_attendance', 'label' => 'Perfect Attendance', 'icon' => 'calendar', 'unlocked' => (int)$attendance['absent_days'] === 0 && (int)$attendance['present_days'] > 0],
        ['key' => 'on_time_driver', 'label' => 'On-Time Driver', 'icon' => 'time', 'unlocked' => $onTimePct !== null && $onTimePct >= 90],
        ['key' => 'passenger_mover', 'label' => '500 Passengers', 'icon' => 'people', 'unlocked' => $passengerTotal >= 500],
        ['key' => 'top_performer', 'label' => 'Top Performer', 'icon' => 'trophy', 'unlocked' => $score['value'] !== null && $score['value'] >= 90],
    ];

    /*
    |--------------------------------------------------------------------------
    | INSIGHT (only from supported data)
    |--------------------------------------------------------------------------
    */
    $insight = null;
    $parts = [];
    if ($completionPct !== null) {
        $parts[] = "You completed {$completionPct}% of your assigned trips this month.";
    }
    if ($curTrips['on_time_pct'] !== null) {
        $parts[] = "{$curTrips['on_time_pct']}% of your trips finished within the 2h 30m route window.";
    }
    if ($attendance['rate_pct'] !== null) {
        if ((int)$attendance['absent_days'] === 0 && (int)$attendance['present_days'] > 0) {
            $parts[] = 'You completed trips on every day of the month so far.';
        } else {
            $parts[] = "You were present on {$attendance['present_days']} of " . ((int)$attendance['present_days'] + (int)$attendance['absent_days']) . " days this month.";
        }
    }
    if ($curPassengers > 0) {
        $parts[] = "You transported " . number_format($curPassengers) . " passengers this month.";
    }
    if (!empty($parts)) $insight = implode(' ', $parts);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */
    echo json_encode([
        'success' => true,
        'driver' => [
            'full_name' => $driverRow['full_name'] ?? '',
            'driver_id' => $driverRow['driver_id'] ?? '',
            'photo' => $photoUrl,
            'status' => $pillStatus,
            'assigned_bus' => $assignedBus,
            'current_route' => $currentRoute,
        ],
        'score' => [
            'value' => $score['value'],
            'rating' => $score['rating'],
            'metrics_used' => $score['metrics_used'],
            'message' => $scoreMessage,
        ],
        'quick_stats' => $quickStats,
        'monthly' => $monthlyOut,
        'breakdown' => $breakdown,
        'trend' => $trend,
        'recent_trips' => $recentTrips,
        'attendance' => $attendance,
        'achievements' => $achievements,
        'insight' => $insight,
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
    exit;
}
