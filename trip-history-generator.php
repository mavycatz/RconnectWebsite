<?php
declare(strict_types=1);

require_once __DIR__ . '/app/init.php';
require_once __DIR__ . '/finance/models/AuditTrailModel.php';
/* Pure helper library used by the finance/admin reports for the per-driver
   fixed operating deductions (amortization, insurance, GPS, OPTR). */
require_once __DIR__ . '/admin/api/Driver/driver-perf-occupancy.php';
require_once __DIR__ . '/admin/api/TripDashboard/live_map_helpers.php';
/* Fleet-scope profile for the September-October 2026 demo window (buses,
   trips per bus, passenger band). Pure functions only. */
require_once __DIR__ . '/admin/api/TripDashboard/fleet_scope_profile.php';
/* Daily-routine rules for window-day passenger generation (cohort sizing,
   attendance, out-and-back commutes). Pure functions only. */
require_once __DIR__ . '/admin/api/TripDashboard/passenger_routines.php';
/* Deterministic weekly travel plans (travel weekdays, daily legs, one-way
   odds), leg-to-trip matching, and demand-based pool sizing. Pure functions
   only, shared by the live and archive generation paths. */
require_once __DIR__ . '/admin/api/TripDashboard/weekly_travel_patterns.php';
/* Fleet-wide daily trip target, deterministic bus allocation, and
   seat-scaled passenger load bands. Pure functions only. */
require_once __DIR__ . '/admin/api/TripDashboard/fleet_daily_volume.php';
/* Daily dispatch coverage rules (eligible fleet/roster filters, lineup and
   terminal-assignment selection, gap labels, per-date validation helpers).
   Pure functions only. */
require_once __DIR__ . '/admin/api/TripDashboard/dispatch_coverage.php';
/* Admin-owned dispatch queue service: its idempotent ensureSchema() is reused
   so historical coverage rows and the interactive queue share one table
   shape. */
require_once __DIR__ . '/admin/services/Dispatch/DispatchQueueService.php';

/* Demo-window fleet: the page may pin the exact bus IDs this deployment
   actually has (comma-separated field "window_buses"); an empty value covers
   the whole fleet so every bus receives generated history (and therefore a
   historical KPL). Bus IDs that do not exist in the fleet are simply
   never operated. */
if (!empty($_POST['window_buses'])) {
    fleetScopeSetBusList((string)$_POST['window_buses']);
}

/* When no window fleet was pinned, allow every bus in the fleet to operate on
   window days, so each bus gets generated trips and trip-level fuel logs with
   a real KPL - the reference the dispatcher reads as historical KPL. */
function applyDefaultFleetScope(PDO $db, array $input = []): void
{
    if (!empty($GLOBALS['fleetScopeBusOverride'])) {
        return;   // already pinned for this request
    }
    if (!empty($input['window_buses'])) {
        fleetScopeSetBusList((string)$input['window_buses']);
        return;
    }
    $fleetIds = [];
    try {
        foreach ($db->query('SELECT bus_id FROM buses')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = trim((string)($row['bus_id'] ?? ''));
            if ($id !== '') {
                $fleetIds[$id] = true;
            }
        }
    } catch (Throwable $e) {
        $fleetIds = [];
    }
    if ($fleetIds) {
        fleetScopeSetBusList(array_keys($fleetIds));
    }
}

use App\Auth\Auth;
use App\Core\Database;
use Finance\Models\AuditTrailModel;

date_default_timezone_set('Asia/Manila');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* =========================================================
   SERVICE WINDOW
   ========================================================= */
const SERVICE_START = 5 * 3600;             // 5:00 AM — first departure
const DEPARTURE_GRID = 15 * 60;             // departures run on a 15-minute headway
const LAST_DEPARTURE = 20 * 3600;           // 8:00 PM — last boarding time
const SERVICE_END = 21 * 3600;              // 9:00 PM — latest arrival at the destination terminal
/* The last SCHEDULED departure targets 8:00 PM (a typical 55-minute trip still
   arrives before the 9:00 PM terminal cutoff); trips on longer routes are
   pulled earlier so their arrival stays inside SERVICE_END. */
const LAST_SCHEDULED_DEPARTURE = LAST_DEPARTURE;
const LOCAL_SHARE_PERCENT = 10;
const LOCAL_BOARDING_POINT = 'Terminal';
const LOCAL_FARE = 15.0;

/* =========================================================
   GENERATED PASSENGER RECORDS (passengers + passenger_trips)
   Each ride is written once, into passenger_trips. Demo accounts are
   identified by a reserved synthetic identity so re-runs can reuse them
   and cleanup can never match genuine rows.
   ========================================================= */
/* Demo account emails look like ordinary personal Gmail addresses
   (firstlast123@gmail.com and natural variants). Because they carry no visible
   marker, identification uses the audit-trail registry plus the legacy
   patterned addresses from earlier builds, which stay recognizable. */
const DEMO_EMAIL_DOMAIN = 'gmail.com';
const DEMO_EMAIL_LEGACY_DOMAIN = 'demo.rconnect.local';
const DEMO_EMAIL_PATTERN = '%.9______@gmail.com';   // old marker: dot + 9xxxxxx
const DEMO_ID_PREFIX = 'DM-';
const DEMO_CODE_PREFIX = 'DM-';

/* Settled history is the default; these are the only dates left unremitted
   (pending / overdue) in newly generated records. */
/* Remittance/discrepancy workflow state is decided from the Asia/Manila
   current date (the cut-off is read from the database where available):
   past operating dates settle into submitted/verified/remitted, while today
   and every future date stay pending until an actual submission or
   verification action occurs. No hardcoded hold dates. */
function remittanceDayState(string $day, string $cutoff): string
{
    $day = trim($day);
    $cutoff = trim($cutoff);
    if ($day === '' || $cutoff === '') {
        return 'pending';
    }
    return $day < $cutoff ? 'settled' : 'pending';
}

/* Archive history generator (passenger_trip_history) */
const ARCHIVE_TRIP_PREFIX = 'ARCV-';
const ARCHIVE_YEARS = [2022, 2023, 2024, 2025, 2026];
const ARCHIVE_2026_LAST_MONTH = 9;    // 2026 archive data covers January-September only
const ARCHIVE_NICKNAME_SHARE = 0.4;   // share of profiles recorded by nickname

/* Natural nicknames the cooperative's passengers are known by ("like before"),
   mixed with full names so old manifests read like paper records. */
const ARCHIVE_NICKNAMES = [
    'Maria', 'Jun', 'Bebe', 'Totoy', 'Carlo', 'Jing', 'Boy', 'Kiko', 'Nene', 'Inday',
    'Mae', 'Jhun', 'Mike', 'Renz', 'Migs', 'Nico', 'Jaja', 'Lala', 'Aya', 'Mimi',
    'Dodong', 'Anne', 'Len', 'Tin', 'Pio', 'Bing', 'Lita', 'Romy', 'Selya', 'Ogie',
    'Nida', 'Paeng', 'Tess', 'Elsa', 'Domeng', 'Vicky', 'Marlon', 'Ana', 'Lucing', 'Pedring',
    'Berto', 'Susing', 'Tino', 'Maring', 'Anding', 'Nonoy', 'Cardo', 'Dindo', 'Edu', 'Ferdie',
    'Abi', 'Tinze', 'Joms', 'Kay', 'Chai', 'Bea', 'Ate Tess', 'Kuya Carlo', 'Mang Ben', 'Aling Rosa',
];
/* Payment mix used for generated rides: cash/GCash/QR Ph/hybrid. The QR Ph
   share is dropped automatically on deployments whose payment_method column
   cannot store it (enum schemas without the value). */
const HIST_PAYMENT_MIX = ['cash' => 62, 'gcash' => 20, 'qrph' => 10, 'hybrid' => 8];
const HIST_CONTACT_PREFIX = '0998';   // reserved demo mobile block

/* =========================================================
   CAPACITY / BEHAVIOR LIMITS
   ========================================================= */
const TRIP_PASSENGER_MAX = 30;      // legacy fallback band when no seat bounds apply
const TRIP_PASSENGER_MIN = 10;      // legacy fallback band when no seat bounds apply
const PASSENGER_CAP_PER_TRIP = TRIP_PASSENGER_MAX; // legacy fallback occupied-space cap
const MAX_RIDES_PER_PASSENGER = 3;  // per passenger per day
const MAX_OTHER_PER_DAY = 15;       // baggage/luggage records per day
const MAX_TRIPS_PER_BUS = 4;        // hard per-bus upper bound
const ABSENT_DRIVER_BASE = 0.05;    // base per-driver per-day absence probability

/* =========================================================
   FUEL MODEL CONFIG
   fuel_logs is reserved strictly for trip-level fuel consumption:
   (trip_id, bus_id, driver_id, starting_odo, ending_odo, distance_km, kpl,
   liters, price_per_liter, expected_fuel) where liters is the actual fuel
   consumed by the trip (distance / km_l) at the historical diesel price of
   that date and kpl equals distance / liters. Cost stays 0 there because
   purchases - not consumption - carry purchase cost.
   Actual refueling events are recorded in the fuel_refuels table:
   (refuel_id, bus_id, driver_id, trip_id, refuel_odo, liters, price_per_liter,
   total_cost, is_full_tank, refuel_type, notes, fuel_proof_url, created_at, updated_at).
   Buses maintain persistent fuel balance and tank capacity; refueling triggers
   when fuel drops to 20-30% of capacity plus a full-tank morning top-up on
   operating days.
   ========================================================= */
const FUEL_BASELINE_KPL_MIN = 8.5;   // per-bus baseline KPL floor
const FUEL_BASELINE_KPL_MAX = 10.0;  // per-bus baseline KPL ceiling
const FUEL_TANK_CAPACITY    = 100.0; // default tank (L) with no buses capacity column
const FUEL_AVG_SPEED_KPH    = 24.0;  // base average speed used for trip durations
const FUEL_THRESHOLD_MIN_RATIO = 0.20; // refuel when fuel falls to 20%...
const FUEL_THRESHOLD_MAX_RATIO = 0.30; // ...30% of tank (per-bus random)
const FUEL_PARTIAL_EVENT_RATE  = 0.04; // share of refuels that are partial top-ups

/* Historical diesel price anchors (approximate PH pump prices, ₱/L). */
const DIESEL_PRICE_ANCHORS = [
    '2022-01-01' => 55.0,
    '2022-06-01' => 75.0,
    '2022-10-01' => 65.0,
    '2023-01-01' => 63.0,
    '2023-07-01' => 58.0,
    '2024-01-01' => 60.0,
    '2024-07-01' => 56.0,
    '2025-01-01' => 57.0,
    '2025-07-01' => 53.0,
    '2026-01-01' => 58.0,
    '2026-09-01' => 65.55,
];

/* Payment behavior profiles: per-10,000 records for past-dated days. */
const PAYMENT_PROFILES = [
    'normal'      => ['paid' => 9980, 'pending' => 12, 'rejected' => 2,  'discrepancy' => 6],
    'delayed'     => ['paid' => 9900, 'pending' => 60, 'rejected' => 10, 'discrepancy' => 30],
    'problematic' => ['paid' => 9700, 'pending' => 100, 'rejected' => 50, 'discrepancy' => 150],
];

/* Simulated inspection rates (%) — no inspection rows are written. */
const INSPECTION_RATES = [
    'low'    => ['minor' => 4.0, 'significant' => 1.0],
    'medium' => ['minor' => 7.0, 'significant' => 2.0],
    'high'   => ['minor' => 10.0, 'significant' => 3.0],
];

/* Fuel anomaly probability per generated fuel log. */
const ANOMALY_RATES = ['low' => 0.02, 'medium' => 0.05, 'high' => 0.10];

/* Holidays (month-day apply every year; year-month-day are specific dates such as Good Friday). */
const HOLIDAYS_MD = ['01-01', '02-25', '04-09', '05-01', '06-12', '08-21', '11-01', '11-30', '12-25', '12-30'];
const HOLIDAYS_YMD = ['2022-04-15', '2023-04-07', '2024-03-29', '2025-04-18', '2026-04-03'];

function isHoliday(string $date): bool
{
    if (in_array(substr($date, 5), HOLIDAYS_MD, true)) {
        return true;
    }
    return in_array($date, HOLIDAYS_YMD, true);
}

function dayTypeOf(string $date): string
{
    if (isHoliday($date)) {
        return 'holiday';
    }
    $n = (int)date('N', strtotime($date));
    return $n >= 6 ? 'weekend' : 'weekday';
}

/* Detailed service profile used for trip-count ranges (Friday differs from weekdays). */
function serviceProfile(string $date): string
{
    if (isHoliday($date)) {
        return 'holiday';
    }
    return match ((int)date('N', strtotime($date))) {
        7 => 'sunday',
        6 => 'saturday',
        5 => 'friday',
        default => 'weekday',
    };
}

/* Time-of-day demand weight for a given second, biased by day type, matching the
   operating window 5:00 AM–8:00 PM. Weekends/holidays dampen demand. */
function minuteWeight(int $sec, string $dayType): int
{
    $h = (int)($sec / 3600);
    if ($h >= 4 && $h < 6) {            // 4:00–6:00 low
        $w = 2;
    } elseif ($h >= 7 && $h < 8) {      // 7:00–8:00 high morning rush
        $w = 8;
    } elseif ($h >= 8 && $h < 10) {     // 8:00–10:00 moderate
        $w = 5;
    } elseif ($h >= 11 && $h < 12) {    // 11:00–12:00 medium/high
        $w = 6;
    } elseif ($h >= 12 && $h < 15) {    // 12:00–15:00 moderate/low
        $w = 4;
    } elseif ($h >= 16 && $h < 18) {    // 16:00–18:00 high evening rush
        $w = 8;
    } elseif ($h >= 18 && $h < 21) {    // 18:00–21:00 moderate/low
        $w = 4;
    } else {
        $w = 1;                         // 6:00–7:00 and edges
    }
    if ($dayType === 'weekend') {
        return (int)round($w * 0.5);
    }
    if ($dayType === 'holiday') {
        return (int)round($w * 0.35);
    }
    return $w;
}

/* =========================================================
   OPTIONS, MATH AND STABLE-HASH HELPERS
   ========================================================= */
function clampf(float $v, float $lo, float $hi): float
{
    return max($lo, min($hi, $v));
}

function stableUnit(string $key): float
{
    return (crc32($key) % 100000) / 100000.0;
}

function stableRange(string $key, float $min, float $max): float
{
    return $min + stableUnit($key) * ($max - $min);
}

function stablePickWeighted(array $weighted, string $key): string
{
    $total = (float)array_sum($weighted);
    $r = stableUnit($key) * $total;
    $acc = 0.0;
    foreach ($weighted as $k => $w) {
        $acc += (float)$w;
        if ($r <= $acc) {
            return (string)$k;
        }
    }
    return (string)array_key_first($weighted);
}

function stableDigits(string $key, int $digits): string
{
    $h = md5($key);
    $mod = (int)pow(10, $digits);
    $val = (int)(hexdec(substr($h, 0, 12)) % $mod);
    return str_pad((string)$val, $digits, '0', STR_PAD_LEFT);
}

function fmtTime(int $sec): string
{
    $sec = max(0, $sec);
    return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
}

function resolveOptions(array $input): array
{
    /* Fleet-wide daily trip target: 0 keeps the automatic day-type band,
       positive values are the configured center (clamped to a sane ceiling).
       The retired target_trips_per_bus field is accepted but ignored. */
    $dailyTarget = (int)($input['daily_trips_target'] ?? 0);
    if ($dailyTarget < 0) {
        $dailyTarget = 0;
    }
    if ($dailyTarget > 0) {
        $dailyTarget = min($dailyTarget, FLEET_DAILY_TRIPS_CEILING);
    }

    /* Passenger pool target: 0 keeps the demand-based pool sizing; a positive
       value sets how many passenger profiles the run builds for the selected
       period, so monthly account volume is configurable per deployment. */
    $poolTarget = (int)($input['passenger_pool_target'] ?? 0);
    if ($poolTarget < 0) {
        $poolTarget = 0;
    }
    if ($poolTarget > 0) {
        $poolTarget = max(100, min(5000, $poolTarget));
    }

    $cap = (int)($input['max_trips_per_bus'] ?? 0);
    if ($cap < 0) {
        $cap = 0;
    }
    if ($cap > 0) {
        $cap = min($cap, MAX_TRIPS_PER_BUS);
    }

    $scenario = strtolower(trim((string)($input['demand_scenario'] ?? 'normal')));
    if (!isset(FLEET_DEMAND_SCENARIOS[$scenario])) {
        $scenario = 'normal';
    }

    $mult = (float)($input['demand_multiplier'] ?? 1.0);
    if (!is_finite($mult) || $mult <= 0) {
        $mult = 1.0;
    }
    /* The named scenario preset scales the custom multiplier. */
    $mult = clampf($mult * fleetScenarioMultiplier($scenario), 0.1, 3.0);

    $payment = strtolower(trim((string)($input['payment_profile'] ?? 'normal')));
    if (!isset(PAYMENT_PROFILES[$payment])) {
        $payment = 'normal';
    }

    $inspection = strtolower(trim((string)($input['inspection_discrepancy_rate'] ?? 'low')));
    if (!isset(INSPECTION_RATES[$inspection])) {
        $inspection = 'low';
    }

    $fuel = strtolower(trim((string)($input['fuel_realism'] ?? 'high')));
    if (!in_array($fuel, ['high', 'standard'], true)) {
        $fuel = 'high';
    }

    $anomaly = strtolower(trim((string)($input['anomaly_rate'] ?? 'low')));
    if (!isset(ANOMALY_RATES[$anomaly])) {
        $anomaly = 'low';
    }

    $seedRaw = trim((string)($input['random_seed'] ?? ''));
    $seed = null;
    if ($seedRaw !== '' && preg_match('/^-?\d+$/', $seedRaw)) {
        $seed = (int)$seedRaw;
    }

    return [
        'daily_trips_target' => $dailyTarget,
        'passenger_pool_target' => $poolTarget,
        'max_trips_per_bus' => $cap,
        'demand_scenario' => $scenario,
        'demand_multiplier' => $mult,
        'payment_profile' => $payment,
        'inspection_rate' => $inspection,
        'fuel_realism' => $fuel,
        'anomaly_rate' => $anomaly,
        'seed' => $seed,
    ];
}

/* Operating bus count for one window day: the profile-aware per-day budget
   from the fleet-scope profile, one bus fewer on rainy days (never below the
   day's floor), so window days no longer all run the same fleet size. */
function windowDayBusBudget(array $allowedIds, string $day): int
{
    $budget = fleetScopeDayBusBudget($allowedIds, $day, serviceProfile($day));
    if ($budget <= 0) {
        return 0;
    }
    if (dayWeather($day) === 'rain') {
        $floor = min(FLEET_SCOPE_MIN_BUSES_PER_DAY, $budget);
        $budget = max($floor, $budget - 1);
    }
    return $budget;
}

/* Expected completed trips for one day under the active volume rules: the
   window allow-list when the day is in scope, otherwise the fleet target. */
function expectedDailyTrips(string $day, array $ctx, array $opts): int
{
    if (!empty($opts['fleet_scope']) && fleetScopeDayActive($day)) {
        $allowed = [];
        foreach (array_keys($ctx['busStates']) as $bid) {
            if (fleetScopeAllowsBus((string)$bid)) {
                $allowed[] = (string)$bid;
            }
        }
        /* Window days run a varying 7-9 bus fleet (day profile + stable
           per-day value, one bus fewer in the rain), so every day inside the
           13-18 band has its own trip total while the rotation still covers
           every allowed bus across the period. */
        $operating = count(fleetScopeDayBuses($allowed, $day, windowDayBusBudget($allowed, $day)));
        return max(1, $operating * FLEET_SCOPE_TRIPS_PER_BUS);
    }
    return fleetDailyTarget($day, $opts + ['day_profile' => serviceProfile($day)]);
}

/* Average expected daily trips across a generated period, used to size the
   passenger pool from weekly demand instead of per-bus volume. */
function periodDailyTripTarget(array $days, array $ctx, array $opts): int
{
    if (!$days) {
        return fleetDailyTarget(date('Y-m-d'), $opts);
    }
    $sum = 0;
    foreach ($days as $day) {
        $sum += expectedDailyTrips($day, $ctx, $opts);
    }
    return max(1, (int)ceil($sum / count($days)));
}

/* Time-dependent diesel price: anchor interpolation + small month-level
   movement. A database anchor (['date', 'price'], read from fuel_prices or the
   finance cost settings) replaces the curve's value from its own date onward;
   earlier dates keep interpolating the historical anchors, and the constant
   anchors remain the fallback when no database value exists. */
function pricePerLiterForDate(string $date, ?array $anchor = null): float
{
    static $anchors = null;
    if ($anchors === null) {
        $anchors = [];
        foreach (DIESEL_PRICE_ANCHORS as $d => $p) {
            $anchors[strtotime($d)] = $p;
        }
        ksort($anchors);
    }
    $effective = $anchors;
    if ($anchor !== null && !empty($anchor['date']) && isset($anchor['price'])) {
        $anchorTs = strtotime((string)$anchor['date']);
        if ($anchorTs !== false && (float)$anchor['price'] > 0) {
            $effective[$anchorTs] = (float)$anchor['price'];
            ksort($effective);
        }
    }
    $ts = strtotime($date);
    $prev = null;
    $next = null;
    foreach ($effective as $aTs => $p) {
        if ($aTs <= $ts) {
            $prev = [$aTs, $p];
        } else {
            $next = [$aTs, $p];
            break;
        }
    }
    if ($prev === null) {
        $base = reset($effective);
    } elseif ($next === null) {
        $base = $prev[1];
    } else {
        $frac = ($ts - $prev[0]) / max(1, $next[0] - $prev[0]);
        $base = $prev[1] + ($next[1] - $prev[1]) * $frac;
    }
    $noise = stableRange('diesel-' . date('Y-m', $ts), -0.8, 0.8);
    return round(max(20.0, $base + $noise), 2);
}

function dayWeather(string $date): string
{
    $month = (int)date('n', strtotime($date));
    $rainy = $month >= 6 && $month <= 11;
    $r = stableUnit('weather-' . $date);
    if ($rainy) {
        return $r < 0.35 ? 'rain' : ($r < 0.70 ? 'cloudy' : 'clear');
    }
    return $r < 0.08 ? 'rain' : ($r < 0.35 ? 'cloudy' : 'clear');
}

function weatherFactor(string $weather): float
{
    return match ($weather) {
        'rain' => 0.94,
        'cloudy' => 0.98,
        default => 1.01,
    };
}

function trafficFactor(int $sec, string $dayType): float
{
    $h = intdiv($sec, 3600);
    $f = 1.0;
    if (($h >= 7 && $h < 9) || ($h >= 16 && $h < 19)) {
        $f = 0.88;                                  // rush hour — slower
    } elseif (($h >= 9 && $h < 11) || ($h >= 14 && $h < 16)) {
        $f = 1.04;
    } elseif ($h < 6 || $h >= 19) {
        $f = 1.08;                                  // off-peak — faster
    }
    if ($dayType !== 'weekday') {
        $f += 0.05;
    }
    return $f;
}

/* Passengers targeted for ONE individual trip: a natural band weighted by
   time of day and day type, so every completed trip carries a realistic load
   and never falls below the minimum. The trip's seat-scaled capacity band, or
   a window profile's reduced band, replaces both ends when given. */
function tripDemandCount(int $sec, string $dayType, float $multiplier = 1.0, ?array $bounds = null): int
{
    $h = (int)($sec / 3600);
    $rush = ($h >= 7 && $h < 9) || ($h >= 16 && $h < 19);
    $mid  = ($h >= 11 && $h < 14);
    $early = ($h >= 4 && $h < 7);
    $late = ($h >= 19 && $h < 21);
    if ($dayType === 'weekday') {
        if ($rush) { $base = mt_rand(24, 30); }
        elseif ($mid) { $base = mt_rand(20, 28); }
        elseif ($early) { $base = mt_rand(14, 22); }
        elseif ($late) { $base = mt_rand(14, 20); }
        else { $base = mt_rand(18, 26); }
    } elseif ($dayType === 'weekend') {
        if ($rush) { $base = mt_rand(20, 28); }
        elseif ($mid) { $base = mt_rand(18, 25); }
        elseif ($early) { $base = mt_rand(12, 18); }
        elseif ($late) { $base = mt_rand(12, 18); }
        else { $base = mt_rand(16, 24); }
    } else {
        if ($rush) { $base = mt_rand(18, 26); }
        elseif ($mid) { $base = mt_rand(16, 22); }
        elseif ($early) { $base = mt_rand(12, 16); }
        elseif ($late) { $base = mt_rand(12, 16); }
        else { $base = mt_rand(14, 20); }
    }
    $band = mt_rand(1, 100);
    $factor = 1.0;
    if ($band <= 15) {
        $factor = 0.93;      // slightly lighter trip
    } elseif ($band >= 85) {
        $factor = 1.07;      // slightly heavier trip
    }
    $target = (int)round($base * $factor * $multiplier);
    $paxMin = $bounds !== null ? (int)$bounds[0] : TRIP_PASSENGER_MIN;
    $paxMax = $bounds !== null ? (int)$bounds[1] : TRIP_PASSENGER_MAX;
    return max($paxMin, min($paxMax, $target));
}

function pickPType(): string
{
    // Discount mix used for the passenger_type column (fare differentiation).
    $opts = [
        'Regular' => 70,
        'Student' => 10,
        'Senior' => 8,
        'PWD' => 5,
        'Beneficiary' => 4,
        'Cooperative' => 3,
    ];
    $r = mt_rand(1, 100);
    $acc = 0;
    foreach ($opts as $k => $share) {
        $acc += $share;
        if ($r <= $acc) {
            return $k;
        }
    }
    return 'Regular';
}

/* Build a natural Tagalog/Filipino baggage description whose leading count word
   matches `space_occupied` (isang=1, dalawang=2, tatlong=3). */
function baggageDescription(int $space, string $recipient): string
{
    $countWord = match ($space) {
        1 => 'Isang',
        2 => 'Dalawang',
        default => 'Tatlong',
    };
    $units = ['kahon', 'sako', 'bag', 'box', 'kaban', 'balikbayan', 'supot', 'bundle', 'plastik'];
    $things = [
        'ng bigas', 'ng gulay', 'ng gatas', 'ng harina', 'ng labada',
        'ng tubig', 'ng dokumento', 'ng tinapay', 'ng isda', 'ng sibuyas', 'ng mangga',
        'ng prutas', 'ng pananim', 'ng gamit', 'ng produkto', 'ng paninda',
    ];
    $names = ['Ana', 'Timbong', 'Bogart', 'Tess', 'Carlo', 'Maria', 'Ate Tess', 'Kuya Carlo', 'Boss Ana', 'Ate Maria'];
    $unit = $units[mt_rand(0, count($units) - 1)];
    $thing = $things[mt_rand(0, count($things) - 1)];
    $base = $countWord . ' ' . $unit . ' ' . $thing;
    if (mt_rand(0, 1) === 0) {
        $base .= ' para kay ' . $names[mt_rand(0, count($names) - 1)];
    }
    return $base;
}

/* Sample `$count` departure seconds for a day on the 15-minute headway grid,
   weighted toward the rush hours (05:00 through the 8:00 PM last departure). */
function sampleDayDepartures(string $dayType, int $count): array
{
    $slots = range(SERVICE_START, LAST_SCHEDULED_DEPARTURE, DEPARTURE_GRID);
    $weights = [];
    foreach ($slots as $slot) {
        $weights[] = minuteWeight($slot, $dayType);
    }
    $total = (int)array_sum($weights);
    if ($total <= 0) {
        return [];
    }
    $times = [];
    for ($i = 0; $i < $count; $i++) {
        $idx = weightedPick($slots, $weights, $total);
        $times[] = $slots[$idx];
    }
    sort($times);
    return $times;
}

function weightedPick(array $items, array $weights, int $total): int
{
    $r = mt_rand(1, $total);
    $acc = 0;
    foreach ($items as $i => $item) {
        $acc += $weights[$i];
        if ($r <= $acc) {
            return $i;
        }
    }
    return count($items) - 1;
}

/* One weighted departure inside [from, to] on the 15-minute headway grid,
   following the day's time-of-day demand curve. Used for the window-day
   commute schedule. */
function weightedDepartureBetween(int $from, int $to, string $dayType): int
{
    $to = max($from, $to);
    $start = (int)(ceil($from / DEPARTURE_GRID) * DEPARTURE_GRID);
    $end = (int)(floor($to / DEPARTURE_GRID) * DEPARTURE_GRID);
    if ($end < $start) {
        return $from;
    }
    $seconds = range($start, $end, DEPARTURE_GRID);
    $weights = [];
    foreach ($seconds as $s) {
        $weights[] = minuteWeight($s, $dayType);
    }
    $total = (int)array_sum($weights);
    if ($total <= 0) {
        return $from;
    }
    $idx = weightedPick($seconds, $weights, $total);
    return min($seconds[$idx], $end);
}

/* Window-day schedule: one morning outbound departure inside the commute
   window (05:00-09:30) and one afternoon/evening return (15:30-19:30). */
function sampleRoutineDepartures(string $dayType): array
{
    $morningEnd = 9 * 3600 + 1800;
    $afternoonStart = 15 * 3600 + 1800;
    return [
        weightedDepartureBetween(SERVICE_START, $morningEnd, $dayType),
        weightedDepartureBetween($afternoonStart, LAST_SCHEDULED_DEPARTURE, $dayType),
    ];
}

/* Deterministic out-and-back assignment for the window fleet: bus index n
   runs its morning trip on the pair's outbound route and its afternoon trip
   on the opposite direction. */
function routineBusPair(array $ctx, int $busIndex): array
{
    $list = $ctx['routePairList'] ?? [];
    if (!$list) {
        return [];
    }
    return $list[$busIndex % count($list)];
}

try {
    $authedUser = [
        'id' => Auth::check() ? Auth::userId() : null,
        'name' => Auth::check() ? Auth::username() : null,
        'role' => Auth::check() ? Auth::role() : null,
    ];
} catch (Throwable $e) {
    $authedUser = ['id' => null, 'name' => null, 'role' => null];
}

function describeTable(PDO $db, string $table): array
{
    return $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
}

function enumValues(PDO $db, string $table, string $column): ?array
{
    foreach (describeTable($db, $table) as $col) {
        if (strcasecmp($col['Field'], $column) === 0) {
            if (preg_match('/^enum\((.*)\)$/i', $col['Type'], $m)) {
                $parts = array_map(fn($v) => trim($v, "'"), explode(',', $m[1]));
                return array_map('strtolower', $parts);
            }
            return null;
        }
    }
    return null;
}

/* Payment mix for a table whose payment_method column may be an enum: the
   QR Ph share is only offered when the column can actually store it. */
function paymentMixForTable(PDO $db, string $table): array
{
    $mix = HIST_PAYMENT_MIX;
    try {
        $allowed = enumValues($db, $table, 'payment_method');
        if (is_array($allowed) && $allowed) {
            $filtered = [];
            foreach ($mix as $method => $weight) {
                if (in_array((string)$method, $allowed, true)) {
                    $filtered[(string)$method] = $weight;
                }
            }
            if ($filtered) {
                $mix = $filtered;
            }
        }
    } catch (Throwable $e) {
        // varchar column or introspection failure: keep the full mix
    }
    return $mix;
}

function resolvePeriod(array $input): array
{
    $mode = $input['mode'] ?? '';
    $allowedYears = [2022, 2023, 2024, 2025, 2026];

    if ($mode === 'year') {
        $y = (int)($input['year'] ?? 0);
        if (!in_array($y, $allowedYears, true)) {
            throw new RuntimeException('Year must be one of 2022, 2023, 2024, 2025, 2026.');
        }
        return ["$y-01-01", "$y-12-31", (string)$y];
    }

    if ($mode === 'month') {
        $y = (int)($input['year2'] ?? $input['year'] ?? 0);
        $m = (int)($input['month'] ?? 0);
        if (!in_array($y, $allowedYears, true)) {
            throw new RuntimeException('Year must be one of 2022, 2023, 2024, 2025, 2026.');
        }
        if ($m < 1 || $m > 12) {
            throw new RuntimeException('Month must be between 1 and 12.');
        }
        $start = sprintf('%04d-%02d-01', $y, $m);
        return [$start, date('Y-m-t', strtotime($start)), date('F Y', strtotime($start))];
    }

    if ($mode === 'week') {
        $s = trim((string)($input['week_start'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || !strtotime($s)) {
            throw new RuntimeException('Week start must be a valid Y-m-d date.');
        }
        $end = date('Y-m-d', strtotime($s . ' +6 days'));
        return [$s, $end, "$s to $end"];
    }

    if ($mode === 'day') {
        $d = trim((string)($input['day_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
            throw new RuntimeException('Day must be a valid Y-m-d date.');
        }
        if ($d < '2022-01-01' || $d > '2026-12-31') {
            throw new RuntimeException('Day must stay within 2022-01-01 and 2026-12-31.');
        }
        return [$d, $d, $d];
    }

    if ($mode === 'custom') {
        $s = trim((string)($input['start_date'] ?? ''));
        $e = trim((string)($input['end_date'] ?? ''));
        $min = '2022-01-01';
        $max = '2026-12-31';
        foreach ([$s, $e] as $d) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
                throw new RuntimeException('Custom range dates must be valid Y-m-d dates.');
            }
            if ($d < $min || $d > $max) {
                throw new RuntimeException('Custom range must stay within 2022-01-01 and 2026-12-31.');
            }
        }
        if ($s > $e) {
            throw new RuntimeException('Start date cannot be after end date.');
        }
        return [$s, $e, "$s to $e"];
    }

    throw new RuntimeException('Invalid generation mode.');
}

function dateRange(string $start, string $end): array
{
    $out = [];
    $cur = strtotime($start);
    $last = strtotime($end);
    while ($cur <= $last) {
        $out[] = date('Y-m-d', $cur);
        $cur = strtotime('+1 day', $cur);
    }
    return $out;
}

/* =========================================================
   PASSENGER PROFILES
   ========================================================= */
/* Preferred travel windows by rider occupation. Students ride around school
   hours, market vendors at market opening/closing, workers in the two rush
   windows, seniors mid-day, and general commuters per their own habit. */
function preferredWindowsFor(string $occupation, bool $commuter): array
{
    return match ($occupation) {
        'student' => [[6 * 3600, 8 * 3600 + 1800], [15 * 3600, 17 * 3600 + 1800]],
        'vendor' => [[SERVICE_START, 7 * 3600], [16 * 3600, 18 * 3600 + 1800]],
        'worker' => [[5 * 3600 + 1800, 8 * 3600 + 1800], [16 * 3600, 18 * 3600 + 1800]],
        'senior' => [[8 * 3600, 15 * 3600]],
        default => $commuter
            ? [[6 * 3600, 9 * 3600], [16 * 3600, 18 * 3600]]
            : [[7 * 3600, 17 * 3600]],
    };
}

function pickRoutePair(array $stops): array
{
    $n = count($stops);
    if ($n < 2) {
        $only = $stops[0] ?? null;
        return ['boarding' => $only, 'destination' => $only];
    }
    $roll = mt_rand(1, 100);
    if ($roll <= 45) {
        // Boarding at origin terminal/hub, traveling to downstream stops
        $i = 0;
        $j = mt_rand(1, $n - 1);
    } elseif ($roll <= 75) {
        // Boarding at intermediate stop, traveling to destination terminal/hub
        $i = mt_rand(0, $n - 2);
        $j = $n - 1;
    } else {
        // Intermediate segment
        $i = mt_rand(0, $n - 2);
        $j = mt_rand($i + 1, $n - 1);
    }
    return ['boarding' => $stops[$i], 'destination' => $stops[$j]];
}

function findStopPair(array $stops, ?string $fromName, ?string $toName): ?array
{
    if ($fromName === null || $toName === null) {
        return null;
    }
    $from = strtolower(trim($fromName));
    $to = strtolower(trim($toName));
    $i = null;
    $j = null;
    foreach ($stops as $idx => $s) {
        $name = strtolower(trim((string)($s['destination'] ?? '')));
        if ($i === null && $name === $from) {
            $i = $idx;
        }
        if ($j === null && $name === $to) {
            $j = $idx;
        }
    }
    if ($i === null || $j === null || $i >= $j) {
        return null;
    }
    return ['boarding' => $stops[$i], 'destination' => $stops[$j]];
}

/* Which fare_pricing column applies to a passenger type. Discount types stay
   on a discount column whenever the live table exposes one. */
function fareColumnForType(string $type, array $fareColumns): ?string
{
    $t = strtolower(trim($type));
    if (in_array($t, ['student', 'senior', 'pwd'], true)) {
        return $fareColumns['student']
            ?? $fareColumns['senior']
            ?? $fareColumns['pwd']
            ?? $fareColumns['special']
            ?? $fareColumns['regular'];
    }
    if (in_array($t, ['beneficiary', 'cooperative', 'special', 'teacher', 'nurse'], true)) {
        return $fareColumns['special']
            ?? $fareColumns['student']
            ?? $fareColumns['regular'];
    }
    return $fareColumns['regular'];
}

/* Segment fare straight from the fare_pricing rows: the destination fare when
   boarding at the route's first stop, otherwise the destination-minus-boarding
   difference, floored at the route's own lowest regular fare. */
function resolveSegmentFare(array $boarding, array $destination, ?string $column, ?string $regularColumn, float $minimumFare): float
{
    $pick = function (array $row) use ($column, $regularColumn): float {
        foreach ([$column, $regularColumn] as $c) {
            if ($c !== null && array_key_exists($c, $row) && $row[$c] !== null) {
                return (float)$row[$c];
            }
        }
        return 0.0;
    };
    $boardingFare = $pick($boarding);
    $destFare = $pick($destination);
    if ((int)($boarding['display_order'] ?? 1) === 1) {
        $fare = $destFare;
    } else {
        $fare = max($destFare - $boardingFare, $minimumFare);
    }
    return round(max(0.0, $fare), 2);
}

function makePassengerProfile(string $name, array $routeNames, array $routes): array
{
    /* Profile attributes derive from the passenger name, so the same person
       keeps the same occupation, type, ID, route and travel habits across runs. */
    /* Featured Araullo University students from the cooperative's service
       area keep a stable student identity even when their name alone would
       hash into another occupation. */
    $featuredStudents = ['marieveth ortiz', 'nicko avanzado', 'vincin manlapaz'];
    /* Cooperative service area: students, market vendors, workers, seniors
       and general commuters from Cabanatuan City and nearby Nueva Ecija. */
    $occupation = in_array(strtolower(trim($name)), $featuredStudents, true)
        ? 'student'
        : stablePickWeighted([
            'student' => 18,
            'worker' => 30,
            'vendor' => 26,
            'senior' => 10,
            'commuter' => 16,
        ], 'profile-' . $name . '-occupation');

    /* Education and work attendance is mostly obligatory, so students and
       workers draw mostly from the regular bands; market, senior, and general
       travel keeps the wider regular/semi/occasional spread. */
    $band = stableUnit('profile-' . $name . '-band');
    $regularCut = 0.35;
    $semiCut = 0.65;
    if ($occupation === 'student') {
        $regularCut = 0.65;
        $semiCut = 0.92;
    } elseif ($occupation === 'worker') {
        $regularCut = 0.70;
        $semiCut = 0.95;
    } elseif ($occupation === 'senior') {
        $regularCut = 0.20;
        $semiCut = 0.50;
    }
    if ($band <= $regularCut) {           // regular commuter
        $regularity = stableRange('profile-' . $name . '-reg', 0.80, 0.95);
        $commuter = true;
    } elseif ($band <= $semiCut) {        // semi-regular
        $regularity = stableRange('profile-' . $name . '-reg', 0.45, 0.75);
        $commuter = stableUnit('profile-' . $name . '-com') < 0.5;
    } else {                              // occasional
        $regularity = stableRange('profile-' . $name . '-reg', 0.10, 0.40);
        $commuter = stableUnit('profile-' . $name . '-com') < 0.5;
    }

    $ptype = match ($occupation) {
        'student' => 'Student',
        'senior' => 'Senior',
        'vendor' => stablePickWeighted([
            'Regular' => 58,
            'Cooperative' => 32,
            'Beneficiary' => 10,
        ], 'profile-' . $name . '-type'),
        'worker' => stablePickWeighted([
            'Regular' => 86,
            'PWD' => 7,
            'Beneficiary' => 7,
        ], 'profile-' . $name . '-type'),
        default => stablePickWeighted([
            'Regular' => 82,
            'Student' => 8,
            'PWD' => 5,
            'Beneficiary' => 5,
        ], 'profile-' . $name . '-type'),
    };

    $route = $routeNames[(int)floor(stableUnit('profile-' . $name . '-route') * count($routeNames)) % count($routeNames)];
    $stops = $routes[$route];
    $n = count($stops);
    if ($n >= 2) {
        $roll = stableUnit('profile-' . $name . '-hub');
        if ($roll < 0.45) {
            $i = 0;
            $j = min($n - 1, max(1, (int)floor(stableRange('profile-' . $name . '-j', 1, $n))));
        } elseif ($roll < 0.75) {
            $j = $n - 1;
            $i = max(0, min($n - 2, (int)floor(stableRange('profile-' . $name . '-i', 0, $n - 1))));
        } else {
            $i = min((int)floor(stableUnit('profile-' . $name . '-i') * ($n - 1)), $n - 2);
            $j = min($i + 1 + (int)floor(stableUnit('profile-' . $name . '-j') * ($n - 1 - $i)), $n - 1);
        }
        $pair = ['boarding' => $stops[$i], 'destination' => $stops[$j]];
    } else {
        $only = $stops[0] ?? null;
        $pair = ['boarding' => $only, 'destination' => $only];
    }
    $boardingStop = $pair['boarding']['destination'] ?? null;
    $destinationStop = $pair['destination']['destination'] ?? null;

    [$idField, $idValue] = match ($ptype) {
        'Student' => ['student_id', stableDigits('profile-' . $name . '-student', 12)],
        'Senior', 'PWD' => ['senior_pwd_id', stableDigits('profile-' . $name . '-senior', 12)],
        'Beneficiary' => ['beneficiary_id', 'BEN-' . stableDigits('profile-' . $name . '-ben', 6)],
        'Cooperative' => ['coop_disc_id', 'COOP-' . stableDigits('profile-' . $name . '-coop', 5)],
        default => [null, null],
    };

    /* Discount verification status using the application's own vocabulary
       (pending|verified|needs_review|expired|rejected, per
       PassengerAccountService::normalizeVerification). Regular commuters have
       no discount to verify, so their status stays empty (the directory
       treats empty as pending); discount profiles carry a realistic mix -
       most verified, a meaningful pending share, and rare review/expired/
       rejected cases. Only verified profiles count as approved. */
    $discountStatus = null;
    if ($ptype !== 'Regular') {
        $statusRoll = stableUnit('profile-' . $name . '-discount-status');
        $discountStatus = $statusRoll < 0.70 ? 'verified'
            : ($statusRoll < 0.90 ? 'pending'
            : ($statusRoll < 0.93 ? 'needs_review'
            : ($statusRoll < 0.95 ? 'expired' : 'rejected')));
    }

    return [
        'name' => $name,
        'ptype' => $ptype,
        'occupation' => $occupation,
        'regularity' => $regularity,
        'commuter' => $commuter,
        'route' => $route,
        'boardingStop' => $boardingStop,
        'destinationStop' => $destinationStop,
        'windows' => preferredWindowsFor($occupation, $commuter),
        'idField' => $idField,
        'idValue' => $idValue,
        'discountStatus' => $discountStatus,
        /* Weekly routine (travel weekdays, legs per day, one-way/absence
           odds) so the same person keeps the same habits across runs. */
        'plan' => weeklyPlanFor($name, $occupation, $regularity, $commuter),
    ];
}

function buildPassengerPool(array $routeNames, array $routes, int $targetSize = 0): array
{
    /* Contemporary and traditional Filipino given names drawn from the
       cooperative's service area (Cabanatuan City and nearby Nueva Ecija). */
    $firstNames = [
        'Marieveth','Nicko','Jomar','Angelica','Christian','Kimberly','Mark Joseph','Aira','Danica','John Paul',
        'Trisha','Earl','Precious','Jayson','Nicole','Rica','Kevin','Shaina','Jerald','Mica',
        'Paolo','Bea','Arnel','Rowena','Dante','Marilou','Rodel','Editha','Warlito','Jocelyn',
        'Michael','Aiza','Ferdinand','Melanie','Rolando','Ivy','Dennis','Sheryl','Rex','Joanna',
        'Marlon','Lani','Andrew','Rubilyn','Oliver','Rhea','Renato','Mylene','Froilan','Clark',
        'Daisy','Rustom','Ailene','Christine','Jasmine','Lyka','Karl','Lester','Melvin','Noriel',
        'Perla','Queenie','Reymart','Sheila','Tristan','Veronica','Wilfredo','Yvonne','Zosimo','Abegail',
        'Bryan','Cristina','Donna','Emmanuel','Faith','Geraldine','Hazel','Ian','Jenny','Kris',
        'Lourdes','Marissa','Noel','Ogie','Rowel','Sunshine','Tessie','Ulysses','Virgie','Winnie',
        'Adoracion','Bartolome','Cipriano','Eulogio','Feliciana','Gaudencio','Hilario','Isabelita','Juanito','Leonila',
        'Macario','Pacita','Rosalinda','Sergio','Teodoro','Ubaldo','Visitacion','Wenceslao','Yolanda','Zenaida',
        'Luzviminda','Apolinario','Erlinda','Gloria','Norma','Fe','Delia','Corazon','Mila','Nena',
        'Boyet','Bebot','Tessie','Romy','Lito','Dindo','Sonny','Bong','Junrey','Jhun',
        'Vincin','Mariel','Jopay','Kyla','Aian','Micah','Raven','Jade','Paula','Xander',
        'Trishia','Joshua','Alyssa','Bien','Celine','Dexter','Elaine','Francis','Glaiza','Harvey',
    ];
    $lastNames = [
        'Abad','Bautista','Castro','Dela Cruz','Espino','Fernandez','Garcia','Hernandez','Ignacio','Javier',
        'Katigbak','Lopez','Mendoza','Navarro','Ocampo','Pascual','Quinto','Reyes','Santos','Torres',
        'Uy','Villanueva','Wong','Yap','Zamora','Aguilar','Bernardo','Cruz','Dimagiba','Estrada',
        'Fajardo','Gonzales','Herrera','Ibanez','Jimenez','Kalaw','Lumanglas','Manalo','Nunez','Oliveros',
        'Palencia','Quijano','Rivera','Salazar','Tan','Untalan','Velasco','Wee','Yu','Zulueta',
        'Alcantara','Basco','Cabrera','Dizon','Enriquez','Ferrer','Guevarra','Hidalgo','Isidro','Juarez',
        'Lacsamana','Mercado','Natividad','Ortiz','Panganiban','Quiambao','Rosario','Sarmiento','Tolentino','Umali',
        'Valdez','Wenceslao','Ybanez','Zarate','Aquino','Bermudez','Cayanan','Domingo','Evangelista','Flores',
        'Avazando','Avanzado','Maniego','Bacani','Viernes','Mangahas','Capulong','Balagtas','Yumul','Buan','Quizon',
        'Villamor','Manalastas','Soriano','Nicolas','Galang','Esteban','Cayetano','Pineda','Buenaventura','Corpuz',
        'Valino','Bernabe','Roxas','Apilado','Bumanlag','Dacumos','Espiritu','Faundo','Lorenzo','Mactal',
        'Pajarillo','Ramos','Sibug','Talanay','Wagan','Yabut','Manlapaz','Mariano','Sebastian','Concepcion',
        'Batac','Coronel','Dionisio','Eugenio','Fontanilla','Gatchalian','Ilagan','Jose','Lorenzo','Mateo',
        'Nolasco','Ocampo','Pascua','Quinto','Rigor','Sagun','Tiongson','Villar','Ylagan','Zabala',
    ];
    /* Natural full names, including students, workers, vendors and commuters
       the cooperative actually serves. */
    $namedPassengers = [
        'Marieveth Ortiz','Nicko Avazando','Nicko Avanzado','Jomar Dela Cruz','Angelica Bautista','Christian Santos',
        'Kimberly Reyes','Mark Joseph Garcia','Aira Mendoza','Danica Flores','John Paul Ramos',
        'Trisha Villanueva','Earl Castillo','Precious Santiago','Jayson Navarro','Nicole Aquino',
        'Rica Domingo','Kevin Corpuz','Shaina Padilla','Jerald Tolentino','Mica Salvador',
        'Paolo Evangelista','Bea Manalo','Arnel Panganiban','Rowena Jimenez','Dante Herrera',
        'Marilou Soriano','Rodel Mariano','Editha Nicolas','Warlito Galang','Jocelyn Fernandez',
        'Michael Yumul','Aiza Pangilinan','Ferdinand Lacsamana','Melanie Bautista','Rolando Dela Rosa',
        'Ivy Rivera','Dennis Aguilar','Sheryl Cabrera','Rex Balagtas','Joanna Viernes',
        'Marlon Estrada','Lani Villamor','Andrew Quizon','Rubilyn Dizon','Oliver Mangahas',
        'Rhea Capulong','Renato Bernardo','Mylene Ocampo','Froilan Bacani','Daisy Maniego',
        'Rustom Buan','Ailene Sebastian','Clark Jimenez','Sweet Marie Concepcion','Noriel Apilado',
        'Perla Dacumos','Reymart Sibug','Sheila Talanay','Tristan Wagan','Veronica Yabut',
        'Wilfredo Manlapaz','Yvonne Esteban','Zosimo Pineda','Abegail Cayetano','Bryan Buenaventura',
        'Vincin Manlapaz',
    ];

    $passengers = [];
    $seen = [];
    foreach ($namedPassengers as $nm) {
        $seen[strtolower($nm)] = true;
        $passengers[] = makePassengerProfile($nm, $routeNames, $routes);
    }

    /* Size the pool from expected weekly demand so most active profiles ride
       several days per week instead of appearing once a month. The pool is
       built deterministically (stable thinning) so the same roster persists
       across separate generation runs. */
    if ($targetSize <= 0) {
        $targetSize = planPoolTarget(30, 10);
    }
    $targetSize = max(PLAN_POOL_MIN, min(PLAN_POOL_MAX, $targetSize, count($firstNames) * count($lastNames)));
    $density = ($targetSize - count($passengers)) / max(1, count($firstNames) * count($lastNames));
    foreach ($firstNames as $fn) {
        foreach ($lastNames as $ln) {
            if (count($passengers) >= $targetSize) {
                break 2;
            }
            $full = $fn . ' ' . $ln;
            if (isset($seen[strtolower($full)])) {
                continue;
            }
            if (stableUnit('pool-' . $full) >= $density) {
                continue;
            }
            $seen[strtolower($full)] = true;
            $passengers[] = makePassengerProfile($full, $routeNames, $routes);
        }
    }
    if (count($passengers) < $targetSize) {
        foreach ($firstNames as $fn) {
            foreach ($lastNames as $ln) {
                if (count($passengers) >= $targetSize) {
                    break 2;
                }
                $full = $fn . ' ' . $ln;
                if (isset($seen[strtolower($full)])) {
                    continue;
                }
                $seen[strtolower($full)] = true;
                $passengers[] = makePassengerProfile($full, $routeNames, $routes);
            }
        }
    }
    return $passengers;
}

/* =========================================================
   SIMULATION CONTEXT (preloaded once per run)
   ========================================================= */
/* True when a bus row is explicitly marked unavailable in the fleet list.
   Detection is schema-adaptive: a `status` column value that marks the bus out
   of service excludes it from every generated day. */
function busStatusUnavailable(array $bus): bool
{
    $status = '';
    foreach ($bus as $col => $val) {
        if (strtolower((string)$col) === 'status') {
            $status = strtolower(trim((string)$val));
            break;
        }
    }
    return $status !== '' && in_array($status, [
        'inactive', 'maintenance', 'archived', 'retired',
        'out of service', 'out-of-service', 'out_of_service',
    ], true);
}

/* True when the deployment's bus_trips.bus_id column is numeric (a buses.id
   foreign key) rather than the string bus id. Cached per request. */
function busTripBusIdIsNumeric(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = false;
    try {
        foreach (describeTable($db, 'bus_trips') as $col) {
            if (strcasecmp((string)$col['Field'], 'bus_id') === 0) {
                $cached = (bool)preg_match(
                    '/^\s*(tinyint|smallint|mediumint|bigint|int|decimal|numeric)\b/i',
                    (string)($col['Type'] ?? '')
                );
                break;
            }
        }
    } catch (Throwable $e) {
        $cached = false;
    }
    return $cached;
}

/* Translates a bus reference read from bus_trips back to the string bus id
   when the deployment stores the numeric buses.id there; string references
   pass through unchanged. */
function busKeyFromReference(PDO $db, $busRef): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach ($db->query('SELECT id, bus_id FROM buses')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int)($row['id'] ?? 0)] = (string)($row['bus_id'] ?? '');
            }
        } catch (Throwable $e) {
            $map = [];
        }
    }
    $ref = trim((string)$busRef);
    if ($ref === '') {
        return '';
    }
    return $map[(int)$ref] ?? $ref;
}

/* Idempotent, best-effort indexes that keep the admin passenger directory and
   trip-history views fast once a large demo data set exists. Each CREATE is
   attempted once per generation run; existing indexes just raise and are
   ignored. */
function ensurePassengerQueryIndexes(PDO $db): void
{
    $indexes = [
        'passenger_trips' => [
            'idx_pt_passenger' => ['(`passenger_id`)'],
            'idx_pt_boarding_time' => ['(`boarding_time`)'],
            'idx_pt_trip_no' => ['(`trip_no`)'],
        ],
        'passenger_trip_history' => [
            'idx_pth_passenger_name' => ['(`passenger_name`(100))', '(`passenger_name`)'],
            'idx_pth_trip_date' => ['(`trip_date`)'],
            'idx_pth_trip_id' => ['(`trip_id`)'],
        ],
    ];
    foreach ($indexes as $table => $defs) {
        foreach ($defs as $name => $alternatives) {
            foreach ((array)$alternatives as $columns) {
                try {
                    $db->exec("CREATE INDEX `$name` ON `$table` $columns");
                    break;
                } catch (Throwable $e) {
                    // Already present, or this variant is not supported.
                }
            }
        }
    }
}

function buildSimulationContext(PDO $db, string $start, array $opts): array
{
    /* Drivers (active and unblocked only). The status/availability columns
       are read schema-adaptively and filtered through the shared coverage
       eligibility predicate, so trips and coverage use one roster rule. */
    $driverSelect = ['driver_id', 'full_name'];
    try {
        $driverColumns = [];
        foreach (describeTable($db, 'drivers') as $col) {
            $driverColumns[strtolower((string)($col['Field'] ?? ''))] = true;
        }
        if (isset($driverColumns['status'])) {
            $driverSelect[] = 'status';
        }
        if (isset($driverColumns['availability'])) {
            $driverSelect[] = 'availability';
        }
    } catch (Throwable $e) {
        // Base columns only; the predicate stays schema-adaptive.
    }
    $drivers = $db->query('SELECT ' . implode(', ', $driverSelect) . ' FROM drivers')->fetchAll(PDO::FETCH_ASSOC);
    $drivers = array_values(array_filter($drivers, 'dispatchCoverageDriverEligible'));
    if (!$drivers) {
        throw new RuntimeException('No active drivers found in the drivers table.');
    }

    /* Buses. Analytics join passenger_trip_history.bus_number against
       driver_bus_assignment.bus_id, so buses are identified by `bus_id`. */
    $buses = $db->query("SELECT * FROM buses")->fetchAll(PDO::FETCH_ASSOC);
    if (!$buses) {
        throw new RuntimeException('No buses found in the buses table.');
    }
    $busByNumber = [];
    foreach ($buses as $bus) {
        $busByNumber[(string)($bus['bus_id'] ?? '')] = $bus;
    }

    /* Seat capacity per bus drives the capacity-aware passenger load bands. */
    $busSeats = [];
    foreach ($buses as $bus) {
        $bid = (string)($bus['bus_id'] ?? '');
        if ($bid !== '') {
            $busSeats[$bid] = max(0, (int)($bus['seats'] ?? 0));
        }
    }

    /* Buses explicitly marked unavailable in the fleet list are skipped every
       day; every other bus operates. The shared coverage predicate adds the
       operational-status gate when that column exists. */
    $busStatusUnavailable = [];
    foreach ($buses as $bus) {
        $bid = (string)($bus['bus_id'] ?? '');
        if ($bid !== '' && !dispatchCoverageBusEligible($bus)) {
            $busStatusUnavailable[$bid] = true;
        }
    }

    /* Driver -> bus_id via the real driver_bus_assignment relationship. */
    $driverBuses = [];
    try {
        $asg = $db->query(
            "SELECT dba.driver_id, dba.bus_id AS bn
             FROM driver_bus_assignment dba
             JOIN buses b ON b.bus_id = dba.bus_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($asg as $a) {
            $driverBuses[(string)$a['driver_id']][] = (string)$a['bn'];
        }
    } catch (Throwable $e) {
        $driverBuses = [];
    }
    if (empty($driverBuses)) {
        foreach ($drivers as $d) {
            $nums = array_filter(array_map('trim', explode(',', (string)($d['assigned_buses'] ?? ''))));
            if ($nums) {
                $driverBuses[(string)$d['driver_id']] = $nums;
            }
        }
    }

    /* Ensure EVERY active bus is assignable so the generated history reflects
       the full fleet rather than only pre-linked buses. */
    $assignedBuses = [];
    foreach ($driverBuses as $list) {
        foreach ($list as $b) {
            $assignedBuses[(string)$b] = true;
        }
    }
    if (!empty($drivers)) {
        foreach ($buses as $bus) {
            $bid = (string)($bus['bus_id'] ?? '');
            if ($bid === '' || isset($assignedBuses[$bid])) {
                continue;
            }
            $ownerCount = mt_rand(1, 2);
            for ($o = 0; $o < $ownerCount; $o++) {
                $d = $drivers[mt_rand(0, count($drivers) - 1)];
                $driverBuses[(string)$d['driver_id']][] = $bid;
            }
            $assignedBuses[$bid] = true;
        }
    }
    $driversWithBuses = array_values(array_filter(
        $drivers,
        fn($d) => !empty($driverBuses[(string)$d['driver_id']])
    ));

    /* Fare pricing / routes / opposite-direction pairing. */
    $fareCols = array_map(fn($c) => $c['Field'], describeTable($db, 'fare_pricing'));
    $kmCol = null;
    foreach ($fareCols as $f) {
        $low = strtolower($f);
        if (strpos($low, 'km') !== false || strpos($low, 'distance') !== false) {
            $kmCol = $f;
            break;
        }
    }
    $fareRows = $db->query("SELECT * FROM fare_pricing ORDER BY route_name ASC, display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (!$fareRows) {
        throw new RuntimeException('No fare pricing records found in the fare_pricing table.');
    }
    $routes = [];
    foreach ($fareRows as $row) {
        $routes[$row['route_name']][] = $row;
    }
    $routes = array_filter($routes, fn($stops) => count($stops) >= 2);
    if (!$routes) {
        throw new RuntimeException('No route with at least two stops found in fare_pricing.');
    }

    /* fare_pricing is the single source of truth for every amount. Detect the
       fare columns the live table exposes and derive each route's floor fare
       from its own rows; no fare amount is hardcoded. */
    $fareColumns = [
        'regular' => 'regular_fare',
        'student' => 'student_fare',
        'senior' => 'senior_fare',
        'pwd' => 'pwd_fare',
        'special' => 'special_fare',
    ];
    $availableFareCols = [];
    foreach (array_keys($fareRows[0]) as $k) {
        $availableFareCols[strtolower((string)$k)] = (string)$k;
    }
    foreach ($fareColumns as $logical => $col) {
        $fareColumns[$logical] = $availableFareCols[$col] ?? null;
    }
    $routeMinFare = [];
    foreach ($routes as $rn => $stops) {
        $min = 0.0;
        if ($fareColumns['regular'] !== null) {
            foreach ($stops as $s) {
                $v = (float)($s[$fareColumns['regular']] ?? 0);
                if ($v > 0 && ($min === 0.0 || $v < $min)) {
                    $min = $v;
                }
            }
        }
        $routeMinFare[$rn] = $min;
    }

    $routeNames = array_keys($routes);
    $routeEndpoints = [];
    foreach ($routeNames as $rn) {
        $st = $routes[$rn];
        $routeEndpoints[$rn] = [$st[0], $st[count($st) - 1]];
    }
    $routePairs = [];
    foreach ($routeNames as $rn) {
        $parts = explode('-', (string)$rn, 2);
        if (count($parts) === 2) {
            /* 'X-Y' and 'Y-X' share one order-insensitive key so each
               direction finds its opposite in the route map. */
            $pairParts = [strtolower(trim($parts[0])), strtolower(trim($parts[1]))];
            sort($pairParts);
            $key = implode('|', $pairParts);
            $routePairs[$key][] = $rn;
        }
    }
    $routeOpposite = [];
    foreach ($routePairs as $grp) {
        if (count($grp) === 2) {
            $routeOpposite[$grp[0]] = $grp[1];
            $routeOpposite[$grp[1]] = $grp[0];
        }
    }

    /* Out-and-back schedule pairs for window days: each unordered pair of
       opposite route directions contributes both orientations, outbound
       oriented first, so the sorted allow-list buses cover each direction
       as a morning outbound while its return direction stays available. */
    $routePairList = [];
    $unorderedPairs = [];
    foreach ($routeOpposite as $rn => $opp) {
        if (strcmp((string)$rn, (string)$opp) < 0) {
            $unorderedPairs[] = [(string)$rn, (string)$opp];
        }
    }
    usort($unorderedPairs, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
    foreach ($unorderedPairs as $pair) {
        $routePairList[] = $pair;
    }
    foreach ($unorderedPairs as $pair) {
        $routePairList[] = [$pair[1], $pair[0]];
    }

    $kmByFpId = [];
    $kmByStopName = [];
    try {
        $stopRows = $db->query("SELECT fare_pricing_id, km_marker, stop_name FROM route_stops")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stopRows as $sr) {
            if ($sr['km_marker'] !== null && $sr['km_marker'] !== '') {
                $fpId = $sr['fare_pricing_id'] ?? null;
                if ($fpId !== null) {
                    $kmByFpId[(int)$fpId] = (float)$sr['km_marker'];
                }
                $key = strtolower(trim((string)$sr['stop_name']));
                if ($key !== '') {
                    $kmByStopName[$key] = (float)$sr['km_marker'];
                }
            }
        }
    } catch (Throwable $e) {
        $kmByFpId = [];
        $kmByStopName = [];
    }

    /* Real per-route distances from the route's own km markers, joined through
       fare_pricing so each route keeps its actual length instead of a shared
       placeholder. */
    $routeDistanceByName = [];
    try {
        $rdRouteRows = $db->query(
            "SELECT fp.route_name AS rn, rs.km_marker AS km
             FROM route_stops rs
             JOIN fare_pricing fp ON fp.id = rs.fare_pricing_id
             WHERE rs.km_marker IS NOT NULL AND rs.km_marker <> ''"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byRoute = [];
        foreach ($rdRouteRows as $r) {
            $byRoute[(string)$r['rn']][] = (float)$r['km'];
        }
        foreach ($byRoute as $rn => $markers) {
            if (count($markers) >= 2) {
                $d = max($markers) - min($markers);
                if ($d >= 1.0) {
                    $routeDistanceByName[$rn] = round($d, 2);
                }
            }
        }
    } catch (Throwable $e) {
        // Fall back to the per-route marker lookup below.
    }
    foreach ($routes as $rn => $stops) {
        if (isset($routeDistanceByName[$rn])) {
            continue;
        }
        $markers = [];
        foreach ($stops as $s) {
            $fpId = (int)($s['id'] ?? 0);
            $mk = $kmByFpId[$fpId]
                ?? $kmByStopName[strtolower(trim((string)$s['destination']))]
                ?? null;
            if ($mk !== null) {
                $markers[] = (float)$mk;
            }
        }
        $dist = count($markers) >= 2 ? max($markers) - min($markers) : null;
        if ($dist === null || $dist < 1.0) {
            /* Stable per-route fallback so distinct routes never collapse to
               one shared placeholder distance. */
            $dist = round(stableRange('route-dist-' . $rn, 8.0, 38.0), 1);
        }
        $routeDistanceByName[$rn] = round($dist, 2);
    }

    /* Diesel price anchored to the database where available: the newest
       fuel_prices row, or the finance cost settings' gas value when the fuel
       table has none. Historical dates interpolate the constant anchors; the
       DB value anchors the current end of the curve. */
    $dieselPrice = null;
    $dieselPriceDate = null;
    $dieselPriceSource = 'anchor-fallback';
    try {
        $fpRow = $db->query(
            "SELECT price_per_liter, updated_at FROM fuel_prices WHERE fuel_type = 'Diesel' ORDER BY updated_at DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!empty($fpRow) && $fpRow['price_per_liter'] !== null && $fpRow['price_per_liter'] !== ''
            && (float)$fpRow['price_per_liter'] > 0) {
            $dieselPrice = (float)$fpRow['price_per_liter'];
            $stamp = trim((string)($fpRow['updated_at'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $stamp)) {
                $dieselPriceDate = substr($stamp, 0, 10);
            }
            $dieselPriceSource = 'fuel_prices';
        }
    } catch (Throwable $e) {
        $dieselPrice = null;
    }
    if ($dieselPrice === null || $dieselPrice <= 0) {
        try {
            $gasRow = $db->query(
                "SELECT amount FROM financial_cost_settings WHERE cost_key = 'gas' AND status = 'Active' ORDER BY id DESC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if (!empty($gasRow) && (float)$gasRow['amount'] > 0) {
                $dieselPrice = (float)$gasRow['amount'];
                $dieselPriceSource = 'financial_cost_settings';
            }
        } catch (Throwable $e) {
            // fall through to the constant fallback
        }
    }
    if ($dieselPrice === null || $dieselPrice <= 0) {
        $dieselPrice = 65.55;
    }
    $dieselPriceAnchor = $dieselPriceDate !== null
        ? ['date' => $dieselPriceDate, 'price' => $dieselPrice]
        : null;

    /* Payment mix for generated rides: QR Ph is only offered when the
       target table's payment_method column can actually store it (enum
       schemas without the value drop the QR Ph share). */
    $paymentMix = paymentMixForTable($db, 'passenger_trips');
    $paymentMixHistory = paymentMixForTable($db, 'passenger_trip_history');

    /* Audit actors: real user accounts matched by role for generated activity
       events. Actions whose role has no account in this deployment fall back
       to the generator's system actor instead of borrowing an unrelated user. */
    $auditActors = ['admin' => [], 'dispatcher' => [], 'finance_manager' => [], 'finance_officer' => []];
    try {
        foreach ($db->query(
            "SELECT id, username, full_name, role FROM users WHERE COALESCE(is_active, 1) = 1 AND LOWER(COALESCE(status, 'active')) = 'active'"
        )->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $role = strtolower(trim((string)($u['role'] ?? '')));
            $username = trim((string)($u['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $actor = ['id' => (int)($u['id'] ?? 0), 'username' => $username, 'role' => $role];
            if ($role === 'dispatcher') {
                $auditActors['dispatcher'][] = $actor;
            } elseif ($role === 'finance_manager') {
                $auditActors['finance_manager'][] = $actor;
            } elseif ($role === 'finance_officer') {
                $auditActors['finance_officer'][] = $actor;
            } elseif (in_array($role, ['admin', 'super_admin'], true)) {
                $auditActors['admin'][] = $actor;
            }
        }
    } catch (Throwable $e) {
        $auditActors = ['admin' => [], 'dispatcher' => [], 'finance_manager' => [], 'finance_officer' => []];
    }

    /* Insert columns (excluding auto_increment and generated columns) for
       passenger_trips, the table every generated ride is written to. */
    $ptCols = [];
    $ptRequiredDefaults = [];
    foreach (describeTable($db, 'passenger_trips') as $col) {
        $extra = (string)($col['Extra'] ?? '');
        if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'generated') !== false) {
            continue;
        }
        $ptCols[] = $col['Field'];
        $default = $col['Default'] ?? null;
        $hasDefault = $default !== null && strtoupper((string)$default) !== 'NULL';
        if (strtoupper((string)($col['Null'] ?? '')) === 'NO') {
            $ptRequiredDefaults[strtolower((string)$col['Field'])] = $hasDefault
                ? normalizeColumnDefault($default, (string)($col['Type'] ?? ''))
                : passengerRequiredDefault((string)($col['Type'] ?? ''), (string)$col['Field']);
        }
    }
    if (!$ptCols) {
        throw new RuntimeException('No usable passenger_trips columns found.');
    }

    /* Same protection for the passenger registry: deployments add required
       columns over time and a demo account must still insert cleanly. */
    $passengerRequiredDefaults = [];
    foreach (describeTable($db, 'passengers') as $col) {
        $extra = (string)($col['Extra'] ?? '');
        if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'generated') !== false) {
            continue;
        }
        $default = $col['Default'] ?? null;
        $hasDefault = $default !== null && strtoupper((string)$default) !== 'NULL';
        if (strtoupper((string)($col['Null'] ?? '')) === 'NO') {
            $passengerRequiredDefaults[strtolower((string)$col['Field'])] = $hasDefault
                ? normalizeColumnDefault($default, (string)($col['Type'] ?? ''))
                : passengerRequiredDefault((string)($col['Type'] ?? ''), (string)$col['Field']);
        }
    }

    /* Keep the admin directory and history views fast once data grows. */
    ensurePassengerQueryIndexes($db);

    /* Discrepancy workflow tables, created before the run's transaction so the
       DDL cannot implicitly commit generation work mid-run. */
    ensureDiscrepancySchema($db);

    /* Operational trips: when the deployment has a usable bus_trips table,
       every generated trip becomes a real row there and passenger_trips
       references its id, exactly like the live QR/one-time flows. Without it
       the generator falls back to its own TRIP-#### references. */
    $busTripCols = [];
    $busTripRequiredDefaults = [];
    $busTripsUsable = false;
    $busTripBusIdNumeric = busTripBusIdIsNumeric($db);
    try {
        foreach (describeTable($db, 'bus_trips') as $col) {
            $extra = (string)($col['Extra'] ?? '');
            if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'generated') !== false) {
                continue;
            }
            $busTripCols[] = $col['Field'];
            $default = $col['Default'] ?? null;
            $hasDefault = $default !== null && strtoupper((string)$default) !== 'NULL';
            if (strtoupper((string)($col['Null'] ?? '')) === 'NO') {
                $busTripRequiredDefaults[strtolower((string)$col['Field'])] = $hasDefault
                    ? normalizeColumnDefault($default, (string)($col['Type'] ?? ''))
                    : passengerRequiredDefault((string)($col['Type'] ?? ''), (string)$col['Field']);
            }
        }
        $busTripColsLower = array_map('strtolower', $busTripCols);
        $busTripsUsable = in_array('bus_id', $busTripColsLower, true)
            && (in_array('trip_date', $busTripColsLower, true) || in_array('created_at', $busTripColsLower, true));
    } catch (Throwable $e) {
        $busTripCols = [];
        $busTripRequiredDefaults = [];
        $busTripsUsable = false;
    }

    /* Real dispatcher accounts when the deployment has them, so operational
       trip rows carry the same accountable staff the monitoring pages show. */
    $dispatchers = [];
    try {
        foreach ($db->query(
            "SELECT inspector_id FROM inspector_accounts
             WHERE LOWER(COALESCE(role,'')) = 'dispatcher'
             ORDER BY inspector_id LIMIT 20"
        )->fetchAll(PDO::FETCH_COLUMN) as $row) {
            $id = trim((string)$row);
            if ($id !== '') {
                $dispatchers[] = $id;
            }
        }
    } catch (Throwable $e) {
        $dispatchers = [];
    }

    /* Trip history stores generated ids as TRIP-####. Only when there is no
       bus_trips to reference does passenger_trips.trip_no need widening to
       carry those ids; a numeric column then falls back to numeric ids. */
    $ptTripNoIsText = false;
    foreach (describeTable($db, 'passenger_trips') as $col) {
        if (strcasecmp((string)$col['Field'], 'trip_no') === 0) {
            $ptTripNoIsText = (bool)preg_match('/char|text/i', (string)$col['Type']);
            break;
        }
    }
    $ptTripNoNumericOnly = false;
    if (!$busTripsUsable && in_array('trip_no', $ptCols, true) && !$ptTripNoIsText) {
        try {
            $db->exec('ALTER TABLE `passenger_trips` MODIFY COLUMN `trip_no` VARCHAR(100) NULL');
            $ptTripNoIsText = true;
        } catch (Throwable $e) {
            $ptTripNoNumericOnly = true;
        }
    }

    $demoMarker = demoAccountMarker($db);

    /* Ensure fuel_refuels table exists for actual refueling transactions. */
    try {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `fuel_refuels` (
                `refuel_id` INT AUTO_INCREMENT PRIMARY KEY,
                `bus_id` VARCHAR(50) NOT NULL,
                `driver_id` VARCHAR(50) NULL,
                `trip_id` VARCHAR(100) NULL,
                `refuel_odo` DECIMAL(10,2) NOT NULL,
                `liters` DECIMAL(10,2) NOT NULL,
                `price_per_liter` DECIMAL(10,2) NOT NULL,
                `total_cost` DECIMAL(10,2) NOT NULL,
                `is_full_tank` TINYINT(1) NOT NULL DEFAULT 1,
                `refuel_type` ENUM('Full Tank', 'Partial') NOT NULL DEFAULT 'Full Tank',
                `notes` TEXT NULL,
                `fuel_proof_url` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_refuels_bus` (`bus_id`),
                INDEX `idx_refuels_driver` (`driver_id`),
                INDEX `idx_refuels_trip` (`trip_id`),
                INDEX `idx_refuels_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $e) {}

    /* Ensure fuel_logs has distance_km column for trip-level consumption. */
    try {
        $db->exec("ALTER TABLE `fuel_logs` ADD COLUMN IF NOT EXISTS `distance_km` DECIMAL(10,2) NULL AFTER `ending_odo`");
    } catch (Throwable $e) {
        try {
            $db->exec("ALTER TABLE `fuel_logs` ADD COLUMN `distance_km` DECIMAL(10,2) NULL");
        } catch (Throwable $e2) {}
    }

    /* Fuel log schema introspection. */
    $fuelCols = [];
    foreach (describeTable($db, 'fuel_logs') as $col) {
        $fuelCols[strtolower($col['Field'])] = $col;
    }
    $fuelColNames = array_keys($fuelCols);
    $fuelTripIdIsText = isset($fuelCols['trip_id'])
        && (bool)preg_match('/char|text/i', (string)$fuelCols['trip_id']['Type']);
    $fuelTripIdNumericOnly = false;
    if (isset($fuelCols['trip_id']) && !$fuelTripIdIsText) {
        /* Trip history stores generated ids as TRIP-####. Widen a numeric
           trip_id so every odometer/KPL record links to its trip; existing
           numeric dispatch ids keep working through numeric comparison. */
        try {
            $db->exec('ALTER TABLE fuel_logs MODIFY COLUMN trip_id VARCHAR(100) NULL');
            $fuelTripIdIsText = true;
            $fuelCols['trip_id']['Type'] = 'varchar(100)';
        } catch (Throwable $e) {
            $fuelTripIdNumericOnly = true;
        }
    }

    /* Refuel transactions schema introspection (excluding auto_increment refuel_id). */
    $refuelCols = [];
    foreach (describeTable($db, 'fuel_refuels') as $col) {
        if (stripos((string)($col['Extra'] ?? ''), 'auto_increment') !== false) {
            continue;
        }
        $refuelCols[] = $col['Field'];
    }

    /* Continue numbering after every TRIP-#### id the database already holds -
       archive rows from earlier runs and previously generated live rows. */
    $maxTripPth = 0;
    try {
        $maxTripPth = (int)$db->query(
            "SELECT MAX(CAST(SUBSTRING_INDEX(trip_id,'-',-1) AS UNSIGNED)) FROM passenger_trip_history WHERE trip_id LIKE 'TRIP-%'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $maxTripPth = 0;
    }
    $maxTripPt = 0;
    try {
        $maxTripPt = (int)$db->query(
            "SELECT MAX(CAST(SUBSTRING_INDEX(trip_no,'-',-1) AS UNSIGNED)) FROM passenger_trips WHERE trip_no LIKE 'TRIP-%'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $maxTripPt = 0;
    }
    $nextTrip = max(3000, $maxTripPth + 1, $maxTripPt + 1);
    $nextTripFloor = max(3000, $maxTripPth + 1);

    /* Per-bus persistent profiles + odometer/fuel seeding. Continues monotonic
       odometer from prior fuel_logs or fuel_refuels rows. */
    $priorFuel = [];
    try {
        $prev = $db->prepare(
            "SELECT bus_id, MAX(ending_odo) AS odo FROM fuel_logs
             WHERE created_at < ? AND ending_odo IS NOT NULL AND ending_odo > 0
             GROUP BY bus_id"
        );
        $prev->execute([$start]);
        foreach ($prev->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $priorFuel[(string)$row['bus_id']] = (float)$row['odo'];
        }
    } catch (Throwable $e) {
        try {
            $prev = $db->prepare(
                "SELECT bus_id, MAX(starting_odo) AS odo FROM fuel_logs
                 WHERE created_at < ? AND starting_odo IS NOT NULL AND starting_odo > 0
                 GROUP BY bus_id"
            );
            $prev->execute([$start]);
            foreach ($prev->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $priorFuel[(string)$row['bus_id']] = (float)$row['odo'];
            }
        } catch (Throwable $e2) {
            $priorFuel = [];
        }
    }

    $lastRefuelOdo = [];
    try {
        $prevRefuel = $db->prepare(
            "SELECT bus_id, MAX(refuel_odo) AS odo FROM fuel_refuels
             WHERE created_at < ? AND refuel_odo IS NOT NULL AND refuel_odo > 0
             GROUP BY bus_id"
        );
        $prevRefuel->execute([$start]);
        foreach ($prevRefuel->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bid = (string)$row['bus_id'];
            $rodo = (float)$row['odo'];
            $lastRefuelOdo[$bid] = $rodo;
            if (!isset($priorFuel[$bid]) || $rodo > $priorFuel[$bid]) {
                $priorFuel[$bid] = $rodo;
            }
        }
    } catch (Throwable $e) {}

    $busStates = [];
    foreach ($buses as $bus) {
        $bid = (string)($bus['bus_id'] ?? '');
        if ($bid === '') {
            continue;
        }
        $cap = null;
        foreach (['fuel_capacity', 'tank_capacity', 'fuel_tank_capacity', 'capacity_liters', 'fuel_tank'] as $colName) {
            if (isset($bus[$colName]) && is_numeric($bus[$colName]) && (float)$bus[$colName] > 0) {
                $cap = (float)$bus[$colName];
                break;
            }
        }
        $tank = $cap ?? FUEL_TANK_CAPACITY;
        $baseline = round(stableRange('kpl-' . $bid, FUEL_BASELINE_KPL_MIN, FUEL_BASELINE_KPL_MAX), 2);

        /* Per-bus low-fuel threshold: 20%–30% of tank with slight per-bus
           randomness, so the fleet does not all refuel at the same level. */
        $threshold = round($tank * stableRange('threshold-' . $bid, FUEL_THRESHOLD_MIN_RATIO, FUEL_THRESHOLD_MAX_RATIO), 2);

        /* Refuel-interval consistency. When real fuel history exists the bus
           starts from its prior ending odometer with whatever fuel it has
           burned since its last recorded refuel, so the first threshold
           refuel that follows reconciles with that unrecorded consumption.
           Otherwise it starts with a realistic 80–100% tank plus the matching
           unmodeled interval distance, so the first refuel's liters still
           match its ODO interval. */
        if (isset($priorFuel[$bid])) {
            $odo = max(1000.0, $priorFuel[$bid]);
            if (isset($lastRefuelOdo[$bid]) && $odo >= $lastRefuelOdo[$bid]) {
                $shortfall = round(min($tank * 0.90, ($odo - $lastRefuelOdo[$bid]) / max(1.0, $baseline)), 2);
            } else {
                $shortfall = round($tank * stableRange('seed-shortfall-' . $bid, 0.02, 0.15), 2);
            }
        } else {
            $odo = round(stableRange('odo-' . $bid, 40000, 120000), 2);
            $shortfall = round($tank * stableRange('seed-shortfall-' . $bid, 0.0, 0.20), 2);
        }
        $busStates[$bid] = [
            'bus' => $bus,
            'tank' => (float)$tank,
            'baselineKpl' => $baseline,
            'threshold' => $threshold,
            'reliability' => round(stableRange('rel-' . $bid, 0.85, 1.15), 3),
            'odo' => $odo,
            'fuel' => round((float)$tank - $shortfall, 2),
            'lastEventOdo' => round($odo - $shortfall * $baseline, 2),
            'lastFullTankOdo' => $odo,
            'intervalFuel' => $shortfall,
            'expectedSinceRefuel' => $shortfall,
            'headroomAtLastEvent' => 0.0,
            'lastRefuelDate' => null,
            'lastFuelLogAt' => null,
            'tripCount' => 0,
            'totalDistance' => 0.0,
        ];
    }

    /* Per-driver absence probability (sparse, stable per driver). */
    $driverAbsence = [];
    foreach ($drivers as $d) {
        $did = (string)$d['driver_id'];
        $driverAbsence[$did] = clampf(ABSENT_DRIVER_BASE * stableRange('absence-' . $did, 0.6, 1.8), 0.01, 0.15);
    }

    return [
        'drivers' => $drivers,
        'driversWithBuses' => $driversWithBuses,
        'driverBuses' => $driverBuses,
        'driverAbsence' => $driverAbsence,
        'buses' => $buses,
        'busByNumber' => $busByNumber,
        'busSeats' => $busSeats,
        'busStatusUnavailable' => $busStatusUnavailable,
        'busStates' => $busStates,
        'fareCols' => $fareCols,
        'kmCol' => $kmCol,
        'routes' => $routes,
        'routeNames' => $routeNames,
        'routeEndpoints' => $routeEndpoints,
        'routeOpposite' => $routeOpposite,
        'routePairList' => $routePairList,
        'fareColumns' => $fareColumns,
        'routeMinFare' => $routeMinFare,
        'kmByFpId' => $kmByFpId,
        'kmByStopName' => $kmByStopName,
        'routeDistanceByName' => $routeDistanceByName,
        'dieselPrice' => $dieselPrice,
        'dieselPriceDate' => $dieselPriceDate,
        'dieselPriceSource' => $dieselPriceSource,
        'dieselPriceAnchor' => $dieselPriceAnchor,
        'paymentMix' => $paymentMix,
        'paymentMixHistory' => $paymentMixHistory,
        'auditActors' => $auditActors,
        'ptCols' => $ptCols,
        'ptRequiredDefaults' => $ptRequiredDefaults,
        'passengerRequiredDefaults' => $passengerRequiredDefaults,
        'busTripCols' => $busTripCols,
        'busTripRequiredDefaults' => $busTripRequiredDefaults,
        'busTripsUsable' => $busTripsUsable,
        'busTripBusIdNumeric' => $busTripBusIdNumeric,
        'dispatchers' => $dispatchers,
        'ptTripNoIsText' => $ptTripNoIsText,
        'ptTripNoNumericOnly' => $ptTripNoNumericOnly,
        'passengerCols' => liveColumns($db, 'passengers'),
        'demoMarker' => $demoMarker,
        'fuelCols' => $fuelCols,
        'fuelColNames' => $fuelColNames,
        'fuelTripIdIsText' => $fuelTripIdIsText,
        'fuelTripIdNumericOnly' => $fuelTripIdNumericOnly,
        'refuelCols' => $refuelCols,
        'nextTrip' => $nextTrip,
        'nextTripFloor' => $nextTripFloor,
    ];
}

/* =========================================================
   DAILY SCHEDULE
   ========================================================= */
/* One driver per bus per day. The pairing is drawn fresh each day (the bus's
   own drivers first, with occasional rotation) but never changes between that
   bus's trips; a bus left without a driver stays idle for the day. */
function assignDayBusDrivers(array $ctx, array $present, array $unavailable, string $day, bool $scopeActive = false): array
{
    $presentById = [];
    foreach ($present as $drv) {
        $did = (string)($drv['driver_id'] ?? '');
        if ($did !== '') {
            $presentById[$did] = $drv;
        }
    }
    $taken = [];
    $primary = [];

    /* When active drivers are fewer than the fleet, the scarce driver slots
       rotate across the whole fleet by day (stable per-day ranking) instead
       of always leaving the same trailing buses without a driver. Every
       active bus therefore operates - and gets fuel records - across a
       multi-day period. With enough drivers the table order is kept. */
    $busIds = array_keys($ctx['busStates']);
    if (count($presentById) < count($busIds)) {
        usort($busIds, static function (string $a, string $b) use ($day): int {
            $ra = stableUnit('day-bus-staff-' . $day . '-' . $a);
            $rb = stableUnit('day-bus-staff-' . $day . '-' . $b);
            if ($ra === $rb) {
                return strcmp($a, $b);
            }
            return $ra <=> $rb;
        });
    }

    foreach ($busIds as $bid) {
        $bid = (string)$bid;
        if (isset($unavailable[$bid])) {
            continue;
        }
        if ($scopeActive && !fleetScopeAllowsBus((string)$bid)) {
            continue;   // outside the window fleet; drivers stay free for allowed buses
        }
        $own = [];
        $nonOwn = [];
        foreach ($presentById as $did => $drv) {
            if (isset($taken[$did])) {
                continue;
            }
            if (in_array((string)$bid, $ctx['driverBuses'][$did] ?? [], true)) {
                $own[] = $drv;
            } else {
                $nonOwn[] = $drv;
            }
        }
        if (!$own && !$nonOwn) {
            continue;   // no driver left for this bus today
        }
        $rotate = stableUnit('day-driver-rotate-' . $day . '-' . $bid) < 0.25;
        $pool = ($rotate && $nonOwn) ? $nonOwn : ($own ?: $nonOwn);
        $drv = $pool[mt_rand(0, count($pool) - 1)];
        $primary[(string)$bid] = $drv;
        $taken[(string)$drv['driver_id']] = true;
    }
    return $primary;
}

function generateDailySchedule(
    string $day,
    array $ctx,
    array $opts,
    array $present,
    array $unavailable,
    int &$nextTrip,
    array &$stats,
    array &$ledger
): array {
    $dayType = dayTypeOf($day);
    $weather = dayWeather($day);
    $scopeActive = !empty($opts['fleet_scope']) && fleetScopeDayActive($day);
    $scopeBounds = $scopeActive ? fleetScopeBoundsForDay($day) : null;
    $routineDay = $scopeActive && !empty($ctx['routePairList']);
    $driverBusy = [];
    $busBusy = [];
    $planned = [];

    /* Window days run a bounded, varying fleet: a stable per-day budget of
       FLEET_SCOPE_MIN_BUSES_PER_DAY..FLEET_SCOPE_MAX_BUSES_PER_DAY
       allow-listed buses (day profile biased, one fewer in the rain; two
       trips each keeps the day inside the 13-18 band), chosen by a stable
       per-day rotation so every allowed bus still operates across the
       period. The rest stay idle for this day. */
    if ($scopeActive) {
        $scopeCandidates = [];
        foreach (array_keys($ctx['busStates']) as $scopeBid) {
            $scopeBid = (string)$scopeBid;
            if (isset($unavailable[$scopeBid]) || !fleetScopeAllowsBus($scopeBid)) {
                continue;
            }
            $scopeCandidates[] = $scopeBid;
        }
        $scopeDayBuses = fleetScopeDayBuses($scopeCandidates, $day, windowDayBusBudget($scopeCandidates, $day));
        $scopeDaySet = array_fill_keys($scopeDayBuses, true);
        foreach (array_keys($ctx['busStates']) as $scopeBid) {
            $scopeBid = (string)$scopeBid;
            if (fleetScopeAllowsBus($scopeBid) && !isset($scopeDaySet[$scopeBid])) {
                $unavailable[$scopeBid] = true;   // rotates back in on other days
            }
        }
    }

    $primaryDrivers = assignDayBusDrivers($ctx, $present, $unavailable, $day, $scopeActive);

    /* Fleet-wide daily volume: only a deterministic subset of the available,
       staffed buses runs, sharing the day's configured trip target. Window
       days keep their own per-bus count and skip this allocation. */
    $fleetTarget = 0;
    $allocation = [];
    if (!$scopeActive) {
        $fleetTarget = fleetDailyTarget($day, $opts + ['day_profile' => serviceProfile($day)]);
        $candidateIds = [];
        foreach (array_keys($ctx['busStates']) as $candidateBid) {
            $candidateBid = (string)$candidateBid;
            if (isset($unavailable[$candidateBid])) {
                continue;
            }
            if (!isset($primaryDrivers[$candidateBid])) {
                continue;   // unstaffed buses cannot take a share
            }
            $candidateIds[] = $candidateBid;
        }
        $allocation = fleetAllocateTrips($candidateIds, $day, $fleetTarget, $opts);
        /* The effective target is what the fleet can absorb under the per-bus
           caps, so the ledger compares planned trips against it. */
        $fleetTarget = array_sum($allocation);
    }
    $cancelledBefore = (int)$stats['cancelled'];
    /* The day's opening departure is pinned inside the first 90 minutes of the
       service window (about 5:00 AM); later departures keep the demand curve. */
    $dayOpenerAssigned = false;

    /* Window days run each operating bus as an out-and-back pair: a morning
       departure on the bus's assigned outbound route and an afternoon/evening
       return on the opposite direction, so commute routines always have both
       legs scheduled. */
    $busPairIndex = [];
    if ($routineDay) {
        $allowedBusIds = [];
        foreach ($scopeDayBuses as $pairBid) {
            if (!isset($unavailable[$pairBid]) && fleetScopeAllowsBus((string)$pairBid)) {
                $allowedBusIds[] = (string)$pairBid;
            }
        }
        sort($allowedBusIds, SORT_STRING);
        foreach ($allowedBusIds as $listIdx => $listBid) {
            $busPairIndex[$listBid] = $listIdx;
        }
    }

    /* The allocated buses run their share of the day's plan: each bus's trips
       are sampled from the day-type departure weights and chained with
       arrival-to-departure turnaround for that bus. */
    foreach ($ctx['busStates'] as $bid => $state) {
        if (isset($unavailable[$bid])) {
            continue;
        }
        if ($scopeActive && !fleetScopeAllowsBus((string)$bid)) {
            continue;   // only the window's allow-list operates on these days
        }
        if (!isset($primaryDrivers[$bid])) {
            $stats['no_driver_buses']++;   // no driver available for this bus today
            continue;
        }
        $tripCount = $scopeActive
            ? FLEET_SCOPE_TRIPS_PER_BUS
            : (int)($allocation[$bid] ?? 0);
        if ($tripCount <= 0) {
            continue;
        }
        $depTimes = $routineDay
            ? sampleRoutineDepartures($dayType)
            : sampleDayDepartures($dayType, $tripCount);
        if (!$scopeActive && !$dayOpenerAssigned && $depTimes) {
            $earlySlots = range(SERVICE_START, SERVICE_START + 90 * 60, DEPARTURE_GRID);
            $opener = $earlySlots[(int)floor(stableUnit('day-opener-' . $day) * count($earlySlots)) % count($earlySlots)];
            $depTimes[0] = $opener;
            sort($depTimes);
            $dayOpenerAssigned = true;
        }
        $busFreeFrom = SERVICE_START;
        $depIndex = 0;
        foreach ($depTimes as $depSec) {
            /* A bus cannot depart before it has arrived from its previous trip
               and completed its turnaround. */
            if ($depSec < $busFreeFrom) {
                $depSec = $busFreeFrom + mt_rand(0, 5 * 60);
            }
            if ($depSec > LAST_SCHEDULED_DEPARTURE) {
                $stats['cancelled']++;
                continue;
            }
            /* Rare operational events: cancelled trip. Window days skip this so
               an operating allow-listed bus reliably produces its two trips. */
            if (!$scopeActive && mt_rand(1, 1000) <= 8) {
                $stats['cancelled']++;
                continue;
            }
            $actSec = $depSec;
            if (mt_rand(1, 1000) <= 30) {              // delayed departure
                $actSec = min($depSec + mt_rand(5, 25) * 60, LAST_SCHEDULED_DEPARTURE);
                $stats['delayed']++;
            }

            if ($routineDay) {
                $pair = routineBusPair($ctx, (int)($busPairIndex[(string)$bid] ?? 0));
                $routeName = (string)($pair[$depIndex === 0 ? 0 : 1] ?? $ctx['routeNames'][0]);
            } else {
                $routeName = $ctx['routeNames'][mt_rand(0, count($ctx['routeNames']) - 1)];
            }
            $depIndex++;
            $distance = $ctx['routeDistanceByName'][$routeName];

            $traffic = trafficFactor($actSec, $dayType) * (mt_rand(96, 104) / 100);
            $speed = max(10.0, FUEL_AVG_SPEED_KPH * $traffic * weatherFactor($weather));
            $durationMin = $distance / $speed * 60 + mt_rand(4, 12);
            $durationMin *= mt_rand(90, 112) / 100;
            if (mt_rand(1, 1000) <= 20) {              // unusually short/long trip
                $durationMin *= mt_rand(0, 1) === 0 ? 0.65 : 1.6;
                $stats['unusual_durations']++;
            }
            $durationMin = clampf($durationMin, 15.0, 240.0);
            $durationSec = (int)round($durationMin * 60);
            $turnSec = (int)round(max(12 * 60, ($durationMin * 0.30 + 8) * 60));

            /* The final trip must arrive at the destination terminal by the
               service end (9:00 PM): long routes leave earlier instead of
               arriving late. A bus that cannot fit the trip before the cutoff
               after its previous trip is cancelled. */
            $latestDeparture = SERVICE_END - $durationSec;
            if ($actSec > $latestDeparture) {
                $actSec = max($busFreeFrom, min($actSec, $latestDeparture));
                $depSec = min($depSec, $actSec);
                if ($actSec + $durationSec > SERVICE_END) {
                    $stats['cancelled']++;
                    continue;
                }
            }

            $planned[] = [
                'bus' => (string)$bid,
                'routeName' => $routeName,
                'distance' => $distance,
                'sec' => $actSec,
                'schedSec' => $depSec,
                'durationMin' => $durationMin,
                'durationSec' => $durationSec,
                'turnSec' => $turnSec,
                'trafficFactor' => $traffic,
                'weather' => $weather,
            ];
            $busFreeFrom = $actSec + $durationSec + $turnSec;
        }
    }

    usort($planned, fn($a, $b) => $a['sec'] <=> $b['sec']);

    /* Today is generated as a day still in progress: only trips that have
       already arrived by the present time (Asia/Manila) become completed
       records. Nothing departs after "now", so today's history never shows a
       trip that has not happened yet. */
    if ($day === date('Y-m-d')) {
        $nowSec = ((int)date('G')) * 3600 + ((int)date('i')) * 60 + (int)date('s');
        $beforeTrim = count($planned);
        $planned = array_values(array_filter(
            $planned,
            static fn($p) => (int)$p['sec'] + (int)$p['durationSec'] <= $nowSec
        ));
        $stats['today_future_trips_skipped'] = (int)($stats['today_future_trips_skipped'] ?? 0)
            + ($beforeTrim - count($planned));
    }

    /* Allocate scarce driver time across the whole fleet first: every available
       bus gets its first trip before any bus takes a second. */
    $firstByBus = [];
    $remainingPlans = [];
    foreach ($planned as $plan) {
        $bid = (string)$plan['bus'];
        if (!isset($firstByBus[$bid])) {
            $firstByBus[$bid] = $plan;
        } else {
            $remainingPlans[] = $plan;
        }
    }
    $staffingOrder = array_merge(array_values($firstByBus), $remainingPlans);

    $trips = [];

    /* Every planned trip is driven by the bus's assigned driver for the day. */
    foreach ($staffingOrder as $plan) {
        $bid = (string)$plan['bus'];
        $actSec = (int)$plan['sec'];
        $busReady = (int)($busBusy[$bid] ?? 0);
        if ($busReady > $actSec) {
            $actSec = $busReady + mt_rand(0, 2 * 60);
            $stats['delayed']++;
        }
        if ($actSec > LAST_SCHEDULED_DEPARTURE) {
            $stats['cancelled']++;
            continue;
        }
        /* The trip must still arrive at the destination terminal by the
           service end (9:00 PM); a bus delayed past its latest departure is
           cancelled instead of arriving after the terminal cutoff. */
        if ($actSec + (int)$plan['durationSec'] > SERVICE_END) {
            $stats['cancelled']++;
            continue;
        }

        $routeName = (string)$plan['routeName'];
        $distance = (float)$plan['distance'];
        $durationMin = (float)$plan['durationMin'];
        $traffic = (float)$plan['trafficFactor'];
        $weather = (string)$plan['weather'];
        $depSec = (int)$plan['schedSec'];

        $drv = $primaryDrivers[$bid] ?? null;
        if ($drv === null || !isset($ctx['busByNumber'][$bid])) {
            $stats['cancelled']++;
            continue;
        }
        $did = (string)$drv['driver_id'];

        $durationSec = (int)$plan['durationSec'];
        $turnSec = (int)$plan['turnSec'];
        $arrSec = $actSec + $durationSec;
        $driverBusy[$did] = $actSec + $durationSec + $turnSec;
        $busBusy[$bid] = max((int)($busBusy[$bid] ?? 0), $actSec + $durationSec + $turnSec);

        /* Capacity-aware load band for this trip: the window profile's reduced
           bounds on window days, otherwise the assigned bus's seat-scaled
           band with the bus's seats as the occupied-space cap. */
        $busSeatCount = (int)($ctx['busSeats'][$bid] ?? 0);
        $seatCapacity = fleetNormalizeSeats($busSeatCount);
        $tripBounds = $scopeBounds;
        if ($tripBounds === null) {
            [$bandMin, $bandMax] = loadBoundsForSeats($busSeatCount);
            $tripBounds = [$bandMin, $bandMax, $seatCapacity];
        }

        $tripId = 'TRIP-' . $nextTrip++;
        if (isset($ledger['trip_ids'][$tripId])) {
            throw new RuntimeException("Validation failed: duplicate generated trip id $tripId.");
        }
        $ledger['trip_ids'][$tripId] = true;
        $ledger['trip_id_count']++;
        $ledger['trip_bounds'][$tripId] = $tripBounds;
        $ledger['durations'][] = $durationMin;
        $ledger['busy']['driver'][$day][$did][] = [$actSec, $actSec + $durationSec, $actSec + $durationSec + $turnSec];
        $ledger['busy']['bus'][$day][$bid][] = [$actSec, $actSec + $durationSec, $actSec + $durationSec + $turnSec];

        $stats['trips_scheduled']++;
        $stats['buses_used'][$bid] = true;
        $ledger['operating_buses'][$bid] = true;
        $stats['drivers_used'][$did] = true;

        $trips[] = [
            'id' => $tripId,
            'drv' => $drv,
            'busNumber' => $bid,
            'routeName' => $routeName,
            'distance' => $distance,
            'sec' => $actSec,
            'schedSec' => $depSec,
            'departure' => fmtTime($actSec),
            'arrival' => fmtTime($arrSec),
            'arrSec' => $arrSec,
            'durationMin' => $durationMin,
            'turnSec' => $turnSec,
            'trafficFactor' => $traffic,
            'weather' => $weather,
            'space' => $scopeActive ? FLEET_SCOPE_SPACE_MAX : $seatCapacity,
            'paxTarget' => tripDemandCount($actSec, $dayType, (float)$opts['demand_multiplier'], $tripBounds),
            'paxBounds' => $tripBounds,
            'paxRows' => [],
        ];
    }

    /* Rare requested replacement: a driver whose own trips are finished takes
       over the bus's remaining trips for the day. The bus-driver pairing is
       otherwise untouched between trips - no silent swaps. */
    if ($trips) {
        $indexesByBus = [];
        foreach ($trips as $idx => $t) {
            $indexesByBus[(string)$t['busNumber']][] = $idx;
        }
        /* Each driver's ready time is their last arrival plus that trip's
           turnaround; a candidate still driving or turning around at the
           handover moment must never be selected, or the batch validation
           rejects the day. */
        $driverReady = dispatchCoverageDriverReadyTimes($trips);
        $replacementUsed = [];
        foreach ($indexesByBus as $bid => $indexes) {
            sort($indexes);
            if (count($indexes) < 2) {
                continue;   // nothing to hand over
            }
            if (stableUnit('driver-replace-' . $day . '-' . $bid) >= 0.035) {
                continue;   // ~3.5% of bus-days receive a replacement request
            }
            $current = (string)$trips[$indexes[0]]['drv']['driver_id'];
            $splitPos = 1 + (int)floor(stableUnit('driver-replace-at-' . $day . '-' . $bid) * (count($indexes) - 1));
            $changeSec = (int)$trips[$indexes[$splitPos]]['sec'];
            $candidates = [];
            foreach ($present as $drv) {
                $did = (string)$drv['driver_id'];
                if ($did === $current || isset($replacementUsed[$did])) {
                    continue;
                }
                if (($driverReady[$did] ?? 0) >= $changeSec) {
                    continue;   // still driving or turning around at that moment
                }
                $candidates[] = $drv;
            }
            if (!$candidates) {
                continue;   // the request cannot be granted today
            }
            $replacement = $candidates[(int)floor(stableUnit('driver-replace-pick-' . $day . '-' . $bid) * count($candidates)) % count($candidates)];
            $newDid = (string)$replacement['driver_id'];
            for ($k = $splitPos; $k < count($indexes); $k++) {
                $t = $trips[$indexes[$k]];
                $trips[$indexes[$k]]['drv'] = $replacement;
                $ledger['busy']['driver'][$day][$newDid][] = [
                    (int)$t['sec'],
                    (int)$t['sec'] + (int)round((float)$t['durationMin'] * 60),
                    (int)$t['sec'] + (int)round((float)$t['durationMin'] * 60) + (int)($t['turnSec'] ?? 0),
                ];
            }
            $replacementUsed[$newDid] = true;
            $stats['driver_replacements']++;
            $stats['drivers_used'][$newDid] = true;
        }
    }

    /* Fleet-target bookkeeping for the batch validation: the day's planned
       trips either ran or were cancelled. Window days carry no target here. */
    if (!$scopeActive) {
        $ledger['fleet_stats'][$day] = [
            'target' => $fleetTarget,
            'scheduled' => count($trips),
            'cancelled' => max(0, (int)$stats['cancelled'] - $cancelledBefore),
        ];
    }
    return $trips;
}

/* =========================================================
   PASSENGER DEMAND
   ========================================================= */
/* Fill every scheduled trip from the persistent passenger pool using each
   profile's weekly travel plan. Planned legs are matched onto the day's own
   trips (outbound on the home route, return on the opposite direction), the
   same person may ride different buses on different days, under-minimum trips
   are topped up deterministically from plan-compatible profiles with weekly
   slack, and a wider fallback keeps every completed trip inside its band.
   Rides are recorded in the ledger for weekly conformance validation. */
function generateTripPassengers(
    array &$trips,
    array $profiles,
    array &$pstate,
    array $ctx,
    string $day,
    array &$ledger
): array {
    $out = [];
    if (!$trips || !$profiles) {
        return $out;
    }

    $dayType = dayTypeOf($day);
    $weekKey = date('o-W', strtotime($day));
    $profilesByName = [];
    foreach ($profiles as $p) {
        $profilesByName[(string)$p['name']] = $p;
    }

    /* Per-day plan materialization: each profile's legs for this day are
       computed once and reused across trips. */
    if (($pstate['plan_day'] ?? null) !== $day) {
        $pstate['plan_day'] = $day;
        $pstate['plan_legs'] = [];
    }

    $dayPlans = [];
    foreach ($profiles as $p) {
        $name = (string)$p['name'];
        if (!array_key_exists($name, $pstate['plan_legs'])) {
            $plan = $p['plan'] ?? weeklyPlanFor(
                $name,
                (string)$p['occupation'],
                (float)$p['regularity'],
                (bool)$p['commuter']
            );
            $pstate['plan_legs'][$name] = plannedLegsFor(
                (array)$plan,
                $name,
                $day,
                (string)$p['occupation'],
                (float)$p['regularity'],
                $dayType
            );
        }
        $legs = $pstate['plan_legs'][$name];
        if (!$legs) {
            continue;
        }
        $route = (string)$p['route'];
        $dayPlans[] = [
            'name' => $name,
            'day' => $day,
            'route' => $route,
            'return' => $ctx['routeOpposite'][$route] ?? null,
            'legs' => $legs,
            'windows' => (array)$p['windows'],
        ];
    }

    /* The day's trips as the pure matcher expects them. The fill floor is
       demand-aware inside the seat band: peak/rush trips must reach a share of
       their time-of-day target while quiet trips only need the band minimum,
       which keeps average loads inside the requested 15-35 range without
       pushing every trip to the same count. */
    $tripInputs = [];
    foreach ($trips as $i => $tr) {
        $bounds = $tr['paxBounds'] ?? null;
        $paxMin = $bounds !== null ? (int)$bounds[0] : TRIP_PASSENGER_MIN;
        $paxMax = $bounds !== null ? (int)$bounds[1] : TRIP_PASSENGER_MAX;
        $target = max($paxMin, min($paxMax, (int)$tr['paxTarget']));
        $fillMin = max($paxMin, min($target, (int)round($paxMin + ($target - $paxMin) * 0.50)));
        $tripInputs[] = [
            'index' => (int)$i,
            'id' => (string)$tr['id'],
            'routeName' => (string)$tr['routeName'],
            'sec' => (int)$tr['sec'],
            'arrSec' => (int)$tr['arrSec'],
            'min' => $fillMin,
            'target' => $target,
            'capacity' => max(0, (int)$tr['space']),
        ];
    }

    $match = assignPlannedLegs($dayPlans, $tripInputs, $ctx['routeOpposite']);
    $assignments = $match['assignments'];
    $load = $match['load'];

    $underMin = static function (array $trips, array $load): array {
        $list = [];
        foreach ($trips as $t) {
            if ((int)($load[(int)$t['index']] ?? 0) < (int)$t['min']) {
                $list[] = $t;
            }
        }
        return $list;
    };

    $assignedNames = [];
    foreach ($assignments as $a) {
        $assignedNames[(string)$a['name']] = true;
    }

    /* Top-up from plan-compatible profiles that still have weekly slack. */
    $pending = $underMin($tripInputs, $load);
    if ($pending) {
        $topUpPool = [];
        foreach ($profiles as $p) {
            $name = (string)$p['name'];
            if (isset($assignedNames[$name])) {
                continue;
            }
            if ((int)($ledger['week_rides'][$weekKey][$name] ?? 0) >= planWeeklyRideCap((string)$p['occupation'])) {
                continue;
            }
            $route = (string)$p['route'];
            $topUpPool[] = [
                'name' => $name,
                'route' => $route,
                'return' => $ctx['routeOpposite'][$route] ?? null,
                'windows' => (array)$p['windows'],
            ];
        }
        if ($topUpPool) {
            $topUp = assignTopUpLegs($pending, $topUpPool, $load, $day);
            $assignments = array_merge($assignments, $topUp['assignments']);
            $load = $topUp['load'];
            foreach ($topUp['assignments'] as $a) {
                $assignedNames[(string)$a['name']] = true;
            }
        }
    }

    /* Wider fallback: riders with daily and weekly room fill anything still
       below its minimum, so every completed trip stays inside its band. */
    $pending = $underMin($tripInputs, $load);
    if ($pending) {
        $fallbackPool = [];
        foreach ($profiles as $p) {
            $name = (string)$p['name'];
            if (isset($assignedNames[$name])) {
                continue;
            }
            if ((int)($ledger['rides'][$day][$name] ?? 0) >= MAX_RIDES_PER_PASSENGER) {
                continue;
            }
            if ((int)($ledger['week_rides'][$weekKey][$name] ?? 0) >= planWeeklyRideCap((string)$p['occupation'])) {
                continue;
            }
            $route = (string)$p['route'];
            $fallbackPool[] = [
                'name' => $name,
                'route' => $route,
                'return' => $ctx['routeOpposite'][$route] ?? null,
                'windows' => (array)$p['windows'],
            ];
        }
        if ($fallbackPool) {
            $fallback = assignTopUpLegs($pending, $fallbackPool, $load, $day);
            $assignments = array_merge($assignments, $fallback['assignments']);
            $load = $fallback['load'];
        }
    }

    /* Emit the assignment rows the row-writing loop consumes. */
    foreach ($assignments as $a) {
        $i = (int)$a['trip'];
        $name = (string)$a['name'];
        if (!isset($trips[$i], $profilesByName[$name])) {
            continue;
        }
        $tr = $trips[$i];
        $p = $profilesByName[$name];
        $rides = (int)($ledger['rides'][$day][$name] ?? 0);
        if ($rides >= MAX_RIDES_PER_PASSENGER) {
            continue;
        }
        /* A profile that already reached its weekly cap (for example through
           earlier top-ups) skips further planned legs for the week. */
        if ((int)($ledger['week_rides'][$weekKey][$name] ?? 0) >= planWeeklyRideCap((string)$p['occupation'])) {
            continue;
        }
        $route = (string)$tr['routeName'];
        $stops = $ctx['routes'][$route] ?? null;
        if ($stops === null) {
            continue;
        }
        if ((string)$a['leg'] === 'ret') {
            /* Return leg: board where the passenger usually alights. */
            $pair = findStopPair($stops, $p['destinationStop'], $p['boardingStop']) ?? pickRoutePair($stops);
        } else {
            $pair = findStopPair($stops, $p['boardingStop'], $p['destinationStop']) ?? pickRoutePair($stops);
        }
        if ($pair['boarding'] === null || $pair['destination'] === null) {
            continue;
        }

        $tripId = (string)$tr['id'];
        $ledger['rides'][$day][$name] = $rides + 1;
        $ledger['week_rides'][$weekKey][$name] = (int)($ledger['week_rides'][$weekKey][$name] ?? 0) + 1;
        $ledger['trip_space'][$tripId] = ($ledger['trip_space'][$tripId] ?? 0) + 1;
        $ledger['trip_pax'][$tripId] = ($ledger['trip_pax'][$tripId] ?? 0) + 1;
        $trips[$i]['space']--;

        $rideKey = $tripId . '|' . $name;
        if (isset($ledger['ride_keys'][$day][$rideKey])) {
            $ledger['ride_dupes'][] = $day . '|' . $rideKey;
        }
        $ledger['ride_keys'][$day][$rideKey] = true;

        /* Weekly conformance bookkeeping. */
        if (!isset($ledger['plan'][$day][$name])) {
            $ledger['plan'][$day][$name] = [
                'planned' => (array)$pstate['plan_legs'][$name],
                'placed' => [],
                'topup' => false,
                'out_at' => null,
                'ret_at' => null,
            ];
            $ledger['plan_caps'][$name] = planWeeklyRideCap((string)$p['occupation']);
        }
        $leg = (string)$a['leg'];
        $ledger['plan'][$day][$name]['placed'][$leg] = $route;
        $ledger['plan'][$day][$name][$leg === 'ret' ? 'ret_at' : 'out_at'] = (int)$tr['sec'];
        if (!empty($a['topup'])) {
            $ledger['plan'][$day][$name]['topup'] = true;
        }

        $out[] = [
            'trip' => $i,
            'demand' => [
                'name' => $name,
                'commuter' => (bool)$p['commuter'],
                'route' => $route,
                'ptype' => (string)$p['ptype'],
                'time' => (int)$tr['sec'],
                'profile' => $p,
            ],
            'dir' => ($leg === 'ret') ? 'ba' : 'ab',
            'effectiveRoute' => $route,
            'pair' => $pair,
        ];
    }
    return $out;
}

/* =========================================================
   PAYMENT / INSPECTION SIMULATION
   ========================================================= */
function simulatePayment(string $day, string $cutoff, float $fare, array $opts, string $remitBy, array &$stats, string $method = 'cash'): array
{
    $method = in_array($method, ['cash', 'gcash', 'qrph', 'hybrid'], true) ? $method : 'cash';
    $stats['methods'][$method] = ($stats['methods'][$method] ?? 0) + 1;

    /* Cash the driver is expected to remit for this payment method. */
    $expectedFor = static function (string $m) use ($fare): float {
        if ($m === 'gcash' || $m === 'qrph') {
            return 0.0;
        }
        if ($m === 'hybrid') {
            return round($fare * 0.6, 2);
        }
        return $fare;
    };

    /* Today and future dates keep the REMITTANCE pending until an actual
       submission or verification happens. The passenger payment itself is a
       separate state: rides on the current operating day are recorded as
       paid (the queue needs them to list drivers awaiting remittance), while
       future-dated rides stay pending because they have not operated yet. */
    if (remittanceDayState($day, $cutoff) === 'pending') {
        $isFuture = $day > $cutoff;
        if ($isFuture) {
            $stats['pending']++;
        } else {
            $stats['paid']++;
        }
        $stats['remit_pending']++;
        return [
            'payment_method' => $method,
            'payment_status' => $isFuture ? 'pending' : 'paid',
            'remittance_status' => 'Pending',
            'remitted_by' => null,
            'remitted_at' => null,
            'expected_cash' => $expectedFor($method),
            'actual_cash' => null,
            'discrepancy_amount' => null,
        ];
    }

    $stats['paid']++;
    $stats['remit_remitted']++;
    return [
        'payment_method' => $method,
        'payment_status' => 'paid',
        'remittance_status' => 'Remitted',
        'remitted_by' => $remitBy,
        'remitted_at' => $day . ' ' . fmtTime(mt_rand(17 * 3600, 22 * 3600)),
        'expected_cash' => $expectedFor($method),
        'actual_cash' => $expectedFor($method),
        'discrepancy_amount' => 0.0,
    ];
}

/* Simulated inspection accounting only — nothing is written to tables. */
function simulateInspection(bool $tripHasDiscrepancy, array $opts, array &$stats): void
{
    $rates = INSPECTION_RATES[$opts['inspection_rate']] ?? INSPECTION_RATES['low'];
    if ($tripHasDiscrepancy) {
        $stats['inspections_significant']++;
        return;
    }
    if (mt_rand(1, 1000) <= (int)round($rates['significant'] * 10)) {
        $stats['inspections_significant']++;
    } elseif (mt_rand(1, 1000) <= (int)round($rates['minor'] * 10)) {
        $stats['inspections_minor']++;
    }
}

/* =========================================================
   GENERATED PASSENGER ACCOUNTS + PASSENGER_TRIPS ROWS
   ========================================================= */

/* Type -> code prefix for generated passenger codes: Student STD-, Senior
   SEN-, PWD PWD-, Regular REG-, Beneficiary BEN-, Cooperative COO-, cargo
   OTH-, and the remaining discount classifications on their own prefixes. */
function demoPassengerPrefix(string $type): string
{
    static $map = [
        'regular' => 'REG', 'standard' => 'REG',
        'student' => 'STD',
        'senior' => 'SEN', 'seniorcitizen' => 'SEN',
        'pwd' => 'PWD', 'personwithdisability' => 'PWD',
        'beneficiary' => 'BEN',
        'cooperative' => 'COO', 'coop' => 'COO',
        'teacher' => 'TCH', 'nurse' => 'NUR',
        'voucher' => 'VCH', 'promo' => 'PRO',
        'rpmvfmpcmember' => 'MEM', 'member' => 'MEM',
        'special' => 'SPC', 'other' => 'OTH',
    ];
    $key = preg_replace('/[^a-z]/', '', strtolower(trim($type))) ?? '';
    return $map[$key] ?? 'REG';
}

function demoSlug(string $value): string
{
    $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '.', trim($value)));
    $slug = trim($slug, '.');
    return $slug !== '' ? substr($slug, 0, 24) : 'passenger';
}

function demoIdentityToken(string $identity): string
{
    $n = (int)(hexdec(substr(md5('demo-token-' . $identity), 0, 8)) % 100000000);
    return str_pad((string)$n, 8, '0', STR_PAD_LEFT);
}

/* Natural-looking Gmail address derived from a name: firstlast123@gmail.com,
   amartinez123@gmail.com, lastfirst123@gmail.com or firstl123@gmail.com.
   The identity key drives the variant and suffix so the same person always
   gets the same address; `attempt` resolves collisions inside the pool. */
function demoNaturalEmail(string $identity, string $displayName, int $attempt = 0): string
{
    $parts = array_values(array_filter(
        preg_split('/\s+/', trim($displayName)) ?: [],
        static fn($p) => trim((string)$p) !== ''
    ));
    $first = strtolower((string)preg_replace('/[^a-z]/i', '', (string)($parts[0] ?? 'passenger')));
    $last = strtolower((string)preg_replace('/[^a-z]/i', '', (string)($parts[count($parts) - 1] ?? 'user')));
    if ($first === '') {
        $first = 'passenger';
    }
    if ($last === '') {
        $last = 'user';
    }
    $variant = (int)floor(stableUnit('email-variant-' . $identity) * 4) % 4;
    $local = match ($variant) {
        1 => substr($first, 0, 1) . $last,
        2 => $last . $first,
        3 => $first . substr($last, 0, 1),
        default => $first . $last,
    };
    $number = 10 + (int)(hexdec(substr(md5('email-number-' . $identity . '-' . $attempt), 0, 8)) % 990);
    return substr($local, 0, 40) . $number . '@' . DEMO_EMAIL_DOMAIN;
}

/* One natural Gmail address per pool profile, guaranteed unique. */
function buildDemoEmailMap(array $passengers): array
{
    $used = [];
    $map = [];
    foreach ($passengers as $profile) {
        $name = (string)($profile['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $attempt = 0;
        do {
            $email = demoNaturalEmail($name, $name, $attempt);
            $attempt++;
        } while (isset($used[strtolower($email)]));
        $used[strtolower($email)] = true;
        $map[strtolower($name)] = $email;
    }
    return $map;
}

/* Which marker column identifies demo accounts in the deployed schema:
   email (production), the natural id (varchar deployments such as the local
   dev database), or passenger_code as a last resort. */
function demoAccountMarker(PDO $db): array
{
    $cols = liveColumns($db, 'passengers');
    if (isset($cols['email'])) {
        return ['mode' => 'email', 'column' => $cols['email']];
    }
    if (isset($cols['id'])) {
        $isAuto = false;
        foreach (describeTable($db, 'passengers') as $c) {
            if (strcasecmp((string)$c['Field'], 'id') === 0) {
                $isAuto = stripos((string)($c['Extra'] ?? ''), 'auto_increment') !== false;
                break;
            }
        }
        if (!$isAuto) {
            return ['mode' => 'id', 'column' => $cols['id']];
        }
    }
    if (isset($cols['passenger_code'])) {
        return ['mode' => 'code', 'column' => $cols['passenger_code']];
    }
    return ['mode' => 'none', 'column' => null];
}

function demoProfileLookup(array $profile, array $ctx): ?string
{
    $name = (string)$profile['name'];
    $email = $ctx['demoEmails'][strtolower($name)]
        ?? demoNaturalEmail($name, $name);
    $token = demoIdentityToken($name);
    return match ($ctx['demoMarker']['mode'] ?? 'none') {
        'email' => $email,
        'id' => DEMO_ID_PREFIX . $token,
        'code' => DEMO_CODE_PREFIX . $token,
        default => null,
    };
}

function demoCargoLookup(string $identity, array $ctx, string $recipient = '', int $attempt = 0): ?string
{
    $token = demoIdentityToken($identity);
    $sender = $recipient !== '' ? $recipient : 'market vendor';
    return match ($ctx['demoMarker']['mode'] ?? 'none') {
        'email' => demoNaturalEmail($identity, $sender, $attempt),
        'id' => DEMO_ID_PREFIX . $token,
        'code' => DEMO_CODE_PREFIX . $token,
        default => null,
    };
}

function demoApplyLookup(array &$data, array $ctx, string $lookup): void
{
    switch ($ctx['demoMarker']['mode'] ?? 'none') {
        case 'email':
            $data['email'] = $lookup;
            break;
        case 'id':
            $data['id'] = $lookup;
            break;
        case 'code':
            $data['passenger_code'] = $lookup;
            break;
    }
}

function demoAccountCondition(array $marker, string $alias): ?string
{
    $col = $marker['column'] ?? null;
    if ($col === null) {
        return null;
    }
    $sql = $alias . '.`' . $col . '`';
    return match ($marker['mode']) {
        'email' => '(' . $sql . " LIKE '" . DEMO_EMAIL_PATTERN . "'"
            . ' OR ' . $sql . " LIKE '%@" . DEMO_EMAIL_LEGACY_DOMAIN . "'"
            . ' OR ' . $alias . ".id IN (SELECT record_id FROM audit_trail"
            . " WHERE user_agent = '" . LIVE_SIM_UA . "' AND action = 'History Demo Account'))",
        'id' => $sql . " LIKE '" . DEMO_ID_PREFIX . "%'",
        'code' => $sql . " LIKE '" . DEMO_CODE_PREFIX . "%'",
        default => null,
    };
}

/* Preload every demo account the previous runs created so a re-run reuses
   ids/codes instead of inserting duplicates. Natural emails carry no visible
   marker, so email-mode preload matches the exact addresses computed for this
   pool plus the legacy patterned addresses from earlier builds. */
function buildDemoAccountCache(PDO $db, array $marker, array $poolEmails = []): array
{
    $map = [];
    $col = $marker['column'] ?? null;
    if ($col === null) {
        return $map;
    }
    $colSql = '`' . $col . '`';
    $rows = [];
    if ($marker['mode'] === 'email') {
        foreach (array_chunk(array_values($poolEmails), 400) as $chunk) {
            $chunk = array_values(array_filter($chunk, static fn($v) => (string)$v !== ''));
            if (!$chunk) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            foreach (liveRows(
                $db,
                "SELECT id, passenger_code, type, discount_status, $colSql AS k FROM passengers WHERE $colSql IN ($placeholders)",
                $chunk
            ) as $row) {
                $rows[] = $row;
            }
        }
        foreach (liveRows(
            $db,
            "SELECT id, passenger_code, type, discount_status, $colSql AS k FROM passengers WHERE ($colSql LIKE ? OR $colSql LIKE ?)",
            [DEMO_EMAIL_PATTERN, '%@' . DEMO_EMAIL_LEGACY_DOMAIN]
        ) as $row) {
            $rows[] = $row;
        }
        /* Accounts this tool created are registered in audit_trail. Cargo
           addresses derive from per-trip identities, so they cannot be
           recomputed from the pool; loading the registered accounts keeps
           re-runs idempotent instead of colliding on the unique email. */
        foreach (liveRows(
            $db,
            "SELECT p.id, p.passenger_code, p.type, p.discount_status, p.$colSql AS k FROM passengers p
             WHERE p.id IN (
                 SELECT record_id FROM audit_trail
                 WHERE user_agent = ? AND action = 'History Demo Account'
             )",
            [LIVE_SIM_UA]
        ) as $row) {
            $rows[] = $row;
        }
    } elseif ($marker['mode'] === 'id') {
        $rows = liveRows($db, "SELECT id, passenger_code, type, discount_status, id AS k FROM passengers WHERE id LIKE ?", [DEMO_ID_PREFIX . '%']);
    } elseif ($marker['mode'] === 'code') {
        $rows = liveRows($db, "SELECT id, passenger_code, type, discount_status, $colSql AS k FROM passengers WHERE $colSql LIKE ?", [DEMO_CODE_PREFIX . '%']);
    }
    foreach ($rows as $row) {
        $key = strtolower(trim((string)($row['k'] ?? '')));
        if ($key !== '') {
            $map[$key] = $row;
        }
    }
    return $map;
}

/* Settle every previously generated demo record dated before the cutoff
   (Asia/Manila today): paid + Remitted with reconciled cash. Today and future
   records stay pending, and genuine/imported rows are never matched. Returns
   the number of rows settled per table. */
function settleDemoRemittances(PDO $db, array $marker, string $remitBy = 'Finance Officer', string $cutoff = ''): array
{
    $result = ['passenger_trips' => 0, 'passenger_trip_history' => 0];
    $cutoff = trim($cutoff) !== '' ? trim($cutoff) : date('Y-m-d');
    $cashExpr = static function (string $alias): string {
        return "CASE WHEN LOWER(COALESCE($alias.payment_method,'')) IN ('gcash','qrph') THEN 0.00 "
            . "WHEN LOWER(COALESCE($alias.payment_method,'')) = 'hybrid' THEN ROUND(COALESCE($alias.fare,0) * 0.6, 2) "
            . "ELSE COALESCE($alias.fare,0) END";
    };

    /* Live rides that belong to this tool's demo accounts. */
    $condition = demoAccountCondition($marker, 'p');
    $ptCols = liveColumns($db, 'passenger_trips');
    if ($condition !== null
        && isset($ptCols['remittance_status'], $ptCols['payment_status'], $ptCols['boarding_time'], $ptCols['passenger_id'])) {
        $sets = ["pt.payment_status = 'paid'", "pt.remittance_status = 'Remitted'"];
        $params = [];
        if (isset($ptCols['expected_cash'])) {
            $sets[] = 'pt.expected_cash = ' . $cashExpr('pt');
        }
        if (isset($ptCols['actual_cash'])) {
            $sets[] = 'pt.actual_cash = ' . $cashExpr('pt');
        }
        if (isset($ptCols['discrepancy_amount'])) {
            $sets[] = 'pt.discrepancy_amount = 0.00';
        }
        if (isset($ptCols['remitted_by'])) {
            $sets[] = "pt.remitted_by = COALESCE(NULLIF(pt.remitted_by, ''), ?)";
            $params[] = $remitBy;
        }
        if (isset($ptCols['remitted_at'])) {
            $sets[] = "pt.remitted_at = COALESCE(pt.remitted_at, CONCAT(DATE(pt.boarding_time), ' 18:15:00'))";
        }
        $result['passenger_trips'] = liveExec(
            $db,
            "UPDATE passenger_trips pt
             JOIN passengers p ON p.id = pt.passenger_id
             SET " . implode(', ', $sets) . "
             WHERE $condition
               AND DATE(pt.boarding_time) < ?
               AND (COALESCE(pt.remittance_status, '') <> 'Remitted'
                    OR LOWER(COALESCE(pt.payment_status, '')) <> 'paid')",
            array_merge($params, [$cutoff])
        );
    }

    /* Archive rows generated by this tool (new ARCV- rows and the legacy
       TRIP- manifest ids). */
    $pthCols = liveColumns($db, 'passenger_trip_history');
    if (isset($pthCols['remittance_status'], $pthCols['payment_status'], $pthCols['trip_date'], $pthCols['trip_id'])) {
        $sets = ["payment_status = 'paid'", "remittance_status = 'Remitted'"];
        $params = [];
        if (isset($pthCols['expected_cash'])) {
            $sets[] = 'expected_cash = ' . $cashExpr('passenger_trip_history');
        }
        if (isset($pthCols['actual_cash'])) {
            $sets[] = 'actual_cash = ' . $cashExpr('passenger_trip_history');
        }
        if (isset($pthCols['discrepancy_amount'])) {
            $sets[] = 'discrepancy_amount = 0.00';
        }
        if (isset($pthCols['remitted_by'])) {
            $sets[] = "remitted_by = COALESCE(NULLIF(remitted_by, ''), ?)";
            $params[] = $remitBy;
        }
        if (isset($pthCols['remitted_at'])) {
            $sets[] = "remitted_at = COALESCE(remitted_at, CONCAT(trip_date, ' 18:15:00'))";
        }
        $result['passenger_trip_history'] = liveExec(
            $db,
            "UPDATE passenger_trip_history
             SET " . implode(', ', $sets) . "
             WHERE (trip_id LIKE '" . ARCHIVE_TRIP_PREFIX . "%' OR trip_id LIKE 'TRIP-%')
               AND trip_date < ?
               AND (COALESCE(remittance_status, '') <> 'Remitted'
                    OR LOWER(COALESCE(payment_status, '')) <> 'paid')",
            array_merge($params, [$cutoff])
        );
    }

    /* Driver-day logs this tool generated earlier (or overdue rows auto-flagged
       before a run) settle for past operating dates only; today's and future
       rows stay pending. */
    $rlCols = liveColumns($db, 'remittance_logs');
    $rlDateCol = isset($rlCols['business_date']) ? 'business_date' : (isset($rlCols['created_at']) ? 'created_at' : null);
    if ($rlDateCol !== null
        && isset($rlCols['status'], $rlCols['submitted_by'], $rlCols['expected_cash'], $rlCols['actual_cash'])) {
        $setSql = "SET status = 'Completed', actual_cash = expected_cash, discrepancy_amount = COALESCE(discrepancy_amount, 0)";
        $params = [];
        if (isset($rlCols['confirmed_by'])) {
            $setSql .= ', confirmed_by = COALESCE(confirmed_by, ?)';
            $params[] = $remitBy;
        }
        $params[] = $cutoff;
        $result['remittance_logs'] = liveExec(
            $db,
            "UPDATE remittance_logs
             $setSql
             WHERE DATE(`$rlDateCol`) < ?
               AND (submitted_by = 'Data Simulator' OR LOWER(COALESCE(status, '')) = 'overdue')
               AND LOWER(COALESCE(status, '')) <> 'completed'",
            $params
        );
    }

    return $result;
}

/* Ensure the discrepancy workflow tables exist. Must run OUTSIDE the
   generation transaction: CREATE/ALTER statements implicitly commit in MySQL,
   which would end the transaction early and make the final commit fail. */
function ensureDiscrepancySchema(PDO $db): void
{
    try {
        $modelPath = __DIR__ . '/finance/models/DiscrepancyCaseModel.php';
        if (is_file($modelPath)) {
            require_once $modelPath;
        }
        if (class_exists('Finance\\Models\\DiscrepancyCaseModel')) {
            $model = new \Finance\Models\DiscrepancyCaseModel($db);
            $model->initTables();
        }
    } catch (Throwable $e) {
        // Best effort only; the generator degrades gracefully without it.
    }
}

/* Aggregate every previously generated demo driver-day so missing remittance
   logs can be backfilled from the existing ride records (days produced before
   remittance logging existed, or outside the current run's period). */
function collectPreviousDriverDays(PDO $db, array $marker): array
{
    $condition = demoAccountCondition($marker, 'p');
    $ptCols = liveColumns($db, 'passenger_trips');
    if ($condition === null
        || !isset($ptCols['driver_id'], $ptCols['boarding_time'], $ptCols['fare'], $ptCols['payment_method'], $ptCols['passenger_id'])) {
        return [];
    }
    $busExpr = isset($ptCols['bus_no'])
        ? 'MAX(pt.bus_no)'
        : (isset($ptCols['bus_id']) ? 'MAX(pt.bus_id)' : "''");
    $out = [];
    foreach (liveRows(
        $db,
        "SELECT pt.driver_id AS did, DATE(pt.boarding_time) AS d,
                COUNT(*) AS pax,
                COALESCE(SUM(pt.fare), 0) AS collection,
                COALESCE(SUM(CASE
                    WHEN LOWER(COALESCE(pt.payment_method, '')) = 'cash' THEN pt.fare
                    WHEN LOWER(COALESCE(pt.payment_method, '')) = 'hybrid' THEN ROUND(pt.fare * 0.6, 2)
                    ELSE 0 END), 0) AS expected,
                $busExpr AS bus
         FROM passenger_trips pt
         JOIN passengers p ON p.id = pt.passenger_id
         WHERE $condition AND pt.driver_id IS NOT NULL AND pt.driver_id <> ''
         GROUP BY pt.driver_id, DATE(pt.boarding_time)"
    ) as $row) {
        $day = (string)($row['d'] ?? '');
        $did = trim((string)($row['did'] ?? ''));
        if ($day === '' || $did === '') {
            continue;
        }
        $out[$day][$did] = [
            'collection' => round((float)($row['collection'] ?? 0), 2),
            'expected' => round((float)($row['expected'] ?? 0), 2),
            'passengers' => (int)($row['pax'] ?? 0),
            'bus' => (string)($row['bus'] ?? ''),
        ];
    }
    return $out;
}

/* =========================================================
   REALISTIC ACTIVITY AUDIT FOR GENERATED HISTORY
   Individual business actions (queue publication, departures, arrivals, fare
   payments, remittance submissions and verification, discrepancies, manifest
   imports) are logged instead of one bulk "generated N records" message.
   Every event references a record the run actually inserted, uses a real user
   whose role matches the action when the deployment has one (falling back to
   the system actor convention otherwise), and carries the generator's user
   agent plus an explicit demo flag so simulated activity is never mistaken
   for genuine operations. */
function historyAuditActor(array $ctx, string $capability, string $key): array
{
    $pool = $ctx['auditActors'][$capability] ?? [];
    if ($pool) {
        return $pool[(int)floor(stableUnit('audit-actor-' . $capability . '-' . $key) * count($pool)) % count($pool)];
    }
    return ['id' => null, 'username' => 'Data Simulator', 'role' => 'System'];
}

/* Running count of activity events written in this process, so each run can
   report exactly how many audit rows it created (or failed to create). */
function historyAuditCount(?int $add = null): int
{
    static $count = 0;
    if ($add !== null) {
        $count += $add;
    }
    return $count;
}

function historyAuditEvent(
    PDO $db,
    array $ctx,
    string $module,
    string $action,
    string $description,
    ?string $recordId,
    array $newValues,
    string $at,
    string $capability,
    string $key,
    ?array $actorOverride = null
): int {
    /* One event per record per process: the two remittance passes (current
       period and history backfill) can both touch the same driver-day. */
    static $emitted = [];
    $eventKey = $module . '|' . $action . '|' . (string)$recordId;
    if (isset($emitted[$eventKey])) {
        return 0;
    }
    $emitted[$eventKey] = true;

    $actor = $actorOverride ?? historyAuditActor($ctx, $capability, $key);
    $newValues['demo_generated'] = true;
    $newValues['source'] = 'trip-history-generator';
    $id = liveInsert($db, 'audit_trail', [
        'user_id' => is_numeric($actor['id'] ?? null) ? (int)$actor['id'] : null,
        'username' => (string)($actor['username'] ?? 'Data Simulator'),
        'user_role' => (string)($actor['role'] ?? 'System'),
        'module' => $module,
        'action' => $action,
        'record_id' => $recordId,
        'description' => $description,
        'previous_values' => null,
        'new_values' => json_encode($newValues, JSON_UNESCAPED_UNICODE),
        'ip_address' => null,
        'user_agent' => LIVE_SIM_UA,
        'status' => 'Success',
        'is_read' => 0,
        'created_at' => $at,
    ]);
    if ($id <= 0) {
        /* Diagnostics: liveInsert swallows insert failures on non-required
           writes, which would otherwise hide activity events silently. */
        error_log("trip-history-generator: audit event insert failed ($module/$action record=" . (string)$recordId . ').');
        return 0;
    }
    historyAuditCount(1);
    return $id;
}

/* Discrepancy workflow events for one generated case: the shortage is
   recorded when the case opens, and resolution is logged when it closed. */
function historyAuditDiscrepancy(
    PDO $db,
    array $ctx,
    string $day,
    string $did,
    int $logId,
    float $discrepancy,
    int $caseId,
    string $openedAt,
    bool $resolved,
    ?string $resolvedAt
): void {
    if ($openedAt === '') {
        $openedAt = $day . ' 18:00:00';
    }
    $ref = $caseId > 0 ? (string)$caseId : (string)$logId;
    historyAuditEvent(
        $db,
        $ctx,
        'Finance',
        'Discrepancy Case Opened',
        'Cash shortage of PHP ' . number_format($discrepancy, 2) . " recorded on remittance #$logId for driver $did ($day).",
        $ref,
        [
            'case_id' => $caseId,
            'remittance_log_id' => $logId,
            'driver_id' => $did,
            'business_date' => $day,
            'discrepancy_amount' => $discrepancy,
        ],
        $openedAt,
        'finance_officer',
        'disc-open-' . $day . '-' . $did
    );
    if ($resolved && $resolvedAt !== null && $resolvedAt !== '') {
        historyAuditEvent(
            $db,
            $ctx,
            'Finance',
            'Discrepancy Case Resolved',
            'Discrepancy case #' . ($caseId > 0 ? $caseId : $logId) . ' resolved: PHP ' . number_format($discrepancy, 2) . " recovered for driver $did ($day).",
            $ref,
            [
                'case_id' => $caseId,
                'driver_id' => $did,
                'business_date' => $day,
                'discrepancy_amount' => $discrepancy,
            ],
            $resolvedAt,
            'finance_manager',
            'disc-res-' . $day . '-' . $did
        );
    }
}

/* Create one remittance_logs row per generated driver-day plus discrepancy
   cases for a small deterministic share (some resolved, some open), so the
   finance remittance and discrepancy pages show real, referenceable records.
   Rows are tagged submitted_by = 'Data Simulator' so re-runs update instead of
   duplicating. */
function generateDriverRemittances(PDO $db, array $remittanceDay, string $remitBy, array &$stats, array $ctx = [], string $periodStart = '', string $periodEnd = '', string $cutoff = ''): void
{
    if (!$remittanceDay) {
        return;
    }
    $cutoff = trim($cutoff) !== '' ? trim($cutoff) : date('Y-m-d');

    /* Same-day lookup for the reuse rule below: deployments with the
       business_date column filter on it, earlier schemas on the row's own
       created_at (the generator writes the business day there). */
    $remitCols = liveColumns($db, 'remittance_logs');
    $dayFilter = isset($remitCols['business_date'])
        ? 'business_date = ?'
        : (isset($remitCols['created_at']) ? 'DATE(created_at) = ?' : null);

    foreach ($remittanceDay as $day => $drivers) {
        $day = (string)$day;
        $pendingDay = remittanceDayState($day, $cutoff) === 'pending';
        $settled = !$pendingDay;
        foreach ($drivers as $did => $agg) {
            $did = (string)$did;
            $expected = round((float)($agg['expected'] ?? 0.0), 2);
            $collection = round((float)($agg['collection'] ?? 0.0), 2);
            $discrepancy = 0.0;
            $overage = 0.0;
            $isShortfall = false;
            $status = 'Completed';

            /* Shortages and overages are only detected once an operating date
               has passed and its remittance was actually processed; today and
               future dates stay pending with no discrepancy workflow. */
            if ($settled && $expected > 30 && stableUnit('remit-disc-' . $day . '-' . $did) < 0.045) {
                $short = round(stableRange('remit-short-' . $day . '-' . $did, 25, 350), 2);
                $discrepancy = max(5.0, min($short, $expected - 5.0));
                $isShortfall = true;
            } elseif ($settled && $expected > 30 && stableUnit('remit-over-' . $day . '-' . $did) < 0.02) {
                $overage = round(stableRange('remit-over-amt-' . $day . '-' . $did, 5, 60), 2);
                $stats['remittance_overages'] = (int)($stats['remittance_overages'] ?? 0) + 1;
            }

            /* No remittance record is created for today or a future operating
               date: the record appears only when finance actually submits it.
               The remittance page treats every non-'overdue' log as already
               submitted (RemittanceModel::alreadySubmitted), so a stale demo
               row from an earlier build must not survive: it is deleted when
               nothing references it, or downgraded to the app's own
               'overdue' (= not yet submitted) state when a discrepancy case
               still links to it. Rows processed through the real workflow
               (authorization code recorded) are never touched. */
            if ($pendingDay) {
                $lookupCols = ['id', 'submitted_by', 'status'];
                if (isset($remitCols['finance_code'])) {
                    $lookupCols[] = 'finance_code';
                }
                $existing = $dayFilter !== null
                    ? liveRows(
                        $db,
                        'SELECT ' . implode(', ', $lookupCols) . " FROM remittance_logs WHERE driver_id = ? AND $dayFilter ORDER BY id DESC LIMIT 1",
                        [$did, $day]
                    )
                    : [];
                if (!empty($existing)) {
                    $staleTag = strtolower(trim((string)($existing[0]['submitted_by'] ?? '')));
                    $staleCode = trim((string)($existing[0]['finance_code'] ?? ''));
                    $staleStatus = strtolower(trim((string)($existing[0]['status'] ?? '')));
                    if ($staleTag === 'data simulator' && $staleCode === '' && $staleStatus !== 'overdue') {
                        $staleId = (int)($existing[0]['id'] ?? 0);
                        $hasCase = (int)(liveValue(
                            $db,
                            'SELECT id FROM discrepancy_cases WHERE remittance_log_id = ? LIMIT 1',
                            [$staleId]
                        ) ?? 0) > 0;
                        if (!$hasCase) {
                            liveExec($db, 'DELETE FROM remittance_logs WHERE id = ?', [$staleId]);
                        } else {
                            $reset = [
                                'status' => 'overdue',
                                'actual_cash' => null,
                                'discrepancy_amount' => 0.0,
                            ];
                            foreach (['confirmed_by', 'verified_at', 'remitted_by', 'remitted_at'] as $nullCol) {
                                if (isset($remitCols[$nullCol])) {
                                    $reset[$nullCol] = null;
                                }
                            }
                            liveUpdate($db, 'remittance_logs', $reset, 'id', $staleId);
                        }
                    }
                }
                continue;
            }

            $createdAt = $day . ' ' . fmtTime(mt_rand(17 * 3600, 19 * 3600));
            $fields = [
                'driver_id' => $did,
                'bus_no' => (string)($agg['bus'] ?? ''),
                'business_date' => $day,
                'total_collection' => $collection,
                'total_passengers' => (int)($agg['passengers'] ?? 0),
                'expected_cash' => $expected,
                'actual_cash' => round($expected + $overage, 2),
                'discrepancy_amount' => $discrepancy,
                'status' => $status,
                'submitted_by' => 'Data Simulator',
                'confirmed_by' => $remitBy,
                'verified_at' => $createdAt,
                'created_at' => $createdAt,
            ];
            /* Reuse any same-day log - including an auto-flagged 'overdue' row
               created before the generator ran - and settle it instead of
               stacking a second log for the same driver-day. Logs already
               processed through the real finance workflow (authorization code
               recorded) or updated by hand are never overwritten. */
            $lookupCols = ['id', 'submitted_by', 'status'];
            if (isset($remitCols['finance_code'])) {
                $lookupCols[] = 'finance_code';
            }
            $existing = $dayFilter !== null
                ? liveRows(
                    $db,
                    'SELECT ' . implode(', ', $lookupCols) . " FROM remittance_logs WHERE driver_id = ? AND $dayFilter "
                    . "ORDER BY (LOWER(COALESCE(status, '')) = 'overdue') DESC, id DESC LIMIT 1",
                    [$did, $day]
                )
                : [];
            $logId = 0;
            if (!empty($existing)) {
                $existingId = (int)($existing[0]['id'] ?? 0);
                $existingTag = strtolower(trim((string)($existing[0]['submitted_by'] ?? '')));
                $existingStatus = strtolower(trim((string)($existing[0]['status'] ?? '')));
                $financeCode = trim((string)($existing[0]['finance_code'] ?? ''));
                if (($existingTag === 'data simulator' || $existingStatus === 'overdue') && $financeCode === '') {
                    liveUpdate($db, 'remittance_logs', $fields, 'id', $existingId);
                    $logId = $existingId;
                } else {
                    continue;   // genuine or manually processed log: leave it alone
                }
            } else {
                $logId = liveInsert($db, 'remittance_logs', $fields, false);
                if ($logId > 0) {
                    $stats['remittance_logs']++;
                }
            }

            /* Activity audit only for actions that actually occurred: today
               and future remittances stay pending, so no submission or
               verification event is claimed for them. Only business days
               inside the generated period are logged; the history backfill
               pass covers older driver-days outside this period. */
            $inPeriod = $periodStart !== '' && $periodEnd !== '' && $day >= $periodStart && $day <= $periodEnd;
            if ($logId > 0 && $inPeriod && $ctx && $settled) {
                $driverActor = ['id' => null, 'username' => $did, 'role' => 'Driver'];
                historyAuditEvent(
                    $db,
                    $ctx,
                    'Finance',
                    'Remittance Submitted',
                    "Driver $did submitted remittance #$logId for $day with a collection of PHP " . number_format($collection, 2) . '.',
                    (string)$logId,
                    [
                        'remittance_log_id' => $logId,
                        'driver_id' => $did,
                        'business_date' => $day,
                        'total_collection' => $collection,
                        'expected_cash' => $expected,
                        'status' => $status,
                    ],
                    $createdAt,
                    'finance_officer',
                    'remit-sub-' . $day . '-' . $did,
                    $driverActor
                );
                if ($settled) {
                    $verifiedAt = date(
                        'Y-m-d H:i:s',
                        strtotime($createdAt) + (int)round(stableRange('audit-verify-' . $day . '-' . $did, 15, 150)) * 60
                    );
                    historyAuditEvent(
                        $db,
                        $ctx,
                        'Finance',
                        'Remittance Verified',
                        "Remittance #$logId for driver $did ($day) verified with PHP " . number_format($expected, 2) . ' expected cash.'
                            . ($overage > 0 ? '; overage PHP ' . number_format($overage, 2) . ' remitted' : ''),
                        (string)$logId,
                        [
                            'remittance_log_id' => $logId,
                            'driver_id' => $did,
                            'business_date' => $day,
                            'expected_cash' => $expected,
                            'discrepancy_amount' => $discrepancy,
                            'overage_amount' => $overage,
                        ],
                        $verifiedAt,
                        'finance_officer',
                        'remit-ver-' . $day . '-' . $did
                    );
                }
            }

            if ($logId <= 0 || !$isShortfall) {
                continue;
            }
            $caseExists = (int)(liveValue(
                $db,
                'SELECT id FROM discrepancy_cases WHERE remittance_log_id = ? LIMIT 1',
                [$logId]
            ) ?? 0);
            if ($caseExists > 0) {
                /* Reclaimed case from an earlier run: re-emit its workflow
                   events so a replaced period keeps its audit story. */
                if ($inPeriod && $ctx) {
                    $caseRow = liveRows(
                        $db,
                        'SELECT id, status, opened_at, resolved_at FROM discrepancy_cases WHERE id = ? LIMIT 1',
                        [$caseExists]
                    );
                    if (!empty($caseRow)) {
                        historyAuditDiscrepancy(
                            $db,
                            $ctx,
                            $day,
                            $did,
                            $logId,
                            $discrepancy,
                            (int)($caseRow[0]['id'] ?? 0),
                            (string)($caseRow[0]['opened_at'] ?? ''),
                            strtolower((string)($caseRow[0]['status'] ?? '')) === 'resolved',
                            isset($caseRow[0]['resolved_at']) ? (string)$caseRow[0]['resolved_at'] : null
                        );
                    }
                }
                continue;
            }

            /* Past cases follow valid workflow states: some were resolved
               (recovery posted), some remain under investigation, and the
               rest stay open awaiting the driver's explanation. Today and
               future remittances never reach this branch because pending
               remittances are not investigated yet. */
            $caseRoll = stableUnit('disc-resolved-' . $day . '-' . $did);
            $caseStatus = $caseRoll < 0.45 ? 'Resolved' : ($caseRoll < 0.70 ? 'Under Investigation' : 'Open');
            $resolved = $caseStatus === 'Resolved';
            $investigating = $caseStatus === 'Under Investigation';
            $openedAt = $day . ' ' . fmtTime(mt_rand(18 * 3600, 20 * 3600));
            $caseData = [
                'remittance_log_id' => $logId,
                'driver_id' => $did,
                'business_date' => $day,
                'case_type' => 'Cash Shortage',
                'status' => $caseStatus,
                'expected_amount' => $expected,
                'actual_amount' => round($expected - $discrepancy, 2),
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => 0.0,
                'assigned_reviewer' => 'Finance Officer',
                'assigned_at' => $openedAt,
                'due_date' => date('Y-m-d', strtotime($day . ' +3 days')),
                'findings' => $resolved
                    ? 'Verified short remittance; the missing amount was recovered.'
                    : ($investigating
                        ? 'Review of the short remittance is ongoing.'
                        : 'Short remittance logged; awaiting the driver explanation.'),
                'opened_by' => 'Finance Officer',
                'opened_at' => $openedAt,
            ];
            if ($resolved) {
                $resolvedAt = date('Y-m-d H:i:s', strtotime($openedAt . ' +' . mt_rand(1, 3) . ' days'));
                $caseData['resolution_reason'] = 'Recovered through payroll deduction.';
                $caseData['resolved_at'] = $resolvedAt;
                $caseData['closed_at'] = $resolvedAt;
                $caseData['manager_decision'] = 'approved';
                $caseData['manager_decision_by'] = 'Finance Manager';
                $caseData['manager_decision_at'] = $resolvedAt;
                $caseData['manager_decision_reason'] = 'Recovery posted.';
            }
            $caseId = liveInsert($db, 'discrepancy_cases', $caseData, false);
            if ($caseId <= 0) {
                continue;
            }
            $stats['discrepancy_cases']++;

            /* Workflow audit for the case the run just opened (and resolved,
               when the deterministic roll closed it). */
            if ($inPeriod && $ctx) {
                historyAuditDiscrepancy(
                    $db,
                    $ctx,
                    $day,
                    $did,
                    $logId,
                    $discrepancy,
                    $caseId,
                    $openedAt,
                    $resolved,
                    $resolved ? (string)($caseData['resolved_at'] ?? '') : null
                );
            }

            liveInsert($db, 'discrepancy_status_history', [
                'case_id' => $caseId,
                'from_status' => null,
                'to_status' => 'Open',
                'event_type' => 'case_opened',
                'description' => 'Case opened from the short remittance of ' . number_format($discrepancy, 2) . '.',
                'actor_username' => 'Data Simulator',
                'actor_role' => 'System',
                'created_at' => $openedAt,
            ], false);

            if ($investigating) {
                /* Valid transition: the review started after the case opened. */
                liveInsert($db, 'discrepancy_status_history', [
                    'case_id' => $caseId,
                    'from_status' => 'Open',
                    'to_status' => 'Under Investigation',
                    'event_type' => 'status_change',
                    'description' => 'Investigation started; awaiting the driver response.',
                    'reason' => 'Under investigation',
                    'actor_username' => 'Finance Officer',
                    'actor_role' => 'Finance',
                    'created_at' => date('Y-m-d H:i:s', strtotime($openedAt . ' +' . mt_rand(4, 48) . ' hours')),
                ], false);
                continue;
            }
            if (!$resolved) {
                continue;
            }
            $stats['discrepancy_resolved']++;
            liveInsert($db, 'discrepancy_status_history', [
                'case_id' => $caseId,
                'from_status' => 'Open',
                'to_status' => 'Resolved',
                'event_type' => 'status_change',
                'description' => 'Payroll recovery posted; case resolved.',
                'reason' => 'Payroll deduction',
                'actor_username' => 'Finance Manager',
                'actor_role' => 'Finance',
                'created_at' => $caseData['resolved_at'],
            ], false);
            liveInsert($db, 'discrepancy_ledger_entries', [
                'case_id' => $caseId,
                'entry_type' => 'payroll_recovery',
                'recovery_class' => 'payroll',
                'amount' => $discrepancy,
                'direction' => 'reduce',
                'state' => 'posted',
                'reference' => 'PAY-' . date('Ym', strtotime($day)) . '-' . str_pad((string)$caseId, 4, '0', STR_PAD_LEFT),
                'notes' => 'Payroll deduction recovered the short remittance.',
                'proposed_by' => 'Finance Officer',
                'proposed_at' => $caseData['resolved_at'],
                'decided_by' => 'Finance Manager',
                'decided_at' => $caseData['resolved_at'],
                'posted_by' => 'Finance Manager',
                'posted_at' => $caseData['resolved_at'],
                'created_at' => $caseData['resolved_at'],
                'updated_at' => $caseData['resolved_at'],
            ], false);
        }
    }
}

/* Next code under the app's prefix scheme, continuing after every code the
   database already uses for that prefix (live + retired history). */
function demoNextCode(PDO $db, string $prefix, array &$acct): string
{
    if (!isset($acct['seq'][$prefix])) {
        $max = (int)liveValue(
            $db,
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(passenger_code,'-',-1) AS UNSIGNED)),0) FROM passengers WHERE passenger_code LIKE ?",
            [$prefix . '-%']
        );
        $historyMax = (int)liveValue(
            $db,
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(passenger_code,'-',-1) AS UNSIGNED)),0) FROM passenger_code_history WHERE passenger_code LIKE ?",
            [$prefix . '-%']
        );
        $acct['seq'][$prefix] = max($max, $historyMax);
    }
    $acct['seq'][$prefix]++;
    return $prefix . '-' . str_pad((string)$acct['seq'][$prefix], 3, '0', STR_PAD_LEFT);
}

/* Best-effort continuation record for real registrations that allocate later. */
function flushDemoSequences(PDO $db, array $acct): void
{
    foreach (($acct['seq'] ?? []) as $prefix => $last) {
        liveExec(
            $db,
            'INSERT INTO passenger_id_sequences (prefix, last_number) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))',
            [$prefix, (int)$last]
        );
    }
}

function demoAddressFor(string $name, string $stop): string
{
    static $streets = [
        'Maharlika Highway', 'Burgos Avenue', 'Mabini Extension', 'Del Pilar Street', 'Rizal Street',
        'Bonifacio Street', 'Paco Roman Street', 'Melencio Street', 'Sumacab Road', 'Vallejo Street',
        'Gen. Tinio Street', 'Capt. Pepe Street',
    ];
    static $barangays = [
        'Poblacion', 'San Juan Poblacion', 'Zulueta', 'Barrera', 'Bantug Norte',
        'Bantug Sur', 'Sumacab Este', 'Sumacab Norte', 'Sumacab Sur', 'Valle Cruz',
        'Kapitan Pepe', 'San Roque', 'Sta. Arcadia', 'DC Garcia', 'Mabini Homesite',
        'Camp Tinio', 'Bakod Bayan', 'Mayapyap Norte', 'Mayapyap Sur', 'Sangitan East',
        'Sangitan West', 'Talipapa', 'Imelda', 'Malapit',
    ];
    /* The cooperative is based in Cabanatuan City and also serves the nearby
       Nueva Ecija towns its routes pass through. */
    static $towns = [
        'Cabanatuan City', 'Cabanatuan City', 'Cabanatuan City', 'Cabanatuan City',
        'Gapan City', 'San Jose City', 'Palayan City', 'Munoz', 'Talavera', 'Guimba',
        'Sta. Rosa', 'Aliaga', 'Zaragoza', 'Cabiao', 'Jaen', 'Rizal', 'Bongabon',
    ];
    $no = (int)round(stableRange('addr-no-' . $name, 1, 420));
    $street = $streets[(int)floor(stableUnit('addr-street-' . $name) * count($streets)) % count($streets)];
    $brgy = $barangays[(int)floor(stableUnit('addr-brgy-' . $name) * count($barangays)) % count($barangays)];
    $town = $towns[(int)floor(stableUnit('addr-town-' . $name) * count($towns)) % count($towns)];
    return substr('#' . $no . ' ' . $street . ', Brgy. ' . $brgy . ', ' . $town . ', Nueva Ecija', 0, 150);
}

function cargoCategoryFor(string $key): string
{
    static $cats = ['Box', 'Carton', 'Luggage', 'Sack', 'Bundle', 'Plastic', 'Other'];
    return $cats[(int)floor(stableUnit('cargo-cat-' . $key) * count($cats)) % count($cats)];
}

/* Deterministic Tagalog baggage description (same vocabulary as the random
   version) so re-runs describe the same cargo record identically. */
function stableBaggageDescription(int $space, string $recipient, string $key): string
{
    $countWord = match ($space) {
        1 => 'Isang',
        2 => 'Dalawang',
        default => 'Tatlong',
    };
    $units = ['kahon', 'sako', 'bag', 'box', 'kaban', 'balikbayan', 'supot', 'bundle', 'plastik'];
    $things = [
        'ng bigas', 'ng gulay', 'ng gatas', 'ng harina', 'ng labada',
        'ng tubig', 'ng dokumento', 'ng tinapay', 'ng isda', 'ng sibuyas', 'ng mangga',
        'ng prutas', 'ng pananim', 'ng gamit', 'ng produkto', 'ng paninda',
    ];
    $unit = $units[(int)floor(stableUnit($key . '-unit') * count($units)) % count($units)];
    $thing = $things[(int)floor(stableUnit($key . '-thing') * count($things)) % count($things)];
    $base = $countWord . ' ' . $unit . ' ' . $thing;
    if (stableUnit($key . '-para') < 0.5) {
        $base .= ' para kay ' . $recipient;
    }
    return $base;
}

function demoProfileAccountData(array $profile, array $ctx, string $periodStart): array
{
    $name = (string)$profile['name'];
    $type = (string)$profile['ptype'];
    $occupation = (string)($profile['occupation'] ?? '');
    $ageRange = match ($occupation) {
        'student' => [15, 24],
        'senior' => [60, 80],
        'vendor' => [25, 65],
        'worker' => [20, 58],
        default => match ($type) {
            'Student' => [15, 25],
            'Senior' => [60, 80],
            'PWD' => [22, 70],
            'Beneficiary' => [25, 70],
            'Cooperative' => [30, 70],
            default => [18, 65],
        },
    };
    $age = (int)round(stableRange('acct-age-' . $name, (float)$ageRange[0], (float)$ageRange[1]));
    $dob = date('Y-m-d', strtotime('-' . $age . ' years -' . (int)round(stableRange('acct-dob-' . $name, 0, 300)) . ' days'));
    $created = date(
        'Y-m-d H:i:s',
        strtotime($periodStart . ' -' . (int)round(stableRange('acct-created-' . $name, 2, 90)) . ' days')
            + (int)round(stableRange('acct-created-time-' . $name, 7, 19)) * 3600
    );
    $phone = HIST_CONTACT_PREFIX . substr(demoIdentityToken($name), 1);

    $discountStatus = $profile['discountStatus'] ?? null;
    $data = [
        'full_name' => $name,
        'name' => $name,
        'type' => $type,
        'discount_type' => $type,
        'space_occupied' => 1,
        'registration_type' => 'regular',
        'status' => 'Active',
        'account_status' => 'ACTIVE',
        'date_of_birth' => $dob,
        'phone' => $phone,
        'contact_number' => $phone,
        'created_at' => $created,
        'privacy_accepted_at' => $created,
    ];
    /* Discount verification follows the profile's status; only verified
       profiles are approved and carry verified_at. Regular commuters keep the
       column empty because they have no discount to verify. */
    if (is_string($discountStatus) && $discountStatus !== '') {
        $data['discount_status'] = $discountStatus;
    }
    if ($discountStatus === 'verified') {
        $data['verified_at'] = $created;
    }
    $idField = (string)($profile['idField'] ?? '');
    $idValue = $profile['idValue'] ?? null;
    if ($idField !== '' && $idValue !== null) {
        $data[$idField] = (string)$idValue;
        /* Deployments without the dedicated discount-ID column still carry
           the person's student/senior/PWD number in the declared-ID fields. */
        if (!isset($ctx['passengerCols'][strtolower($idField)])) {
            if (isset($ctx['passengerCols']['declared_id_number']) && !isset($data['declared_id_number'])) {
                $data['declared_id_number'] = (string)$idValue;
            }
            if (isset($ctx['passengerCols']['declared_id_status']) && !isset($data['declared_id_status'])) {
                $data['declared_id_status'] = is_string($discountStatus) && $discountStatus !== ''
                    ? $discountStatus
                    : 'pending';
            }
        }
    }

    /* Student school / institution: written only into a school-like column
       the deployment's schema actually exposes (no column is invented).
       Araullo University in Cabanatuan City is the modelled school for most
       student profiles, with other local schools mixed in. */
    if ($occupation === 'student' || $type === 'Student') {
        $otherSchools = [
            'Cabanatuan City Colleges',
            'Nueva Ecija University of Science and Technology',
            'Wesleyan University-Philippines',
        ];
        $school = stableUnit('school-araullo-' . $name) < 0.6
            ? 'Araullo University'
            : $otherSchools[(int)floor(stableUnit('school-pick-' . $name) * count($otherSchools)) % count($otherSchools)];
        foreach (($ctx['passengerCols'] ?? []) as $lower => $actual) {
            if ((str_contains($lower, 'school') || str_contains($lower, 'institution') || str_contains($lower, 'university'))
                && !isset($data[$lower])) {
                $data[$lower] = $school;
            }
        }
    }

    $addressStop = (string)($profile['boardingStop'] ?? '');
    foreach (($ctx['passengerCols'] ?? []) as $lower => $actual) {
        if (str_contains($lower, 'address') && !isset($data[$lower])) {
            $data[$lower] = demoAddressFor($name, $addressStop);
        }
    }
    return $data;
}

function demoCargoAccountData(string $identity, string $description, int $space, array $ctx, string $periodStart): array
{
    $created = date(
        'Y-m-d H:i:s',
        strtotime($periodStart . ' -' . (int)round(stableRange('cargo-created-' . $identity, 0, 5)) . ' days')
            + (int)round(stableRange('cargo-created-time-' . $identity, 6, 18)) * 3600
    );
    return [
        'full_name' => $description,
        'name' => $description,
        'type' => 'other',
        'space_occupied' => $space,
        'baggage_category' => cargoCategoryFor($identity),
        'status' => 'Active',
        'account_status' => 'ACTIVE',
        'created_at' => $created,
    ];
}

/* Resolve (or create) the demo account for one identity. Reuses the preloaded
   account when the same deterministic identity already exists. */
function ensureDemoAccount(PDO $db, array $ctx, string $cacheKey, ?string $lookup, array $data, array &$acct): array
{
    if (isset($acct['cache'][$cacheKey])) {
        return $acct['cache'][$cacheKey];
    }
    $mode = $ctx['demoMarker']['mode'] ?? 'none';
    $lower = $lookup !== null ? strtolower($lookup) : '';
    $existing = $lower !== '' ? ($acct['preload'][$lower] ?? null) : null;

    /* Natural addresses carry no visible marker, so a re-run must recognize
       accounts this tool already created even when the address cannot be
       recomputed from the current pool (cargo accounts key off per-trip
       identities). Ask the database directly, but adopt the row only when the
       tool's own audit registry claims it; a genuine passenger who happens to
       share a derived address must never be charged with demo rides. */
    if ($existing === null && $lower !== '' && $mode === 'email') {
        $column = (string)($ctx['demoMarker']['column'] ?? '');
        if ($column !== '') {
            $found = liveRows(
                $db,
                "SELECT id, passenger_code, type, discount_status FROM passengers WHERE `$column` = ? LIMIT 1",
                [$lookup]
            );
            if (!empty($found)) {
                if (demoAccountRegistered($db, (string)($found[0]['id'] ?? ''))) {
                    $existing = $found[0];
                    $acct['preload'][$lower] = $existing;
                } else {
                    demoAuditSkippedAccount($db, (string)$lookup, $data, $acct);
                    return $acct['cache'][$cacheKey] = ['id' => '', 'code' => '', 'created' => false, 'skipped' => true];
                }
            }
        }
    }

    if ($existing !== null) {
        $row = $existing;
        $acct['reused']++;
        $id = (string)$row['id'];
        $code = trim((string)($row['passenger_code'] ?? ''));

        /* Keep the stored account's type consistent with the deterministic
           profile when the pool changed between builds (for example a
           featured student). Only demo accounts this tool owns reach here, so
           genuine passengers are never retyped. */
        $intendedType = (string)($data['type'] ?? '');
        $storedType = (string)($row['type'] ?? '');
        if ($intendedType !== '' && $storedType !== '' && strcasecmp($intendedType, $storedType) !== 0) {
            liveUpdate($db, 'passengers', ['type' => $intendedType, 'discount_type' => $intendedType], 'id', $id);
            $row['type'] = $intendedType;
            $acct['preload'][$lower] = $row;
        }

        /* Earlier builds stamped every demo account as discount-verified;
           align reused demo accounts with the deterministic verification mix
           so the passenger directory shows pending and rejected profiles too.
           Only this tool's own accounts reach this path. */
        $intendedDiscount = $data['discount_status'] ?? null;
        $intendedLower = $intendedDiscount !== null ? strtolower((string)$intendedDiscount) : '';
        $storedDiscount = strtolower(trim((string)($row['discount_status'] ?? '')));
        if ($intendedLower !== $storedDiscount) {
            $align = ['discount_status' => $intendedDiscount];
            if ($intendedLower !== 'verified') {
                /* Pending/rejected/expired profiles must not carry an
                   approval timestamp. */
                $align['verified_at'] = null;
            }
            if (isset($ctx['passengerCols']['declared_id_status'])) {
                $align['declared_id_status'] = $intendedLower !== '' ? $intendedLower : 'pending';
            }
            liveUpdate($db, 'passengers', $align, 'id', $id);
            $row['discount_status'] = $intendedDiscount;
            $acct['preload'][$lower] = $row;
        }
        $expectedPrefix = demoPassengerPrefix((string)($data['type'] ?? 'Regular'));
        $currentPrefix = strtoupper((string)strstr($code . '-', '-', true));
        if ($code === '' || $currentPrefix !== $expectedPrefix) {
            /* Reissue codes whose prefix no longer matches the passenger type
               (for example an old STU- student under the STD- scheme). */
            $oldCode = $code;
            $code = demoNextCode($db, $expectedPrefix, $acct);
            liveUpdate($db, 'passengers', ['passenger_code' => $code], 'id', $id);
            if ($oldCode !== '') {
                liveExec(
                    $db,
                    'INSERT IGNORE INTO passenger_code_history (passenger_code, passenger_id, classification) VALUES (?, ?, ?)',
                    [$oldCode, $id, (string)($data['type'] ?? 'Regular')]
                );
                liveExec(
                    $db,
                    'UPDATE passenger_code_history SET retired_at = COALESCE(retired_at, NOW()) WHERE passenger_code = ? AND passenger_id = ?',
                    [$oldCode, $id]
                );
            }
            liveExec(
                $db,
                'INSERT IGNORE INTO passenger_code_history (passenger_code, passenger_id, classification) VALUES (?, ?, ?)',
                [$code, $id, (string)($data['type'] ?? 'Regular')]
            );
            $acct['preload'][$lower] = ['id' => $id, 'passenger_code' => $code];
            $acct['codes_reissued'] = ($acct['codes_reissued'] ?? 0) + 1;
        }
        return $acct['cache'][$cacheKey] = [
            'id' => $id,
            'code' => $code,
            'created' => false,
        ];
    }
    if ($mode !== 'code') {
        $data['passenger_code'] = demoNextCode($db, demoPassengerPrefix((string)($data['type'] ?? 'Regular')), $acct);
    }
    if ($lookup !== null && $mode !== 'none') {
        demoApplyLookup($data, $ctx, $lookup);
    }
    $anchor = (string)($data['created_at'] ?? date('Y-m-d H:i:s'));
    $data = applyRequiredDefaults($data, $ctx['passengerRequiredDefaults'] ?? [], $anchor);
    $inserted = liveInsert($db, 'passengers', $data, true);
    $id = isset($data['id']) ? (string)$data['id'] : (string)$inserted;
    if ($id === '' || $id === '0') {
        throw new RuntimeException('Validation failed: demo passenger insert returned no identifier.');
    }
    $acct['created']++;
    $acct['ids'][$id] = true;
    if (!empty($data['passenger_code'])) {
        liveExec(
            $db,
            'INSERT IGNORE INTO passenger_code_history (passenger_code, passenger_id, classification) VALUES (?, ?, ?)',
            [(string)$data['passenger_code'], $id, (string)($data['type'] ?? 'Regular')]
        );
    }
    /* Registry that lets period cleanup recognize this tool's own accounts even
       though their natural Gmail addresses carry no visible marker. */
    liveInsert($db, 'audit_trail', [
        'user_id' => null,
        'username' => 'Data Simulator',
        'user_role' => 'System',
        'module' => 'Passenger',
        'action' => 'History Demo Account',
        'record_id' => $id,
        'description' => 'Generated demo passenger account',
        'previous_values' => null,
        'new_values' => json_encode([
            'name' => (string)($data['full_name'] ?? $data['name'] ?? ''),
            'email' => (string)($data['email'] ?? ''),
            'passenger_code' => (string)($data['passenger_code'] ?? ''),
        ], JSON_UNESCAPED_UNICODE),
        'ip_address' => '127.0.0.1',
        'user_agent' => LIVE_SIM_UA,
        'status' => 'Success',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    if ($lower !== '') {
        $acct['preload'][$lower] = ['id' => $id, 'passenger_code' => (string)($data['passenger_code'] ?? '')];
    }
    return $acct['cache'][$cacheKey] = [
        'id' => $id,
        'code' => (string)($data['passenger_code'] ?? ''),
        'created' => true,
    ];
}

/* True when this tool's audit registry records creating the passenger, which
   is how an address that is not part of the current pool is still recognized
   as a demo account on re-runs. */
function demoAccountRegistered(PDO $db, string $passengerId): bool
{
    if ($passengerId === '') {
        return false;
    }
    return (int)(liveValue(
        $db,
        "SELECT COUNT(*) FROM audit_trail WHERE user_agent = ? AND action = 'History Demo Account' AND record_id = ?",
        [LIVE_SIM_UA, $passengerId]
    ) ?? 0) > 0;
}

/* A derived demo address that already belongs to a passenger this tool did
   not create is never adopted: the ride is skipped and the collision is
   recorded, so the run continues instead of aborting on a duplicate key. */
function demoAuditSkippedAccount(PDO $db, string $email, array $data, array &$acct): void
{
    $acct['skipped'] = (int)($acct['skipped'] ?? 0) + 1;
    $acct['skipped_reasons']['derived-address-owned-by-non-demo'] =
        (int)($acct['skipped_reasons']['derived-address-owned-by-non-demo'] ?? 0) + 1;
    liveInsert($db, 'audit_trail', [
        'user_id' => null,
        'username' => 'Data Simulator',
        'user_role' => 'System',
        'module' => 'Passenger',
        'action' => 'History Demo Account Skipped',
        'record_id' => null,
        'description' => 'Derived demo address already belongs to a non-demo passenger; ride skipped',
        'previous_values' => null,
        'new_values' => json_encode([
            'name' => (string)($data['full_name'] ?? $data['name'] ?? ''),
            'email' => $email,
        ], JSON_UNESCAPED_UNICODE),
        'ip_address' => '127.0.0.1',
        'user_agent' => LIVE_SIM_UA,
        'status' => 'Skipped',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/* Remove only this tool's own demo output inside the target period: rides
   linked to demo accounts (both the numeric bus_trips ids and legacy TRIP-####
   references) plus the operational bus_trips rows behind them. Genuine rows
   are never linked to demo accounts, so they can never match. */
function demoCleanupPeriod(PDO $db, string $start, string $end, array $marker): array
{
    $result = ['rides' => 0, 'bus_trips' => 0, 'fuel_logs' => 0, 'fuel_refuels' => 0, 'audit_events' => 0, 'dispatch_rows' => 0];
    $ptCols = liveColumns($db, 'passenger_trips');
    if (!isset($ptCols['boarding_time'], $ptCols['passenger_id'])) {
        return $result;
    }
    $condition = demoAccountCondition($marker, 'p');
    if ($condition === null) {
        return $result;
    }

    /* Collect the operational trip ids behind the demo rides before deleting,
       so the matching bus_trips and fuel rows go away with them. Text
       deployments write 'TRIP-####' references; numeric ones keep the digits. */
    $tripIds = [];
    if (isset($ptCols['trip_no'])) {
        foreach (liveRows(
            $db,
            "SELECT DISTINCT pt.trip_no AS t FROM passenger_trips pt
             JOIN passengers p ON p.id = pt.passenger_id
             WHERE $condition
               AND DATE(pt.boarding_time) >= ? AND DATE(pt.boarding_time) <= ?",
            [$start, $end]
        ) as $row) {
            $trip = trim((string)($row['t'] ?? ''));
            if ($trip === '') {
                continue;
            }
            if (ctype_digit($trip)) {
                $tripIds[$trip] = true;
            } elseif (preg_match('/^TRIP-(\d+)$/', $trip, $m)) {
                $tripIds[$m[1]] = true;
            }
        }
    }

    $result['rides'] = liveExec(
        $db,
        "DELETE pt FROM passenger_trips pt
         JOIN passengers p ON p.id = pt.passenger_id
         WHERE $condition
           AND DATE(pt.boarding_time) >= ? AND DATE(pt.boarding_time) <= ?",
        [$start, $end]
    );

    if ($tripIds) {
        $busCols = liveColumns($db, 'bus_trips');
        if (isset($busCols['id'])) {
            $ids = array_keys($tripIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $result['bus_trips'] = liveExec($db, "DELETE FROM bus_trips WHERE id IN ($placeholders)", $ids);
        }

        /* Fuel and refuel rows reference the generated trips; replacements must
           remove exactly those references so re-runs never stack duplicate fuel
           records. A numeric trip_id column only accepts the numeric form; a
           text column may hold either form. Genuine refuels keep their own trip
           references and are never touched. */
        $refs = [];
        foreach (array_keys($tripIds) as $tid) {
            $refs[(string)$tid] = true;
            $refs['TRIP-' . $tid] = true;
        }
        foreach (['fuel_logs', 'fuel_refuels'] as $fuelTable) {
            $fuelColsLocal = liveColumns($db, $fuelTable);
            if (!isset($fuelColsLocal['trip_id'])) {
                continue;
            }
            $allowed = $refs;
            if (demoFuelTripIdIsNumeric($db, $fuelTable)) {
                $allowed = array_filter($refs, static fn($k): bool => ctype_digit((string)$k));
            }
            if (!$allowed) {
                continue;
            }
            $keys = array_keys($allowed);
            $ph = implode(',', array_fill(0, count($keys), '?'));
            $result[$fuelTable] = liveExec($db, "DELETE FROM `$fuelTable` WHERE trip_id IN ($ph)", $keys);
        }

        /* Trip-bound activity events (departures/arrivals) go away with their
           trips; day, remittance and discrepancy events are removed by the
           period window below. The demo-account registry
           ('History Demo Account') is preserved because account reuse
           depends on it, and genuine audit records are never matched
           (user_agent filter). */
        $ids = array_keys($tripIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $result['audit_events'] = (int)$result['audit_events'] + liveExec(
            $db,
            "DELETE FROM audit_trail WHERE user_agent = ? AND action IN ('Trip Departed','Trip Completed') AND record_id IN ($placeholders)",
            array_merge([LIVE_SIM_UA], $ids)
        );
    }
    /* Orphan fuel rows from earlier full-fleet builds: when a period later
       became window-scoped (or its buses changed), their trips were replaced
       but the old fuel rows survived, making fuel history cover buses that no
       longer ran. Only dangling references are removed for the replaced
       period - rows whose trip reference resolves to no bus_trips row under
       either form (numeric id, or the legacy TRIP-#### pattern whose numeric
       part matches no trip). Live fuel rows always reference an existing
       trip, so genuine records are never touched. */
    foreach (['fuel_logs', 'fuel_refuels'] as $fuelTable) {
        $fuelColsLocal = liveColumns($db, $fuelTable);
        if (!isset($fuelColsLocal['trip_id'], $fuelColsLocal['created_at'])) {
            continue;
        }
        $dangling = demoFuelTripIdIsNumeric($db, $fuelTable)
            ? 'bt.id IS NULL'
            : "( (fl.trip_id REGEXP '^[0-9]+$' AND bt.id IS NULL)
                 OR (fl.trip_id LIKE 'TRIP-%' AND NOT EXISTS (
                        SELECT 1 FROM bus_trips bt2 WHERE bt2.id = CAST(SUBSTRING(fl.trip_id, 6) AS UNSIGNED)
                    )) )";
        $result[$fuelTable] = (int)($result[$fuelTable] ?? 0) + liveExec(
            $db,
            "DELETE fl FROM `$fuelTable` fl
             LEFT JOIN bus_trips bt ON bt.id = fl.trip_id
             WHERE DATE(fl.created_at) >= ? AND DATE(fl.created_at) <= ?
               AND fl.trip_id IS NOT NULL AND fl.trip_id <> ''
               AND ($dangling)",
            [$start, $end]
        );
    }

    /* Dispatch coverage rows this tool wrote for the replaced period: the
       Queue Published payloads list the exact (date, id) pairs, so cleanup
       removes only the generator's own lineup and terminal-assignment rows.
       Legacy events carry no lists and nothing is guessed from them. */
    $writtenBuses = [];
    $writtenDrivers = [];
    foreach (liveRows(
        $db,
        "SELECT record_id, new_values FROM audit_trail
           WHERE user_agent = ? AND action = 'Queue Published'
             AND DATE(created_at) >= ? AND DATE(created_at) <= ?",
        [LIVE_SIM_UA, $start, $end]
    ) as $queueEvent) {
        $payload = json_decode((string)($queueEvent['new_values'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }
        if (trim((string)($payload['operating_date'] ?? '')) === '') {
            $payload['operating_date'] = (string)($queueEvent['record_id'] ?? '');
        }
        $pairs = dispatchCoverageWrittenPairs($payload);
        foreach ($pairs['buses'] as $pairDay => $ids) {
            foreach (array_keys($ids) as $busId) {
                $writtenBuses[$pairDay][$busId] = true;
            }
        }
        foreach ($pairs['drivers'] as $pairDay => $ids) {
            foreach (array_keys($ids) as $driverId) {
                $writtenDrivers[$pairDay][$driverId] = true;
            }
        }
    }
    foreach ($writtenBuses as $pairDay => $ids) {
        if (!$ids) {
            continue;
        }
        $keys = array_keys($ids);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $result['dispatch_rows'] = (int)$result['dispatch_rows'] + liveExec(
            $db,
            "DELETE FROM bus_dispatch_queue WHERE operating_date = ? AND bus_id IN ($placeholders)",
            array_merge([$pairDay], $keys)
        );
    }
    foreach ($writtenDrivers as $pairDay => $ids) {
        if (!$ids) {
            continue;
        }
        $keys = array_keys($ids);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $result['dispatch_rows'] = (int)$result['dispatch_rows'] + liveExec(
            $db,
            "DELETE FROM driver_terminal_assignments WHERE operating_date = ? AND driver_id IN ($placeholders)",
            array_merge([$pairDay], $keys)
        );
    }

    $result['audit_events'] = (int)$result['audit_events'] + liveExec(
        $db,
        "DELETE FROM audit_trail WHERE user_agent = ?
           AND action IN ('Queue Published','Driver Checked In','Fare Payment Recorded','Remittance Submitted','Remittance Verified','Discrepancy Case Opened','Discrepancy Case Resolved')
           AND DATE(created_at) >= ? AND DATE(created_at) <= ?",
        [LIVE_SIM_UA, $start, $end]
    );
    /* Resolution events can land a few days after the case's business date,
       so discrepancy events are also removed by their case ids for the
       replaced period. */
    $caseIds = [];
    foreach (liveRows(
        $db,
        'SELECT id FROM discrepancy_cases WHERE business_date >= ? AND business_date <= ?',
        [$start, $end]
    ) as $caseRow) {
        $caseId = (string)($caseRow['id'] ?? '');
        if ($caseId !== '') {
            $caseIds[$caseId] = true;
        }
    }
    if ($caseIds) {
        $caseIdKeys = array_keys($caseIds);
        $casePh = implode(',', array_fill(0, count($caseIdKeys), '?'));
        $result['audit_events'] = (int)$result['audit_events'] + liveExec(
            $db,
            "DELETE FROM audit_trail WHERE user_agent = ? AND action IN ('Discrepancy Case Opened','Discrepancy Case Resolved') AND record_id IN ($casePh)",
            array_merge([LIVE_SIM_UA], $caseIdKeys)
        );
    }
    return $result;
}

/* True when a fuel table's trip_id column is numeric-typed, so replacements
   must use the numeric trip reference only (a 'TRIP-####' literal would be
   coerced to 0 and could match unrelated rows). Cached per request. */
function demoFuelTripIdIsNumeric(PDO $db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $numeric = true;
    try {
        foreach (describeTable($db, $table) as $col) {
            if (strcasecmp((string)$col['Field'], 'trip_id') === 0) {
                $numeric = (bool)preg_match(
                    '/^\s*(tinyint|smallint|mediumint|bigint|int|decimal|numeric)\b/i',
                    (string)($col['Type'] ?? '')
                );
                break;
            }
        }
    } catch (Throwable $e) {
        $numeric = true;
    }
    $cache[$table] = $numeric;
    return $numeric;
}

function ptTripNoValue(string $tripId, array $ctx)
{
    if (!empty($ctx['ptTripNoIsText'])) {
        return $tripId;
    }
    return (int)preg_replace('/\D+/', '', $tripId);
}

/* Continue each bus's odometer and fuel balance from everything the database
   still holds up to the replaced period's end. Called AFTER the demo cleanup,
   so a re-run resumes from the last surviving odometer reading instead of
   restarting from its pre-period value - regenerating days can never reuse an
   odometer range (or duplicate a morning top-up) that a surviving row already
   covers. */
function reseedBusFuelState(PDO $db, array &$ctx, string $end): void
{
    if (empty($ctx['busStates'])) {
        return;
    }
    $endTs = $end . ' 23:59:59';

    $priorFuel = [];
    try {
        $stmt = $db->prepare(
            "SELECT bus_id, MAX(ending_odo) AS odo FROM fuel_logs
             WHERE created_at <= ? AND ending_odo IS NOT NULL AND ending_odo > 0
             GROUP BY bus_id"
        );
        $stmt->execute([$endTs]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $priorFuel[(string)$row['bus_id']] = (float)$row['odo'];
        }
    } catch (Throwable $e) {
        try {
            $stmt = $db->prepare(
                "SELECT bus_id, MAX(starting_odo) AS odo FROM fuel_logs
                 WHERE created_at <= ? AND starting_odo IS NOT NULL AND starting_odo > 0
                 GROUP BY bus_id"
            );
            $stmt->execute([$endTs]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $priorFuel[(string)$row['bus_id']] = (float)$row['odo'];
            }
        } catch (Throwable $e2) {
            $priorFuel = [];
        }
    }

    $lastRefuelOdo = [];
    try {
        $stmt = $db->prepare(
            "SELECT bus_id, MAX(refuel_odo) AS odo FROM fuel_refuels
             WHERE created_at <= ? AND refuel_odo IS NOT NULL AND refuel_odo > 0
             GROUP BY bus_id"
        );
        $stmt->execute([$endTs]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bid = (string)$row['bus_id'];
            $rodo = (float)$row['odo'];
            $lastRefuelOdo[$bid] = $rodo;
            if (!isset($priorFuel[$bid]) || $rodo > $priorFuel[$bid]) {
                $priorFuel[$bid] = $rodo;
            }
        }
    } catch (Throwable $e) {
    }

    foreach ($ctx['busStates'] as $bid => &$st) {
        if (!isset($priorFuel[$bid])) {
            continue; // brand-new bus: keep its seeded starting odometer
        }
        $tank = (float)$st['tank'];
        $baseline = max(1.0, (float)$st['baselineKpl']);
        $odo = max(1000.0, (float)$priorFuel[$bid]);
        if (isset($lastRefuelOdo[$bid]) && $odo >= $lastRefuelOdo[$bid]) {
            $shortfall = round(min($tank * 0.90, ($odo - $lastRefuelOdo[$bid]) / $baseline), 2);
        } else {
            $shortfall = round($tank * stableRange('seed-shortfall-' . $bid, 0.02, 0.15), 2);
        }
        $st['odo'] = $odo;
        $st['fuel'] = round($tank - $shortfall, 2);
        $st['lastEventOdo'] = round($odo - $shortfall * $baseline, 2);
        $st['lastFullTankOdo'] = $odo;
        $st['intervalFuel'] = $shortfall;
        $st['expectedSinceRefuel'] = $shortfall;
        $st['lastRefuelDate'] = null;
        $st['lastFuelLogAt'] = null;
        $st['tripCount'] = 0;
        $st['totalDistance'] = 0.0;
    }
    unset($st);
}

/* Boarding/drop-off timestamps for one ride, derived from the trip's own
   schedule: the fraction of the trip needed to reach the boarding stop for
   boarding, and the destination stop for drop-off. Late trips compress all
   boardings into the time left before the 8:00 PM cut-off; drop-off can run
   until the trip's arrival. */
function rideBoardingTimes(string $day, array $stops, array $tr, $boardingRow, $destRow, string $key): array
{
    $tripStartTs = strtotime($day . ' ' . (string)($tr['departure'] ?? '00:00:00'));
    $totalSec = max(60, (int)($tr['arrSec'] ?? 0) - (int)($tr['sec'] ?? 0));
    $positions = [];
    foreach ($stops as $i => $stop) {
        $positions[strtolower(trim((string)($stop['destination'] ?? '')))] = $i;
    }
    $n = max(2, count($stops));
    $pb = $positions[strtolower(trim((string)($boardingRow['destination'] ?? '')))] ?? null;
    $pd = $positions[strtolower(trim((string)($destRow['destination'] ?? '')))] ?? null;
    $fB = $pb !== null ? $pb / ($n - 1) : 0.15;
    $fD = $pd !== null ? $pd / ($n - 1) : 0.85;
    if ($fD <= $fB) {
        $fD = min(1.0, $fB + 0.2);
    }

    $cutoffTs = strtotime($day . ' ' . fmtTime(LAST_DEPARTURE));
    $span = max(60, min($totalSec, $cutoffTs - $tripStartTs));
    $bOffset = (int)round($span * $fB) + (int)round(stableRange($key . '-b', 10, 60));
    $bOffset = max(0, min($bOffset, max(0, $span - 30)));
    $dOffset = (int)round($totalSec * $fD) + (int)round(stableRange($key . '-d', 10, 45));
    $dOffset = max($bOffset + 60, min($dOffset, $totalSec));

    $boardTs = $tripStartTs + $bOffset;
    if ($boardTs > $cutoffTs) {
        $boardTs = $cutoffTs;
    }
    $dropTs = max($tripStartTs + $dOffset, $boardTs + 60);
    return [date('Y-m-d H:i:s', $boardTs), date('Y-m-d H:i:s', $dropTs)];
}

/* Guarantee per-trip distinct timestamps: nudge collisions by one second and
   never let boarding pass the 8:00 PM cut-off. */
function reserveRideTimes(string $tripId, string $boardingAt, string $dropoffAt, array &$boardCursor, array &$dropCursor): array
{
    $cutoffTs = strtotime(substr($boardingAt, 0, 10) . ' ' . fmtTime(LAST_DEPARTURE));
    $boardTs = (int)strtotime($boardingAt);
    $prevBoard = (int)($boardCursor[$tripId] ?? 0);
    if ($boardTs <= $prevBoard) {
        $boardTs = min($prevBoard + 1, $cutoffTs);
    }
    if ($boardTs <= $prevBoard) {
        $boardTs = $cutoffTs;
    }
    $boardCursor[$tripId] = $boardTs;

    $dropTs = (int)strtotime($dropoffAt);
    $prevDrop = (int)($dropCursor[$tripId] ?? 0);
    if ($dropTs <= $prevDrop) {
        $dropTs = $prevDrop + 1;
    }
    if ($dropTs <= $boardTs) {
        $dropTs = $boardTs + 60;
    }
    $dropCursor[$tripId] = $dropTs;

    return [date('Y-m-d H:i:s', $boardTs), date('Y-m-d H:i:s', $dropTs)];
}

/* Cash/GCash split that agrees with the recorded payment method and fare. */
function histPaymentAmounts(string $method, float $fare, array $pay): array
{
    $method = in_array($method, ['cash', 'gcash', 'qrph', 'hybrid'], true) ? $method : 'cash';
    $cash = 0.0;
    $gcash = 0.0;
    if (($pay['payment_status'] ?? '') === 'paid') {
        if ($method === 'cash') {
            $cash = $fare;
        } elseif ($method === 'gcash' || $method === 'qrph') {
            /* The schema exposes one digital-wallet amount: QR Ph settlements
               land in the same unverified digital column. */
            $gcash = $fare;
        } else {
            $gcash = round($fare * 0.4, 2);
            $cash = round($fare - $gcash, 2);
        }
        if (($pay['remittance_status'] ?? '') === 'Discrepancy') {
            $actual = (float)($pay['actual_cash'] ?? 0.0);
            if ($method === 'cash') {
                $cash = $actual;
            } else {
                $gcash = min($gcash, $actual);
                $cash = max(0.0, round($actual - $gcash, 2));
            }
        }
    }
    return [
        'cash' => round($cash, 2),
        'gcash' => round($gcash, 2),
        'verified' => ($pay['payment_status'] ?? '') === 'paid' ? round($cash, 2) : null,
    ];
}

/* One generated operational trip (bus_trips). Dispatchers are real accounts
   when the deployment has them; totals are filled after the ride rows exist. */
function busTripRowValues(array $cols, array $d, array $requiredDefaults = []): array
{
    $values = [];
    foreach ($cols as $col) {
        $lower = strtolower($col);
        $value = match ($lower) {
            'bus_id' => $d['bus_id'],
            'driver_id' => $d['driver_id'],
            'route_name' => $d['route_name'],
            'trip_date' => $d['trip_date'],
            'trip_number' => $d['trip_number'] ?? null,
            'departure_time' => $d['departure_time'],
            'arrival_time' => $d['arrival_time'],
            'duration_minutes' => $d['duration_minutes'] ?? null,
            'status' => 'Completed',
            'total_passengers' => $d['total_passengers'] ?? 0,
            'total_revenue' => $d['total_revenue'] ?? 0.0,
            'fuel_cost' => $d['fuel_cost'] ?? null,
            'trip_type' => 'Regular',
            'dispatcher_id' => $d['dispatcher_id'] ?? null,
            'submitted' => 1,
            'created_at' => $d['created_at'],
            'updated_at' => $d['updated_at'] ?? $d['created_at'],
            'from_terminal_id' => null,
            'to_terminal_id' => null,
            default => null,
        };
        if ($value === null && isset($requiredDefaults[$lower])) {
            $value = $requiredDefaults[$lower];
            if ($value === '__ride_time__') {
                $value = (string)($d['created_at'] ?? $d['boarding_time'] ?? date('Y-m-d H:i:s'));
            } elseif ($value === '__ride_date__') {
                $value = substr((string)($d['trip_date'] ?? $d['boarding_time'] ?? date('Y-m-d')), 0, 10);
            } elseif ($value === '__ride_time_only__') {
                $value = substr((string)($d['departure_time'] ?? $d['boarding_time'] ?? '00:00:00'), 0, 8);
            }
        }
        $values[] = $value;
    }
    return $values;
}

/* Insert the operational trip row and return its id (null when the deployment
   has no usable bus_trips table, in which case TRIP-#### ids are used). */
function insertGeneratedBusTrip(PDO $db, array $ctx, array $tr, string $day, int $tripNumber, string $cutoff = ''): ?int
{
    $cutoff = trim($cutoff) !== '' ? trim($cutoff) : date('Y-m-d');
    $dispatcher = null;
    if (!empty($ctx['dispatchers'])) {
        $list = array_values($ctx['dispatchers']);
        $dispatcher = (string)$list[(int)floor(stableUnit('bus-trip-dispatcher-' . $tr['id']) * count($list)) % count($list)];
    }
    /* The deployment may key operational trips by the string bus id or by the
       numeric buses.id; write whichever shape the column actually uses. */
    $busNumber = (string)$tr['busNumber'];
    $busIdValue = $busNumber;
    if (!empty($ctx['busTripBusIdNumeric'])) {
        $busRow = $ctx['busByNumber'][$busNumber] ?? null;
        if (is_array($busRow) && isset($busRow['id'])) {
            $busIdValue = (int)$busRow['id'];
        }
    }
    $data = [
        'bus_id' => $busIdValue,
        'driver_id' => (string)$tr['drv']['driver_id'],
        'route_name' => (string)$tr['routeName'],
        'trip_date' => $day,
        'trip_number' => $tripNumber,
        'departure_time' => (string)($tr['departure'] ?? '00:00:00'),
        'arrival_time' => (string)($tr['arrival'] ?? '00:00:00'),
        'duration_minutes' => (int)round((float)($tr['durationMin'] ?? 0)),
        'status' => 'Completed',
        'total_passengers' => 0,
        'total_revenue' => 0.0,
        'trip_type' => 'Regular',
        'dispatcher_id' => $dispatcher,
        'submitted' => remittanceDayState($day, $cutoff) === 'settled' ? 1 : 0,
        'created_at' => $day . ' ' . (string)($tr['departure'] ?? '00:00:00'),
        'updated_at' => $day . ' ' . (string)($tr['arrival'] ?? '00:00:00'),
    ];
    $data = applyRequiredDefaults($data, $ctx['busTripRequiredDefaults'] ?? [], $day . ' ' . (string)($tr['departure'] ?? '00:00:00'));
    $id = liveInsert($db, 'bus_trips', $data, true);
    return $id > 0 ? $id : null;
}

/* =========================================================
   ROW BUILDING / BATCH INSERTS
   ========================================================= */
/* Type-safe placeholder for a NOT NULL column without a default that the
   generator does not populate itself. Dynamic markers resolve per row. */
function passengerRequiredDefault(string $type, string $column)
{
    $t = strtolower(trim($type));
    if (preg_match('/^enum\((.*)\)$/i', $t, $m)) {
        $parts = explode(',', $m[1]);
        return trim((string)($parts[0] ?? ''), " '\"");
    }
    if (preg_match('/int|decimal|float|double|numeric|bit/', $t)) {
        return 0;
    }
    if (preg_match('/datetime|timestamp/', $t)) {
        return '__ride_time__';
    }
    if ($t === 'date') {
        return '__ride_date__';
    }
    if (preg_match('/^time/', $t)) {
        return '__ride_time_only__';
    }
    return '';
}

/* Normalize a column DEFAULT fetched from DESCRIBE: timestamp functions become
   row-anchored markers, everything else is used verbatim. */
function normalizeColumnDefault($default, string $type): string
{
    $s = trim((string)$default);
    if ($s === '' || strtoupper($s) === 'NULL') {
        return passengerRequiredDefault($type, '');
    }
    if (preg_match('/^(current_timestamp|now)(\(\))?$/i', $s)) {
        return '__ride_time__';
    }
    return $s;
}

/* Fill every NOT NULL column that has no default and no generated value so a
   deployment with extra required columns still accepts the batch. */
function applyRequiredDefaults(array $data, array $defaults, string $timeAnchor): array
{
    foreach ($defaults as $col => $value) {
        if (array_key_exists($col, $data) && $data[$col] !== null) {
            continue;
        }
        if ($value === '__ride_time__') {
            $value = $timeAnchor;
        } elseif ($value === '__ride_date__') {
            $value = substr($timeAnchor, 0, 10);
        } elseif ($value === '__ride_time_only__') {
            $value = substr($timeAnchor, 11, 8);
        }
        $data[$col] = $value;
    }
    return $data;
}

/* Live-table row mapper: emits one value per existing passenger_trips column
   and NULL for optional columns the generator does not populate, so inserts
   never reference invented columns. Required columns without defaults receive
   the mapped value or a type-safe fallback. */
function passengerTripRowValues(array $cols, array $d, array $requiredDefaults = []): array
{
    $values = [];
    foreach ($cols as $col) {
        $lower = strtolower($col);
        $value = match ($lower) {
            'passenger_id' => $d['passenger_id'],
            'bus_no' => $d['bus_id'],
            'bus_id' => $d['bus_id'],
            'driver_id' => $d['driver_id'],
            'trip_no' => $d['trip_no'],
            'passenger_type' => $d['passenger_type'],
            'boarding_point' => $d['boarding'],
            'destination', 'selected_destination' => $d['destination'],
            'fare', 'estimated_fare', 'final_fare' => $d['fare'],
            'base_fare' => $d['base_fare'] ?? $d['fare'],
            'discount_amount' => $d['discount_amount'] ?? 0.0,
            'payment_method' => $d['payment_method'],
            'payment_status' => $d['payment_status'],
            'cash_amount' => $d['cash_amount'] ?? 0.0,
            'gcash_amount' => $d['gcash_amount'] ?? 0.0,
            'verified_cash_amount' => $d['verified_cash_amount'] ?? null,
            'expected_cash' => $d['expected_cash'] ?? null,
            'actual_cash' => $d['actual_cash'] ?? null,
            'discrepancy_amount' => $d['discrepancy_amount'] ?? 0.0,
            'remittance_status' => $d['remittance_status'],
            'remitted_by' => $d['remitted_by'] ?? null,
            'remitted_at' => $d['remitted_at'] ?? null,
            'boarding_time' => $d['boarding_time'],
            'dropoff_time' => $d['dropoff_time'],
            'archived_status' => 'no',
            'trip_status' => 'Completed',
            'identification_source' => $d['identification_source'] ?? 'qr',
            'dropoff_type' => $d['dropoff_type'] ?? 'normal',
            'unverified' => $d['unverified'] ?? 0,
            'declared_id_number' => $d['declared_id_number'] ?? null,
            'declared_id_status' => $d['declared_id_status'] ?? null,
            'latitude' => null,
            'longitude' => null,
            'inspector_id' => null,
            'updated_by' => null,
            'finance_note' => null,
            default => null,
        };
        if ($value === null && isset($requiredDefaults[$lower])) {
            $value = $requiredDefaults[$lower];
            if ($value === '__ride_time__') {
                $value = (string)($d['boarding_time'] ?? date('Y-m-d H:i:s'));
            } elseif ($value === '__ride_date__') {
                $value = substr((string)($d['boarding_time'] ?? date('Y-m-d')), 0, 10);
            } elseif ($value === '__ride_time_only__') {
                $value = substr((string)($d['boarding_time'] ?? '00:00:00'), 11, 8);
            }
        }
        $values[] = $value;
    }
    return $values;
}

/* Manifest name for an archive row: nickname (like the cooperative's paper
   records), "Last, First" style, or the full name - chosen deterministically
   per person so their history stays consistent. */
function archiveDisplayName(string $name): string
{
    $roll = stableUnit('archive-name-style-' . $name);
    if ($roll < ARCHIVE_NICKNAME_SHARE) {
        $list = ARCHIVE_NICKNAMES;
        return (string)$list[(int)floor(stableUnit('archive-nick-pick-' . $name) * count($list)) % count($list)];
    }
    if ($roll < ARCHIVE_NICKNAME_SHARE + 0.2) {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: [], static fn($p) => trim((string)$p) !== ''));
        if (count($parts) >= 2) {
            $last = (string)$parts[count($parts) - 1];
            $first = implode(' ', array_slice($parts, 0, -1));
            return $last . ', ' . $first;
        }
    }
    return $name;
}

/* passenger_trip_history row painter (archive manifests). */
function archiveHistoryRowValues(array $cols, array $d, array $requiredDefaults = []): array
{
    $values = [];
    foreach ($cols as $col) {
        $lower = strtolower($col);
        $value = match ($lower) {
            'passenger_name' => $d['name'],
            'passenger_type' => $d['ptype'],
            'type' => $d['row_type'],
            'space_occupied' => $d['space'],
            'student_id' => $d['student_id'] ?? null,
            'boarding_point' => $d['boarding'],
            'destination', 'selected_destination', 'actual_destination' => $d['destination'],
            'drivers_full_name' => $d['driver_name'],
            'bus_number' => $d['bus_number'],
            'bus_id' => $d['bus_id'] ?? null,
            'driver_id' => $d['driver_id'],
            'trip_id' => $d['trip_id'],
            'fare', 'estimated_fare', 'final_fare' => $d['fare'],
            'payment_method' => $d['payment_method'],
            'date_booked' => $d['date_booked'],
            'trip_date' => $d['trip_date'],
            'payment_status' => $d['payment_status'],
            'remittance_status' => $d['remittance_status'],
            'remitted_by' => $d['remitted_by'] ?? null,
            'remitted_at' => $d['remitted_at'] ?? null,
            'expected_cash' => $d['expected_cash'] ?? 0.0,
            'actual_cash' => $d['actual_cash'] ?? null,
            'discrepancy_amount' => $d['discrepancy_amount'] ?? 0.0,
            'time_of_departure' => $d['departure'],
            'time_of_arrival' => $d['arrival'],
            'archived_at' => $d['date_booked'],
            'senior_pwd_id' => $d['senior_pwd_id'] ?? null,
            'beneficiary_id' => $d['beneficiary_id'] ?? null,
            'coop_disc_id' => $d['coop_disc_id'] ?? null,
            'remarks' => $d['remarks'] ?? null,
            'archived' => 0,
            'boarding_km' => $d['boarding_km'] ?? null,
            'selected_destination_km' => $d['dest_km'] ?? null,
            'actual_destination_km' => $d['dest_km'] ?? null,
            'baggage_category' => $d['baggage_category'] ?? null,
            default => null,
        };
        if ($value === null && isset($requiredDefaults[$lower])) {
            $value = $requiredDefaults[$lower];
            if ($value === '__ride_time__') {
                $value = (string)($d['date_booked'] ?? $d['trip_date'] ?? date('Y-m-d H:i:s'));
            } elseif ($value === '__ride_date__') {
                $value = substr((string)($d['trip_date'] ?? date('Y-m-d')), 0, 10);
            } elseif ($value === '__ride_time_only__') {
                $value = substr((string)($d['departure'] ?? '00:00:00'), 0, 8);
            }
        }
        $values[] = $value;
    }
    return $values;
}

function fuelRowValues(array $fuelColNames, array $d): array
{
    $values = [];
    foreach ($fuelColNames as $name) {
        $values[] = match (strtolower($name)) {
            'bus_id' => $d['bus_id'],
            'driver_id' => $d['driver_id'],
            'trip_id' => $d['trip_id'],
            'starting_odo' => $d['start_odo'],
            'ending_odo' => $d['end_odo'],
            'starting_odometer' => $d['start_odo'],
            'ending_odometer' => $d['end_odo'],
            'start_km' => $d['start_odo'],
            'end_km' => $d['end_odo'],
            'distance_km' => $d['distance'],
            'distance_traveled' => $d['distance'],
            'kpl' => $d['kpl'],
            'expected_fuel' => $d['liters'],
            /* Actual liters consumed by this trip, derived from its own
               distance and drawn km/L. Fuel purchases live separately in
               fuel_refuels, so cost stays 0 here; the historical diesel
               price is recorded for reference and cost estimation. */
            'liters' => $d['liters'],
            'cost' => 0.00,
            'price_per_liter' => $d['price'],
            'is_full_tank' => 0,
            'notes' => null,
            'created_at' => $d['created_at'],
            'updated_at' => $d['created_at'],
            default => null,
        };
    }
    return $values;
}

function refuelRowValues(array $refuelColNames, array $d): array
{
    $values = [];
    foreach ($refuelColNames as $name) {
        $values[] = match (strtolower($name)) {
            'bus_id' => $d['bus_id'],
            'driver_id' => $d['driver_id'],
            'trip_id' => $d['trip_id'],
            'refuel_odo' => $d['refuel_odo'],
            'liters' => $d['liters'],
            'price_per_liter' => $d['price_per_liter'],
            'total_cost' => $d['total_cost'],
            'is_full_tank' => $d['is_full_tank'],
            'refuel_type' => $d['refuel_type'],
            'notes' => $d['notes'],
            'fuel_proof_url' => $d['fuel_proof_url'],
            'created_at' => $d['created_at'],
            'updated_at' => $d['updated_at'],
            default => null,
        };
    }
    return $values;
}

/* Append one actual refuel transaction (fuel_refuels) and update the bus's
   fuel state, run statistics, and validation ledger. Refuel quantities are
   never written to fuel_logs; those rows stay trip-level consumption only. */
function recordRefuelEvent(
    array &$state,
    string $busId,
    ?string $driverId,
    $tripRef,
    string $day,
    string $createdAt,
    float $price,
    float $liters,
    int $isFullTank,
    string $refuelType,
    ?string $notes,
    bool $anomaly,
    bool $morning,
    array &$refuelBuffer,
    array $refuelColNames,
    array &$stats,
    array &$ledger
): bool {
    if ($liters < 0.5) {
        return false;
    }
    $expectedBefore = round((float)($state['expectedSinceRefuel'] ?? 0.0), 2);
    $tank = (float)$state['tank'];
    $state['fuel'] = min($tank, round((float)$state['fuel'] + $liters, 2));
    $totalCost = round($liters * $price, 2);
    $refuelBuffer[] = refuelRowValues($refuelColNames, [
        'bus_id' => $busId,
        'driver_id' => $driverId,
        'trip_id' => $tripRef,
        'refuel_odo' => round((float)$state['odo'], 2),
        'liters' => $liters,
        'price_per_liter' => $price,
        'total_cost' => $totalCost,
        'is_full_tank' => $isFullTank,
        'refuel_type' => $refuelType,
        'notes' => $notes,
        'fuel_proof_url' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $state['lastEventOdo'] = round((float)$state['odo'], 2);
    if ($isFullTank === 1) {
        $state['lastFullTankOdo'] = round((float)$state['odo'], 2);
    }
    $state['lastRefuelDate'] = $day;
    $state['lastFuelLogAt'] = $createdAt;
    $state['intervalFuel'] = 0.0;
    /* Keep the "expected since refuel" figure equal to the tank room left after
       this event, not zero. A top-up buys exactly that room, so its liters must
       reconcile with the accumulated consumption. Resetting to zero only held
       when every refuel filled the tank; a partial or under-filled refuel left
       room behind and made the next morning's liters look larger than the fuel
       actually burned (the "too much fuel" inconsistency). */
    $state['expectedSinceRefuel'] = round($tank - (float)$state['fuel'], 2);

    $stats['refuel_events']++;
    $stats['refuel_buses'][$busId] = true;
    $stats['fuel_full'] += $isFullTank;
    $stats['fuel_partials'] += ($isFullTank === 0 ? 1 : 0);
    $stats['fuel_liters'] += $liters;
    $stats['fuel_cost'] += $totalCost;
    if ($anomaly) {
        $stats['fuel_anomalies']++;
    }

    $ledger['refuels'][] = [
        'bus' => $busId,
        'driver' => $driverId,
        'day' => $day,
        'morning' => $morning,
        'expected_since_refuel' => $expectedBefore,
        'trip' => $tripRef === null ? '' : (string)$tripRef,
        'odo' => round((float)$state['odo'], 2),
        'liters' => $liters,
        'price' => $price,
        'cost' => $totalCost,
        'full' => $isFullTank,
        'type' => $refuelType,
        'notes' => $notes,
    ];
    return true;
}

function flushBatch(PDO $db, string $table, array $cols, array &$buffer): int
{
    if (!$buffer) {
        return 0;
    }
    $total = count($buffer);
    $colSql = implode(',', array_map(fn($c) => "`$c`", $cols));
    $rowSql = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    foreach (array_chunk($buffer, 100) as $chunk) {
        $sql = "INSERT INTO `$table` ($colSql) VALUES " . implode(',', array_fill(0, count($chunk), $rowSql));
        $params = [];
        foreach ($chunk as $row) {
            foreach ($row as $v) {
                $params[] = $v;
            }
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    $buffer = [];
    return $total;
}

/* =========================================================
   FUEL SIMULATION
   fuel_logs: trip-level consumption (distance, kpl, expected_fuel, ODO)
   fuel_refuels: actual refueling transactions (liters, price, total_cost, ODO)
   ========================================================= */
function simulateFuelConsumption(
    array &$state,
    array $busTripList,
    string $day,
    array $opts,
    array &$stats,
    array &$fuelBuffer,
    array $fuelColNames,
    bool $fuelTripIdIsText,
    bool $fuelTripIdNumericOnly,
    array &$refuelBuffer,
    array $refuelColNames,
    array &$ledger,
    ?array $dieselAnchor = null
): void {
    if (!$busTripList) {
        return;
    }
    $busId = (string)$state['bus']['bus_id'];
    $tank = (float)$state['tank'];
    $baseline = (float)$state['baselineKpl'];
    $threshold = (float)($state['threshold'] ?? ($tank * 0.25));
    $anomalyRate = ANOMALY_RATES[$opts['anomaly_rate']] ?? 0.02;
    $kplLow = $opts['fuel_realism'] === 'high' ? 0.80 : 0.65;
    $kplHigh = $opts['fuel_realism'] === 'high' ? 1.20 : 1.35;

    $price = pricePerLiterForDate($day, $dieselAnchor);

    $tripRefFor = function (string $tripId) use ($fuelTripIdIsText, $fuelTripIdNumericOnly) {
        if ($tripId === '') {
            return null;
        }
        if ($fuelTripIdIsText) {
            return $tripId;
        }
        if ($fuelTripIdNumericOnly) {
            return (int)preg_replace('/\D+/', '', $tripId);
        }
        return null;
    };

    /* Morning top-up: every bus that operates today refuels to a full tank
       before its first departure, so each operating bus has one recorded fuel
       purchase every operating morning. Liters equal the tank room at that
       moment (the fuel burned since its last refuel); the per-bus low-fuel
       threshold refuel below remains a mid-day safety net. */
    $firstTrip = null;
    foreach ($busTripList as $candidate) {
        if ($firstTrip === null || (int)$candidate['sec'] < (int)$firstTrip['sec']) {
            $firstTrip = $candidate;
        }
    }
    if ($firstTrip !== null) {
        $room = max(0.0, round($tank - (float)$state['fuel'], 2));
        if ($room >= 0.5) {
            $liters = round(min($room, $room * (0.985 + mt_rand(0, 25) / 1000)), 2);
            $notes = 'Morning refueling';
            $anomaly = false;
            if (mt_rand(1, 10000) <= (int)round($anomalyRate * 10000)) {
                $liters = round($liters * (mt_rand(0, 1) === 0 ? mt_rand(108, 118) / 100 : mt_rand(90, 96) / 100), 2);
                $liters = min($liters, $room);
                $notes = 'Fuel consumption differs from expected; for review';
                $anomaly = true;
            }
            $firstDriver = (string)($firstTrip['drv']['driver_id'] ?? '');
            recordRefuelEvent(
                $state,
                $busId,
                $firstDriver !== '' ? $firstDriver : null,
                $tripRefFor((string)($firstTrip['busTripId'] ?? $firstTrip['id'] ?? '')),
                $day,
                $day . ' ' . fmtTime(max(0, (int)$firstTrip['sec'] - mt_rand(10, 40) * 60)),
                $price,
                $liters,
                1,
                'Full Tank',
                $notes,
                $anomaly,
                true,
                $refuelBuffer,
                $refuelColNames,
                $stats,
                $ledger
            );
        }
    }

    /* Every completed trip emits one trip-level fuel_logs record; actual
       purchases go to fuel_refuels (the morning top-up above plus any
       threshold safety refuel). */
    foreach ($busTripList as $tr) {
        $dist = (float)$tr['distance'];
        /* Prefer the operational bus trip id so fuel logs, passenger rides and
           bus_trips all reference the same trip. */
        $tripId = (string)($tr['busTripId'] ?? $tr['id'] ?? '');
        $tripRef = $tripRefFor($tripId);
        $driverId = (string)($tr['drv']['driver_id'] ?? '');
        $arrSec = (int)($tr['arrSec'] ?? $tr['sec'] ?? SERVICE_START);
        $createdAt = $day . ' ' . fmtTime($arrSec);

        $load = (PASSENGER_CAP_PER_TRIP - (int)$tr['space']) / PASSENGER_CAP_PER_TRIP;
        $trafficF = (float)($tr['trafficFactor'] ?? 1.0);
        $weatherF = weatherFactor((string)($tr['weather'] ?? 'clear'));
        $routeF = 0.98 + min(0.04, $dist / 600.0);
        $loadF = 1.0 - 0.08 * clampf($load, 0.0, 1.0);
        $noise = $opts['fuel_realism'] === 'high' ? mt_rand(98, 102) / 100 : mt_rand(94, 106) / 100;
        $kpl = $baseline * $trafficF * $weatherF * $routeF * $loadF * $noise;
        $kpl = clampf($kpl, $baseline * $kplLow, $baseline * $kplHigh);
        /* Actual liters consumed by this trip: its own distance over the
           drawn km/L. The stored kpl is recomputed from the stored liters so
           every row satisfies kpl = distance / liters exactly. */
        $litersConsumed = round($dist / max(0.5, $kpl), 2);
        if ($litersConsumed < 0.01) {
            $litersConsumed = 0.01;
        }
        $logKpl = round($dist / $litersConsumed, 2);

        $startOdo = round((float)$state['odo'], 2);
        $endOdo = round($startOdo + $dist, 2);
        $state['odo'] = $endOdo;

        /* Fuel drops by exactly the recorded consumption */
        $state['fuel'] = max(0.0, round((float)$state['fuel'] - $litersConsumed, 4));
        $state['intervalFuel'] = (float)($state['intervalFuel'] ?? 0.0) + $litersConsumed;
        $state['totalDistance'] = ($state['totalDistance'] ?? 0) + $dist;
        $state['tripCount'] = ($state['tripCount'] ?? 0) + 1;

        $expectedSinceRefuel = (float)($state['expectedSinceRefuel'] ?? 0.0) + $litersConsumed;
        $state['expectedSinceRefuel'] = $expectedSinceRefuel;

        /* Trip-level consumption record only; purchases stay in fuel_refuels. */
        $fuelBuffer[] = fuelRowValues($fuelColNames, [
            'bus_id' => $busId,
            'driver_id' => $driverId !== '' ? $driverId : null,
            'trip_id' => $tripRef,
            'start_odo' => $startOdo,
            'end_odo' => $endOdo,
            'distance' => round($dist, 2),
            'kpl' => $logKpl,
            'liters' => $litersConsumed,
            'price' => $price,
            'created_at' => $createdAt,
        ]);

        $ledger['trip_fuel'][] = [
            'bus' => $busId,
            'trip' => $tripId,
            'start' => $startOdo,
            'end' => $endOdo,
            'distance' => round($dist, 2),
            'kpl' => $logKpl,
            'liters' => $litersConsumed,
            'price' => $price,
            'expected' => $litersConsumed,
        ];

        /* Trigger refueling when fuel reaches around 20–30% of tank capacity.
           Do not create a refuel record after every trip. */
        $reserve = max(2.0, $threshold * 0.25);
        $emergency = (float)$state['fuel'] < $litersConsumed + $reserve;

        if ($emergency || (float)$state['fuel'] <= $threshold) {
            $room = max(0.0, $tank - (float)$state['fuel']);
            if ($room >= 1.0) {
                $isPartial = (mt_rand(1, 100) <= (int)round(FUEL_PARTIAL_EVENT_RATE * 100)); // ~4% partial
                $anomaly = false;

                if ($isPartial) {
                    $target = max(5.0, min($room * 0.45, (float)$state['intervalFuel']));
                    $liters = round(min($room, $target * (0.97 + mt_rand(0, 60) / 1000)), 2);
                    $refuelType = 'Partial';
                    $isFullTank = 0;
                    $notes = 'Partial top-up';
                } else {
                    $liters = round(min($room, $room * (0.985 + mt_rand(0, 25) / 1000)), 2);
                    $refuelType = 'Full Tank';
                    $isFullTank = 1;
                    $notes = $emergency ? 'Emergency refueling' : (['Full Tank refill', 'Scheduled refueling', 'Routine refueling'][mt_rand(0, 2)]);
                }

                if (mt_rand(1, 10000) <= (int)round($anomalyRate * 10000)) {
                    $liters = round($liters * (mt_rand(0, 1) === 0 ? mt_rand(108, 118) / 100 : mt_rand(90, 96) / 100), 2);
                    $liters = min($liters, round($room, 2));
                    $notes = 'Fuel consumption differs from expected; for review';
                    $anomaly = true;
                }

                recordRefuelEvent(
                    $state,
                    $busId,
                    $driverId !== '' ? $driverId : null,
                    $tripRef,
                    $day,
                    $createdAt,
                    $price,
                    $liters,
                    $isFullTank,
                    $refuelType,
                    $notes,
                    $anomaly,
                    false,
                    $refuelBuffer,
                    $refuelColNames,
                    $stats,
                    $ledger
                );
            }
        }

        $stats['fuel_logs']++;
        $stats['kpl_sum'] += $logKpl * max(0.5, $dist);
        $stats['kpl_dist'] += max(0.5, $dist);
        $stats['fuel_buses'][$busId] = true;
    }
}

/* =========================================================
   VALIDATION + SUMMARY
   ========================================================= */
function validateGeneratedData(PDO $db, array $ledger, array $ctx, array $samples, array $accounts = [], bool $checkFuel = true, array $coverageDays = [], string $today = ''): void
{
    foreach ($ledger['rides'] as $day => $names) {
        foreach ($names as $name => $count) {
            if ($count > MAX_RIDES_PER_PASSENGER) {
                throw new RuntimeException(
                    "Validation failed: $name has $count rides on $day (max " . MAX_RIDES_PER_PASSENGER . ")."
                );
            }
        }
    }
    foreach ($ledger['trip_space'] as $tripId => $space) {
        $bounds = $ledger['trip_bounds'][$tripId] ?? null;
        $spaceCap = $bounds !== null ? (int)$bounds[2] : PASSENGER_CAP_PER_TRIP;
        if ($space > $spaceCap) {
            throw new RuntimeException("Validation failed: trip $tripId exceeds capacity ($space).");
        }
        if ($space < 0) {
            throw new RuntimeException("Validation failed: trip $tripId has negative occupancy.");
        }
    }
    /* Every completed trip must carry the passenger band in force for its day
       (10-30 by default; the window profile's reduced band where configured). */
    foreach ($ledger['trip_ids'] as $tripId => $_) {
        $pax = (int)($ledger['trip_pax'][$tripId] ?? 0);
        $bounds = $ledger['trip_bounds'][$tripId] ?? null;
        $paxMin = $bounds !== null ? (int)$bounds[0] : TRIP_PASSENGER_MIN;
        $paxMax = $bounds !== null ? (int)$bounds[1] : TRIP_PASSENGER_MAX;
        if ($pax < $paxMin) {
            throw new RuntimeException(
                "Validation failed: trip $tripId has only $pax passengers (min $paxMin)."
            );
        }
        if ($pax > $paxMax) {
            throw new RuntimeException(
                "Validation failed: trip $tripId has $pax passengers (max $paxMax)."
            );
        }
    }
    if (count($ledger['trip_ids']) !== $ledger['trip_id_count']) {
        throw new RuntimeException('Validation failed: duplicate generated trip identifiers.');
    }
    /* Weekly travel-plan conformance: per-profile weekly caps, return legs
       after their outbound, no stranded returns, extra legs only through
       topped-up days, and no duplicate passenger-on-trip rows on any day. */
    foreach (weeklyPlanViolations($ledger) as $violation) {
        throw new RuntimeException('Validation failed: ' . $violation . '.');
    }
    /* Round journeys pair opposite route directions of the same route pair. */
    foreach (($ledger['plan'] ?? []) as $day => $names) {
        foreach ($names as $name => $entry) {
            $placed = (array)($entry['placed'] ?? []);
            $outRoute = $placed['out'] ?? null;
            $retRoute = $placed['ret'] ?? null;
            if ($outRoute === null || $retRoute === null) {
                continue;
            }
            $expectedReturn = (string)($ctx['routeOpposite'][(string)$outRoute] ?? '');
            if ($expectedReturn === '' || (string)$retRoute !== $expectedReturn) {
                throw new RuntimeException("Validation failed: return direction mismatch for $name on $day.");
            }
        }
    }
    foreach ($ledger['busy'] as $kind => $days) {
        foreach ($days as $day => $entities) {
            foreach ($entities as $id => $intervals) {
                usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
                foreach ($intervals as $interval) {
                    if ((int)($interval[0] ?? 0) > LAST_SCHEDULED_DEPARTURE) {
                        throw new RuntimeException("Validation failed: $kind $id departs after the last scheduled departure on $day.");
                    }
                    if ((int)($interval[1] ?? 0) > SERVICE_END) {
                        throw new RuntimeException("Validation failed: $kind $id arrives after the terminal cutoff on $day.");
                    }
                }
                for ($i = 1; $i < count($intervals); $i++) {
                    $prev = $intervals[$i - 1];
                    $prevReady = (int)($prev[2] ?? $prev[1]);
                    if ($intervals[$i][0] < $prev[1]) {
                        throw new RuntimeException("Validation failed: overlapping $kind assignments for $id on $day.");
                    }
                    /* Consecutive trips of one bus/driver must leave at least
                       the previous travel time plus turnaround before the
                       next departure. */
                    if ($intervals[$i][0] < $prevReady) {
                        throw new RuntimeException("Validation failed: $kind $id departs before the previous trip's turnaround on $day.");
                    }
                }
            }
        }
    }
    /* Fleet-target adherence: every planned trip of the day either ran or
       was cancelled, and never more ran than the day's effective target. */
    foreach (($ledger['fleet_stats'] ?? []) as $day => $fs) {
        $target = (int)($fs['target'] ?? 0);
        $scheduled = (int)($fs['scheduled'] ?? 0);
        $cancelled = (int)($fs['cancelled'] ?? 0);
        if ($scheduled > $target) {
            throw new RuntimeException("Validation failed: $day scheduled $scheduled trips above the fleet target of $target.");
        }
        if ($scheduled + $cancelled !== $target) {
            throw new RuntimeException(
                "Validation failed: $day accounted for " . ($scheduled + $cancelled) . " trips against a fleet target of $target."
            );
        }
    }
    foreach ($ledger['durations'] as $min) {
        if ($min < 10.0 || $min > 260.0) {
            throw new RuntimeException('Validation failed: impossible trip duration ' . $min . ' minutes.');
        }
    }
    if ($checkFuel) {
        foreach ($ledger['fuel_negative'] as $busId => $bad) {
            if ($bad) {
                throw new RuntimeException("Validation failed: negative fuel for $busId.");
            }
        }
        foreach ($ledger['fuel_chain'] as $busId => $chain) {
            for ($i = 1; $i < count($chain); $i++) {
                if ($chain[$i] < $chain[$i - 1]) {
                    throw new RuntimeException("Validation failed: decreasing odometer for $busId.");
                }
            }
        }
        /* Per-trip fuel records: exactly one record per completed trip, a continuous
           per-bus odometer chain, expected-fuel arithmetic, and plausible KPL. */
        $totalFuelRecords = 0;
        $chainByBus = [];
        foreach ($ledger['trip_fuel'] as $rec) {
            if (abs($rec['distance'] - ($rec['end'] - $rec['start'])) > 0.011) {
                throw new RuntimeException("Validation failed: trip fuel distance does not match odometer delta for {$rec['bus']}.");
            }
            if ($rec['distance'] <= 0 || $rec['start'] <= 0 || $rec['end'] <= $rec['start']) {
                throw new RuntimeException("Validation failed: invalid odometer range for {$rec['bus']}.");
            }
            if ($rec['liters'] <= 0) {
                throw new RuntimeException("Validation failed: trip fuel record has no consumed liters for {$rec['bus']}.");
            }
            if (abs(round($rec['distance'] / max(0.01, $rec['kpl']), 2) - $rec['liters']) > 0.02) {
                throw new RuntimeException("Validation failed: recorded kpl does not equal distance / liters for {$rec['bus']}.");
            }
            if (abs($rec['expected'] - $rec['liters']) > 0.011) {
                throw new RuntimeException("Validation failed: expected fuel does not equal consumed liters for {$rec['bus']}.");
            }
            if ((float)($rec['price'] ?? 0) <= 0) {
                throw new RuntimeException("Validation failed: trip fuel record has no historical fuel price for {$rec['bus']}.");
            }
            $base = (float)($ctx['busStates'][$rec['bus']]['baselineKpl'] ?? 9.0);
            if ($rec['kpl'] < $base * 0.60 || $rec['kpl'] > $base * 1.40) {
                throw new RuntimeException("Validation failed: implausible KPL {$rec['kpl']} for {$rec['bus']}.");
            }
            $prevEnd = $chainByBus[$rec['bus']] ?? null;
            if ($prevEnd !== null && abs($rec['start'] - $prevEnd) > 0.011) {
                throw new RuntimeException("Validation failed: broken per-trip odometer chain for {$rec['bus']}.");
            }
            $chainByBus[$rec['bus']] = $rec['end'];
            $totalFuelRecords++;
        }
        if ($totalFuelRecords !== (int)$ledger['trip_id_count']) {
            throw new RuntimeException(
                'Validation failed: expected one fuel record per completed trip ('
                . $ledger['trip_id_count'] . "), got $totalFuelRecords."
            );
        }

        /* Refuel transactions in fuel_refuels: cost arithmetic, types, at most
           one morning top-up per bus per operating day, and a partial-top-up
           minority. Threshold refuels stay a mid-day safety net. */
        $totalRefuelEvents = count($ledger['refuels'] ?? []);
        $totalPartialEvents = 0;
        $morningByBusDay = [];
        foreach (($ledger['refuels'] ?? []) as $rf) {
            if ($rf['cost'] <= 0.0 || abs($rf['cost'] - round($rf['liters'] * $rf['price'], 2)) > 0.011) {
                throw new RuntimeException("Validation failed: refuel cost does not equal liters x price for {$rf['bus']}.");
            }
            if (!in_array($rf['type'], ['Full Tank', 'Partial'], true)) {
                throw new RuntimeException("Validation failed: invalid refuel type {$rf['type']} for {$rf['bus']}.");
            }
            if ($rf['type'] === 'Partial') {
                $totalPartialEvents++;
            }
            if (!empty($rf['morning'])) {
                $key = ($rf['bus'] ?? '') . '|' . ($rf['day'] ?? '');
                $morningByBusDay[$key] = ($morningByBusDay[$key] ?? 0) + 1;
                if ($morningByBusDay[$key] > 1) {
                    throw new RuntimeException("Validation failed: multiple morning refuels for {$rf['bus']} on {$rf['day']}.");
                }
                $exp = (float)($rf['expected_since_refuel'] ?? 0.0);
                $tol = max(1.5, (strpos((string)$rf['notes'], 'review') !== false ? 0.22 : 0.08) * $exp);
                if ($exp > 0.0 && abs((float)$rf['liters'] - $exp) > $tol) {
                    throw new RuntimeException("Validation failed: morning refuel liters inconsistent with consumption for {$rf['bus']} on {$rf['day']}.");
                }
            }
        }
        /* The partial-top-up proportion is a statistical shape, so it is only
           enforced once a period produced enough refuel events: with fewer
           threshold refuels (short periods, window months) a single legitimate
           partial would otherwise trip the ratio. */
        if ($totalRefuelEvents >= 20 && $totalPartialEvents / $totalRefuelEvents > 0.20) {
            throw new RuntimeException('Validation failed: partial top-ups are not a small minority of refuel events.');
        }

        /* Fuel coverage: every bus that operated in the generated period must
           carry trip-level fuel records, so fuel history reflects the whole
           fleet that actually ran - not just a subset. */
        $fuelBuses = [];
        foreach (($ledger['trip_fuel'] ?? []) as $rec) {
            $fuelBuses[(string)($rec['bus'] ?? '')] = true;
        }
        foreach (array_keys((array)($ledger['operating_buses'] ?? [])) as $ob) {
            if (!isset($fuelBuses[(string)$ob])) {
                throw new RuntimeException("Validation failed: operating bus $ob has no fuel records.");
            }
        }
    }
    foreach ($samples as $row) {
        foreach (['fare', 'expected_cash', 'actual_cash', 'discrepancy_amount'] as $key) {
            if (isset($row[$key]) && $row[$key] !== null && (float)$row[$key] < 0) {
                throw new RuntimeException("Validation failed: negative $key in generated row.");
            }
        }
        if (($row['remittance_status'] ?? null) === 'Remitted'
            && isset($row['actual_cash'], $row['expected_cash'])
            && abs((float)$row['actual_cash'] - (float)$row['expected_cash']) > 0.01) {
            throw new RuntimeException('Validation failed: remitted cash does not match expected cash.');
        }
        if (($row['remittance_status'] ?? null) === 'Discrepancy') {
            if ((float)($row['discrepancy_amount'] ?? 0) <= 0) {
                throw new RuntimeException('Validation failed: discrepancy row without a discrepancy amount.');
            }
            if (isset($row['actual_cash'], $row['expected_cash'])
                && abs((float)$row['actual_cash'] - ((float)$row['expected_cash'] - (float)$row['discrepancy_amount'])) > 0.011) {
                throw new RuntimeException('Validation failed: discrepancy amount does not reconcile with expected/actual cash.');
            }
        }
        if (($row['payment_status'] ?? null) === 'rejected' && ($row['actual_cash'] ?? null) !== null) {
            throw new RuntimeException('Validation failed: rejected payment with recorded cash.');
        }
        /* Every ride must link to a real (generated or reused) passenger account. */
        if (array_key_exists('passenger_id', $row)) {
            $pid = (string)($row['passenger_id'] ?? '');
            if ($pid === '') {
                throw new RuntimeException('Validation failed: generated ride without a passenger account.');
            }
            $exists = (int)(liveValue($db, 'SELECT COUNT(*) FROM passengers WHERE id = ?', [$pid]) ?? 0);
            if ($exists <= 0) {
                throw new RuntimeException('Validation failed: generated ride links to an unknown passenger account.');
            }
        }
        /* When generated rides reference operational trips, that trip must exist. */
        if (!empty($ctx['busTripsUsable']) && array_key_exists('trip_no', $row)) {
            $tripNo = trim((string)($row['trip_no'] ?? ''));
            if ($tripNo !== '' && ctype_digit($tripNo)) {
                $tripExists = (int)(liveValue($db, 'SELECT COUNT(*) FROM bus_trips WHERE id = ?', [$tripNo]) ?? 0);
                if ($tripExists <= 0) {
                    throw new RuntimeException('Validation failed: generated ride references a missing bus trip.');
                }
            }
        }
        /* Boarding inside 05:00-20:00; drop-off strictly after boarding whenever
           the deployed schema stores drop-off times. */
        $boarding = (string)($row['boarding_time'] ?? '');
        if ($boarding !== '') {
            $time = substr($boarding, 11, 8);
            if ($time < '05:00:00' || $time > '20:00:00') {
                throw new RuntimeException("Validation failed: boarding time $boarding is outside the 05:00-20:00 service window.");
            }
            if (array_key_exists('dropoff_time', $row)) {
                $dropoff = (string)($row['dropoff_time'] ?? '');
                if ($dropoff === '' || strcmp($dropoff, $boarding) <= 0) {
                    throw new RuntimeException('Validation failed: drop-off time is missing or not after boarding.');
                }
            }
        }
    }
    $validDrivers = [];
    foreach ($ctx['drivers'] as $d) {
        $validDrivers[(string)$d['driver_id']] = true;
    }
    foreach ($samples as $row) {
        if (!empty($row['driver_id']) && !isset($validDrivers[(string)$row['driver_id']])) {
            throw new RuntimeException('Validation failed: unknown driver_id in generated row.');
        }
        $busRef = (string)($row['bus_id'] ?? ($row['bus_no'] ?? ($row['bus_number'] ?? '')));
        if ($busRef !== '' && !isset($ctx['busByNumber'][$busRef])) {
            throw new RuntimeException('Validation failed: unknown bus reference in generated row.');
        }
    }

    /* Per-date dispatch coverage: trip volume inside the band, every trip
       participant present in the date's coverage records, unique sequential
       queue positions, publication before the day's first departure, and
       coverage references resolving to real buses, drivers, and terminals.
       Failures are collected date-tagged and reported in one aggregated error
       so no failing date is silently hidden. Preserved pre-existing dispatch
       records (queue_source/assign_source 'existing') are genuine rows this
       run did not write; the summary reports them instead of failing the run. */
    if ($coverageDays) {
        $knownBuses = array_keys((array)$ctx['busByNumber']);
        $knownDrivers = [];
        foreach ((array)$ctx['drivers'] as $driverRow) {
            $knownDrivers[] = (string)($driverRow['driver_id'] ?? '');
        }
        $knownTerminals = (array)($ctx['coverageTerminals'] ?? []);
        $coverageErrors = [];
        foreach ($coverageDays as $covDay => $coverage) {
            $covDay = (string)$covDay;
            $isPastDay = $today === '' || $covDay < $today;
            foreach (dispatchCoverageTripCountErrors($isPastDay, (int)($coverage['trips'] ?? 0)) as $err) {
                $coverageErrors[] = $covDay . ': ' . $err;
            }
            if ((string)($coverage['queue_source'] ?? '') === 'generated') {
                foreach (dispatchCoverageParticipantErrors((array)$coverage['trip_buses'], [], (array)$coverage['lineup_buses'], []) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
                foreach (dispatchCoverageQueuePositionErrors((array)$coverage['positions']) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
                foreach (dispatchCoverageChronologyErrors(
                    isset($coverage['published_at']) ? (string)$coverage['published_at'] : null,
                    (string)($coverage['first_departure'] ?? '')
                ) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
                foreach (dispatchCoverageReferenceErrors((array)$coverage['lineup_buses'], $knownBuses, [], $knownDrivers, $knownTerminals) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
            }
            if ((string)($coverage['assign_source'] ?? '') === 'generated') {
                foreach (dispatchCoverageParticipantErrors([], (array)$coverage['trip_drivers'], [], (array)$coverage['assigned_drivers']) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
                foreach (dispatchCoverageReferenceErrors([], $knownBuses, (array)($coverage['assigned_terminals'] ?? []), $knownDrivers, $knownTerminals) as $err) {
                    $coverageErrors[] = $covDay . ': ' . $err;
                }
            }
        }
        if ($coverageErrors) {
            throw new RuntimeException(
                'Validation failed: dispatch coverage checks failed for ' . count($coverageErrors) . " case(s):\n- "
                . implode("\n- ", $coverageErrors)
            );
        }
    }
}

/* Actual revenue minus the same applicable expenses the financial reports use:
   generated fuel (from real refuel events) + per-driver fixed deductions
   (amortization, insurance, GPS, OPTR, prorated to the period) + any recorded
   other expenses for the period. Profit is never set to a target value. */
function buildProfitSummary(PDO $db, string $start, string $end, float $grossRevenue, float $fuelCost, int $activeDrivers, array $meta = []): array
{
    $days = max(1, (int)round((strtotime($end) - strtotime($start)) / 86400) + 1);
    $months = round($days / 30.4375, 4);

    /* Fixed deductions come from the shared helper the finance/admin reports
       use, so the amounts cannot drift from the existing model. */
    $perDriver = function_exists('dpm_fixed_costs')
        ? dpm_fixed_costs($months)
        : ['amortization' => 0.0, 'insurance' => 0.0, 'gps' => 0.0, 'optr' => 0.0, 'total' => 0.0, 'monthly_total' => 0.0];
    $driverCount = max(0, $activeDrivers);
    $fixed = [
        'amortization' => round((float)$perDriver['amortization'] * $driverCount, 2),
        'insurance' => round((float)$perDriver['insurance'] * $driverCount, 2),
        'gps' => round((float)$perDriver['gps'] * $driverCount, 2),
        'optr' => round((float)$perDriver['optr'] * $driverCount, 2),
    ];
    $fixed['total'] = round(array_sum($fixed), 2);

    /* Recorded non-fuel expenses for the period (fuel is counted from the
       generated refuel events, never from a hardcoded amount). */
    $other = 0.0;
    foreach (['expenses', 'financial_expenses'] as $table) {
        try {
            $cols = describeTable($db, $table);
            if (!$cols) {
                continue;
            }
            $dateCol = null;
            foreach (['created_at', 'expense_date', 'date'] as $candidate) {
                foreach ($cols as $c) {
                    if (strtolower((string)$c['Field']) === $candidate) {
                        $dateCol = $c['Field'];
                        break 2;
                    }
                }
            }
            if ($dateCol === null) {
                continue;
            }
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM `$table`
                 WHERE DATE(`$dateCol`) BETWEEN ? AND ?
                   AND LOWER(COALESCE(category, '')) <> 'fuel'"
            );
            $stmt->execute([$start, $end]);
            $other += (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Table or columns absent in this environment: no recorded expenses.
        }
    }
    $other = round($other, 2);
    $fuel = round($fuelCost, 2);
    $total = round($fuel + $fixed['total'] + $other, 2);

    return [
        'gross_revenue' => round($grossRevenue, 2),
        'fuel_expense' => $fuel,
        'fixed_costs' => $fixed + [
            'per_driver_monthly' => round((float)($perDriver['monthly_total'] ?? 0), 2),
            'month_factor' => $months,
            'drivers' => $driverCount,
        ],
        'other_expenses' => $other,
        'total_expenses' => $total,
        'net_profit' => round($grossRevenue - $total, 2),
        /* Volume/economics provenance so the run can be judged: the demand
           scenario and fleet target that shaped the volume, and the diesel
           price source. Profit stays collections minus the defined expenses
           and may be negative. */
        'demand_scenario' => (string)($meta['scenario'] ?? 'normal'),
        'daily_trips_target' => (int)($meta['daily_trips_target'] ?? 0),
        'diesel_price' => round((float)($meta['diesel_price'] ?? 0), 2),
        'diesel_price_source' => (string)($meta['diesel_price_source'] ?? ''),
        'fuel_cost_basis' => 'refuel transactions only; estimated per-trip consumption excluded',
    ];
}

/* Metrics exactly as the admin passenger dashboard computes them
   (admin/api/Passenger/getPassengerStats.php): the same status and
   discount_status predicates and the same "Boarded Today" query shape,
   adapted to whichever columns the deployment actually has. Reported in the
   run summary so the dashboard and the generated records can be reconciled
   right after a run. */
function passengerDashboardMetrics(PDO $db, string $start, string $end): array
{
    $pCols = liveColumns($db, 'passengers');
    $ptCols = liveColumns($db, 'passenger_trips');
    $notArchived = isset($pCols['status']) ? " AND (p.status IS NULL OR p.status != 'Archived')" : '';
    $metrics = [
        'pending_verification' => null,
        'verified' => null,
        'rejected' => null,
        'trip_records_range' => null,
        'trip_records_today' => null,
        'boarded_today' => null,
    ];

    if (isset($pCols['discount_status'])) {
        $predicates = [
            'pending_verification' => "LOWER(COALESCE(p.discount_status, 'pending')) IN ('pending', '')",
            'verified' => "LOWER(COALESCE(p.discount_status, '')) = 'verified'",
            'rejected' => "LOWER(COALESCE(p.discount_status, '')) = 'rejected'",
        ];
        foreach ($predicates as $key => $predicate) {
            try {
                $metrics[$key] = (int)$db->query(
                    "SELECT COUNT(*) FROM passengers p WHERE 1=1 $notArchived AND $predicate"
                )->fetchColumn();
            } catch (Throwable $e) {
                $metrics[$key] = null;
            }
        }
    }

    if (isset($ptCols['boarding_time'])) {
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM passenger_trips WHERE DATE(boarding_time) >= ? AND DATE(boarding_time) <= ?');
            $stmt->execute([$start, $end]);
            $metrics['trip_records_range'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $metrics['trip_records_range'] = null;
        }
        try {
            $metrics['trip_records_today'] =
                (int)$db->query('SELECT COUNT(*) FROM passenger_trips WHERE DATE(boarding_time) = CURDATE()')->fetchColumn();
        } catch (Throwable $e) {
            $metrics['trip_records_today'] = null;
        }
        try {
            $metrics['boarded_today'] = (int)$db->query(
                'SELECT COUNT(DISTINCT pt.passenger_id)
                 FROM passenger_trips pt
                 JOIN passengers p ON p.id = pt.passenger_id
                 WHERE DATE(pt.boarding_time) = CURDATE()' . $notArchived
            )->fetchColumn();
        } catch (Throwable $e) {
            $metrics['boarded_today'] = null;
        }
    }
    return $metrics;
}

function buildRunSummary(array $stats, string $label, string $start, string $end, int $rowsInserted, int $fuelLogCount, array $meta = []): array
{
    return [
        'period' => $label,
        'start' => $start,
        'end' => $end,
        'days' => $stats['days'],
        'operating_days' => $stats['days'],
        'demand_scenario' => (string)($meta['scenario'] ?? 'normal'),
        'daily_trips_target' => (int)($meta['daily_trips_target'] ?? 0),
        'diesel_price' => round((float)($meta['diesel_price'] ?? 0), 2),
        'diesel_price_source' => (string)($meta['diesel_price_source'] ?? ''),
        'unique_passenger_accounts' => ($stats['accounts_created'] ?? 0) + ($stats['accounts_reused'] ?? 0),
        'audit_events' => (int)($stats['audit_events'] ?? 0),
        'dashboard' => $stats['dashboard'] ?? [],
        'accounts_by_type' => $stats['accounts_by_type'] ?? [],
        'student_passenger_accounts' => (int)($stats['accounts_by_type']['Student'] ?? 0),
        'regular_passenger_accounts' => (int)($stats['accounts_by_type']['Regular'] ?? 0),
        'rides_by_type' => $stats['type_rides'] ?? [],
        'skipped_records' => (int)($stats['accounts_skipped'] ?? 0),
        'skipped_reasons' => $stats['skipped_reasons'] ?? [],
        'trips' => $stats['trips_scheduled'],
        'trips_delayed' => $stats['delayed'],
        'trips_cancelled' => $stats['cancelled'],
        'today_future_trips_skipped' => (int)($stats['today_future_trips_skipped'] ?? 0),
        'buses_without_driver' => $stats['no_driver_buses'] ?? 0,
        'driver_replacements' => $stats['driver_replacements'] ?? 0,
        'unavailable_buses' => $stats['unavailable_buses'],
        'out_of_service_days' => $stats['out_of_service_days'],
        'unusual_durations' => $stats['unusual_durations'],
        'passenger_records' => $stats['passenger_rows'],
        'avg_passengers_per_trip' => $stats['trips_scheduled'] > 0
            ? round($stats['passenger_rows'] / $stats['trips_scheduled'], 1)
            : 0,
        'cargo_records' => $stats['cargo_rows'],
        'passenger_trip_rows' => $stats['passenger_trip_rows'] ?? ($stats['passenger_rows'] + $stats['cargo_rows']),
        'bus_trips' => $stats['bus_trips'] ?? 0,
        'passenger_accounts' => ($stats['accounts_created'] ?? 0) + ($stats['accounts_reused'] ?? 0),
        'passenger_accounts_created' => $stats['accounts_created'] ?? 0,
        'passenger_accounts_reused' => $stats['accounts_reused'] ?? 0,
        'passenger_accounts_reissued' => $stats['accounts_reissued'] ?? 0,
        'demo_rides_replaced' => $stats['demo_rides_replaced'] ?? 0,
        'demo_bus_trips_replaced' => $stats['demo_bus_trips_replaced'] ?? 0,
        'remittance_settled' => $stats['remittance_settled'] ?? 0,
        'remittance_logs' => $stats['remittance_logs'] ?? 0,
        'remittance_logs_backfilled' => $stats['remittance_logs_backfilled'] ?? 0,
        'discrepancy_cases' => $stats['discrepancy_cases'] ?? 0,
        'discrepancy_resolved' => $stats['discrepancy_resolved'] ?? 0,
        'gross_revenue' => round($stats['revenue'], 2),
        'avg_fare' => ($stats['passenger_rows'] + $stats['cargo_rows']) > 0
            ? round($stats['revenue'] / ($stats['passenger_rows'] + $stats['cargo_rows']), 2)
            : 0,
        'unique_passengers' => count($stats['passengers_unique']),
        'paid' => $stats['paid'],
        'pending' => $stats['pending'],
        'rejected' => $stats['rejected'],
        'remittance' => [
            'Pending' => $stats['remit_pending'],
            'Remitted' => $stats['remit_remitted'],
            'Discrepancy' => $stats['remit_discrepancy'],
        ],
        'payment_methods' => $stats['methods'],
        'inspections' => [
            'minor' => $stats['inspections_minor'],
            'significant' => $stats['inspections_significant'],
        ],
        'fuel_logs' => $fuelLogCount,
        'odo_records' => $fuelLogCount,
        'refuel_events' => $stats['refuel_events'],
        'fuel_refuels' => $stats['refuel_events'],
        'fleet_size' => $stats['fleet_size'],
        /* Fuel coverage report: which buses actually operated, which carry
           fuel records and refuels, and which operating buses are missing
           fuel data (should always be empty). */
        'fuel_coverage' => [
            'operating_buses' => count($stats['buses_used'] ?? []),
            'buses_with_fuel_records' => count($stats['fuel_buses'] ?? []),
            'buses_with_refuels' => count($stats['refuel_buses'] ?? []),
            'operating_missing_fuel' => array_values(array_keys(array_diff_key(
                (array)($stats['buses_used'] ?? []),
                (array)($stats['fuel_buses'] ?? [])
            ))),
            /* Fleet buses that never operated in the period (id plus status),
               so a bus with no fuel history is explainable. */
            'never_operated' => array_values((array)($meta['never_operated'] ?? [])),
        ],
        'avg_trips_per_bus' => ($stats['fleet_size'] > 0 && $stats['days'] > 0)
            ? round($stats['trips_scheduled'] / ($stats['fleet_size'] * $stats['days']), 2)
            : 0,
        'fuel_full' => $stats['fuel_full'],
        'fuel_partials' => $stats['fuel_partials'],
        'fuel_liters' => round($stats['fuel_liters'], 2),
        'fuel_cost' => round($stats['fuel_cost'], 2),
        'fuel_anomalies' => $stats['fuel_anomalies'],
        'avg_kpl' => $stats['kpl_dist'] > 0 ? round($stats['kpl_sum'] / $stats['kpl_dist'], 2) : 0,
        'buses_used' => count($stats['buses_used']),
        'drivers_used' => count($stats['drivers_used']),
        'trip_records' => $rowsInserted,
        /* Daily dispatch coverage: trip participants versus the day's dispatch
           lineup and terminal assignments, reported separately from the trip
           total and never forced to match. */
        'daily_coverage' => (array)($meta['daily_coverage'] ?? []),
        'coverage_totals' => (array)($meta['coverage_totals'] ?? []),
        'driver_checkins' => (int)($stats['checkin_events'] ?? 0),
        'demo_dispatch_rows_replaced' => (int)($stats['demo_dispatch_rows_replaced'] ?? 0),
    ];
}

/* Terminal ids that historical driver assignments may reference: every
   configured terminal, or only active ones when the status column exists. */
function loadCoverageTerminals(PDO $db): array
{
    try {
        $columns = [];
        foreach (describeTable($db, 'terminals') as $col) {
            $columns[strtolower((string)($col['Field'] ?? ''))] = true;
        }
        $select = 'id';
        if (isset($columns['status'])) {
            $select .= ', status';
        }
        $rows = $db->query('SELECT ' . $select . ' FROM terminals')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $ids = [];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if ($status !== '' && $status !== 'active') {
            continue;
        }
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    $ids = array_keys($ids);
    sort($ids, SORT_NUMERIC);
    return $ids;
}

/* Writes the day's coverage records for a generated past operating date from
   existing eligible records only: the published dispatch lineup (all trip
   buses plus rotation fill, capped at the bus target) and the driver terminal
   assignments (all trip drivers plus rotation fill, capped at the driver
   target). A date that already has dispatch rows keeps them - genuine or
   earlier publications are never overwritten; today and future dates are left
   to the interactive admin queue and the live simulator. */
function writeDayCoverage(
    PDO $db,
    array $ctx,
    array $opts,
    string $day,
    array $trips,
    string $today,
    array &$stats,
    array &$coverageDays
): void {
    $tables = $ctx['coverageTables'] ?? ['queue' => false, 'terminals' => false, 'missing' => []];
    $windowDay = !empty($opts['fleet_scope']) && fleetScopeDayActive($day);

    $tripBuses = [];
    $tripDrivers = [];
    $firstSec = null;
    foreach ($trips as $tr) {
        $bid = trim((string)($tr['busNumber'] ?? ''));
        if ($bid !== '') {
            $tripBuses[$bid] = true;
        }
        $did = trim((string)($tr['drv']['driver_id'] ?? ''));
        if ($did !== '') {
            $tripDrivers[$did] = true;
        }
        $sec = isset($tr['sec']) ? (int)$tr['sec'] : null;
        if ($sec !== null && ($firstSec === null || $sec < $firstSec)) {
            $firstSec = $sec;
        }
    }
    $tripBusIds = array_keys($tripBuses);
    $tripDriverIds = array_keys($tripDrivers);

    $entry = [
        'day' => $day,
        'window_day' => $windowDay,
        'trips' => count($trips),
        'trip_buses' => $tripBusIds,
        'trip_drivers' => $tripDriverIds,
        'first_departure' => $firstSec !== null ? ($day . ' ' . fmtTime($firstSec)) : '',
        'lineup_buses' => [],
        'positions' => [],
        'queue_source' => !empty($tables['queue']) ? 'skipped' : 'unavailable',
        'published_at' => null,
        'assigned_drivers' => [],
        'assigned_terminals' => [],
        'assign_source' => !empty($tables['terminals']) ? 'skipped' : 'unavailable',
    ];

    if ($day >= $today) {
        /* The current/future date's dispatch records belong to the interactive
           admin queue and the live simulator. */
        $entry['queue_source'] = 'interactive';
        $entry['assign_source'] = 'interactive';
        $coverageDays[$day] = $entry;
        return;
    }

    /* One dispatcher check-in per driver per operating day, timed shortly
       before that driver's first departure; driver attendance reporting reads
       these accountability records. Collected here and emitted with the
       period's other activity events after the batch is flushed. */
    $driverFirst = [];
    foreach ($trips as $tr) {
        $did = trim((string)($tr['drv']['driver_id'] ?? ''));
        $sec = isset($tr['sec']) ? (int)$tr['sec'] : null;
        if ($did === '' || $sec === null) {
            continue;
        }
        if (!isset($driverFirst[$did]) || $sec < $driverFirst[$did]['sec']) {
            $driverFirst[$did] = [
                'sec' => $sec,
                'bus' => trim((string)($tr['busNumber'] ?? '')),
                'trip_id' => trim((string)($tr['busTripId'] ?? ($tr['id'] ?? ''))),
            ];
        }
    }
    $entry['checkins'] = [];
    foreach ($driverFirst as $did => $info) {
        $lead = 15 + (int)floor(stableUnit('checkin-lead-' . $day . '-' . $did) * 16);   // 15-30 min before
        $checkinSec = max(4 * 3600 + 30 * 60, $info['sec'] - $lead * 60);                // never before 04:30
        $entry['checkins'][$did] = [
            'bus' => $info['bus'],
            'trip_id' => $info['trip_id'],
            'at' => $day . ' ' . fmtTime($checkinSec),
        ];
    }

    /* -------- Bus dispatch lineup -------- */
    if (!empty($tables['queue'])) {
        try {
            $stmt = $db->prepare(
                'SELECT bus_id, queue_position, published_at FROM bus_dispatch_queue WHERE operating_date = ? ORDER BY queue_position ASC'
            );
            $stmt->execute([$day]);
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $existing = [];
        }
        if ($existing) {
            foreach ($existing as $row) {
                $bid = trim((string)($row['bus_id'] ?? ''));
                if ($bid === '') {
                    continue;
                }
                $entry['lineup_buses'][] = $bid;
                $entry['positions'][] = (int)($row['queue_position'] ?? 0);
                if ($entry['published_at'] === null && !empty($row['published_at'])) {
                    $entry['published_at'] = (string)$row['published_at'];
                }
            }
            $entry['queue_source'] = 'existing';
        } else {
            $eligibleBuses = dispatchCoverageEligibleBuses((array)($ctx['buses'] ?? []), $windowDay);
            $target = dispatchCoverageTarget(count($eligibleBuses));
            $lineup = dispatchCoverageSelection($eligibleBuses, $tripBusIds, $day, $target);
            if ($lineup) {
                $publishedAt = dispatchCoveragePublishedAt($day);
                $actor = historyAuditActor($ctx, 'dispatcher', 'publish-' . $day);
                $publishedBy = trim((string)($actor['username'] ?? ''));
                if ($publishedBy === '') {
                    $publishedBy = 'Data Simulator';
                }
                $terminalIds = $ctx['coverageTerminals'] ?? [];
                $insert = $db->prepare(
                    'INSERT INTO bus_dispatch_queue (operating_date, bus_id, queue_position, published_at, published_by, terminal_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($lineup as $index => $bid) {
                    $busRow = (array)($ctx['busByNumber'][$bid] ?? []);
                    $row = dispatchCoverageQueueRow(
                        $day,
                        $bid,
                        $index + 1,
                        $publishedBy,
                        dispatchCoverageBusTerminalId($busRow, $terminalIds)
                    );
                    $insert->execute([
                        $row['operating_date'],
                        $row['bus_id'],
                        $row['queue_position'],
                        $row['published_at'],
                        $row['published_by'],
                        $row['terminal_id'],
                        $row['created_at'],
                        $row['updated_at'],
                    ]);
                    $entry['positions'][] = $index + 1;
                    $stats['dispatch_queue_rows']++;
                }
                $entry['lineup_buses'] = $lineup;
                $entry['published_at'] = $publishedAt;
                $entry['queue_source'] = 'generated';
            }
        }
    }

    /* -------- Driver terminal assignments -------- */
    if (!empty($tables['terminals'])) {
        try {
            $stmt = $db->prepare(
                'SELECT driver_id, terminal_id FROM driver_terminal_assignments WHERE operating_date = ? ORDER BY driver_id ASC'
            );
            $stmt->execute([$day]);
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $existing = [];
        }
        $terminalIds = $ctx['coverageTerminals'] ?? [];
        if ($existing) {
            foreach ($existing as $row) {
                $did = trim((string)($row['driver_id'] ?? ''));
                if ($did === '') {
                    continue;
                }
                $entry['assigned_drivers'][] = $did;
                $entry['assigned_terminals'][$did] = (int)($row['terminal_id'] ?? 0);
            }
            $entry['assign_source'] = 'existing';
        } elseif (!$terminalIds) {
            $entry['assign_source'] = 'no_terminals';
        } else {
            $eligibleDrivers = dispatchCoverageEligibleDrivers((array)($ctx['drivers'] ?? []));
            $target = dispatchCoverageTarget(count($eligibleDrivers), DISPATCH_COVERAGE_DRIVER_TARGET);
            if ($windowDay) {
                /* Window days keep their focused operation: only the drivers
                   who actually run the window day's trips are assigned. */
                $target = min($target, count($tripDriverIds));
            }
            $selected = dispatchCoverageSelection($eligibleDrivers, $tripDriverIds, $day, $target);
            if ($selected) {
                $insert = $db->prepare(
                    'INSERT INTO driver_terminal_assignments (operating_date, driver_id, terminal_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
                );
                foreach ($selected as $did) {
                    $terminalId = dispatchCoverageDriverTerminalId($terminalIds, $day, $did);
                    if ($terminalId === null) {
                        continue;
                    }
                    $row = dispatchCoverageTerminalAssignmentRow($day, $did, $terminalId);
                    $insert->execute([
                        $row['operating_date'],
                        $row['driver_id'],
                        $row['terminal_id'],
                        $row['created_at'],
                        $row['updated_at'],
                    ]);
                    $entry['assigned_drivers'][] = $did;
                    $entry['assigned_terminals'][$did] = $terminalId;
                    $stats['terminal_assignment_rows']++;
                }
                $entry['assign_source'] = 'generated';
            }
        }
    }

    $coverageDays[$day] = $entry;
}

/* Emits one Driver Checked In accountability record per driver per generated
   past operating date, so driver attendance reporting (which reads these
   records from audit_trail) sees the generated history. Runs inside the
   history transaction after the batch is flushed; a rollback removes both the
   coverage rows and their events, and demoCleanupPeriod replaces them with
   the period on re-run. Today's check-ins stay with the interactive
   dispatcher and live simulator. */
function emitDriverCheckinEvents(PDO $db, array $ctx, array $coverageDays): int
{
    $driverNames = [];
    foreach ((array)($ctx['drivers'] ?? []) as $driverRow) {
        $did = trim((string)($driverRow['driver_id'] ?? ''));
        if ($did !== '') {
            $driverNames[$did] = trim((string)($driverRow['full_name'] ?? '')) !== ''
                ? (string)$driverRow['full_name']
                : $did;
        }
    }

    $emitted = 0;
    foreach ($coverageDays as $day => $coverage) {
        $day = (string)$day;
        $coverage = (array)$coverage;
        if ((string)($coverage['queue_source'] ?? '') === 'interactive') {
            continue;   // the current/future date belongs to the live workflow
        }
        foreach ((array)($coverage['checkins'] ?? []) as $driverId => $checkin) {
            $driverId = trim((string)$driverId);
            if ($driverId === '') {
                continue;
            }
            $checkin = (array)$checkin;
            $busId = trim((string)($checkin['bus'] ?? ''));
            $tripId = trim((string)($checkin['trip_id'] ?? ''));
            $at = trim((string)($checkin['at'] ?? ''));
            if ($at === '') {
                $at = $day . ' 05:00:00';
            }
            $actor = historyAuditActor($ctx, 'dispatcher', 'checkin-' . $day . '-' . $driverId);
            $inserted = historyAuditEvent(
                $db,
                $ctx,
                'Dispatcher',
                'Driver Checked In',
                'Driver ' . ($driverNames[$driverId] ?? $driverId) . ' checked in for bus ' . $busId . ' by dispatcher.',
                $tripId !== '' ? $tripId : 'checkin-' . $day . '-' . $driverId,
                [
                    'trip_id' => $tripId,
                    'bus_id' => $busId,
                    'driver_id' => $driverId,
                    'driver_name' => $driverNames[$driverId] ?? $driverId,
                    'trip_date' => $day,
                    'dispatcher_id' => $actor['id'] ?? null,
                    'checked_in_by' => 'dispatcher',
                    'checked_in_by_name' => trim((string)($actor['username'] ?? '')) !== ''
                        ? (string)$actor['username']
                        : 'Dispatcher',
                    'checked_in_at' => $at,
                ],
                $at,
                'dispatcher',
                'checkin-' . $day . '-' . $driverId
            );
            if ($inserted > 0) {
                $emitted++;
            }
        }
    }
    return $emitted;
}

/* =========================================================
   ORCHESTRATOR
   ========================================================= */
/* Ensures the dispatch coverage tables exist, using the same idempotent
   schema the admin dispatch queue service maintains, then reports whether
   they can carry historical coverage rows. DDL runs before any transaction,
   matching the fuel-table pattern; an unavailable table degrades to a
   reported coverage limitation instead of failing the run. */
function ensureDispatchCoverageSchema(PDO $db): array
{
    try {
        (new \Admin\Services\Dispatch\DispatchQueueService($db))->ensureSchema();
    } catch (Throwable $e) {
        // Fall through to detection; missing tables report as unusable.
    }

    $tableColumns = static function (PDO $db, string $table): ?array {
        try {
            $columns = [];
            foreach (describeTable($db, $table) as $col) {
                $field = (string)($col['Field'] ?? '');
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
            return $columns;
        } catch (Throwable $e) {
            return null;
        }
    };

    return dispatchCoverageSchemaUsable(
        $tableColumns($db, 'bus_dispatch_queue'),
        $tableColumns($db, 'driver_terminal_assignments')
    );
}

function generateHistory(array $input): array
{
    $db = Database::get();
    applyDefaultFleetScope($db, $input);
    set_time_limit(1800);
    ini_set('memory_limit', '512M');

    $opts = resolveOptions($input);
    /* Window fleet/volume rules and daily-routine passengers apply only to
       this main generation path; the archive generator never sets the flag. */
    $opts['fleet_scope'] = true;
    if ($opts['seed'] !== null) {
        mt_srand($opts['seed']);
    }

    /* Remittance settlement cutoff: always the runtime current date, read from
       the database so the generated records roll with the calendar. Rows dated
       before today settle (Remitted); today and later stay Pending. Never a
       hardcoded date. */
    $remitCutoff = date('Y-m-d');
    try {
        $dbDate = $db->query('SELECT CURDATE()')->fetchColumn();
        if (is_string($dbDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dbDate)) {
            $remitCutoff = $dbDate;
        }
    } catch (Throwable $e) {
        // Fall back to the PHP server date (Asia/Manila) when CURDATE() is unavailable.
    }

    /* Actor name stamped onto settled rows. Falls back to a stable label when no
       logged-in user is available (e.g. unauthenticated dev runs). */
    $actor = $GLOBALS['authedUser'] ?? null;
    $remitBy = (is_array($actor) && !empty($actor['name'])) ? (string)$actor['name'] : 'Finance Officer';

    [$start, $end, $label] = resolvePeriod($input);
    $days = dateRange($start, $end);

    $ctx = buildSimulationContext($db, $start, $opts);
    /* Dispatch coverage tables: created idempotently before the transaction
       (DDL implicitly commits), then checked for usability. */
    $ctx['coverageTables'] = ensureDispatchCoverageSchema($db);
    $ctx['coverageTerminals'] = loadCoverageTerminals($db);
    $ptCols = $ctx['ptCols'];
    $fuelColNames = $ctx['fuelColNames'];
    $refuelCols = $ctx['refuelCols'];
    $dailyTripTarget = periodDailyTripTarget($days, $ctx, $opts);
    /* Passenger volume: the configured pool target wins; otherwise the pool is
       sized from expected weekly demand for the fleet and date range. */
    $poolSize = (int)($opts['passenger_pool_target'] ?? 0) > 0
        ? (int)$opts['passenger_pool_target']
        : planPoolTarget($dailyTripTarget, count($ctx['busStates']));
    $passengers = buildPassengerPool($ctx['routeNames'], $ctx['routes'], $poolSize);
    /* Demo accounts are resolved by a deterministic identity, so a re-run
       reuses the same passengers instead of inserting new ones. */
    if (($ctx['demoMarker']['mode'] ?? 'none') === 'email') {
        $ctx['demoEmails'] = buildDemoEmailMap($passengers);
    }
    $accounts = [
        'cache' => [],
        'preload' => buildDemoAccountCache($db, $ctx['demoMarker'], $ctx['demoEmails'] ?? []),
        'seq' => [],
        'ids' => [],
        'created' => 0,
        'reused' => 0,
        'skipped' => 0,
        'skipped_reasons' => [],
    ];

    $stats = [
        'days' => 0,
        'trips_scheduled' => 0,
        'cancelled' => 0,
        'delayed' => 0,
        'unavailable_buses' => 0,
        'out_of_service_days' => 0,
        'unusual_durations' => 0,
        'passenger_rows' => 0,
        'cargo_rows' => 0,
        'passenger_trip_rows' => 0,
        'type_rides' => [],
        'type_passengers' => [],
        'accounts_by_type' => [],
        'skipped_reasons' => [],
        'bus_trips' => 0,
        'no_driver_buses' => 0,
        'driver_replacements' => 0,
        'accounts_created' => 0,
        'accounts_reused' => 0,
        'accounts_reissued' => 0,
        'accounts_skipped' => 0,
        'demo_rides_replaced' => 0,
        'demo_bus_trips_replaced' => 0,
        'remittance_settled' => 0,
        'remittance_logs' => 0,
        'remittance_logs_backfilled' => 0,
        'discrepancy_cases' => 0,
        'discrepancy_resolved' => 0,
        'pth_rows_written' => 0,
        'revenue' => 0.0,
        'passengers_unique' => [],
        'paid' => 0,
        'pending' => 0,
        'rejected' => 0,
        'remit_pending' => 0,
        'remit_remitted' => 0,
        'remit_discrepancy' => 0,
        'discrepancies' => 0,
        'methods' => [],
        'inspections_minor' => 0,
        'inspections_significant' => 0,
        'fuel_logs' => 0,
        'refuel_events' => 0,
        'fleet_size' => count($ctx['busStates']),
        'fuel_full' => 0,
        'fuel_partials' => 0,
        'fuel_liters' => 0.0,
        'fuel_cost' => 0.0,
        'fuel_anomalies' => 0,
        'kpl_sum' => 0.0,
        'kpl_dist' => 0.0,
        'buses_used' => [],
        'drivers_used' => [],
        'fuel_buses' => [],
        'driver_absences' => 0,
        'dispatch_queue_rows' => 0,
        'terminal_assignment_rows' => 0,
        'checkin_events' => 0,
    ];
    $ledger = [
        'rides' => [],
        'trip_space' => [],
        'trip_pax' => [],
        'trip_bounds' => [],
        'trip_ids' => [],
        'trip_id_count' => 0,
        'busy' => ['driver' => [], 'bus' => []],
        'durations' => [],
        'fuel_negative' => [],
        'fuel_chain' => [],
        'trip_fuel' => [],
        'refuels' => [],
        'routine' => [],
        'routine_coverage' => [],
        'ride_keys' => [],
        'ride_dupes' => [],
        'plan' => [],
        'plan_caps' => [],
        'week_rides' => [],
    ];

    /* Activity-audit collectors: every business action the run produces is
       recorded with the record id it inserted and emitted as a realistic
       audit event after the batch is flushed (see the emission block below). */
    $auditQueueDays = [];
    $auditTripEvents = [];
    $auditBoardingSamples = [];
    /* Per-date coverage information (lineup, terminal assignments, sources,
       participants) collected while the days are generated; the run summary
       and validation read it. */
    $coverageDays = [];

    $samples = [];
    $sampleCap = 30;
    $ptBuffer = [];
    $fuelBuffer = [];
    $refuelBuffer = [];
    $tripBoardCursor = [];
    $tripDropCursor = [];
    $busTripSeq = [];
    $usedCargoEmails = [];
    $remittanceDay = [];
    $rowsInserted = 0;
    $fuelLogCount = 0;
    $pstate = [];
    $nextTrip = (int)$ctx['nextTrip'];
    $currentDay = null;

    /* The demo-account registry lives in audit_trail; make sure it exists
       before cleanup queries it (idempotent, best-effort). */
    try {
        $auditInit = new AuditTrailModel();
        $auditInit->initTables();
    } catch (Throwable $e) {
        // Registry is best-effort; pattern markers still cover other modes.
    }

    $db->beginTransaction();
    try {
        /* The archive table is read-only for this generator: capture its row
           count so the run can prove it wrote nothing there. */
        $pthBefore = (int)liveValue($db, 'SELECT COUNT(*) FROM passenger_trip_history');

        /* Repeatable runs: replace only this tool's own demo output inside the
           target period (rides linked to demo accounts plus the operational
           bus_trips behind them), leaving genuine rows untouched. */
        if (($ctx['demoMarker']['mode'] ?? 'none') !== 'none') {
            $previous = demoCleanupPeriod($db, $start, $end, $ctx['demoMarker']);
            $stats['demo_rides_replaced'] = (int)($previous['rides'] ?? 0);
            $stats['demo_bus_trips_replaced'] = (int)($previous['bus_trips'] ?? 0);
            $stats['demo_dispatch_rows_replaced'] = (int)($previous['dispatch_rows'] ?? 0);
            /* Odometer and fuel balance are re-seeded once the replaced rows
               are gone, so regenerated days continue from the last surviving
               reading instead of reusing odometer ranges or topping up fuel
               another surviving row already accounts for. */
            reseedBusFuelState($db, $ctx, $end);
        }

        /* Settle anything this tool generated earlier for operating dates
           before today (Asia/Manila); today's and future rows stay pending. */
        $settled = settleDemoRemittances($db, $ctx['demoMarker'], $remitBy, $remitCutoff);
        $stats['remittance_settled'] = (int)($settled['passenger_trips'] ?? 0)
            + (int)($settled['passenger_trip_history'] ?? 0)
            + (int)($settled['remittance_logs'] ?? 0);

        /* Trip numbering restarts from the surviving maximum so regenerating
           the same period reuses the same TRIP- ids (and therefore the same
           deterministic cargo identities) instead of drifting upward. */
        $maxPtAfterCleanup = 0;
        try {
            $maxPtAfterCleanup = (int)$db->query(
                "SELECT MAX(CAST(SUBSTRING_INDEX(trip_no,'-',-1) AS UNSIGNED)) FROM passenger_trips WHERE trip_no LIKE 'TRIP-%'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $maxPtAfterCleanup = 0;
        }
        $nextTrip = max((int)($ctx['nextTripFloor'] ?? 3000), $maxPtAfterCleanup + 1);

        foreach ($days as $day) {
            $currentDay = $day;
            $stats['days']++;
            $dayType = dayTypeOf($day);

            /* Driver attendance: sparse, stable per-driver probability. */
            $present = [];
            foreach ($ctx['driversWithBuses'] as $drv) {
                $did = (string)$drv['driver_id'];
                if (mt_rand(1, 1000) / 1000.0 <= $ctx['driverAbsence'][$did]) {
                    $stats['driver_absences']++;
                    continue;
                }
                $present[] = $drv;
            }
            if (!$present) {
                $present = $ctx['driversWithBuses'];
            }

            /* Buses explicitly marked unavailable in the fleet list are out for
               the whole day; otherwise only a rare breakdown keeps a bus out. */
            $unavailable = $ctx['busStatusUnavailable'];
            $stats['out_of_service_days'] += count($unavailable);
            foreach ($ctx['buses'] as $bus) {
                $bid = (string)($bus['bus_id'] ?? '');
                if ($bid === '' || isset($unavailable[$bid])) {
                    continue;
                }
                if (mt_rand(1, 1000) <= 20) {   // rare breakdown/unavailability
                    $unavailable[$bid] = true;
                    $stats['unavailable_buses']++;
                }
            }
            /* Never remove the whole fleet; keep at least two buses available. */
            $minAvailable = min(2, count($ctx['busStates']));
            while (count($ctx['busStates']) - count($unavailable) < $minAvailable && $unavailable) {
                unset($unavailable[array_key_first($unavailable)]);
            }

            $trips = generateDailySchedule($day, $ctx, $opts, $present, $unavailable, $nextTrip, $stats, $ledger);
            if (!$trips) {
                continue;
            }

            /* Every day runs through the weekly-plan engine; window days
               arrive with reduced bounds and the fleet allow-list already
               applied to the day's schedule. */
            $assignments = generateTripPassengers($trips, $passengers, $pstate, $ctx, $day, $ledger);
            $byTrip = [];
            foreach ($assignments as $a) {
                $byTrip[$a['trip']][] = $a;
            }

            $otherCount = 0;
            foreach ($trips as $ti => $tr) {
                $tripHasDiscrepancy = false;

                /* Operational trip row first, so every generated ride (and its
                   fuel log) references a real bus_trips record. */
                $busTripId = null;
                $tripPaxCount = 0;
                $tripRevenueSum = 0.0;
                if ($ctx['busTripsUsable']) {
                    $seqKey = $day . '|' . (string)$tr['busNumber'];
                    $busTripSeq[$seqKey] = ($busTripSeq[$seqKey] ?? 0) + 1;
                    $busTripId = insertGeneratedBusTrip($db, $ctx, $tr, $day, (int)$busTripSeq[$seqKey], $remitCutoff);
                    if ($busTripId === null) {
                        throw new RuntimeException('Validation failed: bus trip insert returned no identifier.');
                    }
                    $tr['busTripId'] = $busTripId;
                    $trips[$ti]['busTripId'] = $busTripId;   // fuel chain reads the original list
                    $stats['bus_trips']++;

                    /* Activity-audit collection for this inserted trip. */
                    $auditQueueDays[$day] = (int)($auditQueueDays[$day] ?? 0) + 1;
                    $auditTripEvents[] = [
                        'id' => (string)$busTripId,
                        'ref' => (string)$tr['id'],
                        'day' => $day,
                        'bus' => (string)$tr['busNumber'],
                        'driver' => (string)$tr['drv']['driver_id'],
                        'route' => (string)$tr['routeName'],
                        'departure' => (string)$tr['departure'],
                        'arrival' => (string)$tr['arrival'],
                    ];
                }

                /* Passenger rows for this trip (written to passenger_trips). */
                foreach ($byTrip[$ti] ?? [] as $a) {
                    $d = $a['demand'];
                    $pair = $a['pair'];
                    $boarding = $pair['boarding'];
                    $destination = $pair['destination'];
                    if ($boarding === null || $destination === null) {
                        continue;
                    }
                    $boardingLabel = (string)$boarding['destination'];
                    $destinationLabel = (string)$destination['destination'];

                    $type = $d['ptype'];
                    /* Fare comes straight from fare_pricing for this route,
                       segment, and passenger type. Discount columns apply only
                       to discount profiles whose verification is approved;
                       pending/rejected/expired profiles pay the regular fare. */
                    $fareColumn = fareColumnForType($type, $ctx['fareColumns']);
                    if ($fareColumn !== null && $fareColumn !== $ctx['fareColumns']['regular']) {
                        $discountOk = ($d['profile']['discountStatus'] ?? null) === null
                            || ($d['profile']['discountStatus'] ?? '') === 'verified';
                        if (!$discountOk) {
                            $fareColumn = $ctx['fareColumns']['regular'];
                        }
                    }
                    $fare = resolveSegmentFare(
                        $boarding,
                        $destination,
                        $fareColumn,
                        $ctx['fareColumns']['regular'],
                        (float)($ctx['routeMinFare'][(string)$tr['routeName']] ?? 0.0)
                    );
                    $stats['revenue'] += $fare;

                    $rideKey = (string)$tr['id'] . '|' . $d['name'] . '|' . $boardingLabel . '|' . $destinationLabel;
                    $method = stablePickWeighted($ctx['paymentMix'] ?? HIST_PAYMENT_MIX, 'ride-method-' . $rideKey);
                    $pay = simulatePayment($day, $remitCutoff, $fare, $opts, $remitBy, $stats, (string)$method);
                    if ($pay['remittance_status'] === 'Discrepancy') {
                        $tripHasDiscrepancy = true;
                    }

                    $account = ensureDemoAccount(
                        $db,
                        $ctx,
                        'p:' . $d['name'],
                        demoProfileLookup($d['profile'], $ctx),
                        demoProfileAccountData($d['profile'], $ctx, $start),
                        $accounts
                    );
                    if (empty($account['id'])) {
                        continue;
                    }

                    [$boardingAt, $dropoffAt] = rideBoardingTimes(
                        $day,
                        $ctx['routes'][(string)$tr['routeName']] ?? [],
                        $tr,
                        $boarding,
                        $destination,
                        'ride-time-' . $rideKey
                    );
                    [$boardingAt, $dropoffAt] = reserveRideTimes(
                        (string)$tr['id'],
                        $boardingAt,
                        $dropoffAt,
                        $tripBoardCursor,
                        $tripDropCursor
                    );
                    $amounts = histPaymentAmounts((string)$pay['payment_method'], $fare, $pay);

                    $rowData = [
                        'passenger_id' => (string)$account['id'],
                        'passenger_type' => $type,
                        'bus_id' => (string)$tr['busNumber'],
                        'driver_id' => (string)$tr['drv']['driver_id'],
                        'trip_no' => $busTripId !== null ? (string)$busTripId : ptTripNoValue((string)$tr['id'], $ctx),
                        'boarding' => $boardingLabel,
                        'destination' => $destinationLabel,
                        'fare' => $fare,
                        'payment_method' => $pay['payment_method'],
                        'payment_status' => $pay['payment_status'],
                        'remittance_status' => $pay['remittance_status'],
                        'remitted_by' => $pay['remitted_by'],
                        'remitted_at' => $pay['remitted_at'],
                        'expected_cash' => $pay['expected_cash'],
                        'actual_cash' => $pay['actual_cash'],
                        'discrepancy_amount' => $pay['discrepancy_amount'],
                        'cash_amount' => $amounts['cash'],
                        'gcash_amount' => $amounts['gcash'],
                        'verified_cash_amount' => $amounts['verified'],
                        'boarding_time' => $boardingAt,
                        'dropoff_time' => $dropoffAt,
                    ];
                    $values = passengerTripRowValues($ptCols, $rowData, $ctx['ptRequiredDefaults'] ?? []);
                    $ptBuffer[] = $values;
                    $rowsInserted++;
                    $tripPaxCount++;
                    $tripRevenueSum += $fare;
                    $stats['passenger_rows']++;
                    $stats['passenger_trip_rows']++;
                    $stats['passengers_unique'][$d['name']] = true;
                    $stats['type_rides'][(string)$type] = ($stats['type_rides'][(string)$type] ?? 0) + 1;
                    $stats['type_passengers'][(string)$type][$d['name']] = true;
                    $accounts['ids'][(string)$account['id']] = true;

                    /* Driver-day remittance accumulation. */
                    $remitDriver = (string)$tr['drv']['driver_id'];
                    if (!isset($remittanceDay[$day][$remitDriver])) {
                        $remittanceDay[$day][$remitDriver] = ['collection' => 0.0, 'expected' => 0.0, 'passengers' => 0, 'bus' => (string)$tr['busNumber']];
                    }
                    $remittanceDay[$day][$remitDriver]['collection'] += (float)$fare;
                    if (strtolower((string)$pay['payment_method']) === 'cash') {
                        $remittanceDay[$day][$remitDriver]['expected'] += (float)$fare;
                    } elseif (strtolower((string)$pay['payment_method']) === 'hybrid') {
                        $remittanceDay[$day][$remitDriver]['expected'] += round((float)$fare * 0.6, 2);
                    }
                    $remittanceDay[$day][$remitDriver]['passengers'] += 1;
                    if (count($samples) < $sampleCap - 6) {
                        $samples[] = array_combine($ptCols, $values);
                    }
                    $tr['paxRows'][] = true;
                }

                /* Cargo / Other rows — interleaved with passenger rows per trip.
                   Deterministic per trip slot so re-runs reuse the same cargo
                   accounts instead of inventing new ones. */
                if ($otherCount < MAX_OTHER_PER_DAY && $tr['space'] > 0) {
                    $chance = $dayType === 'weekday' ? 40 : ($dayType === 'weekend' ? 28 : 18);
                    $cargoRollKey = (string)$tr['id'] . '|cargo';
                    if (stableUnit($cargoRollKey . '-roll') * 100 <= $chance) {
                        $nCargo = 1;
                        if (stableUnit($cargoRollKey . '-n') <= 0.25 && $otherCount + 2 <= MAX_OTHER_PER_DAY) {
                            $nCargo = 2;
                        }
                        for ($c = 0; $c < $nCargo && $otherCount < MAX_OTHER_PER_DAY; $c++) {
                            if ($tr['space'] <= 0) {
                                break;
                            }
                            $cargoKey = (string)$tr['id'] . '|cargo|' . $c;
                            $spaceOccupied = 1 + (int)floor(stableUnit($cargoKey . '-space') * min(3, $tr['space']));
                            $spaceOccupied = max(1, min($spaceOccupied, $tr['space']));
                            $recipient = $passengers[(int)floor(stableUnit('cargo-recipient-' . $cargoKey) * count($passengers)) % count($passengers)]['name'];
                            $name = stableBaggageDescription($spaceOccupied, $recipient, 'baggage-' . $cargoKey);

                            /* Cargo rides the trip's own route, like the live baggage flow. */
                            $cargoRoute = (string)$tr['routeName'];
                            $cargoPair = pickRoutePair($ctx['routes'][$cargoRoute]);
                            $cBoarding = $cargoPair['boarding'];
                            $cDest = $cargoPair['destination'];
                            if ($cBoarding === null || $cDest === null) {
                                break;
                            }
                            /* Other/Cargo uses the route's regular base fare
                               for the segment multiplied by space occupied. */
                            $cBase = resolveSegmentFare(
                                $cBoarding,
                                $cDest,
                                $ctx['fareColumns']['regular'],
                                $ctx['fareColumns']['regular'],
                                (float)($ctx['routeMinFare'][$cargoRoute] ?? 0.0)
                            );
                            $fare = round(max(0.0, $cBase * $spaceOccupied), 2);
                            $stats['revenue'] += $fare;

                            $method = stablePickWeighted($ctx['paymentMix'] ?? HIST_PAYMENT_MIX, 'cargo-method-' . $cargoKey);
                            $pay = simulatePayment($day, $remitCutoff, $fare, $opts, $remitBy, $stats, (string)$method);
                            if ($pay['remittance_status'] === 'Discrepancy') {
                                $tripHasDiscrepancy = true;
                            }

                            $cargoAttempt = 0;
                            $cargoLookup = demoCargoLookup($cargoKey, $ctx, $recipient, $cargoAttempt);
                            if ($cargoLookup !== null) {
                                $cargoLookupKey = strtolower($cargoLookup);
                                while (isset($usedCargoEmails[$cargoLookupKey]) && $cargoAttempt < 25) {
                                    $cargoAttempt++;
                                    $cargoLookup = demoCargoLookup($cargoKey, $ctx, $recipient, $cargoAttempt);
                                    $cargoLookupKey = strtolower((string)$cargoLookup);
                                }
                                $usedCargoEmails[$cargoLookupKey] = true;
                            }
                            $account = ensureDemoAccount(
                                $db,
                                $ctx,
                                'c:' . $cargoKey,
                                $cargoLookup,
                                demoCargoAccountData($cargoKey, $name, $spaceOccupied, $ctx, $start),
                                $accounts
                            );
                            if (empty($account['id'])) {
                                break;
                            }

                            [$boardingAt, $dropoffAt] = rideBoardingTimes(
                                $day,
                                $ctx['routes'][$cargoRoute] ?? [],
                                $tr,
                                $cBoarding,
                                $cDest,
                                'cargo-time-' . $cargoKey
                            );
                            [$boardingAt, $dropoffAt] = reserveRideTimes(
                                (string)$tr['id'],
                                $boardingAt,
                                $dropoffAt,
                                $tripBoardCursor,
                                $tripDropCursor
                            );
                    $amounts = histPaymentAmounts((string)$pay['payment_method'], $fare, $pay);

                    /* One boarding/fare sample per trip (the first emitted
                       ride), so the audit story includes individual passenger
                       payments without one event per ride. */
                    if (!isset($auditBoardingSamples[(string)$tr['id']]) && $busTripId !== null) {
                        $auditBoardingSamples[(string)$tr['id']] = [
                            'trip_id' => (string)$busTripId,
                            'time' => $boardingAt,
                            'passenger' => (string)$account['id'],
                            'passenger_type' => (string)$type,
                            'fare' => $fare,
                            'method' => (string)$pay['payment_method'],
                            'boarding' => $boardingLabel,
                            'destination' => $destinationLabel,
                        ];
                    }

                            $rowData = [
                                'passenger_id' => (string)$account['id'],
                                'passenger_type' => 'Regular',
                                'bus_id' => (string)$tr['busNumber'],
                                'driver_id' => (string)$tr['drv']['driver_id'],
                                'trip_no' => $busTripId !== null ? (string)$busTripId : ptTripNoValue((string)$tr['id'], $ctx),
                                'boarding' => (string)$cBoarding['destination'],
                                'destination' => (string)$cDest['destination'],
                                'fare' => $fare,
                                'payment_method' => $pay['payment_method'],
                                'payment_status' => $pay['payment_status'],
                                'remittance_status' => $pay['remittance_status'],
                                'remitted_by' => $pay['remitted_by'],
                                'remitted_at' => $pay['remitted_at'],
                                'expected_cash' => $pay['expected_cash'],
                                'actual_cash' => $pay['actual_cash'],
                                'discrepancy_amount' => $pay['discrepancy_amount'],
                                'cash_amount' => $amounts['cash'],
                                'gcash_amount' => $amounts['gcash'],
                                'verified_cash_amount' => $amounts['verified'],
                                'boarding_time' => $boardingAt,
                                'dropoff_time' => $dropoffAt,
                            ];
                            $values = passengerTripRowValues($ptCols, $rowData, $ctx['ptRequiredDefaults'] ?? []);
                            $ptBuffer[] = $values;
                            $rowsInserted++;
                            $tripRevenueSum += $fare;
                            $stats['cargo_rows']++;
                            $stats['passenger_trip_rows']++;

                            /* Cargo cash counts toward the driver's remittance. */
                            $remitDriver = (string)$tr['drv']['driver_id'];
                            if (!isset($remittanceDay[$day][$remitDriver])) {
                                $remittanceDay[$day][$remitDriver] = ['collection' => 0.0, 'expected' => 0.0, 'passengers' => 0, 'bus' => (string)$tr['busNumber']];
                            }
                            $remittanceDay[$day][$remitDriver]['collection'] += (float)$fare;
                            if (strtolower((string)$pay['payment_method']) === 'cash') {
                                $remittanceDay[$day][$remitDriver]['expected'] += (float)$fare;
                            } elseif (strtolower((string)$pay['payment_method']) === 'hybrid') {
                                $remittanceDay[$day][$remitDriver]['expected'] += round((float)$fare * 0.6, 2);
                            }
                            $remittanceDay[$day][$remitDriver]['passengers'] += 1;
                            if (count($samples) < $sampleCap) {
                                $samples[] = array_combine($ptCols, $values);
                            }
                            $tr['space'] -= $spaceOccupied;
                            $ledger['trip_space'][$tr['id']] = ($ledger['trip_space'][$tr['id']] ?? 0) + $spaceOccupied;
                            $otherCount++;
                        }
                    }
                }

                simulateInspection($tripHasDiscrepancy, $opts, $stats);

                /* Fill the operational trip's totals once every ride is known. */
                if ($busTripId !== null) {
                    liveUpdate($db, 'bus_trips', [
                        'total_passengers' => $tripPaxCount,
                        'total_revenue' => round($tripRevenueSum, 2),
                        'duration_minutes' => (int)round((float)($tr['durationMin'] ?? 0)),
                    ], 'id', $busTripId);
                }

                if (count($ptBuffer) >= 800) {
                    flushBatch($db, 'passenger_trips', $ptCols, $ptBuffer);
                }
                if (count($fuelBuffer) >= 400) {
                    flushBatch($db, 'fuel_logs', $fuelColNames, $fuelBuffer);
                }
            }

            /* Fuel simulation for the day, per bus, in chronological order. */
            $tripsByBus = [];
            foreach ($trips as $tr) {
                $tripsByBus[(string)$tr['busNumber']][] = $tr;
            }
            foreach ($tripsByBus as $bid => $busTrips) {
                if (!isset($ctx['busStates'][$bid])) {
                    continue;
                }
                usort($busTrips, fn($a, $b) => $a['sec'] <=> $b['sec']);
                simulateFuelConsumption(
                    $ctx['busStates'][$bid],
                    $busTrips,
                    $day,
                    $opts,
                    $stats,
                    $fuelBuffer,
                    $fuelColNames,
                    $ctx['fuelTripIdIsText'],
                    $ctx['fuelTripIdNumericOnly'],
                    $refuelBuffer,
                    $refuelCols,
                    $ledger,
                    $ctx['dieselPriceAnchor'] ?? null
                );
                if ($ctx['busStates'][$bid]['fuel'] < -0.0001) {
                    $ledger['fuel_negative'][$bid] = true;
                }
                if ($ctx['busStates'][$bid]['odo'] < 0) {
                    throw new RuntimeException("Validation failed: negative odometer for $bid.");
                }
                $ledger['fuel_chain'][$bid][] = (float)$ctx['busStates'][$bid]['odo'];
            }

            /* The day's dispatch coverage records: published lineup and driver
               terminal assignments for this operating date, written from real
               records before the batch commits. */
            writeDayCoverage($db, $ctx, $opts, $day, $trips, $remitCutoff, $stats, $coverageDays);
        }

        /* An empty run must never look like a success: if no trip could be
           scheduled (no active/assigned buses or drivers for the dates), the
           batch is rejected and rolled back instead of silently reporting
           zero records. A run whose only scheduled day is today with every
           trip still in the future says so explicitly. */
        if ((int)$stats['trips_scheduled'] <= 0) {
            if ((int)($stats['today_future_trips_skipped'] ?? 0) > 0) {
                throw new RuntimeException(
                    "Today's trips are all later than the current time — nothing completed yet. Generate today again after the next scheduled trip arrives."
                );
            }
            throw new RuntimeException(
                'Generated no trips for ' . $label . ' — check that active buses have drivers and bus assignments for the selected dates.'
            );
        }

        /* Driver-day remittance logs plus discrepancy workflow records
           (some resolved, some open) for the finance pages. */
        generateDriverRemittances($db, $remittanceDay, $remitBy, $stats, $ctx, $start, $end, $remitCutoff);

        /* Backfill logs for demo days generated before remittance logging
           existed (or outside this run's period) so Finance -> History lists
           them too. Activity events stay scoped to the current period. */
        $backfillDays = collectPreviousDriverDays($db, $ctx['demoMarker']);
        if ($backfillDays) {
            $logsBefore = (int)$stats['remittance_logs'];
            generateDriverRemittances($db, $backfillDays, $remitBy, $stats, $ctx, $start, $end, $remitCutoff);
            $stats['remittance_logs_backfilled'] = (int)$stats['remittance_logs'] - $logsBefore;
        }

        $fuelLogCount = $stats['fuel_logs'];
        $refuelCount = $stats['refuel_events'];
        flushBatch($db, 'passenger_trips', $ptCols, $ptBuffer);
        flushBatch($db, 'fuel_logs', $fuelColNames, $fuelBuffer);
        flushBatch($db, 'fuel_refuels', $refuelCols, $refuelBuffer);
        flushDemoSequences($db, $accounts);

        /* Realistic activity audit: one event per business action the run
           actually produced (queue publication, departures, arrivals, fare
           payments). Emitted only after the records were flushed and inside
           the same transaction, so a rollback removes both the records and
           their events. */
        $auditCountBefore = historyAuditCount();
        foreach ($auditQueueDays as $auditDay => $auditTripCount) {
            $publishedAt = date(
                'Y-m-d H:i:s',
                strtotime($auditDay . ' 04:45:00') + (int)round(stableRange('audit-publish-' . $auditDay, 0, 900))
            );
            $coverage = $coverageDays[$auditDay] ?? null;
            $lineupCount = $auditTripCount;
            $writtenBuses = [];
            $writtenDrivers = [];
            if (is_array($coverage)) {
                if (!empty($coverage['published_at'])) {
                    $publishedAt = (string)$coverage['published_at'];
                }
                if (in_array((string)($coverage['queue_source'] ?? ''), ['generated', 'existing'], true)) {
                    $lineupCount = count((array)($coverage['lineup_buses'] ?? []));
                }
                if ((string)($coverage['queue_source'] ?? '') === 'generated') {
                    $writtenBuses = array_values((array)($coverage['lineup_buses'] ?? []));
                }
                if ((string)($coverage['assign_source'] ?? '') === 'generated') {
                    $writtenDrivers = array_keys((array)($coverage['assigned_terminals'] ?? []));
                }
            }
            historyAuditEvent(
                $db,
                $ctx,
                'DispatchQueue',
                'Queue Published',
                sprintf('Official dispatch order published for %s (%d bus%s).', $auditDay, $lineupCount, $lineupCount === 1 ? '' : 'es'),
                (string)$auditDay,
                [
                    'operating_date' => $auditDay,
                    'published_at' => $publishedAt,
                    'count' => $lineupCount,
                    'written_buses' => $writtenBuses,
                    'written_drivers' => $writtenDrivers,
                ],
                $publishedAt,
                'dispatcher',
                'publish-' . $auditDay
            );
        }
        /* Driver check-ins for the generated operating days: the attendance
           trail driver performance reporting reads. */
        $stats['checkin_events'] = emitDriverCheckinEvents($db, $ctx, $coverageDays);
        foreach ($auditTripEvents as $ev) {
            $pax = (int)($ledger['trip_pax'][$ev['ref']] ?? 0);
            historyAuditEvent(
                $db,
                $ctx,
                'Dispatcher',
                'Trip Departed',
                "Bus {$ev['bus']} departed {$ev['route']} on trip #{$ev['id']} (driver {$ev['driver']}).",
                $ev['id'],
                [
                    'trip_id' => $ev['id'],
                    'bus_id' => $ev['bus'],
                    'driver_id' => $ev['driver'],
                    'route_name' => $ev['route'],
                    'departure' => $ev['departure'],
                    'passengers' => $pax,
                ],
                $ev['day'] . ' ' . $ev['departure'],
                'dispatcher',
                'depart-' . $ev['id']
            );
            historyAuditEvent(
                $db,
                $ctx,
                'Dispatcher',
                'Trip Completed',
                "Bus {$ev['bus']} arrived at the destination terminal on trip #{$ev['id']} at {$ev['arrival']}.",
                $ev['id'],
                [
                    'trip_id' => $ev['id'],
                    'bus_id' => $ev['bus'],
                    'driver_id' => $ev['driver'],
                    'route_name' => $ev['route'],
                    'arrival' => $ev['arrival'],
                    'passengers' => $pax,
                ],
                $ev['day'] . ' ' . $ev['arrival'],
                'dispatcher',
                'arrive-' . $ev['id']
            );
        }
        foreach ($auditBoardingSamples as $sample) {
            historyAuditEvent(
                $db,
                $ctx,
                'Passenger',
                'Fare Payment Recorded',
                "Passenger {$sample['passenger']} boarded at {$sample['boarding']} and paid PHP "
                    . number_format((float)$sample['fare'], 2) . " ({$sample['method']}) for trip #{$sample['trip_id']}.",
                $sample['trip_id'],
                [
                    'trip_id' => $sample['trip_id'],
                    'passenger_id' => $sample['passenger'],
                    'passenger_type' => $sample['passenger_type'],
                    'boarding_point' => $sample['boarding'],
                    'destination' => $sample['destination'],
                    'fare' => $sample['fare'],
                    'payment_method' => $sample['method'],
                ],
                $sample['time'],
                'system',
                'boarding-' . $sample['trip_id']
            );
        }
        $stats['audit_events'] = historyAuditCount() - $auditCountBefore;

        $stats['accounts_created'] = $accounts['created'];
        $stats['accounts_reused'] = $accounts['reused'];
        $stats['accounts_reissued'] = (int)($accounts['codes_reissued'] ?? 0);
        $stats['accounts_skipped'] = (int)($accounts['skipped'] ?? 0);
        $stats['skipped_reasons'] = (array)($accounts['skipped_reasons'] ?? []);
        /* Unique passenger accounts per type for the generated period: each
           recurring rider counts once no matter how many rides they took. */
        $stats['accounts_by_type'] = [];
        foreach (($stats['type_passengers'] ?? []) as $typeName => $names) {
            $stats['accounts_by_type'][(string)$typeName] = count((array)$names);
        }

        $pthAfter = (int)liveValue($db, 'SELECT COUNT(*) FROM passenger_trip_history');
        if ($pthAfter !== $pthBefore) {
            throw new RuntimeException('Validation failed: generation modified passenger_trip_history.');
        }

        validateGeneratedData($db, $ledger, $ctx, $samples, $accounts, true, $coverageDays, $remitCutoff);

        $db->commit();
    } catch (Throwable $e) {
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Throwable $rollbackError) {
            // The transaction may already have been implicitly committed by a
            // DDL statement; nothing left to roll back.
        }
        throw $e;
    }

    $volumeMeta = [
        'scenario' => (string)($opts['demand_scenario'] ?? 'normal'),
        'daily_trips_target' => (int)($opts['daily_trips_target'] ?? 0),
        'diesel_price' => (float)($ctx['dieselPrice'] ?? 0),
        'diesel_price_source' => (string)($ctx['dieselPriceSource'] ?? ''),
    ];
    /* Fleet buses that never operated in the period, with their recorded
       status, so a bus without generated fuel history (and therefore without
       a historical KPL) is explainable at a glance instead of a mystery. */
    $neverOperated = [];
    foreach ((array)($ctx['busByNumber'] ?? []) as $bid => $busRow) {
        $bid = (string)$bid;
        if ($bid === '' || isset($stats['buses_used'][$bid])) {
            continue;
        }
        $busRow = (array)$busRow;
        $status = trim((string)($busRow['status'] ?? ''));
        if ($status === '') {
            $status = trim((string)($busRow['operational_status'] ?? ''));
        }
        if ($status === '' && !empty($ctx['busStatusUnavailable'][$bid])) {
            $status = 'unavailable';
        }
        $neverOperated[] = $bid . ($status !== '' ? ' (' . $status . ')' : '');
    }
    sort($neverOperated, SORT_STRING);
    $volumeMeta['never_operated'] = $neverOperated;
    /* Daily dispatch coverage report: what the run actually wrote versus the
       targets, with per-date limitations named (roster, fleet, terminals, or
       the Sep-Oct 2026 window profile). Ride totals come from the same
       per-driver-day aggregates the remittance logs use. */
    $dailyRides = [];
    foreach ($remittanceDay as $rideDay => $perDriver) {
        $ridePax = 0;
        $rideFares = 0.0;
        foreach ((array)$perDriver as $agg) {
            $ridePax += (int)($agg['passengers'] ?? 0);
            $rideFares += (float)($agg['collection'] ?? 0);
        }
        $dailyRides[(string)$rideDay] = ['passengers' => $ridePax, 'fares' => round($rideFares, 2)];
    }
    $coverageSummary = dispatchCoverageSummary(
        $coverageDays,
        $dailyRides,
        DISPATCH_COVERAGE_BUS_TARGET,
        DISPATCH_COVERAGE_DRIVER_TARGET,
        !empty($ctx['coverageTerminals'])
    );
    $volumeMeta['daily_coverage'] = $coverageSummary['daily'];
    $volumeMeta['coverage_totals'] = $coverageSummary['totals'];
    /* Dashboard reconciliation: the exact metrics the admin passenger page
       computes (statuses, boarding records, Boarded Today), reported so the
       run and the dashboard can be compared immediately. */
    $stats['dashboard'] = passengerDashboardMetrics($db, $start, $end);
    $summary = buildRunSummary($stats, $label, $start, $end, $rowsInserted, $fuelLogCount, $volumeMeta);
    /* Actual revenue minus the applicable expenses (generated fuel + the
       existing financial-report fixed deductions + recorded other expenses). */
    $summary = array_merge(
        $summary,
        buildProfitSummary(
            $db,
            $start,
            $end,
            (float)$stats['revenue'],
            (float)$stats['fuel_cost'],
            count($stats['drivers_used']),
            $volumeMeta
        )
    );

    /* No bulk "Generated N records" audit row: the individual business events
       (queue publication, departures, arrivals, fare payments, remittances,
       discrepancies) are written with the records they describe. */

    return [
        'rows' => $samples,
        'inserted' => $rowsInserted,
        'trips' => $stats['trips_scheduled'],
        'bus_trips' => $stats['bus_trips'],
        'driver_replacements' => $stats['driver_replacements'],
        'passenger_trip_rows' => $stats['passenger_trip_rows'],
        'accounts_created' => $stats['accounts_created'],
        'accounts_reused' => $stats['accounts_reused'],
        'accounts_reissued' => $stats['accounts_reissued'],
        'accounts_skipped' => $stats['accounts_skipped'],
        'demo_rides_replaced' => $stats['demo_rides_replaced'],
        'demo_bus_trips_replaced' => $stats['demo_bus_trips_replaced'],
        'remittance_settled' => $stats['remittance_settled'],
        'remittance_logs' => $stats['remittance_logs'],
        'remittance_logs_backfilled' => $stats['remittance_logs_backfilled'],
        'discrepancy_cases' => $stats['discrepancy_cases'],
        'discrepancy_resolved' => $stats['discrepancy_resolved'],
        'fuel_logs' => $fuelLogCount,
        'fuel_refuels' => $refuelCount,
        'label' => $label,
        'summary' => $summary,
    ];
}

/* =========================================================
   ARCHIVE HISTORY GENERATOR (passenger_trip_history)
   ---------------------------------------------------------
   Writes old manifest records directly into passenger_trip_history for the
   cooperative's earlier years, reusing the real fleet, drivers, routes and
   fares. Rows are identified by ARCV- trip ids, so re-running a period
   replaces only this generator's own rows. Operating dates before today
   settle (paid/Remitted); today's and future dates stay pending.
   ========================================================= */
/* Archive period: a whole year or any month range inside it. 2026 is limited
   to January-September so the archive never overlaps the current operating
   month. Smaller ranges keep large fleets under the server's gateway
   timeout. */
function archiveResolvePeriod(int $year, int $fromMonth, int $toMonth): array
{
    if (!in_array($year, ARCHIVE_YEARS, true)) {
        throw new RuntimeException('Archive year must be one of ' . implode(', ', ARCHIVE_YEARS) . '.');
    }
    if ($fromMonth < 1 || $fromMonth > 12 || $toMonth < 1 || $toMonth > 12) {
        throw new RuntimeException('Archive months must be between 1 and 12.');
    }
    if ($fromMonth > $toMonth) {
        throw new RuntimeException('The "from" month cannot be after the "to" month.');
    }
    if ($year === 2026 && $toMonth > ARCHIVE_2026_LAST_MONTH) {
        throw new RuntimeException('For 2026, archive data is only available for January to September.');
    }
    $start = sprintf('%04d-%02d-01', $year, $fromMonth);
    $endStart = sprintf('%04d-%02d-01', $year, $toMonth);
    $end = date('Y-m-t', strtotime($endStart));
    if ($fromMonth === $toMonth) {
        $label = date('F Y', strtotime($start));
    } elseif ($fromMonth === 1 && $toMonth === 12) {
        $label = (string)$year;
    } elseif ($year === 2026 && $fromMonth === 1 && $toMonth === ARCHIVE_2026_LAST_MONTH) {
        $label = 'January-September 2026';
    } else {
        $label = date('F', strtotime($start)) . '-' . date('F', strtotime($endStart)) . ' ' . $year;
    }
    return [$start, $end, $label];
}

function generateArchiveHistory(array $input): array
{
    $db = Database::get();
    set_time_limit(1800);
    ini_set('memory_limit', '512M');

    $opts = resolveOptions($input);
    if ($opts['seed'] !== null) {
        mt_srand($opts['seed']);
    }

    $year = (int)($input['arch_year'] ?? 2024);
    $monthFrom = (int)($input['arch_month_from'] ?? 0);
    $monthTo = (int)($input['arch_month_to'] ?? 0);
    if ($monthFrom <= 0 && $monthTo <= 0) {
        /* Backward compatible with the earlier single-month form. */
        $legacyMonth = (int)($input['arch_month'] ?? 0);
        $monthFrom = $legacyMonth > 0 ? $legacyMonth : 1;
        $monthTo = $legacyMonth > 0
            ? $legacyMonth
            : ($year === 2026 ? ARCHIVE_2026_LAST_MONTH : 12);
    }
    [$start, $end, $label] = archiveResolvePeriod($year, $monthFrom, $monthTo);
    $days = dateRange($start, $end);

    $ctx = buildSimulationContext($db, $start, $opts);
    $dailyTripTarget = periodDailyTripTarget($days, $ctx, $opts);
    $poolSize = (int)($opts['passenger_pool_target'] ?? 0) > 0
        ? (int)$opts['passenger_pool_target']
        : planPoolTarget($dailyTripTarget, count($ctx['busStates']));
    $passengers = buildPassengerPool($ctx['routeNames'], $ctx['routes'], $poolSize);

    /* passenger_trip_history columns plus a NOT NULL fallback map so every
       schema variant accepts the batch. */
    $pthCols = [];
    $pthRequiredDefaults = [];
    foreach (describeTable($db, 'passenger_trip_history') as $col) {
        $extra = (string)($col['Extra'] ?? '');
        if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'generated') !== false) {
            continue;
        }
        $pthCols[] = $col['Field'];
        $default = $col['Default'] ?? null;
        $hasDefault = $default !== null && strtoupper((string)$default) !== 'NULL';
        if (strtoupper((string)($col['Null'] ?? '')) === 'NO') {
            $pthRequiredDefaults[strtolower((string)$col['Field'])] = $hasDefault
                ? normalizeColumnDefault($default, (string)($col['Type'] ?? ''))
                : passengerRequiredDefault((string)($col['Type'] ?? ''), (string)$col['Field']);
        }
    }
    if (!$pthCols) {
        throw new RuntimeException('No usable passenger_trip_history columns found.');
    }

    $remitCutoff = date('Y-m-d');
    $actor = $GLOBALS['authedUser'] ?? null;
    $remitBy = (is_array($actor) && !empty($actor['name'])) ? (string)$actor['name'] : 'Finance Officer';

    $stats = [
        'days' => 0, 'trips_scheduled' => 0, 'cancelled' => 0, 'delayed' => 0,
        'unavailable_buses' => 0, 'out_of_service_days' => 0, 'unusual_durations' => 0,
        'passenger_rows' => 0, 'cargo_rows' => 0, 'passenger_trip_rows' => 0,
        'bus_trips' => 0, 'no_driver_buses' => 0, 'driver_replacements' => 0,
        'accounts_created' => 0, 'accounts_reused' => 0, 'accounts_reissued' => 0,
        'demo_rides_replaced' => 0, 'demo_bus_trips_replaced' => 0, 'pth_rows_written' => 0,
        'remittance_settled' => 0,
        'archive_rows' => 0, 'nickname_rows' => 0, 'archive_rows_replaced' => 0,
        'revenue' => 0.0, 'passengers_unique' => [],
        'paid' => 0, 'pending' => 0, 'rejected' => 0,
        'remit_pending' => 0, 'remit_remitted' => 0, 'remit_discrepancy' => 0, 'discrepancies' => 0,
        'methods' => [], 'inspections_minor' => 0, 'inspections_significant' => 0,
        'fuel_logs' => 0, 'refuel_events' => 0, 'fleet_size' => count($ctx['busStates']),
        'fuel_full' => 0, 'fuel_partials' => 0, 'fuel_liters' => 0.0, 'fuel_cost' => 0.0,
        'fuel_anomalies' => 0, 'kpl_sum' => 0.0, 'kpl_dist' => 0.0,
        'buses_used' => [], 'drivers_used' => [], 'fuel_buses' => [], 'driver_absences' => 0,
    ];
    $ledger = [
        'rides' => [], 'trip_space' => [], 'trip_pax' => [], 'trip_ids' => [], 'trip_id_count' => 0,
        'busy' => ['driver' => [], 'bus' => []], 'durations' => [],
        'fuel_negative' => [], 'fuel_chain' => [], 'trip_fuel' => [], 'refuels' => [],
        'ride_keys' => [], 'ride_dupes' => [],
        'plan' => [], 'plan_caps' => [], 'week_rides' => [],
    ];

    $samples = [];
    $sampleCap = 30;
    $pthBuffer = [];
    $rowsInserted = 0;
    $pstate = [];
    $nextTrip = (int)$ctx['nextTrip'];
    $arcvNext = 1;
    /* Per-day import tallies for the activity audit (one import event per
       operating day instead of a single bulk-generation message). */
    $archiveImportDays = [];

    $db->beginTransaction();
    try {
        /* Replace only this generator's own archive rows for the period. */
        $stats['archive_rows_replaced'] = liveExec(
            $db,
            'DELETE FROM passenger_trip_history WHERE trip_id LIKE ? AND trip_date BETWEEN ? AND ?',
            [ARCHIVE_TRIP_PREFIX . '%', $start, $end]
        );
        /* Replace the generator's own import events for the period as well. */
        liveExec(
            $db,
            "DELETE FROM audit_trail WHERE user_agent = ? AND module = 'Passenger' AND action = 'Import' AND DATE(created_at) BETWEEN ? AND ?",
            [LIVE_SIM_UA, $start, $end]
        );
        $maxArcv = (int)liveValue(
            $db,
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(trip_id,'-',-1) AS UNSIGNED)),0) FROM passenger_trip_history WHERE trip_id LIKE ?",
            [ARCHIVE_TRIP_PREFIX . '%']
        );
        $arcvNext = max(1, $maxArcv + 1);

        /* Settle anything this tool generated earlier for operating dates
           before today (Asia/Manila); today's and future rows stay pending. */
        $settled = settleDemoRemittances($db, $ctx['demoMarker'], $remitBy, $remitCutoff);
        $stats['remittance_settled'] = (int)($settled['passenger_trips'] ?? 0)
            + (int)($settled['passenger_trip_history'] ?? 0)
            + (int)($settled['remittance_logs'] ?? 0);

        foreach ($days as $day) {
            $stats['days']++;
            $dayType = dayTypeOf($day);
            $rowsBeforeDay = $rowsInserted;

            $present = [];
            foreach ($ctx['driversWithBuses'] as $drv) {
                $did = (string)$drv['driver_id'];
                if (mt_rand(1, 1000) / 1000.0 <= $ctx['driverAbsence'][$did]) {
                    $stats['driver_absences']++;
                    continue;
                }
                $present[] = $drv;
            }
            if (!$present) {
                $present = $ctx['driversWithBuses'];
            }

            $unavailable = $ctx['busStatusUnavailable'];
            $stats['out_of_service_days'] += count($unavailable);
            foreach ($ctx['buses'] as $bus) {
                $bid = (string)($bus['bus_id'] ?? '');
                if ($bid === '' || isset($unavailable[$bid])) {
                    continue;
                }
                if (mt_rand(1, 1000) <= 20) {
                    $unavailable[$bid] = true;
                    $stats['unavailable_buses']++;
                }
            }
            $minAvailable = min(2, count($ctx['busStates']));
            while (count($ctx['busStates']) - count($unavailable) < $minAvailable && $unavailable) {
                unset($unavailable[array_key_first($unavailable)]);
            }

            $trips = generateDailySchedule($day, $ctx, $opts, $present, $unavailable, $nextTrip, $stats, $ledger);
            if (!$trips) {
                continue;
            }

            $assignments = generateTripPassengers($trips, $passengers, $pstate, $ctx, $day, $ledger);
            $byTrip = [];
            foreach ($assignments as $a) {
                $byTrip[$a['trip']][] = $a;
            }

            $otherCount = 0;
            foreach ($trips as $ti => $tr) {
                $arcvTripId = ARCHIVE_TRIP_PREFIX . $arcvNext++;

                foreach ($byTrip[$ti] ?? [] as $a) {
                    $d = $a['demand'];
                    $pair = $a['pair'];
                    $boarding = $pair['boarding'];
                    $destination = $pair['destination'];
                    if ($boarding === null || $destination === null) {
                        continue;
                    }
                    $boardingLabel = (string)$boarding['destination'];
                    $destinationLabel = (string)$destination['destination'];
                    $type = $d['ptype'];

                    /* Archive manifests follow the same verification rule:
                       discount fares only for approved discount profiles. */
                    $fareColumn = fareColumnForType($type, $ctx['fareColumns']);
                    if ($fareColumn !== null && $fareColumn !== $ctx['fareColumns']['regular']) {
                        $discountOk = ($d['profile']['discountStatus'] ?? null) === null
                            || ($d['profile']['discountStatus'] ?? '') === 'verified';
                        if (!$discountOk) {
                            $fareColumn = $ctx['fareColumns']['regular'];
                        }
                    }
                    $fare = resolveSegmentFare(
                        $boarding,
                        $destination,
                        $fareColumn,
                        $ctx['fareColumns']['regular'],
                        (float)($ctx['routeMinFare'][(string)$tr['routeName']] ?? 0.0)
                    );
                    $stats['revenue'] += $fare;

                    $method = stablePickWeighted($ctx['paymentMixHistory'] ?? HIST_PAYMENT_MIX, 'archive-method-' . $arcvTripId . '-' . $d['name']);
                    $pay = simulatePayment($day, $remitCutoff, $fare, $opts, $remitBy, $stats, (string)$method);

                    $displayName = archiveDisplayName((string)$d['name']);
                    if ($displayName !== (string)$d['name']) {
                        $stats['nickname_rows']++;
                    }

                    if ($ctx['kmCol'] !== null) {
                        $boardingKm = isset($boarding[$ctx['kmCol']]) && $boarding[$ctx['kmCol']] !== null
                            ? (float)$boarding[$ctx['kmCol']] : null;
                        $destKm = isset($destination[$ctx['kmCol']]) && $destination[$ctx['kmCol']] !== null
                            ? (float)$destination[$ctx['kmCol']] : null;
                    } else {
                        $fpId = (int)($boarding['id'] ?? 0);
                        $boardingKm = $ctx['kmByFpId'][$fpId]
                            ?? $ctx['kmByStopName'][strtolower(trim($boardingLabel))] ?? null;
                        $dId = (int)($destination['id'] ?? 0);
                        $destKm = $ctx['kmByFpId'][$dId]
                            ?? $ctx['kmByStopName'][strtolower(trim($destinationLabel))] ?? null;
                    }

                    $bookingTs = max(strtotime($day) - mt_rand(0, 7) * 86400, strtotime($start));
                    $dateBooked = date('Y-m-d', $bookingTs)
                        . sprintf(' %02d:%02d:%02d', mt_rand(0, 23), mt_rand(0, 59), mt_rand(0, 59));

                    $studentId = $type === 'Student' ? $d['profile']['idValue'] : null;
                    $seniorPwdId = in_array($type, ['Senior', 'PWD'], true) ? $d['profile']['idValue'] : null;
                    $beneficiaryId = $type === 'Beneficiary' ? $d['profile']['idValue'] : null;
                    $coopDiscId = $type === 'Cooperative' ? $d['profile']['idValue'] : null;

                    $rowData = [
                        'name' => $displayName,
                        'ptype' => $type,
                        'row_type' => 'Passenger',
                        'space' => 1,
                        'student_id' => $studentId,
                        'boarding' => $boardingLabel,
                        'destination' => $destinationLabel,
                        'driver_name' => (string)$tr['drv']['full_name'],
                        'bus_number' => (string)$tr['busNumber'],
                        'bus_id' => (string)$tr['busNumber'],
                        'driver_id' => (string)$tr['drv']['driver_id'],
                        'trip_id' => $arcvTripId,
                        'fare' => $fare,
                        'payment_method' => $pay['payment_method'],
                        'date_booked' => $dateBooked,
                        'trip_date' => $day,
                        'payment_status' => $pay['payment_status'],
                        'remittance_status' => $pay['remittance_status'],
                        'remitted_by' => $pay['remitted_by'],
                        'remitted_at' => $pay['remitted_at'],
                        'expected_cash' => $pay['expected_cash'],
                        'actual_cash' => $pay['actual_cash'],
                        'discrepancy_amount' => $pay['discrepancy_amount'],
                        'departure' => (string)$tr['departure'],
                        'arrival' => (string)$tr['arrival'],
                        'senior_pwd_id' => $seniorPwdId,
                        'beneficiary_id' => $beneficiaryId,
                        'coop_disc_id' => $coopDiscId,
                        'boarding_km' => $boardingKm,
                        'dest_km' => $destKm,
                    ];
                    $values = archiveHistoryRowValues($pthCols, $rowData, $pthRequiredDefaults);
                    $pthBuffer[] = $values;
                    $rowsInserted++;
                    $stats['passenger_rows']++;
                    $stats['passenger_trip_rows']++;
                    $stats['passengers_unique'][$d['name']] = true;
                    if (count($samples) < $sampleCap - 6) {
                        $samples[] = array_combine($pthCols, $values);
                    }
                }

                /* Cargo / Other archive rows, deterministic per trip slot. */
                if ($otherCount < MAX_OTHER_PER_DAY && $tr['space'] > 0) {
                    $chance = $dayType === 'weekday' ? 40 : ($dayType === 'weekend' ? 28 : 18);
                    $cargoRollKey = 'arcv-cargo|' . $arcvTripId;
                    if (stableUnit($cargoRollKey . '-roll') * 100 <= $chance) {
                        $nCargo = stableUnit($cargoRollKey . '-n') <= 0.25 ? 2 : 1;
                        for ($c = 0; $c < $nCargo && $otherCount < MAX_OTHER_PER_DAY; $c++) {
                            if ($tr['space'] <= 0) {
                                break;
                            }
                            $cargoKey = $cargoRollKey . '|' . $c;
                            $spaceOccupied = 1 + (int)floor(stableUnit($cargoKey . '-space') * min(3, $tr['space']));
                            $spaceOccupied = max(1, min($spaceOccupied, $tr['space']));
                            $recipient = $passengers[(int)floor(stableUnit('cargo-recipient-' . $cargoKey) * count($passengers)) % count($passengers)]['name'];
                            $name = stableBaggageDescription($spaceOccupied, $recipient, 'baggage-' . $cargoKey);

                            $cargoPair = pickRoutePair($ctx['routes'][(string)$tr['routeName']]);
                            $cBoarding = $cargoPair['boarding'];
                            $cDest = $cargoPair['destination'];
                            if ($cBoarding === null || $cDest === null) {
                                break;
                            }
                            $cBase = resolveSegmentFare(
                                $cBoarding,
                                $cDest,
                                $ctx['fareColumns']['regular'],
                                $ctx['fareColumns']['regular'],
                                (float)($ctx['routeMinFare'][(string)$tr['routeName']] ?? 0.0)
                            );
                            $fare = round(max(0.0, $cBase * $spaceOccupied), 2);
                            $stats['revenue'] += $fare;

                            $method = stablePickWeighted($ctx['paymentMixHistory'] ?? HIST_PAYMENT_MIX, 'archive-cargo-method-' . $cargoKey);
                            $pay = simulatePayment($day, $remitCutoff, $fare, $opts, $remitBy, $stats, (string)$method);

                            $rowData = [
                                'name' => $name,
                                'ptype' => 'Regular',
                                'row_type' => 'Other',
                                'space' => $spaceOccupied,
                                'boarding' => (string)$cBoarding['destination'],
                                'destination' => (string)$cDest['destination'],
                                'driver_name' => (string)$tr['drv']['full_name'],
                                'bus_number' => (string)$tr['busNumber'],
                                'bus_id' => (string)$tr['busNumber'],
                                'driver_id' => (string)$tr['drv']['driver_id'],
                                'trip_id' => $arcvTripId,
                                'fare' => $fare,
                                'payment_method' => $pay['payment_method'],
                                'date_booked' => $day . ' ' . (string)$tr['departure'],
                                'trip_date' => $day,
                                'payment_status' => $pay['payment_status'],
                                'remittance_status' => $pay['remittance_status'],
                                'remitted_by' => $pay['remitted_by'],
                                'remitted_at' => $pay['remitted_at'],
                                'expected_cash' => $pay['expected_cash'],
                                'actual_cash' => $pay['actual_cash'],
                                'discrepancy_amount' => $pay['discrepancy_amount'],
                                'departure' => (string)$tr['departure'],
                                'arrival' => (string)$tr['arrival'],
                                'baggage_category' => cargoCategoryFor($cargoKey),
                            ];
                            $values = archiveHistoryRowValues($pthCols, $rowData, $pthRequiredDefaults);
                            $pthBuffer[] = $values;
                            $rowsInserted++;
                            $stats['cargo_rows']++;
                            $stats['passenger_trip_rows']++;
                            if (count($samples) < $sampleCap) {
                                $samples[] = array_combine($pthCols, $values);
                            }
                            $tr['space'] -= $spaceOccupied;
                            $ledger['trip_space'][$tr['id']] = ($ledger['trip_space'][$tr['id']] ?? 0) + $spaceOccupied;
                            $otherCount++;
                        }
                    }
                }

                simulateInspection(false, $opts, $stats);

                if (count($pthBuffer) >= 800) {
                    flushBatch($db, 'passenger_trip_history', $pthCols, $pthBuffer);
                }
            }

            $archiveImportDays[$day] = [
                'trips' => isset($trips) ? count($trips) : 0,
                'records' => max(0, $rowsInserted - $rowsBeforeDay),
            ];
        }

        if ((int)$stats['trips_scheduled'] <= 0) {
            throw new RuntimeException('Generated no archive trips for ' . $label . ' — check that active buses have drivers for the selected dates.');
        }

        $stats['archive_rows'] = $rowsInserted;
        flushBatch($db, 'passenger_trip_history', $pthCols, $pthBuffer);
        validateGeneratedData($db, $ledger, $ctx, $samples, [], false);
        $db->commit();
    } catch (Throwable $e) {
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Throwable $rollbackError) {
            // The transaction may already have been implicitly committed by a
            // DDL statement; nothing left to roll back.
        }
        throw $e;
    }

    $volumeMeta = [
        'scenario' => (string)($opts['demand_scenario'] ?? 'normal'),
        'daily_trips_target' => (int)($opts['daily_trips_target'] ?? 0),
        'diesel_price' => (float)($ctx['dieselPrice'] ?? 0),
        'diesel_price_source' => (string)($ctx['dieselPriceSource'] ?? ''),
    ];
    $summary = buildRunSummary($stats, $label, $start, $end, $rowsInserted, 0, $volumeMeta);
    $summary['archive_rows'] = $stats['archive_rows'];
    $summary['nickname_rows'] = $stats['nickname_rows'];
    $summary['archive_rows_replaced'] = $stats['archive_rows_replaced'];
    $summary['remittance_settled'] = $stats['remittance_settled'];

    /* Realistic import activity: one event per operating day whose manifest
       rows the run imported, instead of a single bulk-generation message.
       Written after the records committed, with the generator's user agent
       and demo flag so simulated imports are distinguishable and a re-run
       replaces them together with the period's rows. */
    $archiveAuditBefore = historyAuditCount();
    try {
        foreach ($archiveImportDays as $arcvDay => $arcvInfo) {
            if ((int)$arcvInfo['records'] <= 0) {
                continue;
            }
            $importedAt = date(
                'Y-m-d H:i:s',
                strtotime((string)$arcvDay . ' 09:00:00') + (int)round(stableRange('archive-import-' . $arcvDay, 0, 7200))
            );
            historyAuditEvent(
                $db,
                $ctx,
                'Passenger',
                'Import',
                sprintf(
                    'Imported historical manifest for %s: %d records across %d trip%s.',
                    (string)$arcvDay,
                    (int)$arcvInfo['records'],
                    (int)$arcvInfo['trips'],
                    (int)$arcvInfo['trips'] === 1 ? '' : 's'
                ),
                (string)$arcvDay,
                ['operating_date' => (string)$arcvDay, 'records' => (int)$arcvInfo['records'], 'trips' => (int)$arcvInfo['trips']],
                $importedAt,
                'admin',
                'archive-import-' . $arcvDay
            );
        }
    } catch (Throwable $e) {
        // audit logging must never break generation
    }
    $stats['audit_events'] = historyAuditCount() - $archiveAuditBefore;

    return [
        'rows' => $samples,
        'inserted' => $rowsInserted,
        'trips' => $stats['trips_scheduled'],
        'label' => $label,
        'archive_rows_replaced' => $stats['archive_rows_replaced'],
        'nickname_rows' => $stats['nickname_rows'],
        'remittance_settled' => $stats['remittance_settled'],
        'summary' => $summary,
    ];
}

/* =========================================================
   STATEFUL LIVE OPERATIONAL SIMULATOR
   ---------------------------------------------------------
   Keeps ONE simulated trip moving through the real operational
   tables. Every call with mode=live reads the trip's anchor
   (bus_trips.created_at), computes how much simulated time has
   elapsed, and persists only the transitions whose time has come.
   The trip therefore progresses across repeated calls instead of
   being written in one shot.

   Tables used: bus_trips, buses, drivers, driver_accounts,
   driver_bus_assignment, driver_current_location,
   driver_location_history, inspector_assignments,
   inspector_inspections(+items), inspectors, inspector_accounts,
   inspectors_location, bus_inspections, passenger_trips,
   passengers, routes, route_stops, fare_pricing, audit_trail,
   bus_problem_reports. No new tables, no redesign of the
   historical generator. Dispatcher and Inspector read the same
   bus_trips + driver_current_location + passenger_trips state.
   ========================================================= */
/* Legacy markers: only used to find/clean rows written by earlier builds.
   New rows are identified by LIVE_SIM_UA (audit_trail.user_agent), so no
   visible monitoring field ever carries a simulator label. */
const LIVE_SIM_MARKER = 'SIMULATOR';
const LIVE_SIM_USER = 'Data Simulator';
const LIVE_SIM_UA = 'trip-history-generator';
const LIVE_SIM_IP = '127.0.0.1';
const LIVE_GPS_TICK_SECONDS = 60;   // real seconds between GPS appends
/* Geographic guard rails: every simulated fix must come from validated
   route_stops rows inside the Philippine area of responsibility. */
const LIVE_PH_LAT_MIN = 4.4;
const LIVE_PH_LAT_MAX = 21.6;
const LIVE_PH_LNG_MIN = 116.0;
const LIVE_PH_LNG_MAX = 127.0;
const LIVE_MAX_SPEED_KPH = 85.0;        // impossible-speed guard for stored GPS speed
const LIVE_PATH_MAX_LEG_KM = 50.0;      // reject path points that jump further than this
const LIVE_NOMINAL_LEG_KM = 5.0;        // timing estimate when a stop has no usable coordinate
const LIVE_MAX_ACTIVE_TRIPS = 4;        // 55-min trips at a 15-min headway overlap up to 4 buses
const LIVE_PASSENGER_MIN = 8;
const LIVE_PASSENGER_MAX = 20;
const LIVE_HEADWAY_SECONDS = 900;          // departures every 15 minutes (:00, :15, :30, :45)
const LIVE_BASE_TRIP_SECONDS = 55 * 60;    // no-traffic one-way trip = 55 minutes
const LIVE_SERVICE_START_MINUTES = 4 * 60; // first scheduled slot 04:00
const LIVE_SERVICE_END_MINUTES = 22 * 60;  // last scheduled slot 22:00
const LIVE_LEG_MIN_SECONDS = 30;
const LIVE_PREDEPARTURE = [
    'checkin' => -720,       // slot - 12 min
    'inspector' => -600,     // slot - 10 min
    'inspect_start' => -540, // slot - 9 min
    'inspect_end' => -300,   // slot - 5 min
    'prepared' => -180,      // slot - 3 min
    'dispatch' => 0,         // departs exactly on the slot
];

function liveResolveOptions(array $input): array
{
    $rate = static function ($value, float $default): float {
        if ($value === null || $value === '') {
            return $default;
        }
        $float = (float)$value;
        return is_finite($float) ? clampf($float, 0.0, 1.0) : $default;
    };

    $scale = (float)($input['live_scale'] ?? 1.0);
    if (!is_finite($scale) || $scale <= 0) {
        $scale = 1.0;
    }

    $tick = (int)($input['live_tick'] ?? LIVE_GPS_TICK_SECONDS);
    if ($tick < 0) {
        $tick = LIVE_GPS_TICK_SECONDS;
    }

    $liveNow = null;
    if (isset($input['live_now']) && preg_match('/^\d+$/', (string)$input['live_now'])) {
        $liveNow = (int)$input['live_now'];
    }

    return [
        'diag' => !empty($input['live_diag']),
        'scale' => $scale,
        'tick' => $tick,
        'live_now' => $liveNow,
        'advance' => !isset($input['live_advance']) || (string)$input['live_advance'] !== '0',
        'restart' => !isset($input['live_restart']) || (string)$input['live_restart'] !== '0',
        'cleanup' => !empty($input['live_cleanup']),
        'cleanup_only' => (string)($input['live_cleanup_only'] ?? '') === '1',
        'delay_rate' => $rate($input['live_delay_rate'] ?? null, 0.15),
        'breakdown_rate' => $rate($input['live_breakdown_rate'] ?? null, 0.04),
        'gps_gap_rate' => $rate($input['live_gps_gap_rate'] ?? null, 0.10),
        'advisory_rate' => $rate($input['live_advisory_rate'] ?? null, 0.12),
        'fail_rate' => $rate($input['live_fail_rate'] ?? null, 0.025),
        'traffic' => (isset($input['live_traffic']) && is_numeric($input['live_traffic']))
            ? max(1.0, (float)$input['live_traffic'])
            : null,
        'max_new' => max(1, min(LIVE_MAX_ACTIVE_TRIPS, (int)($input['live_max_new'] ?? LIVE_MAX_ACTIVE_TRIPS))),
    ];
}

function liveColumns(PDO $db, string $table): array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $map = [];
    try {
        foreach (describeTable($db, $table) as $col) {
            $map[strtolower((string)$col['Field'])] = (string)$col['Field'];
        }
    } catch (Throwable $e) {
        try {
            foreach ($db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $name = (string)($col['name'] ?? '');
                if ($name !== '') {
                    $map[strtolower($name)] = $name;
                }
            }
        } catch (Throwable $e2) {
            $map = [];
        }
    }
    return $cache[$table] = $map;
}

function liveInsert(PDO $db, string $table, array $data, bool $required = false): int
{
    $cols = liveColumns($db, $table);
    $use = [];
    $vals = [];
    foreach ($data as $key => $value) {
        $lower = strtolower((string)$key);
        if (!isset($cols[$lower])) {
            continue;
        }
        $use[] = '`' . $cols[$lower] . '`';
        $vals[] = $value;
    }
    if (!$use) {
        if ($required) {
            throw new RuntimeException("Live simulation: table `$table` has no usable columns.");
        }
        return 0;
    }
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $use) . ') VALUES ('
        . implode(',', array_fill(0, count($use), '?')) . ')';
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($vals);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        if ($required) {
            throw new RuntimeException("Live simulation: insert into `$table` failed: " . $e->getMessage());
        }
        return 0;
    }
}

function liveUpdate(PDO $db, string $table, array $data, string $idColumn, $id): bool
{
    $cols = liveColumns($db, $table);
    if (!isset($cols[strtolower($idColumn)])) {
        return false;
    }
    $sets = [];
    $vals = [];
    foreach ($data as $key => $value) {
        $lower = strtolower((string)$key);
        if (!isset($cols[$lower])) {
            continue;
        }
        $sets[] = '`' . $cols[$lower] . '` = ?';
        $vals[] = $value;
    }
    if (!$sets) {
        return false;
    }
    $vals[] = $id;
    try {
        $stmt = $db->prepare(
            'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `' . $cols[strtolower($idColumn)] . '` = ?'
        );
        return (bool)$stmt->execute($vals);
    } catch (Throwable $e) {
        return false;
    }
}

function liveRows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

function liveValue(PDO $db, string $sql, array $params = [])
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

function liveExec(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function liveDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function liveBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $y = sin(deg2rad($lng2 - $lng1)) * cos(deg2rad($lat2));
    $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2))
        - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng2 - $lng1));
    return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
}

function liveInterpolate(array $a, array $b, float $fraction): array
{
    $fraction = clampf($fraction, 0.0, 1.0);
    return [
        round($a['lat'] + ($b['lat'] - $a['lat']) * $fraction, 6),
        round($a['lng'] + ($b['lng'] - $a['lng']) * $fraction, 6),
    ];
}

/* A simulated coordinate is usable only when it is a real lat/lng inside the
   Philippine area of responsibility. Junk route_stops rows from other places
   are rejected (never clamped) so they can never enter a trip's GPS path. */
function liveValidPhCoordinate($lat, $lng): bool
{
    if (!live_map_valid_coord($lat, $lng)) {
        return false;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;
    return $lat >= LIVE_PH_LAT_MIN && $lat <= LIVE_PH_LAT_MAX
        && $lng >= LIVE_PH_LNG_MIN && $lng <= LIVE_PH_LNG_MAX;
}

/* Write one simulator accountability event. The acting real user (dispatcher,
   inspector, or driver) is recorded as the audit actor; LIVE_SIM_UA on
   user_agent is the only machine marker and is never shown in monitoring. */
function liveRecordAudit(PDO $db, string $module, string $action, array $values, string $description, array $actor = []): void
{
    $actorName = trim((string)($actor['name'] ?? 'System'));
    $actorRole = trim((string)($actor['role'] ?? 'System'));
    $actorId = is_numeric($actor['id'] ?? null) ? (int)$actor['id'] : null;

    liveInsert($db, 'audit_trail', [
        'user_id' => $actorId,
        'username' => $actorName !== '' ? $actorName : 'System',
        'user_role' => $actorRole !== '' ? $actorRole : 'System',
        'module' => $module,
        'action' => $action,
        'record_id' => (string)($values['trip_id'] ?? ($values['bus_id'] ?? '')),
        'description' => $description,
        'previous_values' => null,
        'new_values' => json_encode($values, JSON_UNESCAPED_UNICODE),
        'ip_address' => LIVE_SIM_IP,
        'user_agent' => LIVE_SIM_UA,
        'status' => 'Success',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function liveAuditState(PDO $db, string $tripId): array
{
    $state = [];
    foreach (liveRows(
        $db,
        'SELECT action, new_values FROM audit_trail WHERE record_id = ? AND user_agent = ?',
        [$tripId, LIVE_SIM_UA]
    ) as $row) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        $state[(string)$row['action']][] = is_array($payload) ? $payload : [];
    }
    return $state;
}

function liveEventExists(array $state, string $action): bool
{
    return !empty($state[$action]);
}

function liveStopEventExists(array $state, int $sequence): bool
{
    foreach ($state['Stop Reached'] ?? [] as $payload) {
        if ((int)($payload['sequence'] ?? -1) === $sequence) {
            return true;
        }
    }
    return false;
}

function liveUpsertLocation(PDO $db, string $table, string $idColumn, string $id, array $data): void
{
    $exists = (int)(liveValue($db, "SELECT COUNT(*) FROM `$table` WHERE `$idColumn` = ?", [$id]) ?? 0);
    if ($exists > 0) {
        liveUpdate($db, $table, $data, $idColumn, $id);
        return;
    }
    liveInsert($db, $table, array_merge([$idColumn => $id], $data));
}




function livePreparePolyline(array $points): ?array
{
    if (count($points) < 2) {
        return null;
    }
    $segments = [];
    $cum = [0.0];
    $total = 0.0;
    for ($i = 1; $i < count($points); $i++) {
        $distance = liveDistanceKm($points[$i - 1]['lat'], $points[$i - 1]['lng'], $points[$i]['lat'], $points[$i]['lng']);
        $segments[] = $distance;
        $total += $distance;
        $cum[] = $total;
    }
    if ($total < 0.2) {
        return null;
    }
    return ['points' => $points, 'segments' => $segments, 'cum' => $cum, 'total' => $total];
}

function livePolylineOffset(array $poly, float $lat, float $lng): float
{
    $bestKm = 0.0;
    $bestDistance = INF;
    foreach ($poly['segments'] as $i => $segment) {
        if ($segment <= 0.000001) {
            continue;
        }
        $a = $poly['points'][$i];
        $b = $poly['points'][$i + 1];
        $ax = ($lng - $a['lng']) * cos(deg2rad($a['lat'])) * 111.32;
        $ay = ($lat - $a['lat']) * 110.57;
        $bx = ($b['lng'] - $a['lng']) * cos(deg2rad($a['lat'])) * 111.32;
        $by = ($b['lat'] - $a['lat']) * 110.57;
        $lengthSquared = $bx * $bx + $by * $by;
        if ($lengthSquared <= 0) {
            continue;
        }
        $t = max(0.0, min(1.0, ($ax * $bx + $ay * $by) / $lengthSquared));
        $dx = $ax - $bx * $t;
        $dy = $ay - $by * $t;
        $distance = sqrt($dx * $dx + $dy * $dy);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $bestKm = $poly['cum'][$i] + $segment * $t;
        }
    }
    return $bestKm;
}

function livePolylineAt(array $poly, float $km): array
{
    $km = max(0.0, min((float)$poly['total'], $km));
    if ($km <= 0.0) {
        $a = $poly['points'][0];
        return ['lat' => $a['lat'], 'lng' => $a['lng'], 'heading' => 0.0];
    }
    foreach ($poly['segments'] as $i => $segment) {
        $end = $poly['cum'][$i + 1];
        if ($km <= $end + 0.000001) {
            $t = $segment > 0 ? ($km - $poly['cum'][$i]) / $segment : 0.0;
            $a = $poly['points'][$i];
            $b = $poly['points'][$i + 1];
            [$lat, $lng] = liveInterpolate($a, $b, $t);
            return ['lat' => $lat, 'lng' => $lng, 'heading' => round(liveBearing($a['lat'], $a['lng'], $b['lat'], $b['lng']), 1)];
        }
    }
    $b = $poly['points'][count($poly['points']) - 1];
    return ['lat' => $b['lat'], 'lng' => $b['lng'], 'heading' => 0.0];
}

/* A stop's usable coordinate, from its own route only. Returns null when the
   stop has no validated location; callers skip it instead of interpolating
   through it. */
function liveStopPoint(array $route, int $index): ?array
{
    $stop = $route['stops'][$index] ?? null;
    if ($stop !== null && liveValidPhCoordinate($stop['lat'] ?? null, $stop['lng'] ?? null)) {
        return ['lat' => (float)$stop['lat'], 'lng' => (float)$stop['lng']];
    }
    if (!empty($route['path']) && isset($route['stop_offsets'][$index])) {
        $point = livePolylineAt($route['path'], (float)$route['stop_offsets'][$index]);
        if (liveValidPhCoordinate($point['lat'] ?? null, $point['lng'] ?? null)) {
            return ['lat' => $point['lat'], 'lng' => $point['lng']];
        }
    }
    return null;
}

/* Build trip routes from route_stops rows with strict per-route integrity.
   Every row is validated against the Philippine bounds; bad rows and
   mislinked rows are logged and skipped, never clamped and never reused for
   another route. Each route carries its own stops, path, and stop offsets. */
function liveBuildRoutes(array $rsRows, array $fareById, array $fareByRoute, array $fareColumns): array
{
    $canonicalName = static function (string $value): string {
        return (string)preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));
    };
    /* Token-reversed route key: "Cabanatuan-Rizal" -> "rizalcabanatuan" so a
       reverse-direction fare route can reuse the same physical route_stops. */
    $reverseNameKey = static function (string $value) use ($canonicalName): string {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(trim($value))) ?: [];
        $tokens = array_values(array_filter($tokens, static function ($token) {
            return $token !== '' && !in_array($token, ['to', 'via', 'route', 'trip'], true);
        }));
        if (count($tokens) < 2) {
            return '';
        }
        return $canonicalName(implode('', array_reverse($tokens)));
    };

    $fareRouteOfId = [];
    foreach ($fareById as $fareRowId => $fareRow) {
        $fareRouteOfId[(int)$fareRowId] = trim((string)($fareRow['route_name'] ?? ''));
    }

    $rowsByCanonical = [];
    $routeNameByCanonical = [];
    $stopCoordByRoute = [];
    $stopCoordByFp = [];
    $rowsByFareId = [];
    $rejected = 0;

    /* Register stop-name / fare-id coordinates from the entries actually used
       for a route so fallback-matched routes still get their own coords. */
    $registerStopCoords = function (array $entriesToRegister, string $targetCanonical) use (&$stopCoordByRoute, &$stopCoordByFp, $canonicalName): void {
        foreach ($entriesToRegister as $entryToRegister) {
            $key = $canonicalName((string)($entryToRegister['stop_name'] ?? ''));
            if ($key !== '' && !isset($stopCoordByRoute[$targetCanonical][$key])) {
                $stopCoordByRoute[$targetCanonical][$key] = ['lat' => $entryToRegister['lat'], 'lng' => $entryToRegister['lng']];
            }
            $fpId = (int)($entryToRegister['fare_pricing_id'] ?? 0);
            if ($fpId > 0 && !isset($stopCoordByFp[$fpId])) {
                $stopCoordByFp[$fpId] = ['lat' => $entryToRegister['lat'], 'lng' => $entryToRegister['lng']];
            }
        }
    };

    foreach ($rsRows as $row) {
        if (!liveValidPhCoordinate($row['lat'] ?? null, $row['lng'] ?? null)) {
            $rejected++;
            error_log('[LiveSimulator] rejected route_stops row outside the Philippines: ' . json_encode([
                'id' => $row['row_id'] ?? null,
                'route' => (string)($row['route_name'] ?? ''),
                'stop' => (string)($row['stop_name'] ?? ''),
                'seq' => $row['seq'] ?? null,
                'lat' => $row['lat'] ?? null,
                'lng' => $row['lng'] ?? null,
            ]));
            continue;
        }
        $lat = (float)$row['lat'];
        $lng = (float)$row['lng'];
        $routeName = trim((string)($row['route_name'] ?? ''));
        $stopName = trim((string)($row['stop_name'] ?? ''));
        $fpId = (int)($row['fare_pricing_id'] ?? 0);
        $seq = (int)($row['seq'] ?? 0);
        $rowId = (int)($row['row_id'] ?? 0);

        $routeCanonical = $canonicalName($routeName);
        $fpCanonical = $fpId > 0 ? $canonicalName((string)($fareRouteOfId[$fpId] ?? '')) : '';

        if ($routeCanonical !== '') {
            $rowsByCanonical[$routeCanonical][] = [
                'row_id' => $rowId,
                'seq' => $seq,
                'lat' => $lat,
                'lng' => $lng,
                'stop_name' => $stopName,
                'route_name' => $routeName,
                /* The fare link is only kept when it names the row's own route;
                   a mismatched link cannot pull this row into another route. */
                'fare_pricing_id' => ($fpCanonical !== '' && $fpCanonical === $routeCanonical) ? $fpId : 0,
            ];
            if (!isset($routeNameByCanonical[$routeCanonical])) {
                $routeNameByCanonical[$routeCanonical] = $routeName;
            }
            $stopKey = $canonicalName($stopName);
            if ($stopKey !== '' && !isset($stopCoordByRoute[$routeCanonical][$stopKey])) {
                $stopCoordByRoute[$routeCanonical][$stopKey] = ['lat' => $lat, 'lng' => $lng];
            }
        } elseif ($fpCanonical !== '' && $fpId > 0) {
            /* No route name on the row: the fare link is its only identity. */
            $rowsByCanonical[$fpCanonical][] = [
                'row_id' => $rowId,
                'seq' => $seq,
                'lat' => $lat,
                'lng' => $lng,
                'stop_name' => $stopName,
                'route_name' => '',
                'fare_pricing_id' => $fpId,
            ];
        } else {
            $rejected++;
            error_log('[LiveSimulator] rejected unattributable route_stops row: ' . json_encode([
                'id' => $rowId, 'stop' => $stopName, 'lat' => $lat, 'lng' => $lng,
            ]));
            continue;
        }

        if ($fpId > 0) {
            $rowsByFareId[$fpId][] = [
                'row_id' => $rowId,
                'seq' => $seq,
                'lat' => $lat,
                'lng' => $lng,
                'stop_name' => $stopName,
                'route_name' => $routeName,
                'fare_pricing_id' => $fpId,
            ];
        }
        if ($fpId > 0 && $fpCanonical !== '' && ($routeCanonical === '' || $routeCanonical === $fpCanonical)) {
            if (!isset($stopCoordByFp[$fpId])) {
                $stopCoordByFp[$fpId] = ['lat' => $lat, 'lng' => $lng];
            }
        }
    }

    /* Sort each route's rows by its own sequence/display order, with the row id
       as a stable tie-breaker. */
    $groupPoints = [];
    foreach ($rowsByCanonical as $canonical => $entries) {
        usort($entries, static function (array $a, array $b): int {
            return ($a['seq'] <=> $b['seq']) ?: ($a['row_id'] <=> $b['row_id']);
        });
        $groupPoints[$canonical] = $entries;
    }

    /* Significant-token identity: route names that name the same corridor in a
       different order or with extra words resolve to the same physical group,
       e.g. fare "Cabanatuan-Rizal" vs route_stops "Rizal to Cabanatuan Route". */
    $routeTokens = static function (string $value): array {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(trim($value))) ?: [];
        return array_values(array_filter($tokens, static function ($token) {
            return $token !== '' && !in_array($token, ['to', 'via', 'route', 'trip', 'the', 'and', 'of'], true);
        }));
    };
    $fareNameByCanonical = [];
    foreach ($fareByRoute as $fareNameKey => $fareRowsValue) {
        $fareNameByCanonical[$canonicalName((string)$fareNameKey)] = (string)$fareNameKey;
    }
    $groupSignificantTokens = [];
    $groupTokenKeyToList = [];
    foreach ($groupPoints as $groupKey => $groupEntries) {
        $displayName = $routeNameByCanonical[$groupKey] ?? ($fareNameByCanonical[$groupKey] ?? '');
        if ($displayName === '') {
            $displayName = (string)$groupKey;
        }
        $tokens = $routeTokens($displayName);
        if (empty($tokens)) {
            continue;
        }
        $groupSignificantTokens[$groupKey] = $tokens;
        $sortedTokens = $tokens;
        sort($sortedTokens);
        $groupTokenKeyToList[implode('|', $sortedTokens)][] = $groupKey;
    }

    $routes = [];
    foreach ($fareByRoute as $fareRouteName => $fareRows) {
        if (count($fareRows) < 2) {
            continue;
        }
        usort($fareRows, static function (array $a, array $b): int {
            return ((int)($a['display_order'] ?? 0)) <=> ((int)($b['display_order'] ?? 0));
        });
        $canonical = $canonicalName($fareRouteName);
        $entries = $groupPoints[$canonical] ?? null;
        $reverseMatched = false;

        /* Reverse direction: the fare route "Cabanatuan-Rizal" uses the SAME
           physical route_stops group as "Rizal-Cabanatuan", traversed in the
           opposite direction. Origin/destination come from the fare rows, and
           the existing projection step reverses the path when needed. */
        if ($entries === null) {
            $reverseKey = $reverseNameKey($fareRouteName);
            if ($reverseKey !== '' && isset($groupPoints[$reverseKey])) {
                $entries = $groupPoints[$reverseKey];
                $canonical = $reverseKey;
                $reverseMatched = true;
                if (isset($routeNameByCanonical[$reverseKey])) {
                    $routeNameByCanonical[$canonicalName($fareRouteName)] = $routeNameByCanonical[$reverseKey];
                }
                $registerStopCoords($entries, $canonical);
                error_log('[LiveSimulator] reverse-matched fare route "' . $fareRouteName . '" to route_stops group "'
                    . ($routeNameByCanonical[$reverseKey] ?? $reverseKey) . '"');
            }
        }

        /* Same corridor named differently ("Cabanatuan-Rizal" vs "Rizal to
           Cabanatuan Route"): compare significant tokens as a set, then read
           the travel direction from which end matches the fare origin. */
        if ($entries === null) {
            $fareTokens = $routeTokens($fareRouteName);
            if (count($fareTokens) >= 2) {
                $sortedTokens = $fareTokens;
                sort($sortedTokens);
                $tokenKey = implode('|', $sortedTokens);
                $candidates = $groupTokenKeyToList[$tokenKey] ?? [];
                $bestTokenKey = null;
                $bestTokenCount = 0;
                foreach ($candidates as $candidateKey) {
                    $candidateCount = count($groupPoints[$candidateKey] ?? []);
                    if ($candidateCount > $bestTokenCount) {
                        $bestTokenCount = $candidateCount;
                        $bestTokenKey = $candidateKey;
                    }
                }
                if ($bestTokenKey !== null) {
                    $entries = $groupPoints[$bestTokenKey];
                    $canonical = $bestTokenKey;
                    $groupTokens = $groupSignificantTokens[$bestTokenKey] ?? [];
                    $fareFirstToken = $fareTokens[0];
                    $fareLastToken = $fareTokens[count($fareTokens) - 1];
                    $groupFirstToken = $groupTokens[0] ?? '';
                    $groupLastToken = $groupTokens[count($groupTokens) - 1] ?? '';
                    if (($fareFirstToken === $groupLastToken && $fareFirstToken !== $groupFirstToken)
                        || ($fareLastToken === $groupFirstToken && $fareLastToken !== $groupLastToken)) {
                        $reverseMatched = true;
                    }
                    if (isset($routeNameByCanonical[$bestTokenKey])) {
                        $routeNameByCanonical[$canonicalName($fareRouteName)] = $routeNameByCanonical[$bestTokenKey];
                    }
                    $registerStopCoords($entries, $canonical);
                    error_log('[LiveSimulator] token-matched fare route "' . $fareRouteName . '" to route_stops group "'
                        . ($routeNameByCanonical[$bestTokenKey] ?? $bestTokenKey) . '"'
                        . ($reverseMatched ? ' (reverse direction)' : ''));
                }
            }
        }

        if ($entries === null) {
            /* Spelling differences between fare_pricing.route_name and
               route_stops.route_name are resolved by matching WHOLE route_stops
               groups on shared stop names. Groups are never merged, so stops
               from another route can never enter this trip's path. */
            $fareStopKeys = [];
            foreach ($fareRows as $fareRow) {
                $key = $canonicalName((string)($fareRow['destination'] ?? ''));
                if ($key !== '') {
                    $fareStopKeys[$key] = true;
                }
            }
            $bestCanonical = null;
            $bestScore = 0;
            foreach ($groupPoints as $candidateCanonical => $candidateEntries) {
                $shared = 0;
                foreach ($candidateEntries as $candidateEntry) {
                    $key = $canonicalName((string)($candidateEntry['stop_name'] ?? ''));
                    if ($key !== '' && isset($fareStopKeys[$key])) {
                        $shared++;
                    }
                }
                if ($shared >= 2 && ($shared > $bestScore
                    || ($shared === $bestScore && $bestCanonical !== null
                        && count($candidateEntries) > count($groupPoints[$bestCanonical] ?? [])))) {
                    $bestCanonical = $candidateCanonical;
                    $bestScore = $shared;
                }
            }
            if ($bestCanonical !== null) {
                $canonical = $bestCanonical;
                $entries = $groupPoints[$bestCanonical];
                $registerStopCoords($entries, $canonical);
                $groupFirstKey = $canonicalName((string)($entries[0]['stop_name'] ?? ''));
                $groupLastKey = $canonicalName((string)($entries[count($entries) - 1]['stop_name'] ?? ''));
                $ourFirstKey = $canonicalName((string)($fareRows[0]['destination'] ?? ''));
                $ourLastKey = $canonicalName((string)($fareRows[count($fareRows) - 1]['destination'] ?? ''));
                if ($ourFirstKey !== '' && $ourFirstKey === $groupLastKey && $ourFirstKey !== $groupFirstKey) {
                    $reverseMatched = true;
                } elseif ($ourLastKey !== '' && $ourLastKey === $groupFirstKey && $ourLastKey !== $groupLastKey) {
                    $reverseMatched = true;
                }
                error_log('[LiveSimulator] matched fare route "' . $fareRouteName . '" to route_stops group "'
                    . ($routeNameByCanonical[$bestCanonical] ?? $bestCanonical) . '" on ' . $bestScore . ' shared stop names'
                    . ($reverseMatched ? ' (reverse direction)' : ''));
            } else {
                $entries = [];
            }
        }

        if (empty($entries)) {
            /* Rows explicitly linked to this fare route's own fare_pricing ids.
               Those ids are unique to this route, so even this path cannot pull
               in another route's stops. */
            $linkedEntries = [];
            $seenRows = [];
            foreach ($fareRows as $fareRow) {
                $linkedId = (int)($fareRow['id'] ?? 0);
                foreach (($rowsByFareId[$linkedId] ?? []) as $linkedEntry) {
                    if ($linkedEntry['row_id'] > 0 && isset($seenRows[$linkedEntry['row_id']])) {
                        continue;
                    }
                    if ($linkedEntry['row_id'] > 0) {
                        $seenRows[$linkedEntry['row_id']] = true;
                    }
                    $linkedEntries[] = $linkedEntry;
                }
            }
            if (count($linkedEntries) >= 2) {
                $names = [];
                foreach ($linkedEntries as $linkedEntry) {
                    $linkedName = trim((string)($linkedEntry['route_name'] ?? ''));
                    if ($linkedName !== '') {
                        $names[$linkedName] = ($names[$linkedName] ?? 0) + 1;
                    }
                }
                arsort($names);
                if ($names) {
                    $routeNameByCanonical[$canonicalName($fareRouteName)] = (string)array_key_first($names);
                }
                $entries = $linkedEntries;
                $canonical = $canonicalName($fareRouteName);
                $registerStopCoords($entries, $canonical);
                error_log('[LiveSimulator] matched fare route "' . $fareRouteName . '" to '
                    . count($linkedEntries) . ' fare-linked route_stops rows');
            }
        }

        if (empty($entries)) {
            /* Borrow the physical route from a sibling fare route that shares
               stop names (normally the forward direction). A WHOLE sibling
               group is used, never a per-stop mix. */
            $fareStopKeys = [];
            foreach ($fareRows as $fareRow) {
                $key = $canonicalName((string)($fareRow['destination'] ?? ''));
                if ($key !== '') {
                    $fareStopKeys[$key] = true;
                }
            }
            $bestSiblingCanonical = null;
            $bestSiblingEntries = null;
            $bestSiblingScore = 0;
            foreach ($fareByRoute as $siblingName => $siblingRows) {
                if ($siblingName === $fareRouteName) {
                    continue;
                }
                $siblingCanonical = $canonicalName($siblingName);
                $candidateEntries = $groupPoints[$siblingCanonical] ?? null;
                $candidateCanonical = $siblingCanonical;
                if ($candidateEntries === null) {
                    $siblingReverse = $reverseNameKey($siblingName);
                    if ($siblingReverse !== '' && isset($groupPoints[$siblingReverse])) {
                        $candidateEntries = $groupPoints[$siblingReverse];
                        $candidateCanonical = $siblingReverse;
                    }
                }
                if ($candidateEntries === null || count($candidateEntries) < 2) {
                    continue;
                }
                $shared = 0;
                foreach ($candidateEntries as $candidateEntry) {
                    $key = $canonicalName((string)($candidateEntry['stop_name'] ?? ''));
                    if ($key !== '' && isset($fareStopKeys[$key])) {
                        $shared++;
                    }
                }
                if ($shared >= 2 && $shared > $bestSiblingScore) {
                    $bestSiblingScore = $shared;
                    $bestSiblingCanonical = $candidateCanonical;
                    $bestSiblingEntries = $candidateEntries;
                }
            }
            if ($bestSiblingEntries !== null && $bestSiblingCanonical !== null) {
                $canonical = $bestSiblingCanonical;
                $entries = $bestSiblingEntries;
                $groupFirstKey = $canonicalName((string)($entries[0]['stop_name'] ?? ''));
                $groupLastKey = $canonicalName((string)($entries[count($entries) - 1]['stop_name'] ?? ''));
                $ourFirstKey = $canonicalName((string)($fareRows[0]['destination'] ?? ''));
                $ourLastKey = $canonicalName((string)($fareRows[count($fareRows) - 1]['destination'] ?? ''));
                if ($ourFirstKey !== '' && $ourFirstKey === $groupLastKey && $ourFirstKey !== $groupFirstKey) {
                    $reverseMatched = true;
                } elseif ($ourLastKey !== '' && $ourLastKey === $groupFirstKey && $ourLastKey !== $groupLastKey) {
                    $reverseMatched = true;
                }
                if (isset($routeNameByCanonical[$bestSiblingCanonical])) {
                    $routeNameByCanonical[$canonicalName($fareRouteName)] = $routeNameByCanonical[$bestSiblingCanonical];
                }
                $registerStopCoords($entries, $canonical);
                error_log('[LiveSimulator] borrowed route_stops group "' . ($routeNameByCanonical[$bestSiblingCanonical] ?? $bestSiblingCanonical)
                    . '" for fare route "' . $fareRouteName . '" on ' . $bestSiblingScore . ' shared stop names');
            }
        }

        /* Movement path: only this route's validated points, in sequence order.
           A point that jumps impossibly far from the previous accepted point
           is rejected so it can never be interpolated through. */
        $pathPoints = [];
        $previousPoint = null;
        foreach ($entries as $entry) {
            if ($previousPoint !== null) {
                $jump = liveDistanceKm($previousPoint['lat'], $previousPoint['lng'], $entry['lat'], $entry['lng']);
                if ($jump < 0.001) {
                    continue; /* exact duplicate of the previous point */
                }
                if ($jump > LIVE_PATH_MAX_LEG_KM) {
                    $rejected++;
                    error_log('[LiveSimulator] rejected route_stops point too far from its route: ' . json_encode([
                        'route' => $fareRouteName, 'stop' => $entry['stop_name'], 'seq' => $entry['seq'],
                        'lat' => $entry['lat'], 'lng' => $entry['lng'], 'jump_km' => round($jump, 1),
                    ]));
                    continue;
                }
            }
            $pathPoints[] = ['lat' => $entry['lat'], 'lng' => $entry['lng']];
            $previousPoint = $entry;
        }
        $path = livePreparePolyline($pathPoints);

        /* Boarding points and destinations come from fare_pricing rows only;
           their coordinates come from this route's own validated route_stops. */
        $stops = [];
        $minFare = 0.0;
        foreach ($fareRows as $index => $fareRow) {
            $name = trim((string)($fareRow['destination'] ?? ''));
            $fpId = (int)($fareRow['id'] ?? 0);
            $coord = null;
            $stopKey = $canonicalName($name);
            if ($stopKey !== '' && isset($stopCoordByRoute[$canonical][$stopKey])) {
                $coord = $stopCoordByRoute[$canonical][$stopKey];
            } elseif ($fpId > 0 && isset($stopCoordByFp[$fpId])) {
                $coord = $stopCoordByFp[$fpId];
            }
            if ($coord === null) {
                error_log('[LiveSimulator] no validated coordinate for stop "' . $name . '" on route ' . $fareRouteName);
            }
            $regular = $fareColumns['regular'] !== null ? (float)($fareRow[$fareColumns['regular']] ?? 0) : 0.0;
            if ($regular > 0 && ($minFare === 0.0 || $regular < $minFare)) {
                $minFare = $regular;
            }
            $stops[] = [
                'stop_name' => $name,
                'lat' => $coord['lat'] ?? null,
                'lng' => $coord['lng'] ?? null,
                'seq' => (int)($fareRow['display_order'] ?? ($index + 1)),
                'fare' => $fareRow,
                'display_order' => (int)($fareRow['display_order'] ?? 0),
            ];
        }
        if ($minFare <= 0.0) {
            $minFare = round(stableRange('live-fare-' . $fareRouteName, 12.0, 25.0), 2);
        }

        /* Origin and destination must never be the same stop. */
        $stopCount = count($stops);
        if ($stopCount >= 2) {
            $originKey = $canonicalName((string)($stops[0]['stop_name'] ?? ''));
            $destKey = $canonicalName((string)($stops[$stopCount - 1]['stop_name'] ?? ''));
            $sameStop = ($originKey !== '' && $originKey === $destKey);
            if (!$sameStop
                && liveValidPhCoordinate($stops[0]['lat'] ?? null, $stops[0]['lng'] ?? null)
                && liveValidPhCoordinate($stops[$stopCount - 1]['lat'] ?? null, $stops[$stopCount - 1]['lng'] ?? null)) {
                $sameStop = liveDistanceKm(
                    (float)$stops[0]['lat'],
                    (float)$stops[0]['lng'],
                    (float)$stops[$stopCount - 1]['lat'],
                    (float)$stops[$stopCount - 1]['lng']
                ) < 0.05;
            }
            if ($sameStop) {
                error_log('[LiveSimulator] route "' . $fareRouteName . '" has identical origin/destination stops; GPS disabled for it');
                $path = null;
            }
        }

        /* Project only valid stops onto the path; invalid stops receive no
           offset so livePositionAt never interpolates through them. When the
           fare rows carry no usable coordinates but the physical path is
           known, stops are placed evenly along that path (reversed when the
           route was matched in reverse) and their coordinates are taken from
           the path itself - never invented or clamped. */
        $offsets = [];
        $validIndexes = [];
        foreach ($stops as $i => $stop) {
            if (liveValidPhCoordinate($stop['lat'] ?? null, $stop['lng'] ?? null)) {
                $validIndexes[] = $i;
            }
        }
        if ($path !== null && count($validIndexes) >= 2) {
            $project = static function (array $poly, array $stopList, array $indexes): array {
                $raw = [];
                foreach ($indexes as $i) {
                    $raw[$i] = livePolylineOffset($poly, (float)$stopList[$i]['lat'], (float)$stopList[$i]['lng']);
                }
                return $raw;
            };
            $raw = $project($path, $stops, $validIndexes);
            $isIncreasing = static function (array $values): bool {
                $previous = -INF;
                foreach ($values as $value) {
                    if ($value < $previous - 0.001) {
                        return false;
                    }
                    $previous = $value;
                }
                return true;
            };
            if (!$isIncreasing($raw)) {
                /* A reverse-direction fare route (Cabanatuan -> Rizal) projects
                   onto the forward route_stops polyline as a decreasing
                   sequence. Reverse the physical path so the bus travels the
                   same route in the opposite direction instead of sorting the
                   offsets, which would silently flip the journey. */
                $reversedPath = livePreparePolyline(array_reverse($path['points']));
                $rawReversed = $reversedPath !== null ? $project($reversedPath, $stops, $validIndexes) : [];
                if ($reversedPath !== null && $isIncreasing($rawReversed)) {
                    $path = $reversedPath;
                    $raw = $rawReversed;
                } else {
                    /* Noisy/partial geometry: keep offsets in stop order so the
                       trip still runs in the intended direction. */
                    $sorted = array_values($raw);
                    sort($sorted);
                    $i = 0;
                    foreach (array_keys($raw) as $index) {
                        $raw[$index] = $sorted[$i++];
                    }
                }
            }
            $base = min($raw);
            foreach ($raw as $index => $offset) {
                $offsets[$index] = max(0.0, $offset - $base);
            }
        } elseif ($path !== null && $stopCount >= 2) {
            if ($reverseMatched) {
                $path = livePreparePolyline(array_reverse($path['points']));
            }
            if ($path !== null) {
                foreach ($stops as $i => $stop) {
                    $offsets[$i] = $path['total'] * ($i / max(1, $stopCount - 1));
                    if (!liveValidPhCoordinate($stop['lat'] ?? null, $stop['lng'] ?? null)) {
                        $point = livePolylineAt($path, $offsets[$i]);
                        if (liveValidPhCoordinate($point['lat'] ?? null, $point['lng'] ?? null)) {
                            $stops[$i]['lat'] = $point['lat'];
                            $stops[$i]['lng'] = $point['lng'];
                        }
                    }
                }
            }
        }

        /* bus_trips.route_name drives the admin map: it must be the name the
           route_stops rows use so getRouteStops.php returns the real path. */
        $mapName = $routeNameByCanonical[$canonical] ?? $fareRouteName;
        $route = [
            'route_name' => $mapName,
            'fare_route_name' => $fareRouteName,
            'path_route_name' => isset($routeNameByCanonical[$canonical]) ? $mapName : null,
            'stops_route_name' => isset($routeNameByCanonical[$canonical]) ? $mapName : null,
            'stops' => $stops,
            'min_fare' => $minFare,
            'route_id' => null,
            'path' => $path,
            'path_points' => $pathPoints,
            'stop_offsets' => $offsets,
        ];

        $routes[$mapName] = $route;
        $routes[$fareRouteName] = $route;
    }

    return [
        'routes' => $routes,
        'rejected' => $rejected,
    ];
}

/* Live context: idle drivers/buses, inspector roster, real routes built from
   route_stops linked to fare_pricing, terminals, and a reusable pool of real
   passengers. */
function buildLiveContext(PDO $db): array
{
    $today = date('Y-m-d');
    $now = time();

    $busyBuses = [];
    $busyDrivers = [];
    foreach (liveRows(
        $db,
        "SELECT bus_id, driver_id FROM bus_trips
         WHERE status IN ('Scheduled','Departed','Interrupted')"
    ) as $row) {
        if ((string)$row['bus_id'] !== '') { $busyBuses[busKeyFromReference($db, $row['bus_id'])] = true; }
        if (!empty($row['driver_id'])) { $busyDrivers[(string)$row['driver_id']] = true; }
    }

    /* Drivers/Buses already owned by this simulator (from its plan events) may
       be reused once their trip is finished; real drivers with fresh GPS are
       left alone so live operational data is never overwritten. */
    $simDrivers = [];
    $simBuses = [];
    foreach (liveRows(
        $db,
        'SELECT new_values FROM audit_trail WHERE action IN (?,?) AND user_agent = ?',
        ['Live Trip Planned', 'Simulator Trip Planned', LIVE_SIM_UA]
    ) as $row) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        if (is_array($payload)) {
            if (!empty($payload['driver_id'])) { $simDrivers[(string)$payload['driver_id']] = true; }
            if (!empty($payload['bus_id'])) { $simBuses[(string)$payload['bus_id']] = true; }
        }
    }

    foreach (liveRows($db, 'SELECT driver_id, MAX(updated_at) AS at FROM driver_current_location GROUP BY driver_id') as $row) {
        $at = !empty($row['at']) ? strtotime((string)$row['at']) : 0;
        if ($at && ($now - $at) < 900 && !isset($simDrivers[(string)$row['driver_id']])) {
            $busyDrivers[(string)$row['driver_id']] = true;
        }
    }

    $drivers = [];
    foreach (liveRows(
        $db,
        "SELECT driver_id, full_name FROM drivers
         WHERE COALESCE(status,'') NOT IN ('Archived','Inactive') ORDER BY driver_id"
    ) as $row) {
        $did = (string)$row['driver_id'];
        if ($did !== '' && !isset($busyDrivers[$did])) {
            $drivers[$did] = $row;
        }
    }

    $buses = [];
    foreach (liveRows($db, 'SELECT * FROM buses') as $row) {
        $bid = (string)($row['bus_id'] ?? '');
        if ($bid === '' || isset($busyBuses[$bid]) || busStatusUnavailable($row)) {
            continue;
        }
        if (!fleetScopeAllowsBus($bid)) {
            continue;   // the simulator dispatches only the configured allow-list
        }
        $buses[$bid] = $row;
    }

    $assign = [];
    foreach (liveRows($db, 'SELECT driver_id, bus_id FROM driver_bus_assignment') as $row) {
        $did = (string)$row['driver_id'];
        $bid = (string)$row['bus_id'];
        if ($did !== '' && $bid !== '') {
            $assign[$did][] = $bid;
        }
    }

    /* Real dispatchers (inspector_accounts.role='dispatcher') are the only
       valid dispatcher values; nothing fake is ever generated. */
    $dispatchers = [];
    foreach (liveRows(
        $db,
        "SELECT ia.inspector_id, COALESCE(i.inspector_name, ia.inspector_id) AS dispatcher_name
         FROM inspector_accounts ia
         LEFT JOIN inspectors i ON i.account_id = ia.account_id
         WHERE LOWER(COALESCE(ia.role,'')) = 'dispatcher'
           AND LOWER(COALESCE(ia.status,'')) = 'active'
         ORDER BY ia.inspector_id"
    ) as $row) {
        if ((string)$row['inspector_id'] !== '') {
            $dispatchers[] = $row;
        }
    }
    if (!$dispatchers) {
        foreach (liveRows(
            $db,
            "SELECT ia.inspector_id, COALESCE(i.inspector_name, ia.inspector_id) AS dispatcher_name
             FROM inspector_accounts ia
             LEFT JOIN inspectors i ON i.account_id = ia.account_id
             WHERE LOWER(COALESCE(ia.role,'')) = 'dispatcher'
             ORDER BY ia.inspector_id"
        ) as $row) {
            if ((string)$row['inspector_id'] !== '') {
                $dispatchers[] = $row;
            }
        }
    }
    $dispatcherById = [];
    foreach ($dispatchers as $dispatcherRow) {
        $dispatcherById[(string)$dispatcherRow['inspector_id']] = (string)$dispatcherRow['dispatcher_name'];
    }

    $inspectorQuery = static function (bool $activeOnly) use ($db): array {
        $sql = "SELECT ia.inspector_id, COALESCE(i.inspector_name, ia.inspector_id) AS inspector_name
                FROM inspector_accounts ia
                LEFT JOIN inspectors i ON i.account_id = ia.account_id
                WHERE LOWER(COALESCE(ia.role,'')) = 'inspector'"
            . ($activeOnly ? " AND LOWER(COALESCE(ia.status,'')) = 'active'" : '')
            . ' ORDER BY ia.inspector_id';
        $list = [];
        foreach (liveRows($db, $sql) as $row) {
            if ((string)$row['inspector_id'] !== '') {
                $list[] = $row;
            }
        }
        return $list;
    };
    $inspectors = $inspectorQuery(true);
    if (!$inspectors) {
        $inspectors = $inspectorQuery(false);
    }

    $fareColsMap = liveColumns($db, 'fare_pricing');
    $fareColumns = [
        'regular' => $fareColsMap['regular_fare'] ?? null,
        'student' => $fareColsMap['student_fare'] ?? null,
        'senior' => $fareColsMap['senior_fare'] ?? null,
        'pwd' => $fareColsMap['pwd_fare'] ?? null,
        'special' => $fareColsMap['special_fare'] ?? null,
    ];

    $fareById = [];
    $fareByRoute = [];
    foreach (liveRows($db, 'SELECT * FROM fare_pricing') as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) { $fareById[$id] = $row; }
        $rn = trim((string)($row['route_name'] ?? ''));
        if ($rn !== '') { $fareByRoute[$rn][] = $row; }
    }

    /* Build routes from route_stops linked to fare_pricing rows. The stop's
       parent fare route is the authoritative trip route, so route-name spelling
       differences between route_stops and fare_pricing cannot break them. */
    $routes = [];
    $rsCols = liveColumns($db, 'route_stops');
    $rsRows = [];
    if (isset($rsCols['latitude'], $rsCols['longitude'])) {
        $nameExpr = isset($rsCols['stop_name'])
            ? '`stop_name`'
            : (isset($rsCols['destination']) ? '`destination`' : 'NULL');
        $routeExpr = isset($rsCols['route_name']) ? '`route_name`' : 'NULL';
        $seqExpr = isset($rsCols['sequence_order'])
            ? '`sequence_order`'
            : (isset($rsCols['display_order']) ? '`display_order`' : '`id`');
        $fpExpr = isset($rsCols['fare_pricing_id']) ? '`fare_pricing_id`' : 'NULL';
        $idExpr = isset($rsCols['id']) ? '`id`' : 'NULL';

        $rsRows = liveRows(
            $db,
            "SELECT $idExpr AS row_id, $routeExpr AS route_name, $nameExpr AS stop_name, $seqExpr AS seq,
                    $fpExpr AS fare_pricing_id, `latitude` AS lat, `longitude` AS lng
             FROM `route_stops`
             WHERE `latitude` IS NOT NULL AND `longitude` IS NOT NULL
               AND COALESCE(`latitude`, 0) <> 0 AND COALESCE(`longitude`, 0) <> 0
             ORDER BY $seqExpr ASC"
        );
    }

    /* Route geography is built strictly per route from validated route_stops
       rows; see liveBuildRoutes(). No global stop-name/fare-id coordinate map
       exists anymore, so another route's rows can never leak in. */
    $builtRoutes = liveBuildRoutes($rsRows, $fareById, $fareByRoute, $fareColumns);
    $routes = $builtRoutes['routes'];

    /* The buses table usually carries no route association, so bind each
       allow-listed dispatch bus to a coordinate route deterministically.
       Sorted bus order over sorted route keys gives distinct routes across
       the four buses while the route list lasts. */
    $routeKeys = array_keys($routes);
    if ($routeKeys) {
        $allowedBusIds = array_keys($buses);
        sort($allowedBusIds, SORT_STRING);
        foreach ($allowedBusIds as $idx => $bid) {
            if (trim((string)($buses[$bid]['route_name'] ?? '')) !== '') {
                continue;
            }
            $buses[$bid]['route_name'] = (string)$routeKeys[$idx % count($routeKeys)];
        }
    }

    $terminals = liveRows($db, 'SELECT id, latitude, longitude FROM terminals');

    $pool = [];
    $pCols = liveColumns($db, 'passengers');
    if ($pCols && isset($pCols['id'])) {
        $selects = ['`' . $pCols['id'] . '` AS id'];
        foreach (['full_name', 'passenger_code', 'passenger_type'] as $logical) {
            if (isset($pCols[$logical])) {
                $selects[] = '`' . $pCols[$logical] . '` AS ' . $logical;
            }
        }
        if (!isset($pCols['full_name']) && isset($pCols['name'])) {
            $selects[] = '`' . $pCols['name'] . '` AS full_name';
        }
        if (!isset($pCols['passenger_type']) && isset($pCols['type'])) {
            $selects[] = '`' . $pCols['type'] . '` AS passenger_type';
        }
        foreach (liveRows($db, 'SELECT ' . implode(', ', $selects) . ' FROM `passengers` ORDER BY id ASC LIMIT 200') as $row) {
            if ((int)($row['id'] ?? 0) > 0) {
                $pool[] = $row;
            }
        }
    }

    return [
        'db' => $db,
        'today' => $today,
        'drivers' => $drivers,
        'buses' => $buses,
        'assign' => $assign,
        'inspectors' => $inspectors,
        'dispatchers' => $dispatchers,
        'dispatcher_by_id' => $dispatcherById,
        'routes' => $routes,
        'fare_columns' => $fareColumns,
        'terminals' => $terminals,
        'passenger_pool' => $pool,
        'passenger_cursor' => 0,
        'route_cursor' => 0,
    ];
}

/* Deterministic plan for one trip id: every request regenerates the same
   schedule, passenger list, and variation flags from stable hashes. */
function liveSimClock(PDO $db, array $opts): int
{
    $scale = max(0.001, (float)($opts['scale'] ?? 1.0));

    /* Anchor the simulated clock on the newest simulator trip so it keeps
       advancing from where the last run left off. Only the real-time catch-up
       is capped (15 real minutes), so a freshly created trip advances tick by
       tick instead of being fast-forwarded straight past its destination. */
    $tripIds = liveSimTripIds($db);
    if (!empty($tripIds)) {
        $latestId = max(array_map('intval', $tripIds));
        $latest = liveRows($db, 'SELECT id, created_at FROM bus_trips WHERE id = ? LIMIT 1', [$latestId]);
        if (!empty($latest)) {
            $slot = liveTripSlot($db, (string)$latestId);
            $createdReal = strtotime((string)($latest[0]['created_at'] ?? ''));
            if ($slot !== null && $createdReal !== false) {
                $advance = min(900, max(0, time() - $createdReal)) * $scale;
                return (int)round($slot + $advance);
            }
        }
    }

    return time();
}

/* Trip ids the simulator owns: tracked by its own audit marker, plus legacy
   rows that still carry the old bus_trips.dispatcher_id marker. */
function liveSimTripIds(PDO $db): array
{
    $ids = [];
    foreach (liveRows(
        $db,
        "SELECT record_id FROM audit_trail
         WHERE user_agent = ? AND action IN ('Live Trip Planned','Simulator Trip Planned')
           AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)",
        [LIVE_SIM_UA]
    ) as $row) {
        $recordId = (string)($row['record_id'] ?? '');
        if (preg_match('/^\d+$/', $recordId)) {
            $ids[$recordId] = true;
        }
    }
    foreach (liveRows(
        $db,
        "SELECT id FROM bus_trips WHERE dispatcher_id = ? AND (trip_date = ? OR status IN ('Scheduled','Departed'))",
        [LIVE_SIM_MARKER, date('Y-m-d')]
    ) as $row) {
        $ids[(string)$row['id']] = true;
    }
    return array_keys($ids);
}

/* Pick one real dispatcher deterministically for a trip/slot. */
function livePickDispatcher(array &$ctx, string $key): array
{
    $count = count($ctx['dispatchers'] ?? []);
    if ($count === 0) {
        throw new RuntimeException('Live simulation needs a real dispatcher account (inspector_accounts.role = dispatcher).');
    }
    $index = (int)(crc32('live-dispatcher-' . $key) % $count);
    return $ctx['dispatchers'][$index];
}

/* Pick two distinct real inspectors deterministically for a trip. */
function livePickInspectors(array &$ctx, string $key): array
{
    $count = count($ctx['inspectors'] ?? []);
    if ($count < 2) {
        throw new RuntimeException('Live simulation needs at least two active inspector accounts (found ' . $count . ').');
    }
    $first = (int)(crc32('live-inspector-a-' . $key) % $count);
    $second = (int)(crc32('live-inspector-b-' . $key) % $count);
    if ($second === $first) {
        $second = ($first + 1) % $count;
    }
    return [$ctx['inspectors'][$first], $ctx['inspectors'][$second]];
}

/* Real dispatcher actor for audit rows: name resolved from the roster or DB. */
function liveDispatcherActor(PDO $db, array &$ctx, string $dispatcherId): array
{
    $id = trim($dispatcherId);
    if ($id !== '' && !empty($ctx['dispatcher_by_id'][$id])) {
        return ['id' => null, 'name' => (string)$ctx['dispatcher_by_id'][$id], 'role' => 'Dispatcher'];
    }
    if ($id !== '') {
        $name = trim((string)(liveValue(
            $db,
            'SELECT COALESCE(i.inspector_name, ia.inspector_id)
             FROM inspector_accounts ia
             LEFT JOIN inspectors i ON i.account_id = ia.account_id
             WHERE ia.inspector_id = ? LIMIT 1',
            [$id]
        ) ?? ''));
        if ($name !== '') {
            return ['id' => null, 'name' => $name, 'role' => 'Dispatcher'];
        }
    }
    foreach (($ctx['dispatchers'] ?? []) as $candidate) {
        return ['id' => null, 'name' => (string)$candidate['dispatcher_name'], 'role' => 'Dispatcher'];
    }
    return ['id' => null, 'name' => 'System', 'role' => 'System'];
}

function liveDriverName(PDO $db, string $driverId): string
{
    $name = trim((string)(liveValue($db, 'SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1', [$driverId]) ?? ''));
    return $name !== '' ? $name : $driverId;
}

function livePlanMeta(int $tripId, array $route, array $opts, int $slotTs): array
{
    $key = 'live-' . $tripId . '-' . $slotTs;

    /* Controlled traffic variation. 70% of departures run the no-traffic
       baseline (exactly 55 minutes); the rest slow down modestly. */
    $trafficRoll = stableUnit($key . '-traffic');
    if ($opts['traffic'] !== null) {
        $trafficFactor = (float)$opts['traffic'];
    } elseif ($trafficRoll < 0.70) {
        $trafficFactor = 1.0;
    } elseif ($trafficRoll < 0.90) {
        $trafficFactor = stableRange($key . '-traffic-mid', 1.06, 1.18);
    } else {
        $trafficFactor = stableRange($key . '-traffic-high', 1.20, 1.35);
    }
    $trafficFactor = round(max(1.0, $trafficFactor), 3);
    $totalSeconds = (int)round(LIVE_BASE_TRIP_SECONDS * $trafficFactor);

    $stops = $route['stops'];
    $legDistances = [];
    $totalKm = 0.0;
    for ($i = 0; $i < count($stops) - 1; $i++) {
        $fromValid = liveValidPhCoordinate($stops[$i]['lat'] ?? null, $stops[$i]['lng'] ?? null);
        $toValid = liveValidPhCoordinate($stops[$i + 1]['lat'] ?? null, $stops[$i + 1]['lng'] ?? null);
        if ($fromValid && $toValid) {
            $km = max(0.3, liveDistanceKm(
                (float)$stops[$i]['lat'],
                (float)$stops[$i]['lng'],
                (float)$stops[$i + 1]['lat'],
                (float)$stops[$i + 1]['lng']
            ));
        } else {
            /* A stop without a validated coordinate gets a nominal leg so the
               schedule stays sane; livePositionAt never interpolates through it. */
            $km = LIVE_NOMINAL_LEG_KM;
        }
        $legDistances[] = $km;
        $totalKm += $km;
    }

    $legs = [];
    $arrivals = [$slotTs];
    $assigned = 0;
    $legCount = count($legDistances);
    foreach ($legDistances as $i => $km) {
        $share = $totalKm > 0 ? $km / $totalKm : (1.0 / max(1, $legCount));
        if ($i === $legCount - 1) {
            $sec = max(LIVE_LEG_MIN_SECONDS, $totalSeconds - $assigned);
        } else {
            $sec = max(LIVE_LEG_MIN_SECONDS, (int)round($totalSeconds * $share));
        }
        $assigned += $sec;
        $speed = $sec > 0 ? $km / ($sec / 3600) : 0.0;
        $legs[] = [
            'km' => round($km, 3),
            'sec' => $sec,
            'dwell' => 0,
            'speed' => min(LIVE_MAX_SPEED_KPH, round(is_finite($speed) ? max(0.0, $speed) : 0.0, 1)),
        ];
        $arrivals[] = $arrivals[$i] + $sec;
    }

    $arrival = $slotTs + $totalSeconds;
    if (count($arrivals) > 1) {
        $arrivals[count($arrivals) - 1] = $arrival;
    }

    /* Inspector handoff point: the middle route stop from route_stops, so the
       transfer happens on the real route rather than at the terminal. */
    $stopCount = count($stops);
    if ($stopCount >= 3) {
        $handoffIndex = max(1, (int)floor(($stopCount - 1) / 2));
        $handoffAt = (int)($arrivals[$handoffIndex] ?? ($slotTs + (int)round($totalSeconds * 0.5)));
    } else {
        $handoffIndex = max(0, $stopCount - 2);
        $handoffAt = $slotTs + (int)round($totalSeconds * 0.5);
    }
    $handoffAt = min($handoffAt, $arrival - LIVE_LEG_MIN_SECONDS);

    $inspectionFailed = stableUnit($key . '-inspect-fail') < $opts['fail_rate'];
    $advisory = stableUnit($key . '-advisory') < $opts['advisory_rate'];
    $breakdown = !$inspectionFailed && stableUnit($key . '-breakdown') < $opts['breakdown_rate'];
    $gpsGap = stableUnit($key . '-gps-gap') < $opts['gps_gap_rate'];

    $offsets = [];
    foreach (LIVE_PREDEPARTURE as $phase => $deltaSeconds) {
        $offsets[$phase] = $slotTs + $deltaSeconds;
    }

    $breakdownAt = null;
    if ($breakdown) {
        $breakdownAt = $slotTs + (int)round($totalSeconds * stableRange($key . '-breakdown-at', 0.30, 0.70));
    }
    $gpsGapStart = $gpsGap
        ? $slotTs + (int)round($totalSeconds * stableRange($key . '-gap-at', 0.30, 0.60))
        : null;
    $gpsGapSeconds = $gpsGap ? (int)round(stableRange($key . '-gap-length', 180, 420)) : 0;

    return [
        'key' => $key,
        'slot' => $slotTs,
        'traffic_factor' => $trafficFactor,
        'base_seconds' => LIVE_BASE_TRIP_SECONDS,
        'total_seconds' => $totalSeconds,
        'offsets' => $offsets,
        'legs' => $legs,
        'arrivals' => $arrivals,
        'arrival' => $arrival,
        'travel' => $totalSeconds,
        'delayed' => $trafficFactor > 1.15,
        'delay_seconds' => max(0, $totalSeconds - LIVE_BASE_TRIP_SECONDS),
        'inspection_failed' => $inspectionFailed,
        'advisory' => $advisory,
        'breakdown' => $breakdown,
        'breakdown_at' => $breakdownAt,
        'gps_gap' => $gpsGap,
        'gps_gap_start' => $gpsGapStart,
        'gps_gap_seconds' => $gpsGapSeconds,
        'handoff_index' => $handoffIndex,
        'handoff_at' => $handoffAt,
    ];
}

function livePickPassenger(array &$ctx, int $tripId, int $slot, array &$used): array
{
    $db = $ctx['db'];
    $pool = $ctx['passenger_pool'];
    if ($pool) {
        $count = count($pool);
        $start = (int)(crc32('live-pax-' . $tripId . '-' . $slot) % $count);
        for ($k = 0; $k < $count; $k++) {
            $candidate = $pool[($start + $k) % $count];
            $candidateId = (int)($candidate['id'] ?? 0);
            if ($candidateId > 0 && !isset($used[$candidateId])) {
                $used[$candidateId] = true;
                return $candidate;
            }
        }
    }

    $code = 'SIM-' . $tripId . '-' . str_pad((string)$slot, 3, '0', STR_PAD_LEFT);
    $existing = liveRows($db, 'SELECT id, full_name, passenger_code, passenger_type FROM passengers WHERE passenger_code = ? LIMIT 1', [$code]);
    if (!empty($existing)) {
        return $existing[0];
    }
    $name = 'Simulated Passenger ' . $slot;
    $type = pickPType();
    $id = liveInsert($db, 'passengers', [
        'full_name' => $name,
        'name' => $name,
        'passenger_code' => $code,
        'passenger_type' => $type,
        'type' => $type,
    ]);
    return $id > 0 ? ['id' => $id, 'full_name' => $name, 'passenger_code' => $code, 'passenger_type' => $type] : [];
}

function livePassengerPlan(int $tripId, array $route, array $meta, array &$ctx): array
{
    $stops = $route['stops'];
    $count = count($stops);
    $key = $meta['key'];
    $target = (int)round(stableRange($key . '-pax-count', LIVE_PASSENGER_MIN, LIVE_PASSENGER_MAX));

    $plan = [];
    $usedPassengers = [];
    for ($i = 0; $i < $target; $i++) {
        $board = min($count - 2, (int)floor(stableUnit($key . '-pax-' . $i . '-board') ** 2 * ($count - 1)));
        $board = max(0, $board);
        $remaining = max(0, $count - 2 - $board);
        $offset = (int)floor(sqrt(stableUnit($key . '-pax-' . $i . '-dest')) * ($remaining + 1));
        $dest = min($count - 1, $board + 1 + $offset);

        $type = stablePickWeighted([
            'Regular' => 70,
            'Student' => 10,
            'Senior' => 8,
            'PWD' => 5,
            'Beneficiary' => 4,
            'Cooperative' => 3,
        ], $key . '-pax-' . $i . '-type');

        $fare = resolveSegmentFare(
            $stops[$board]['fare'] ?? $stops[$board],
            $stops[$dest]['fare'] ?? $stops[$dest],
            fareColumnForType($type, $ctx['fare_columns']),
            $ctx['fare_columns']['regular'],
            (float)$route['min_fare']
        );
        if ($fare <= 0.0) {
            $fare = (float)$route['min_fare'];
        }

        $passenger = livePickPassenger($ctx, $tripId, $i, $usedPassengers);
        if (empty($passenger)) {
            continue;
        }

        $method = stableUnit($key . '-pax-' . $i . '-method') < 0.78 ? 'cash' : 'gcash';
        $paid = stableUnit($key . '-pax-' . $i . '-paid') < 0.92;

        $boardingSim = $meta['arrivals'][$board] + (int)round(stableRange($key . '-pax-' . $i . '-board-at', 10, 90));
        $dropoffSim = $meta['arrivals'][$dest] + (int)round(stableRange($key . '-pax-' . $i . '-drop-at', 5, 60));

        $plan[] = [
            'slot' => $i,
            'passenger' => $passenger,
            'board' => $board,
            'dest' => $dest,
            'boarding_sim' => min($meta['arrival'] - 30, $boardingSim),
            'dropoff_sim' => min($meta['arrival'], $dropoffSim),
            'fare' => round($fare, 2),
            'type' => $type,
            'method' => $method,
            'paid' => $paid,
        ];
    }
    return $plan;
}

/* Position on the route at a simulated elapsed second. Interpolation happens
   strictly between two consecutive VALID stop coordinates of this route, so
   an invalid/foreign stop can never be interpolated through. Speed is derived
   from the actual distance between those two route points and the simulated
   leg time, capped at a realistic bus speed. */
function livePositionAt(array $route, array $meta, int $now): array
{
    $stops = $route['stops'];
    $count = count($stops);
    $dispatch = (int)$meta['offsets']['dispatch'];
    $hasPath = !empty($route['path']) && !empty($route['stop_offsets']);

    $validPoint = static function (int $index) use ($route): ?array {
        $stop = $route['stops'][$index] ?? null;
        if ($stop === null || !liveValidPhCoordinate($stop['lat'] ?? null, $stop['lng'] ?? null)) {
            return null;
        }
        return ['lat' => (float)$stop['lat'], 'lng' => (float)$stop['lng']];
    };
    $validOffset = static function (int $index) use ($route): ?float {
        if (!isset($route['stop_offsets'][$index])) {
            return null;
        }
        return (float)$route['stop_offsets'][$index];
    };

    $validIndices = [];
    for ($i = 0; $i < $count; $i++) {
        if ($validPoint($i) === null) {
            continue;
        }
        if ($hasPath && $validOffset($i) === null) {
            continue;
        }
        $validIndices[] = $i;
    }
    if (empty($validIndices)) {
        return [
            'lat' => null, 'lng' => null, 'speed' => 0.0, 'heading' => 0.0,
            'stop_index' => 0, 'next_index' => 0, 'at_stop' => false, 'valid' => false,
        ];
    }

    $positionAtStop = static function (int $index) use ($route, $validPoint, $validOffset, $hasPath): array {
        if ($hasPath && $validOffset($index) !== null) {
            $point = livePolylineAt($route['path'], $validOffset($index));
            return [
                'lat' => $point['lat'], 'lng' => $point['lng'], 'speed' => 0.0,
                'heading' => $point['heading'], 'stop_index' => $index, 'next_index' => $index,
                'at_stop' => true, 'valid' => true,
            ];
        }
        $stop = $validPoint($index);
        return [
            'lat' => $stop['lat'], 'lng' => $stop['lng'], 'speed' => 0.0,
            'heading' => 0.0, 'stop_index' => $index, 'next_index' => $index,
            'at_stop' => true, 'valid' => true,
        ];
    };

    $first = $validIndices[0];
    $last = $validIndices[count($validIndices) - 1];

    if ($now <= $dispatch) {
        $position = $positionAtStop($first);
        $position['next_index'] = $first;
        return $position;
    }

    $currentIndex = $first;
    for ($k = 0; $k < count($validIndices) - 1; $k++) {
        $fromIndex = $validIndices[$k];
        $toIndex = $validIndices[$k + 1];
        $arrive = (int)$meta['arrivals'][$fromIndex];
        $next = (int)$meta['arrivals'][$toIndex];
        if ($now < $arrive) {
            return $positionAtStop($currentIndex);
        }
        if ($now >= $next) {
            $currentIndex = $toIndex;
            continue;
        }
        $fraction = clampf(($now - $arrive) / max(1, $next - $arrive), 0.0, 1.0);
        $legSeconds = max(1, $next - $arrive);

        if ($hasPath) {
            $from = (float)$route['stop_offsets'][$fromIndex];
            $to = (float)$route['stop_offsets'][$toIndex];
            $point = livePolylineAt($route['path'], $from + ($to - $from) * $fraction);
            $speed = ($to - $from) / ($legSeconds / 3600.0);
            return [
                'lat' => $point['lat'], 'lng' => $point['lng'],
                'speed' => min(LIVE_MAX_SPEED_KPH, round(is_finite($speed) ? max(0.0, $speed) : 0.0, 1)),
                'heading' => $point['heading'], 'stop_index' => $fromIndex, 'next_index' => $toIndex,
                'at_stop' => false, 'valid' => true,
            ];
        }

        $a = $validPoint($fromIndex);
        $b = $validPoint($toIndex);
        [$lat, $lng] = liveInterpolate($a, $b, $fraction);
        $km = liveDistanceKm($a['lat'], $a['lng'], $b['lat'], $b['lng']);
        $speed = $km / ($legSeconds / 3600.0);
        return [
            'lat' => $lat, 'lng' => $lng,
            'speed' => min(LIVE_MAX_SPEED_KPH, round(is_finite($speed) ? max(0.0, $speed) : 0.0, 1)),
            'heading' => round(liveBearing($a['lat'], $a['lng'], $b['lat'], $b['lng']), 1),
            'stop_index' => $fromIndex, 'next_index' => $toIndex,
            'at_stop' => false, 'valid' => true,
        ];
    }

    return $positionAtStop($currentIndex);
}

function liveLatestHistory(PDO $db, string $driverId): ?array
{
    $rows = liveRows(
        $db,
        'SELECT id, latitude, longitude, created_at FROM driver_location_history WHERE driver_id = ? ORDER BY id DESC LIMIT 1',
        [$driverId]
    );
    return $rows[0] ?? null;
}

function liveEnsureGps(PDO $db, string $driverId, string $busId, array $position, int $now, array $meta, int $tick): int
{
    if ($meta['gps_gap'] && $meta['gps_gap_start'] !== null
        && $now >= $meta['gps_gap_start']
        && $now <= $meta['gps_gap_start'] + $meta['gps_gap_seconds']) {
        return 0;
    }

    if (($position['valid'] ?? true) === false
        || !liveValidPhCoordinate($position['lat'] ?? null, $position['lng'] ?? null)) {
        error_log('[LiveSimulator] rejected GPS position outside the route area for driver ' . $driverId . ': ' . json_encode([
            'lat' => $position['lat'] ?? null,
            'lng' => $position['lng'] ?? null,
        ]));
        return 0;
    }

    $now = time();
    $last = liveLatestHistory($db, $driverId);
    $append = true;
    if ($last !== null) {
        $lastTs = strtotime((string)$last['created_at']);
        $movedKm = liveDistanceKm(
            (float)$last['latitude'], (float)$last['longitude'],
            (float)$position['lat'], (float)$position['lng']
        );
        $append = ($lastTs === false) || (($now - $lastTs) >= $tick) || ($movedKm >= 0.05);
    }

    if ($append) {
        liveInsert($db, 'driver_location_history', [
            'driver_id' => $driverId,
            'bus_id' => $busId,
            'latitude' => $position['lat'],
            'longitude' => $position['lng'],
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);
        $written = 1;
    } else {
        $written = 0;
    }

    liveUpsertLocation($db, 'driver_current_location', 'driver_id', $driverId, [
        'bus_id' => $busId,
        'latitude' => $position['lat'],
        'longitude' => $position['lng'],
        'speed' => round(clampf((float)($position['speed'] ?? 0), 0.0, LIVE_MAX_SPEED_KPH), 1),
        'heading' => $position['heading'],
        'updated_at' => date('Y-m-d H:i:s', $now),
    ]);

    return $written;
}

function liveTripPassengerRows(PDO $db, string $tripId): array
{
    return liveRows(
        $db,
        'SELECT id, passenger_id, fare, boarding_time, dropoff_time FROM passenger_trips WHERE trip_no = ? ORDER BY id ASC',
        [$tripId]
    );
}

function liveEnsurePassengers(PDO $db, array $plan, array $meta, array $route, string $tripId, string $busId, string $driverId, string $inspectorIdBefore, string $inspectorIdAfter, int $handoffAt, string $updatedBy, int $now): array
{
    $stops = $route['stops'];
    /* Today's live records stay pending until finance actually processes
       them; only past operating dates settle into submitted/verified. */
    $holdRemit = remittanceDayState(date('Y-m-d'), date('Y-m-d')) === 'pending';
    $existing = [];
    foreach (liveTripPassengerRows($db, $tripId) as $row) {
        $existing[(int)$row['passenger_id']] = $row;
    }

    foreach ($plan as $slot) {
        $passenger = $slot['passenger'];
        $pid = (int)($passenger['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $expectedCash = ($slot['method'] === 'cash')
            ? $slot['fare']
            : (($slot['method'] === 'hybrid') ? round($slot['fare'] * 0.6, 2) : 0.0);
        $boardingPoint = liveStopPoint($route, $slot['board']);
        /* Attribute each passenger to the inspector who was on the bus when
           that passenger boarded. */
        $inspectorForSlot = ($handoffAt > 0 && (int)$slot['boarding_sim'] >= $handoffAt)
            ? $inspectorIdAfter
            : $inspectorIdBefore;

        if (!isset($existing[$pid])) {
            if ($now < $slot['boarding_sim']) {
                continue;
            }
            $newId = liveInsert($db, 'passenger_trips', [
                'passenger_id' => $pid,
                'inspector_id' => $inspectorForSlot,
                'bus_no' => $busId,
                'trip_no' => $tripId,
                'driver_id' => $driverId,
                'boarding_point' => $stops[$slot['board']]['stop_name'],
                'destination' => $stops[$slot['dest']]['stop_name'],
                'latitude' => $boardingPoint['lat'] ?? null,
                'longitude' => $boardingPoint['lng'] ?? null,
                'fare' => $slot['fare'],
                'payment_method' => $slot['method'],
                'payment_status' => $holdRemit ? 'pending' : 'paid',
                'boarding_time' => date('Y-m-d H:i:s'),
                'dropoff_time' => null,
                'archived_status' => 'no',
                'remittance_status' => $holdRemit ? 'Pending' : 'Remitted',
                'expected_cash' => $expectedCash,
                'actual_cash' => $holdRemit ? null : $expectedCash,
                'discrepancy_amount' => 0.0,
                'remitted_by' => $holdRemit ? null : $updatedBy,
                'remitted_at' => $holdRemit ? null : date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy,
                'finance_note' => null,
            ]);
            if ($newId > 0) {
                $existing[$pid] = ['id' => $newId, 'passenger_id' => $pid, 'fare' => $slot['fare'], 'boarding_time' => date('Y-m-d H:i:s'), 'dropoff_time' => null];
            }
        }

        if (isset($existing[$pid]) && empty($existing[$pid]['dropoff_time']) && $now >= $slot['dropoff_sim']) {
            liveUpdate($db, 'passenger_trips', [
                'dropoff_time' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy,
            ], 'id', $existing[$pid]['id']);
            $existing[$pid]['dropoff_time'] = date('Y-m-d H:i:s');
        }
    }

    return liveTripPassengerRows($db, $tripId);
}

function liveUpdateTripTotals(PDO $db, int $tripId, array $passengerRows): void
{
    $revenue = 0.0;
    foreach ($passengerRows as $row) {
        $revenue += (float)($row['fare'] ?? 0);
    }
    liveUpdate($db, 'bus_trips', [
        'total_passengers' => count($passengerRows),
        'total_revenue' => round($revenue, 2),
    ], 'id', $tripId);
}

function liveWriteDetailedInspection(PDO $db, array $trip, array $meta, array $route, array $passengerRows, string $inspectorId, string $inspectorName, string $result): void
{
    $tripId = (string)$trip['id'];
    $inspectionId = 'SIM-' . $tripId;
    $exists = (int)(liveValue(
        $db,
        "SELECT COUNT(*) FROM inspector_inspections WHERE inspection_id = ?",
        [$inspectionId]
    ) ?? 0);
    if ($exists > 0) {
        return;
    }

    $validated = 0;
    $review = 0;
    $findings = [];
    $items = [];
    foreach ($passengerRows as $row) {
        $status = stableUnit($meta['key'] . '-item-' . $row['id']) < 0.94 ? 'VALIDATED' : 'NEEDS REVIEW';
        if ($status === 'VALIDATED') { $validated++; } else { $review++; $findings[] = 'Passenger ' . ($row['passenger_id'] ?? '') . ' needs review'; }
        $pax = liveRows($db, 'SELECT full_name, passenger_code, passenger_type FROM passengers WHERE id = ? LIMIT 1', [(int)($row['passenger_id'] ?? 0)]);
        $items[] = [
            'id' => $row['id'],
            'name' => (string)($pax[0]['full_name'] ?? ('Passenger ' . $row['passenger_id'])),
            'code' => (string)($pax[0]['passenger_code'] ?? ''),
            'type' => (string)($pax[0]['passenger_type'] ?? 'Regular'),
            'validation_status' => $status,
            'fare' => (float)$row['fare'],
        ];
    }

    $summary = [
        'validation' => [
            'driver_id' => (string)$trip['driver_id'],
            'payment_information_validated' => true,
            'boarding_destination_validated' => $result === 'Passed',
        ],
        'trip_checklist' => [
            'headcount_correct' => $result === 'Passed',
            'passenger_details_correct' => $result === 'Passed',
            'boarding_alighting_correct' => $result === 'Passed',
        ],
        'overall_result' => $result === 'Passed' ? 'Inspection Complete' : 'Discrepancy Found',
        'headcount_expected' => count($passengerRows),
        'headcount_actual' => count($passengerRows),
        'findings' => $findings,
        'simulated' => true,
    ];

    $headerId = liveInsert($db, 'inspector_inspections', [
        'inspection_id' => $inspectionId,
        'bus_no' => (string)$trip['bus_id'],
        'trip_no' => $tripId,
        'route_name' => (string)$trip['route_name'],
        'inspector_id' => $inspectorId,
        'inspector_name' => $inspectorName,
        'driver_id' => (string)$trip['driver_id'],
        'total_passengers' => count($passengerRows),
        'checked_count' => count($items),
        'validated_count' => $validated,
        'needs_review_count' => $review,
        'status' => 'Submitted',
        'submitted_at' => date('Y-m-d H:i:s'),
        'audit_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
    ]);
    if ($headerId <= 0) {
        return;
    }
    foreach ($items as $item) {
        liveInsert($db, 'inspector_inspection_items', [
            'inspection_id' => $inspectionId,
            'passenger_id' => (int)($item['id'] ?? 0),
            'passenger_code' => $item['code'],
            'passenger_name' => $item['name'],
            'passenger_type' => $item['type'],
            'registration_type' => $item['type'],
            'fare' => $item['fare'],
            'payment_status' => 'paid',
            'validation_status' => $item['validation_status'],
            'checklist_results' => json_encode([
                'payment_information' => 'valid',
                'boarding_destination' => $item['validation_status'] === 'VALIDATED' ? 'valid' : 'review',
            ], JSON_UNESCAPED_UNICODE),
            'discrepancy_reasons' => json_encode($item['validation_status'] === 'VALIDATED' ? [] : ['Boarding detail needs review'], JSON_UNESCAPED_UNICODE),
            'remarks' => $item['validation_status'] === 'VALIDATED' ? null : 'Auto-flagged for inspector review',
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function liveCheckOutInspector(PDO $db, string $tripId): void
{
    $rows = liveRows(
        $db,
        'SELECT id, status FROM inspector_assignments WHERE trip_id = ? ORDER BY id DESC LIMIT 1',
        [$tripId]
    );
    if (!empty($rows) && strcasecmp((string)$rows[0]['status'], 'Checked-Out') !== 0) {
        liveUpdate($db, 'inspector_assignments', [
            'status' => 'Checked-Out',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id', $rows[0]['id']);
    }
}

/* The simulator owns the live position of its active trips. When a trip ends
   (completed/cancelled/interrupted) its driver_current_location row must stop
   feeding the map, so a later trip can never inherit a finished trip's fix.
   The final position stays in driver_location_history. */
function liveClearDriverLocation(PDO $db, string $driverId): void
{
    if ($driverId === '') {
        return;
    }
    liveExec($db, 'DELETE FROM driver_current_location WHERE driver_id = ?', [$driverId]);
}

function liveAssignReplacement(PDO $db, array &$ctx, array $trip, array $position): array
{
    $replacementDriver = null;
    $replacementBus = null;
    foreach ($ctx['drivers'] as $did => $candidate) {
        foreach (($ctx['assign'][$did] ?? []) as $bid) {
            if (isset($ctx['buses'][$bid])) {
                $replacementDriver = $candidate;
                $replacementBus = $ctx['buses'][$bid];
                break 2;
            }
        }
    }
    if ($replacementDriver === null && !empty($ctx['drivers']) && !empty($ctx['buses'])) {
        $replacementDriver = reset($ctx['drivers']);
        $replacementBus = reset($ctx['buses']);
    }
    if ($replacementDriver === null || $replacementBus === null) {
        return [];
    }
    $replacementDriverId = (string)$replacementDriver['driver_id'];
    $replacementBusId = (string)$replacementBus['bus_id'];
    unset($ctx['drivers'][$replacementDriverId]);
    unset($ctx['buses'][$replacementBusId]);

    liveInsert($db, 'driver_bus_assignment', ['driver_id' => $replacementDriverId, 'bus_id' => $replacementBusId]);
    $dispatcherActor = liveDispatcherActor($db, $ctx, (string)($trip['dispatcher_id'] ?? ''));
    liveInsert($db, 'bus_problem_reports', [
        'trip_id' => (string)$trip['id'],
        'trip_no' => (string)$trip['trip_number'],
        'driver_id' => (string)$trip['driver_id'],
        'bus_id' => (string)$trip['bus_id'],
        'passenger_count' => (int)($trip['total_passengers'] ?? 0),
        'problem_type' => ['Mechanical Problem', 'Overheating', 'Flat Tire', 'Fuel Problem'][(int)($trip['id'] % 4)],
        'problem_detail' => 'Bus stopped en route; replacement dispatched.',
        'remarks' => null,
        'photo_url' => null,
        'latitude' => $position['lat'],
        'longitude' => $position['lng'],
        'location_status' => 'ok',
        'status' => 'resolved',
        'identity_key' => 'SIM-' . $trip['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'replacement_bus_id' => $replacementBusId,
        'replacement_driver_id' => $replacementDriverId,
        'replacement_assigned_by' => (string)$dispatcherActor['name'],
        'replacement_assigned_at' => date('Y-m-d H:i:s'),
    ]);
    liveExec(
        $db,
        "UPDATE passenger_trips
         SET bus_no = ?, driver_id = ?, updated_by = ?
         WHERE trip_no = ? AND dropoff_time IS NULL AND archived_status = 'no'",
        [$replacementBusId, $replacementDriverId, (string)$dispatcherActor['name'], (string)$trip['id']]
    );
    return ['bus_id' => $replacementBusId, 'driver_id' => $replacementDriverId];
}

/* Advance one trip to the state implied by its simulated clock. */
function liveAdvanceTrip(PDO $db, array &$ctx, array $trip, array $route, array $inspectors, array $meta, array $opts): array
{
    $tripId = (string)$trip['id'];
    $busId = (string)$trip['bus_id'];
    $driverId = (string)$trip['driver_id'];
    $primaryInspector = $inspectors[0];
    $secondaryInspector = $inspectors[1] ?? $inspectors[0];
    $primaryInspectorId = (string)$primaryInspector['inspector_id'];
    $secondaryInspectorId = (string)$secondaryInspector['inspector_id'];
    $secondaryInspectorName = (string)$secondaryInspector['inspector_name'];
    $inspectorId = $primaryInspectorId;
    $inspectorName = (string)$primaryInspector['inspector_name'];
    $dispatcherId = (string)($trip['dispatcher_id'] ?? '');
    $dispatcherActor = liveDispatcherActor($db, $ctx, $dispatcherId);
    $driverName = liveDriverName($db, $driverId);
    $now = (int)($opts['_sim_now'] ?? liveSimClock($db, $opts));

    $status = (string)$trip['status'];
    $events = liveAuditState($db, $tripId);
    $written = ['events' => 0, 'gps' => 0, 'passengers' => 0];

    /* Completed / cancelled / interrupted trips only report their state. */
    if (!in_array($status, ['Scheduled', 'Departed'], true)) {
        return liveTripSnapshot($db, $trip, $route, $meta, $now, $inspectorId);
    }

    /* Inspection failure cancels the trip before dispatch. */
    if ($meta['inspection_failed'] && $now >= $meta['offsets']['inspect_end'] && !liveEventExists($events, 'Inspection Failed')) {
        $failedInspectionId = liveInsert($db, 'bus_inspections', [
            'bus_id' => $busId,
            'inspector_id' => $inspectorId,
            'result' => 'Failed',
            'remarks' => 'Inspection failed - brake/lighting defects',
            'issues' => 'Brake and lighting defects found; bus held for corrective action.',
            'inspection_date' => date('Y-m-d H:i:s'),
        ]);
        liveRecordAudit($db, 'Inspector', 'Inspection Failed', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'inspector_id' => $inspectorId,
            'dispatcher_id' => $dispatcherId, 'inspection_row_id' => $failedInspectionId,
        ], 'Inspection failed for bus ' . $busId, ['name' => $inspectorName, 'role' => 'Inspector']);
        liveRecordAudit($db, 'Dispatcher', 'Trip Cancelled', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'reason' => 'Inspection failed',
            'dispatcher_id' => $dispatcherId,
        ], 'Trip cancelled after failed inspection', $dispatcherActor);
        liveUpdate($db, 'bus_trips', ['status' => 'Cancelled'], 'id', $trip['id']);
        liveCheckOutInspector($db, $tripId);
        liveClearDriverLocation($db, $driverId);
        $written['events'] += 3;
        $trip['status'] = 'Cancelled';
        return liveTripSnapshot($db, $trip, $route, $meta, $now, $inspectorId) + ['written' => $written];
    }

    if (!liveEventExists($events, 'Driver Checked In') && $now >= $meta['offsets']['checkin']) {
        liveRecordAudit($db, 'Dispatcher', 'Driver Checked In', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'driver_id' => $driverId,
            'driver_name' => $driverName,
            'trip_date' => (string)$trip['trip_date'],
            'dispatcher_id' => $dispatcherId,
            'checked_in_by' => 'dispatcher',
            'checked_in_by_name' => (string)$dispatcherActor['name'],
            'checked_in_at' => date('Y-m-d H:i:s'),
        ], 'Driver checked in for bus ' . $busId, $dispatcherActor);
        $written['events']++;
    }

    if (!liveEventExists($events, 'Inspector Checked In') && $now >= $meta['offsets']['inspector']) {
        liveInsert($db, 'inspector_assignments', [
            'inspector_id' => $inspectorId,
            'bus_id' => $busId,
            'trip_id' => $tripId,
            'status' => 'Checked-In',
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);
        liveRecordAudit($db, 'Inspector', 'Inspector Checked In', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'inspector_id' => $inspectorId,
            'inspector_name' => $inspectorName,
        ], 'Inspector ' . $inspectorName . ' checked in for bus ' . $busId, ['name' => $inspectorName, 'role' => 'Inspector']);
        $inspectorPoint = liveStopPoint($route, 0);
        if ($inspectorPoint !== null) {
            liveUpsertLocation($db, 'inspectors_location', 'inspector_id', $inspectorId, [
                'latitude' => $inspectorPoint['lat'],
                'longitude' => $inspectorPoint['lng'],
                'bus_id' => $busId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $written['events']++;
    }

    if (!liveEventExists($events, 'Inspection Started') && $now >= $meta['offsets']['inspect_start']) {
        liveRecordAudit($db, 'Inspector', 'Inspection Started', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'inspector_id' => $inspectorId,
        ], 'Inspection started for bus ' . $busId, ['name' => $inspectorName, 'role' => 'Inspector']);
        $written['events']++;
    }

    if (!liveEventExists($events, 'Inspection Completed') && $now >= $meta['offsets']['inspect_end']) {
        $issues = $meta['advisory']
            ? 'Advisory: cabin cleanliness and one loose handrail strap for follow-up.'
            : null;
        $passedInspectionId = liveInsert($db, 'bus_inspections', [
            'bus_id' => $busId,
            'inspector_id' => $inspectorId,
            'result' => 'Passed',
            'remarks' => $meta['advisory'] ? 'Passed with advisory' : 'Routine inspection passed',
            'issues' => $issues,
            'inspection_date' => date('Y-m-d H:i:s'),
        ]);
        liveRecordAudit($db, 'Inspector', 'Inspection Completed', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'inspector_id' => $inspectorId,
            'result' => 'Passed', 'advisory' => $meta['advisory'],
            'inspection_row_id' => $passedInspectionId,
        ], 'Inspection completed for bus ' . $busId, ['name' => $inspectorName, 'role' => 'Inspector']);
        $written['events']++;
    }

    if (!liveEventExists($events, 'Trip Prepared') && $now >= $meta['offsets']['prepared']) {
        liveRecordAudit($db, 'Dispatcher', 'Trip Prepared', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'driver_id' => $driverId,
            'driver_name' => $driverName,
            'trip_date' => (string)$trip['trip_date'],
            'dispatcher_id' => $dispatcherId,
            'prepared_by' => (string)$dispatcherActor['name'],
            'prepared_at' => date('Y-m-d H:i:s'),
        ], 'Trip prepared for bus ' . $busId, $dispatcherActor);
        $written['events']++;
    }

    if ($status === 'Scheduled' && $now >= $meta['offsets']['dispatch']) {
        if ($meta['delayed'] && !liveEventExists($events, 'Trip Delayed')) {
            liveRecordAudit($db, 'Dispatcher', 'Trip Delayed', [
                'trip_id' => $tripId, 'bus_id' => $busId,
                'dispatcher_id' => $dispatcherId,
                'delay_minutes' => (int)round($meta['delay_seconds'] / 60),
            ], 'Trip delayed by ' . (int)round($meta['delay_seconds'] / 60) . ' minutes', $dispatcherActor);
            liveRecordAudit($db, 'Dispatcher', 'Trip Resumed', [
                'trip_id' => $tripId, 'bus_id' => $busId, 'dispatcher_id' => $dispatcherId,
            ], 'Trip resumed after delay', $dispatcherActor);
            $written['events'] += 2;
        }
        liveUpdate($db, 'bus_trips', [
            'status' => 'Departed',
            'departure_time' => date('H:i:s', (int)$meta['offsets']['dispatch']),
        ], 'id', $trip['id']);
        liveRecordAudit($db, 'Dispatcher', 'Dispatch Created', [
            'trip_id' => $tripId, 'trip_number' => (int)$trip['trip_number'], 'bus_id' => $busId,
            'driver_id' => $driverId, 'route_name' => (string)$trip['route_name'],
            'trip_date' => (string)$trip['trip_date'], 'dispatcher_id' => $dispatcherId,
            'departure_time' => date('H:i:s', (int)$meta['offsets']['dispatch']),
        ], 'Dispatch created for bus ' . $busId, $dispatcherActor);
        liveRecordAudit($db, 'Dispatcher', 'Trip Departed', [
            'trip_id' => $tripId, 'bus_id' => $busId, 'dispatcher_id' => $dispatcherId,
            'departure_time' => date('H:i:s', (int)$meta['offsets']['dispatch']),
        ], 'Bus departed', $dispatcherActor);
        $trip['status'] = 'Departed';
        $status = 'Departed';
        $written['events'] += 2;
    }

    if ($status === 'Departed') {
        /* Stop arrivals become persisted events exactly when they happen. */
        foreach ($meta['arrivals'] as $i => $arrive) {
            if ($i > 0 && $now >= $arrive && !liveStopEventExists($events, $i + 1)) {
                $stopPoint = liveStopPoint($route, $i);
                liveRecordAudit($db, 'Dispatcher', 'Stop Reached', [
                    'trip_id' => $tripId, 'stop' => (string)$route['stops'][$i]['stop_name'],
                    'sequence' => $i + 1, 'latitude' => $stopPoint['lat'] ?? null,
                    'longitude' => $stopPoint['lng'] ?? null,
                ], 'Bus reached ' . $route['stops'][$i]['stop_name'], ['name' => $driverName, 'role' => 'Driver']);
                $written['events']++;
            }
        }

        /* Inspector handoff at the configured inspection/transfer point: the
           same trip, bus, driver, and dispatcher continue, but inspector #1
           checks out and inspector #2 takes over on the same bus_trips row. */
        if (!liveEventExists($events, 'Inspector Handoff') && $now >= (int)$meta['handoff_at']) {
            liveCheckOutInspector($db, $tripId);
            liveInsert($db, 'inspector_assignments', [
                'inspector_id' => $secondaryInspectorId,
                'bus_id' => $busId,
                'trip_id' => $tripId,
                'status' => 'Checked-In',
                'assigned_at' => date('Y-m-d H:i:s'),
            ]);
            $handoffStopIndex = (int)$meta['handoff_index'];
            $handoffStop = $route['stops'][$handoffStopIndex] ?? ($route['stops'][0] ?? ['stop_name' => '']);
            $handoffPoint = liveStopPoint($route, $handoffStopIndex);
            if ($handoffPoint !== null) {
                liveUpsertLocation($db, 'inspectors_location', 'inspector_id', $secondaryInspectorId, [
                    'latitude' => $handoffPoint['lat'],
                    'longitude' => $handoffPoint['lng'],
                    'bus_id' => $busId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            liveRecordAudit($db, 'Inspector', 'Inspector Handoff', [
                'trip_id' => $tripId,
                'bus_id' => $busId,
                'from_inspector_id' => $inspectorId,
                'from_inspector_name' => $inspectorName,
                'to_inspector_id' => $secondaryInspectorId,
                'to_inspector_name' => $secondaryInspectorName,
                'handoff_stop' => (string)($handoffStop['stop_name'] ?? ''),
                'handoff_sequence' => $handoffStopIndex + 1,
            ], $inspectorName . ' handed over to ' . $secondaryInspectorName . ' at ' . (string)($handoffStop['stop_name'] ?? ''), ['name' => $secondaryInspectorName, 'role' => 'Inspector']);
            $inspectorId = $secondaryInspectorId;
            $inspectorName = $secondaryInspectorName;
            $written['events']++;
        }

        /* Passenger events only when their simulated time has come. */
        $plan = livePassengerPlan((int)$trip['id'], $route, $meta, $ctx);
        $before = count(liveTripPassengerRows($db, $tripId));
        $passengerRows = liveEnsurePassengers(
            $db, $plan, $meta, $route, $tripId, $busId, $driverId,
            $primaryInspectorId, $secondaryInspectorId, (int)$meta['handoff_at'], (string)$dispatcherActor['name'], $now
        );
        $written['passengers'] = max(0, count($passengerRows) - $before);
        liveUpdateTripTotals($db, (int)$trip['id'], $passengerRows);

        /* GPS progress (subject to the trip's GPS-gap variation). */
        $position = livePositionAt($route, $meta, $now);
        $written['gps'] = liveEnsureGps($db, $driverId, $busId, $position, $now, $meta, $opts['tick']);

        /* Rare breakdown interrupts the trip and transfers passengers. */
        if ($meta['breakdown'] && $meta['breakdown_at'] !== null && $now >= $meta['breakdown_at'] && !liveEventExists($events, 'Bus Breakdown Reported')) {
            $breakdownPosition = livePositionAt($route, $meta, $meta['breakdown_at']);
            liveEnsureGps($db, $driverId, $busId, $breakdownPosition, $meta['breakdown_at'], $meta, 0);
            liveRecordAudit($db, 'Dispatcher', 'Bus Breakdown Reported', [
                'trip_id' => $tripId, 'bus_id' => $busId, 'driver_id' => $driverId,
                'latitude' => $breakdownPosition['lat'], 'longitude' => $breakdownPosition['lng'],
            ], 'Bus reported a breakdown en route', ['name' => $driverName, 'role' => 'Driver']);
            $replacement = liveAssignReplacement($db, $ctx, $trip, $breakdownPosition);
            if ($replacement) {
                liveRecordAudit($db, 'Dispatcher', 'Replacement Bus Assigned', [
                    'trip_id' => $tripId, 'bus_id' => $busId, 'dispatcher_id' => $dispatcherId,
                    'replacement_bus_id' => $replacement['bus_id'],
                    'replacement_driver_id' => $replacement['driver_id'],
                ], 'Replacement bus assigned and passengers transferred', $dispatcherActor);
            }
            liveUpdate($db, 'bus_trips', [
                'status' => 'Interrupted',
                'replacement_required' => 1,
            ], 'id', $trip['id']);
            liveCheckOutInspector($db, $tripId);
            liveClearDriverLocation($db, $driverId);
            $trip['status'] = 'Interrupted';
            $written['events'] += 2;
            return liveTripSnapshot($db, $trip, $route, $meta, $now, $inspectorId) + ['written' => $written];
        }

        /* Arrival completes the trip and files the detailed inspection for the
           final active inspector (inspector #2 after the handoff). */
        if ($now >= $meta['arrival'] && !liveEventExists($events, 'Trip Completed')) {
            $finalPosition = livePositionAt($route, $meta, $meta['arrival']);
            liveEnsureGps($db, $driverId, $busId, $finalPosition, $meta['arrival'], $meta, 0);
            liveExec(
                $db,
                "UPDATE passenger_trips
                 SET dropoff_time = ?, updated_by = ?
                 WHERE trip_no = ? AND dropoff_time IS NULL",
                [date('Y-m-d H:i:s'), (string)$dispatcherActor['name'], $tripId]
            );
            $passengerRows = liveTripPassengerRows($db, $tripId);
            liveUpdateTripTotals($db, (int)$trip['id'], $passengerRows);
            liveUpdate($db, 'bus_trips', [
                'status' => 'Completed',
                'arrival_time' => date('H:i:s', (int)$meta['arrival']),
                'duration_minutes' => (int)round(($meta['arrival'] - $meta['offsets']['dispatch']) / 60),
            ], 'id', $trip['id']);
            liveRecordAudit($db, 'Dispatcher', 'Trip Completed', [
                'trip_id' => $tripId, 'bus_id' => $busId, 'dispatcher_id' => $dispatcherId,
                'arrival_time' => date('H:i:s', (int)$meta['arrival']),
                'total_passengers' => count($passengerRows),
                'final_inspector_id' => $inspectorId,
                'final_inspector_name' => $inspectorName,
            ], 'Bus completed the trip', $dispatcherActor);
            liveWriteDetailedInspection($db, $trip, $meta, $route, $passengerRows, $inspectorId, $inspectorName, 'Passed');
            liveCheckOutInspector($db, $tripId);
            liveClearDriverLocation($db, $driverId);
            $trip['status'] = 'Completed';
            $written['events']++;
            return liveTripSnapshot($db, $trip, $route, $meta, $now, $inspectorId) + ['written' => $written];
        }
    }

    return liveTripSnapshot($db, $trip, $route, $meta, $now, $inspectorId) + ['written' => $written];
}

/* The single operational view shared by Dispatcher and Inspector reads. */
function liveTripSnapshot(PDO $db, array $trip, array $route, array $meta, int $now, string $inspectorId): array
{
    $position = livePositionAt($route, $meta, $now);
    $gpsValid = (bool)($position['valid'] ?? false)
        && liveValidPhCoordinate($position['lat'] ?? null, $position['lng'] ?? null);
    $gpsStamp = null;
    $gpsDriverId = (string)($trip['driver_id'] ?? '');
    if ($gpsDriverId !== '') {
        $gpsStamp = liveValue(
            $db,
            'SELECT updated_at FROM driver_current_location WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1',
            [$gpsDriverId]
        );
    }
    $stopCount = count($route['stops']);
    $direction = $stopCount > 0
        ? (string)($route['stops'][0]['stop_name'] ?? '') . ' -> ' . (string)($route['stops'][$stopCount - 1]['stop_name'] ?? '')
        : '';
    $status = (string)$trip['status'];

    if ($status === 'Scheduled') {
        if ($now >= $meta['offsets']['inspect_end']) {
            $stage = $now >= $meta['offsets']['prepared'] ? 'Ready' : 'Inspected';
        } elseif ($now >= $meta['offsets']['inspector']) {
            $stage = 'Inspection';
        } elseif ($now >= $meta['offsets']['checkin']) {
            $stage = 'Checked-In';
        } else {
            $stage = 'Scheduled';
        }
    } elseif ($status === 'Departed') {
        $stage = $now >= $meta['arrival'] ? 'Arrived' : 'En Route';
    } else {
        $stage = $status;
    }

    $passengerRows = liveTripPassengerRows($db, (string)$trip['id']);
    $onboard = 0;
    foreach ($passengerRows as $row) {
        if (empty($row['dropoff_time'])) { $onboard++; }
    }
    $currentStop = $route['stops'][$position['stop_index']] ?? $route['stops'][0];
    $nextStop = $route['stops'][$position['next_index']] ?? null;

    $assignment = liveRows(
        $db,
        'SELECT id, inspector_id, status FROM inspector_assignments WHERE trip_id = ? ORDER BY id DESC LIMIT 1',
        [(string)$trip['id']]
    );
    $currentInspectorId = (string)($assignment[0]['inspector_id'] ?? $inspectorId);

    $view = [
        'trip_id' => (int)$trip['id'],
        'trip_number' => (int)($trip['trip_number'] ?? 0),
        'trip_status' => $status,
        'stage' => $stage,
        'bus_id' => (string)$trip['bus_id'],
        'driver_id' => (string)$trip['driver_id'],
        'route_name' => (string)$trip['route_name'],
        'current_latitude' => $gpsValid ? $position['lat'] : null,
        'current_longitude' => $gpsValid ? $position['lng'] : null,
        'speed_kph' => $gpsValid ? $position['speed'] : 0.0,
        'gps_valid' => $gpsValid,
        'gps_source' => $gpsValid ? 'route_stops' : 'none',
        'gps_updated_at' => $gpsStamp !== null && $gpsStamp !== false ? (string)$gpsStamp : null,
        'direction' => $direction,
        'route_stops_count' => is_array($route['path_points'] ?? null) ? count($route['path_points']) : $stopCount,
        'current_stop' => (string)$currentStop['stop_name'],
        'current_stop_sequence' => (int)$position['stop_index'] + 1,
        'next_stop' => $nextStop !== null ? (string)$nextStop['stop_name'] : null,
        'onboard_passengers' => $onboard,
        'boarded_passengers' => count($passengerRows),
        'dropped_off_passengers' => count($passengerRows) - $onboard,
        'inspector_id' => $currentInspectorId,
        'inspector_assignment' => (string)($assignment[0]['status'] ?? 'Not Assigned'),
        'scheduled_departure' => date('H:i', (int)$meta['offsets']['dispatch']),
        'planned_arrival' => date('H:i', (int)$meta['arrival']),
        'handoff_stop' => (string)($route['stops'][(int)($meta['handoff_index'] ?? 0)]['stop_name'] ?? ''),
        'handoff_at' => date('H:i', (int)($meta['handoff_at'] ?? 0)),
        'traffic_factor' => $meta['traffic_factor'],
        'delay_minutes' => (int)round(($meta['total_seconds'] - LIVE_BASE_TRIP_SECONDS) / 60),
    ];

    return [
        'trip' => $view,
        'dispatcher_view' => $view,
        'inspector_view' => $view,
        'state_match' => true,
        'sim_now' => $now,
        'slot_epoch' => (int)$meta['slot'],
        'slot_at' => date('H:i', (int)$meta['slot']),
        'delayed' => $meta['delayed'],
        'advisory' => $meta['advisory'],
        'breakdown' => $meta['breakdown'],
        'gps_gap' => $meta['gps_gap'],
    ];
}

function liveFindSimTrips(PDO $db): array
{
    $today = date('Y-m-d');
    $tripIds = liveSimTripIds($db);
    if (empty($tripIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
    $rows = liveRows(
        $db,
        "SELECT * FROM bus_trips
         WHERE id IN ($placeholders) AND (trip_date = ? OR status IN ('Scheduled','Departed'))
         ORDER BY id ASC",
        array_merge($tripIds, [$today])
    );
    /* Normalize bus references back to the string bus id so every downstream
       comparison and write uses the application's bus key, whichever shape
       the bus_trips column stores. */
    foreach ($rows as $idx => $row) {
        $rows[$idx]['bus_id'] = busKeyFromReference($db, $row['bus_id'] ?? '');
    }
    return $rows;
}

function liveTripSlot(PDO $db, string $tripId): ?int
{
    foreach (['Live Trip Planned', 'Simulator Trip Planned'] as $action) {
        foreach (liveRows(
            $db,
            'SELECT new_values FROM audit_trail WHERE record_id = ? AND action = ? AND user_agent = ? ORDER BY id DESC LIMIT 1',
            [$tripId, $action, LIVE_SIM_UA]
        ) as $row) {
            $payload = json_decode((string)($row['new_values'] ?? ''), true);
            if (is_array($payload) && isset($payload['slot_epoch']) && preg_match('/^\d+$/', (string)$payload['slot_epoch'])) {
                return (int)$payload['slot_epoch'];
            }
        }
    }
    return null;
}

function liveCreateTripForSlot(PDO $db, array &$ctx, array $opts, int $slotTs): ?array
{
    if (empty($ctx['drivers']) || empty($ctx['buses']) || empty($ctx['routes'])) {
        return null;
    }
    $activeBus = $ctx['active_bus'] ?? [];
    $activeDriver = $ctx['active_driver'] ?? [];

    $driver = null;
    $bus = null;
    $route = null;

    $resolveRoute = static function (string $busRoute) use ($ctx): ?array {
        $busRoute = trim($busRoute);
        if ($busRoute === '') {
            return null;
        }
        if (isset($ctx['routes'][$busRoute])) {
            return $ctx['routes'][$busRoute];
        }
        $canonical = preg_replace('/[^a-z0-9]/', '', strtolower($busRoute));
        if ($canonical === '') {
            return null;
        }
        foreach ($ctx['routes'] as $key => $candidateRoute) {
            if (preg_replace('/[^a-z0-9]/', '', strtolower((string)$key)) === $canonical) {
                return $candidateRoute;
            }
        }
        return null;
    };

    foreach ($ctx['buses'] as $bid => $candidate) {
        if (isset($activeBus[$bid])) {
            continue;
        }
        $candidateRoute = $resolveRoute((string)($candidate['route_name'] ?? ''));
        if ($candidateRoute === null) {
            continue;
        }
        foreach ($ctx['drivers'] as $did => $driverRow) {
            if (isset($activeDriver[$did])) {
                continue;
            }
            if (in_array($bid, $ctx['assign'][$did] ?? [], true)) {
                $driver = $driverRow;
                $bus = $candidate;
                $route = $candidateRoute;
                break 2;
            }
        }
    }
    if ($driver === null) {
        foreach ($ctx['buses'] as $bid => $candidate) {
            if (isset($activeBus[$bid])) {
                continue;
            }
            $candidateRoute = $resolveRoute((string)($candidate['route_name'] ?? ''));
            if ($candidateRoute === null) {
                continue;
            }
            foreach ($ctx['drivers'] as $did => $driverRow) {
                if (!isset($activeDriver[$did])) {
                    $driver = $driverRow;
                    $bus = $candidate;
                    $route = $candidateRoute;
                    break 2;
                }
            }
        }
    }
    if ($driver === null || $bus === null || $route === null) {
        return null;
    }

    $driverId = (string)$driver['driver_id'];
    $busId = (string)($bus['bus_id'] ?? '');
    /* The bus_trips.bus_id column may be the string bus id or the numeric
       buses.id; only the operational trip row uses the column's own shape,
       every other table and key keeps the application's string bus id. */
    $busTripBusRef = $busId;
    if (busTripBusIdIsNumeric($db) && isset($bus['id'])) {
        $busTripBusRef = (int)$bus['id'];
    }
    unset($ctx['drivers'][$driverId]);
    unset($ctx['buses'][$busId]);

    /* Real dispatcher for this departure; the two real inspectors are paired
       after the trip row exists so the choice is tied to the trip id. */
    $dispatcher = livePickDispatcher($ctx, (string)$slotTs);
    $dispatcherActor = ['name' => (string)$dispatcher['dispatcher_name'], 'role' => 'Dispatcher'];

    if (!isset($ctx['assign'][$driverId]) || !in_array($busId, $ctx['assign'][$driverId] ?? [], true)) {
        liveInsert($db, 'driver_bus_assignment', ['driver_id' => $driverId, 'bus_id' => $busId]);
        $ctx['assign'][$driverId][] = $busId;
    }

    $tripNumber = (int)(liveValue(
        $db,
        'SELECT COALESCE(MAX(trip_number),0)+1 FROM bus_trips WHERE trip_date = ?',
        [$ctx['today']]
    ) ?? 1);

    $tripId = liveInsert($db, 'bus_trips', [
        'bus_id' => $busTripBusRef,
        'driver_id' => $driverId,
        'route_name' => $route['route_name'],
        'from_terminal_id' => $bus['from_terminal_id'] ?? null,
        'to_terminal_id' => $bus['to_terminal_id'] ?? null,
        'trip_date' => $ctx['today'],
        'trip_number' => $tripNumber,
        'departure_time' => date('H:i:s', $slotTs),
        'arrival_time' => null,
        'total_passengers' => 0,
        'duration_minutes' => null,
        'dispatcher_id' => (string)$dispatcher['inspector_id'],
        'status' => 'Scheduled',
        'created_at' => date('Y-m-d H:i:s'),
        'trip_type' => 'Regular',
        'submitted' => 0,
        'replacement_required' => 0,
    ], true);

    $inspectors = livePickInspectors($ctx, (string)$tripId);
    $meta = livePlanMeta($tripId, $route, $opts, $slotTs);
    $position = livePositionAt($route, $meta, 0);
    if (($position['valid'] ?? true) === false
        || !liveValidPhCoordinate($position['lat'] ?? null, $position['lng'] ?? null)) {
        error_log('[LiveSimulator] trip ' . $tripId . ' started without a valid route position; GPS not written.');
        $position = null;
    }

    if ($position !== null) {
    liveInsert($db, 'driver_location_history', [
        'driver_id' => $driverId,
        'bus_id' => $busId,
        'latitude' => $position['lat'],
        'longitude' => $position['lng'],
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    liveUpsertLocation($db, 'driver_current_location', 'driver_id', $driverId, [
        'bus_id' => $busId,
        'latitude' => $position['lat'],
        'longitude' => $position['lng'],
        'speed' => 0.0,
        'heading' => 0.0,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    }
    liveRecordAudit($db, 'Dispatcher', 'Live Trip Planned', [
        'trip_id' => $tripId,
        'trip_number' => $tripNumber,
        'bus_id' => $busId,
        'driver_id' => $driverId,
        'dispatcher_id' => (string)$dispatcher['inspector_id'],
        'dispatcher_name' => (string)$dispatcher['dispatcher_name'],
        'route_name' => $route['route_name'],
        'trip_date' => $ctx['today'],
        'slot_epoch' => $slotTs,
        'slot_at' => date('Y-m-d H:i:s', $slotTs),
        'base_trip_seconds' => LIVE_BASE_TRIP_SECONDS,
        'traffic_factor' => $meta['traffic_factor'],
        'planned_arrival_epoch' => $meta['arrival'],
        'inspector_ids' => array_values(array_map(static fn(array $row): string => (string)$row['inspector_id'], $inspectors)),
        'stops' => count($route['stops']),
    ], 'Live trip created for the ' . date('H:i', $slotTs) . ' departure', $dispatcherActor);

    return [
        'trip' => [
            'id' => $tripId,
            'trip_number' => $tripNumber,
            'bus_id' => $busId,
            'driver_id' => $driverId,
            'route_name' => $route['route_name'],
            'trip_date' => $ctx['today'],
            'status' => 'Scheduled',
            'created_at' => date('Y-m-d H:i:s'),
            'departure_time' => date('H:i:s', $slotTs),
        ],
        'route' => $route,
        'inspectors' => $inspectors,
        'dispatcher' => $dispatcher,
        'meta' => $meta,
    ];
}

function handleLiveGeneration(array $input): array
{
    $db = Database::get();
    set_time_limit(300);
    ini_set('memory_limit', '256M');

    $opts = liveResolveOptions($input);
    $result = [
        'mode' => 'live',
        'options' => [
            'scale' => $opts['scale'],
            'tick' => $opts['tick'],
            'advance' => $opts['advance'],
            'restart' => $opts['restart'],
            'cleanup' => $opts['cleanup'],
            'max_new' => $opts['max_new'],
        ],
        'cleanup' => null,
        'created' => 0,
        'active_trips' => 0,
        'trips' => [],
        'warnings' => [],
    ];

    if ($opts['cleanup']) {
        $result['cleanup'] = cleanupSimulatedLiveData($db);
        if ($opts['cleanup_only']) {
            $result['message'] = 'Simulator rows removed.';
            return $result;
        }
    }

    $ctx = buildLiveContext($db);

    if ($opts['diag']) {
        $simNow = (int)($opts['live_now'] ?? liveSimClock($db, $opts));
        $result['mode'] = 'live_diag';
        $result['diagnostics'] = [
            'idle_drivers' => count($ctx['drivers']),
            'idle_buses' => count($ctx['buses']),
            'routes_with_coordinates' => count($ctx['routes']),
            'route_names' => array_slice(array_keys($ctx['routes']), 0, 10),
            'active_inspectors' => count($ctx['inspectors']),
            'inspector_names' => array_slice(array_map(static fn(array $row): string => (string)$row['inspector_name'], $ctx['inspectors']), 0, 5),
            'active_dispatchers' => count($ctx['dispatchers']),
            'dispatcher_names' => array_slice(array_map(static fn(array $row): string => (string)$row['dispatcher_name'], $ctx['dispatchers']), 0, 5),
            'passengers' => count($ctx['passenger_pool']),
            'simulator_trips' => count(liveFindSimTrips($db)),
            'sim_clock' => date('Y-m-d H:i:s', $simNow),
        ];
        if (empty($ctx['routes'])) {
            $result['message'] = 'No route_stops found with latitude/longitude - add coordinates or the route cannot move.';
        } elseif (count($ctx['dispatchers']) === 0 || count($ctx['inspectors']) < 2) {
            $result['message'] = 'Setup incomplete: a real dispatcher and at least two active inspector accounts are required.';
        } else {
            $result['message'] = 'Setup looks usable: routes, real dispatchers, and real inspectors found.';
        }
        return $result;
    }

    if (empty($ctx['routes'])) {
        throw new RuntimeException('Live simulation needs route_stops rows with latitude/longitude.');
    }
    if (empty($ctx['dispatchers'])) {
        throw new RuntimeException('Live simulation needs a real dispatcher account (inspector_accounts.role = dispatcher).');
    }
    if (count($ctx['inspectors']) < 2) {
        throw new RuntimeException('Live simulation needs at least two active inspector accounts (found ' . count($ctx['inspectors']) . ').');
    }

    $simNow = (int)($opts['live_now'] ?? liveSimClock($db, $opts));
    $opts['_sim_now'] = $simNow;

    $existing = liveFindSimTrips($db);
    $activeBus = [];
    $activeDriver = [];
    $activeTrips = 0;
    foreach ($existing as $index => $tripRow) {
        if (in_array((string)$tripRow['status'], ['Scheduled', 'Departed'], true)) {
            $activeTrips++;
            $activeBus[(string)$tripRow['bus_id']] = true;
            $activeDriver[(string)$tripRow['driver_id']] = true;
        }
        /* Earlier builds stamped 'SIMULATOR' on bus_trips.dispatcher_id.
           Replace it with a real dispatcher so monitoring only shows real
           staff, while the same trip keeps advancing. */
        if ((string)($tripRow['dispatcher_id'] ?? '') === LIVE_SIM_MARKER) {
            $migrationDispatcher = livePickDispatcher($ctx, (string)$tripRow['id']);
            liveUpdate($db, 'bus_trips', ['dispatcher_id' => (string)$migrationDispatcher['inspector_id']], 'id', $tripRow['id']);
            $existing[$index]['dispatcher_id'] = (string)$migrationDispatcher['inspector_id'];
        }
    }

    $snapshots = [];

    /* 15-minute departures: keep the recent departure slots covered while
       staying at or under LIVE_MAX_ACTIVE_TRIPS buses. On a cold start the
       most recent departures are backfilled so several buses are already en
       route; afterwards exactly one new departure is created per slot. */
    $headway = LIVE_HEADWAY_SECONDS;
    $dueSlot = (int)(floor($simNow / $headway) * $headway);
    $slotToTrip = [];
    foreach ($existing as $tripRow) {
        $slot = liveTripSlot($db, (string)$tripRow['id']);
        if ($slot !== null) {
            $slotToTrip[(int)$slot] = true;
        }
    }

    if ($opts['restart']) {
        $pendingSlots = [];
        if ($activeTrips === 0) {
            for ($k = 2; $k >= 0; $k--) {
                $pendingSlots[] = $dueSlot - $k * $headway;
            }
        } else {
            $pendingSlots[] = $dueSlot;
        }

        $createBudget = max(1, min(LIVE_MAX_ACTIVE_TRIPS, (int)$opts['max_new']));
        $createdThisTick = 0;
        foreach ($pendingSlots as $slot) {
            if ($createdThisTick >= $createBudget || $activeTrips >= LIVE_MAX_ACTIVE_TRIPS) {
                break;
            }
            if (isset($slotToTrip[$slot])) {
                continue;
            }
            if ($slot < $simNow - LIVE_BASE_TRIP_SECONDS) {
                continue; /* its trip would already have completed */
            }
            $ctx['active_bus'] = $activeBus;
            $ctx['active_driver'] = $activeDriver;
            $made = liveCreateTripForSlot($db, $ctx, $opts, (int)$slot);
            if ($made === null) {
                $result['warnings'][] = 'No idle bus/driver for the ' . date('H:i', $slot) . ' departure.';
                continue;
            }
            $result['created']++;
            $createdThisTick++;
            $activeTrips++;
            $slotToTrip[(int)$slot] = true;
            $activeBus[(string)$made['trip']['bus_id']] = true;
            $activeDriver[(string)$made['trip']['driver_id']] = true;
            $snapshots[(string)$made['trip']['id']] = liveAdvanceTrip(
                $db,
                $ctx,
                $made['trip'],
                $made['route'],
                $made['inspectors'],
                $made['meta'],
                $opts
            );
            $existing[] = $made['trip'];
        }
    }

    /* Advance (or report) every simulated trip currently in the fleet. */
    foreach ($existing as $tripRow) {
        $tripId = (string)$tripRow['id'];
        if (isset($snapshots[$tripId])) {
            continue;
        }
        $route = $ctx['routes'][(string)$tripRow['route_name']] ?? null;
        if ($route === null) {
            continue;
        }
        $slot = liveTripSlot($db, $tripId);
        if ($slot === null) {
            $slot = strtotime((string)$tripRow['created_at']) ?: $simNow;
        }
        $meta = livePlanMeta((int)$tripRow['id'], $route, $opts, (int)$slot);
        $inspectors = livePickInspectors($ctx, $tripId);

        if (in_array((string)$tripRow['status'], ['Scheduled', 'Departed'], true)) {
            $snapshots[$tripId] = liveAdvanceTrip($db, $ctx, $tripRow, $route, $inspectors, $meta, $opts);
        } else {
            $snapshots[$tripId] = liveTripSnapshot(
                $db,
                $tripRow,
                $route,
                $meta,
                $simNow,
                (string)$inspectors[0]['inspector_id']
            );
        }
    }

    if (empty($snapshots) && $opts['restart'] && empty($existing)) {
        throw new RuntimeException('Live simulation needs at least one idle driver and bus.');
    }

    $result['trips'] = array_values($snapshots);
    foreach ($result['trips'] as $snapshot) {
        if (in_array((string)($snapshot['trip']['trip_status'] ?? ''), ['Scheduled', 'Departed'], true)) {
            $result['active_trips']++;
        }
    }
    if (!empty($result['trips'])) {
        $result['snapshot'] = $result['trips'][count($result['trips']) - 1];
    }
    $result['message'] = sprintf(
        'Simulation clock %s - %d active trip(s), %d created this tick.',
        date('H:i:s', $simNow),
        $result['active_trips'],
        $result['created']
    );

    return $result;
}

function cleanupSimulatedLiveData(PDO $db): array
{
    $tripIds = [];
    foreach (liveRows(
        $db,
        "SELECT record_id FROM audit_trail
         WHERE user_agent = ? AND action IN ('Live Trip Planned','Simulator Trip Planned')",
        [LIVE_SIM_UA]
    ) as $row) {
        $recordId = (string)($row['record_id'] ?? '');
        if (preg_match('/^\d+$/', $recordId)) {
            $tripIds[$recordId] = true;
        }
    }
    foreach (liveRows($db, 'SELECT id FROM bus_trips WHERE dispatcher_id = ?', [LIVE_SIM_MARKER]) as $row) {
        $tripIds[(string)$row['id']] = true;
    }
    $tripIds = array_keys($tripIds);

    $deleted = [
        'passenger_trips' => 0,
        'inspector_assignments' => 0,
        'bus_trips' => 0,
        'bus_inspections' => 0,
        'inspector_inspections' => 0,
        'inspector_inspection_items' => 0,
        'bus_problem_reports' => 0,
        'audit_trail' => 0,
        'passengers' => 0,
        'driver_current_location' => 0,
    ];

    if ($tripIds) {
        $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
        $deleted['passenger_trips'] = liveExec($db, "DELETE FROM passenger_trips WHERE trip_no IN ($placeholders)", $tripIds);
        $deleted['inspector_assignments'] = liveExec($db, "DELETE FROM inspector_assignments WHERE trip_id IN ($placeholders)", $tripIds);
        $deleted['bus_problem_reports'] = liveExec($db, "DELETE FROM bus_problem_reports WHERE trip_id IN ($placeholders)", $tripIds);
        $deleted['inspector_inspections'] = liveExec($db, "DELETE FROM inspector_inspections WHERE trip_no IN ($placeholders) AND inspection_id LIKE 'SIM-%'", $tripIds);
        $deleted['inspector_inspection_items'] = liveExec($db, "DELETE FROM inspector_inspection_items WHERE inspection_id LIKE 'SIM-%'");
        $deleted['bus_trips'] = liveExec($db, "DELETE FROM bus_trips WHERE id IN ($placeholders)", $tripIds);
        $deleted['audit_trail'] = liveExec($db, "DELETE FROM audit_trail WHERE record_id IN ($placeholders) AND user_agent = ?", array_merge($tripIds, [LIVE_SIM_UA]));
        $deleted['passengers'] = liveExec($db, "DELETE FROM passengers WHERE passenger_code LIKE 'SIM-%'");
    }

    /* Remove the live position rows owned by simulator trips so finished or
       corrupt simulator fixes stop feeding the live map. */
    if ($tripIds) {
        $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
        $driverIds = [];
        foreach (liveRows($db, "SELECT DISTINCT driver_id FROM bus_trips WHERE id IN ($placeholders)", $tripIds) as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            if ($driverId !== '') {
                $driverIds[$driverId] = true;
            }
        }
        $driverIds = array_keys($driverIds);
        if ($driverIds) {
            $ph = implode(',', array_fill(0, count($driverIds), '?'));
            $deleted['driver_current_location'] = liveExec($db, "DELETE FROM driver_current_location WHERE driver_id IN ($ph)", $driverIds);
        }
    }

    /* Bus inspection rows the simulator created are tracked by id in its audit
       payloads; delete exactly those, plus any legacy rows carrying the old
       visible marker. */
    $inspectionRowIds = [];
    foreach (liveRows(
        $db,
        "SELECT new_values FROM audit_trail
         WHERE user_agent = ? AND action IN ('Inspection Completed','Inspection Failed')",
        [LIVE_SIM_UA]
    ) as $row) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        if (is_array($payload) && (int)($payload['inspection_row_id'] ?? 0) > 0) {
            $inspectionRowIds[] = (int)$payload['inspection_row_id'];
        }
    }
    if ($inspectionRowIds) {
        $ph = implode(',', array_fill(0, count($inspectionRowIds), '?'));
        $deleted['bus_inspections'] += liveExec($db, "DELETE FROM bus_inspections WHERE id IN ($ph)", $inspectionRowIds);
    }
    $deleted['bus_inspections'] += liveExec($db, "DELETE FROM bus_inspections WHERE remarks LIKE '[SIMULATOR]%'");
    if ($deleted['audit_trail'] === 0) {
        $deleted['audit_trail'] = liveExec($db, 'DELETE FROM audit_trail WHERE user_agent = ? AND description LIKE ?', [LIVE_SIM_UA, '[SIMULATOR]%']);
    }

    return $deleted;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        if (strtolower(trim((string)($_POST['mode'] ?? ''))) === 'live') {
            /* Serialize live ticks so two concurrent requests can never create
               competing trips/locations for the same driver or bus. */
            $liveLock = @fopen(sys_get_temp_dir() . '/rconnect_live_sim.lock', 'c');
            if ($liveLock) {
                @flock($liveLock, LOCK_EX);
            }
            try {
                $live = handleLiveGeneration($_POST);
                echo json_encode(['success' => true] + $live);
            } finally {
                if ($liveLock) {
                    @flock($liveLock, LOCK_UN);
                    @fclose($liveLock);
                }
            }
            exit;
        }
        if (strtolower(trim((string)($_POST['mode'] ?? ''))) === 'archive') {
            $res = generateArchiveHistory($_POST);
            $settledNote = (int)($res['remittance_settled'] ?? 0) > 0
                ? ' Settled ' . (int)$res['remittance_settled'] . ' earlier records.'
                : '';
            echo json_encode([
                'success' => true,
                'message' => 'Successfully generated ' . $res['inserted'] . ' archive history rows (' . $res['trips'] . ' trips) for ' . $res['label']
                    . ($res['archive_rows_replaced'] > 0 ? ', replacing ' . $res['archive_rows_replaced'] . ' previous ARCV rows' : '') . '.' . $settledNote,
                'count' => $res['inserted'],
                'trips' => $res['trips'],
                'period' => $res['label'],
                'errors' => [],
                'rows' => $res['rows'],
                'columns' => $res['rows'] ? array_keys($res['rows'][0]) : [],
                'summary' => $res['summary'],
            ]);
            exit;
        }
        $res = generateHistory($_POST);
        $rows = $res['rows'];
        $count = $res['inserted'];
        $trips = $res['trips'];
        $fuels = $res['fuel_logs'] ?? 0;
        $refuels = $res['fuel_refuels'] ?? 0;
        $label = $res['label'];
        $cols = $rows ? array_keys($rows[0]) : [];
        $summary = $res['summary'] ?? null;
        $accountsCreated = (int)($res['accounts_created'] ?? 0);
        $accountsReused = (int)($res['accounts_reused'] ?? 0);
        $accountsReissued = (int)($res['accounts_reissued'] ?? 0);
        $accountsSkipped = (int)($res['accounts_skipped'] ?? 0);
        $busTrips = (int)($res['bus_trips'] ?? 0);
        $settled = (int)($res['remittance_settled'] ?? 0);
        $remLogs = (int)($res['remittance_logs'] ?? 0);
        $discCases = (int)($res['discrepancy_cases'] ?? 0);
        $discResolved = (int)($res['discrepancy_resolved'] ?? 0);
        echo json_encode([
            'success' => true,
            'message' => "Successfully generated $count passenger_trips records and $busTrips operational bus_trips rows ($trips trips), $accountsCreated new passenger accounts ($accountsReused reused, $accountsReissued codes reissued), $fuels fuel logs, and $refuels refuel records for $label."
                . ($accountsSkipped > 0 ? " $accountsSkipped rides skipped because their derived demo address belongs to an existing non-demo passenger." : '')
                . ($settled > 0 ? " Settled $settled earlier records." : '')
                . ($remLogs > 0 ? " $remLogs driver remittance logs ($discCases discrepancies, $discResolved resolved)." : ''),
            'count' => $count,
            'trips' => $trips,
            'bus_trips' => $busTrips,
            'accounts_reissued' => $accountsReissued,
            'accounts_skipped' => $accountsSkipped,
            'period' => $label,
            'errors' => [],
            'rows' => $rows,
            'columns' => $cols,
            'summary' => $summary,
        ]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passenger Trip History Data Generator</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; background: #f1f5f3; margin: 0; padding: 24px; color: #1e2b26; }
    .wrap { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    h1 { font-size: 20px; margin: 0 0 4px; color: #295738; }
    .sub { color: #6b7f75; font-size: 13px; margin: 0 0 22px; }
    label { display: block; font-weight: 600; font-size: 13px; margin: 14px 0 5px; }
    input[type=radio] { margin-right: 6px; }
    .mode-row { margin: 4px 0 2px; }
    input[type=text], input[type=date], input[type=number], select {
        width: 100%; padding: 9px 11px; border: 1px solid #cfdcd6; border-radius: 7px; font-size: 14px; background: #fafcfb;
    }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .group { border: 1px solid #e2ece6; border-radius: 8px; padding: 10px 14px 12px; margin-top: 8px; background: #fbfdfc; }
    .group[hidden] { display: none; }
    .group > label:first-child { margin-top: 4px; }
    details.advanced { margin-top: 14px; border: 1px solid #e2ece6; border-radius: 8px; background: #fbfdfc; }
    details.advanced > summary { cursor: pointer; padding: 10px 14px; font-weight: 700; font-size: 13px; color: #295738; }
    details.advanced .group { border: 0; margin-top: 0; padding-top: 0; }
    details.advanced .hint { font-size: 11px; color: #8aa09a; margin: 2px 0 0; }
    button {
        width: 100%; margin-top: 22px; padding: 12px; font-size: 15px; font-weight: 700; color: #fff;
        background: #295738; border: 0; border-radius: 8px; cursor: pointer;
    }
    button:hover { background: #1f4630; }
    button:disabled { background: #9db4a6; cursor: not-allowed; }
    #result { margin-top: 18px; border-radius: 8px; padding: 14px 16px; font-size: 14px; white-space: pre-wrap; }
    #result[hidden] { display: none; }
    #result.ok { background: #e7f4ec; border: 1px solid #bfe3cd; color: #17452a; }
    #result.err { background: #fdeeee; border: 1px solid #f2c2c2; color: #7c1d1d; }
    .summary { white-space: pre-wrap; }
    .table-wrap { margin-top: 16px; border: 1px solid #dbe7e1; border-radius: 8px; overflow: auto; max-height: 420px; }
    .table-wrap table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
    .table-wrap th { position: sticky; top: 0; background: #eef5f1; text-align: left; padding: 7px 9px; border-bottom: 1px solid #cfe0d7; color: #295738; white-space: nowrap; z-index: 1; }
    .table-wrap td { padding: 5px 9px; border-bottom: 1px solid #e6efe9; white-space: nowrap; }
    .table-wrap tr:nth-child(even) td { background: #f8fbf9; }
    .table-wrap button { margin-top: 14px; }
    .note { margin-top: 18px; font-size: 12px; color: #8aa09a; line-height: 1.5; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Passenger Trip Data Generator</h1>
    <p class="sub">Generates realistic, relationship-aware historical records for <code>passengers</code>, <code>passenger_trips</code>, and <code>bus_trips</code> (plus <code>fuel_logs</code> / <code>fuel_refuels</code>), and archive manifests for <code>passenger_trip_history</code>. Every passenger follows a deterministic weekly travel routine matched onto each day's real scheduled trips. Re-running a period replaces only this tool's own demo output; genuine and imported records are never touched.</p>

    <form id="genForm" data-today="<?php echo date('Y-m-d'); ?>">
        <label>Generation Type</label>
        <div class="mode-row"><input type="radio" name="mode" value="year" checked> Year</div>
        <div class="mode-row"><input type="radio" name="mode" value="month"> Month</div>
        <div class="mode-row"><input type="radio" name="mode" value="week"> Weekly</div>
        <div class="mode-row"><input type="radio" name="mode" value="day"> Day</div>
        <div class="mode-row"><input type="radio" name="mode" value="custom"> Custom Date Range</div>

        <div class="group" id="groupYear">
            <label for="year">Year</label>
            <select id="year" name="year">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
                <option value="2022">2022</option>
            </select>
        </div>

        <div class="group" id="groupMonth" hidden>
            <label for="year2">Year</label>
            <select id="year2" name="year2">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
                <option value="2022">2022</option>
            </select>
            <label for="month">Month</label>
            <select id="month" name="month">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>
        </div>

        <div class="group" id="groupWeek" hidden>
            <div class="grid">
                <div><label for="week_start">Week Start (date)</label><input type="date" id="week_start" name="week_start" value="2026-08-24" min="2022-01-01" max="2026-12-31"></div>
            </div>
        </div>

        <div class="group" id="groupDay" hidden>
            <div class="grid">
                <div><label for="day_date">Date</label><input type="date" id="day_date" name="day_date" value="<?php echo date('Y-m-d'); ?>" min="2022-01-01" max="2026-12-31"></div>
            </div>
        </div>

        <div class="group" id="groupCustom" hidden>
            <div class="grid">
                <div><label for="start_date">Start Date</label><input type="date" id="start_date" name="start_date" value="2022-01-01" min="2022-01-01" max="2026-12-31"></div>
                <div><label for="end_date">End Date</label><input type="date" id="end_date" name="end_date" value="2022-12-31" min="2022-01-01" max="2026-12-31"></div>
            </div>
        </div>

        <details class="advanced">
            <summary>Realism settings (high-realism defaults)</summary>
            <div class="group">
                <div class="grid">
                    <div>
                        <label for="daily_trips_target">Daily Trips Target (fleet-wide)</label>
                        <input type="number" id="daily_trips_target" name="daily_trips_target" value="" min="0" max="18" step="1" placeholder="blank / 0 = automatic 13-18 band">
                    </div>
                    <div>
                        <label for="max_trips_per_bus">Max Trips Per Bus (optional)</label>
                        <input type="number" id="max_trips_per_bus" name="max_trips_per_bus" value="" min="1" max="4" step="1" placeholder="blank = no extra cap">
                    </div>
                    <div>
                        <label for="passenger_pool_target">Passenger Pool Target (optional)</label>
                        <input type="number" id="passenger_pool_target" name="passenger_pool_target" value="" min="100" max="5000" step="50" placeholder="blank / 0 = demand-based">
                    </div>
                </div>
                <p class="hint">The fleet as a whole completes roughly 13-18 trips per operating day (weekdays heaviest, weekends and holidays lighter); buses share the target instead of every unit running daily. Set a target to center the volume on your real operating records. The passenger pool target sets how many recurring passenger profiles/accounts the run builds across the selected dates (higher = more unique passengers and a more varied directory).</p>
                <label for="demand_scenario">Passenger Demand Scenario</label>
                <select id="demand_scenario" name="demand_scenario">
                    <option value="low">Low demand (0.6x)</option>
                    <option value="normal" selected>Normal demand (1.0x)</option>
                    <option value="high">High demand (1.4x)</option>
                </select>
                <label for="demand_multiplier">Custom Demand Multiplier (optional override)</label>
                <input type="number" id="demand_multiplier" name="demand_multiplier" value="1" min="0.1" max="3" step="0.1">
                <p class="hint">Scales scheduled trips and passenger loads on top of the selected scenario. Settlement is automatic for operating dates before today (Asia/Manila); today and future dates stay pending until an actual submission or verification.</p>
                <label for="inspection_discrepancy_rate">Inspection Discrepancy Rate</label>
                <select id="inspection_discrepancy_rate" name="inspection_discrepancy_rate">
                    <option value="low" selected>Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
                <label for="fuel_realism">Fuel Realism</label>
                <select id="fuel_realism" name="fuel_realism">
                    <option value="high" selected>High (tight KPL band)</option>
                    <option value="standard">Standard</option>
                </select>
                <label for="anomaly_rate">Operational / Fuel Anomaly Rate</label>
                <select id="anomaly_rate" name="anomaly_rate">
                    <option value="low" selected>Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
                <label for="window_buses">Demo Window Fleet (Sep-Oct 2026) — bus IDs</label>
                <input type="text" id="window_buses" name="window_buses" value="" placeholder="blank = all buses in the fleet">
                <p class="hint">Buses allowed to operate on dates inside the September-October 2026 demo window. Leave blank to use every bus in the fleet (each bus gets history and a historical KPL); paste specific bus IDs from Fleet Management (comma-separated, e.g. <code>BUS-1001, BUS-1004, BUS-1019</code>) to limit the window to them. Only buses that actually exist in the fleet can run; IDs that are not in your fleet are ignored.</p>
                <label for="random_seed">Random Seed (optional)</label>
                <input type="number" id="random_seed" name="random_seed" value="" step="1" placeholder="same seed + same range = reproducible run">
                <p class="hint">Seeded runs are reproducible only against the same starting database state.</p>
            </div>
        </details>

        <button type="submit" id="btn">Generate Data</button>
        <button type="button" id="btnToday">Generate Today</button>
        <p class="hint">Whole years are generated <strong>month by month automatically</strong> (each month is a normal request), because one January-December run exceeds hosting gateway timeouts. Very long custom ranges may also need splitting into months.</p>
    </form>

    <div class="group" id="liveSimulator" style="margin-top:18px;">
        <label style="margin-top:4px;">Live Trip Simulator (stateful)</label>
        <p class="hint">Keeps up to four live trips moving through <code>bus_trips</code> on a 15-minute departure headway (approximately 55 minutes one-way): a real dispatcher, real bus, real driver, and two real inspectors per trip (inspector #1 hands over to inspector #2 at a mid-route stop). Each advance writes to the real operational tables (<code>bus_trips</code>, <code>driver_current_location</code>, <code>driver_location_history</code>, <code>inspector_assignments</code>, <code>bus_inspections</code>, <code>inspector_inspections</code>, <code>passenger_trips</code>, <code>audit_trail</code>) so the Trip Dashboard and Inspector Monitoring pages show it move. The trip becomes Completed at the destination and its live GPS row is cleared.</p>
        <div class="grid">
            <div>
                <label for="live_scale">Time scale (sim seconds per real second)</label>
                <input type="number" id="live_scale" value="10" min="1" max="600" step="1">
            </div>
        </div>
        <div class="mode-row"><input type="checkbox" id="live_auto"> Auto-advance every 5 seconds</div>
        <button type="button" id="liveBtn">Create / Advance Live Trip</button>
        <button type="button" id="liveDiagBtn" style="background:#2f6f4f;">Check Setup</button>
        <button type="button" id="liveCleanBtn" style="background:#7c5a1d;">Remove Simulator Rows</button>
        <p class="hint" style="margin-top:8px;">
            <a href="/admin/trip-dashboard" target="_blank" rel="noopener">Open Trip Dashboard</a> &middot;
            <a href="/admin/inspectors/?monitoring=dispatcher" target="_blank" rel="noopener">Open Dispatcher Monitoring</a>
        </p>
        <div id="liveResult" class="summary" style="margin-top:10px; white-space:pre-wrap; font-size:13px; line-height:1.6;"></div>
    </div>

    <div class="group" id="archiveGenerator" style="margin-top:18px;">
        <label style="margin-top:4px;">Archive History Generator (passenger_trip_history)</label>
        <p class="hint">Writes old manifest records straight into <code>passenger_trip_history</code> for <strong>2022–2025</strong> plus <strong>January–September 2026</strong>, reusing the cooperative's real buses, drivers, routes and <code>fare_pricing</code>. Names read like paper manifests (full names, nicknames, "Last, First"), operating dates before today are remitted, today's and future dates stay pending, and re-running a period replaces only this tool's own <code>ARCV-</code> rows. Presets: <strong>June–December 2025</strong> and <strong>June–September 2026</strong>. Tip: for a big fleet generate one month or a half-year at a time — whole-year runs can exceed the server's gateway timeout.</p>
        <div class="grid">
            <div>
                <label for="arch_year">Year</label>
                <select id="arch_year">
                    <option value="2026">2026 (Jan–Sep)</option>
                    <option value="2025" selected>2025</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>
            </div>
            <div>
                <label for="arch_month_from">From month</label>
                <select id="arch_month_from">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6" selected>June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div>
                <label for="arch_month_to">To month</label>
                <select id="arch_month_to">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12" selected>December</option>
                </select>
            </div>
        </div>
        <button type="button" id="archBtn">Generate Archive History</button>
        <div id="archResult" class="summary" style="margin-top:10px; white-space:pre-wrap; font-size:13px; line-height:1.6;"></div>
    </div>

    <div id="result"></div>
        <p class="note">Each day is generated as a real operation: the fleet as a whole completes about <strong>13–18 trips</strong> per operating day by default (weekdays heaviest, weekends and holidays lighter, configurable on this page), so buses share the day's work instead of every unit running; trips keep realistic departure intervals and turnaround times with rare delayed or cancelled trips. Buses marked <strong>Inactive, Maintenance, or Archived</strong> in the fleet list are skipped. Boarding is only allowed <strong>5:00 AM–8:00 PM</strong>; departures run on a <strong>15-minute headway</strong> and the final trip arrives at its destination terminal by <strong>9:00 PM</strong>. Every completed trip carries a capacity-aware load — roughly <strong>15–35 passengers</strong> for a 50-seat bus, scaled to the bus's actual seats — varying naturally with time of day, and cargo rows count toward the same seat capacity. Passengers come from persistent profiles with deterministic <strong>weekly travel plans</strong>: students ride to school <strong>5-6 days a week</strong> (up to 10-12 round-journey trips, fewer with absences, holidays, or one-way days), workers follow work-week schedules (mostly Monday-Friday, some six-day or shifted weeks), vendors keep market-day rhythms, and seniors and general commuters travel a varied, stable frequency. Every planned leg is matched onto the day's real scheduled trips (outbound in the morning window, the opposite-direction return in the afternoon/evening, with a reserved return seat), so the same person may ride different buses on different days. Riders take at most <strong>2 planned rides per day</strong> (plus rare top-up legs when a trip needs passengers) and keep their route/stop preferences. Cargo rows are mixed naturally within trips (max 15/day) with Tagalog descriptions and fare = route base × space. Every fare is loaded from the <code>fare_pricing</code> table (regular/student/special columns) for the passenger's route, segment, and type — no amount is hardcoded — producing gross revenue that tracks the day's actual trips and loads. Gross revenue is the sum of the generated fares; net profit deducts the actual fuel cost from <code>fuel_refuels</code> plus the finance report's fixed deductions (amortization, insurance, GPS, OPTR per driver, prorated) and any recorded non-fuel expenses. Buses keep a continuous odometer and fuel balance; every completed trip burns distance ÷ realized KPL and writes one trip-level <code>fuel_logs</code> record (starting ODO, ending ODO, distance, consumed liters at that date's diesel price, and KPL = distance / liters), chained so each trip's starting ODO equals the previous trip's ending ODO. Actual refueling events are written to <code>fuel_refuels</code>: every operating bus takes a full-tank morning top-up before its first trip each operating day (liters equal the fuel burned since its last refuel), with the per-bus low-fuel threshold (20-30% of tank, varied per bus) kept as a mid-day safety net and uncommon partial top-ups, using date-appropriate diesel prices and total_cost = liters × price. The generator replaces only its own previously generated records when a period is re-run; genuine and imported rows are never deleted.</p>
</div>

<script>
/* Completion alerts for long generation runs: an always-visible toast plus a
   page-title flash, and a desktop notification when the tab is in the
   background. (Browser-native dialogs can be suppressed by embedded
   webviews, so the toast is the primary signal.) */
function rcGenAlert(kind, title, detail) {
    try {
        var toast = document.getElementById('rcGenToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'rcGenToast';
            toast.style.cssText = 'position:fixed;top:16px;right:16px;z-index:99999;max-width:360px;padding:12px 16px;border-radius:10px;font:13px/1.5 system-ui,Segoe UI,Arial,sans-serif;box-shadow:0 6px 18px rgba(0,0,0,.18);cursor:pointer;white-space:pre-wrap;';
            toast.title = 'Click to dismiss';
            toast.addEventListener('click', function () { toast.style.display = 'none'; });
            document.body.appendChild(toast);
        }
        var ok = kind === 'success';
        toast.style.background = ok ? '#e7f4ec' : '#fdeeee';
        toast.style.border = '1px solid ' + (ok ? '#bfe3cd' : '#f2c2c2');
        toast.style.color = ok ? '#17452a' : '#7c1d1d';
        toast.textContent = (ok ? '\u2713 ' : '\u2717 ') + title + (detail ? '\n' + detail : '');
        toast.style.display = 'block';
        clearTimeout(window.__rcGenToastTimer);
        window.__rcGenToastTimer = setTimeout(function () { toast.style.display = 'none'; }, 30000);

        var baseTitle = document.documentElement.getAttribute('data-base-title') || document.title;
        document.documentElement.setAttribute('data-base-title', baseTitle);
        document.title = (ok ? '\u2713 ' : '\u2717 ') + title;
        clearTimeout(window.__rcGenTitleTimer);
        window.__rcGenTitleTimer = setTimeout(function () { document.title = baseTitle; }, 30000);

        if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body: detail || '' });
        }
    } catch (e) { /* alerts must never break the page */ }
}
function rcGenArmNotify() {
    try {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    } catch (e) {}
}
</script>
<script>
(function () {
    const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function mode() {
        return document.querySelector('input[name="mode"]:checked').value;
    }

    function syncGroups() {
        const m = mode();
        document.getElementById('groupYear').hidden = m !== 'year';
        document.getElementById('groupMonth').hidden = m !== 'month';
        document.getElementById('groupWeek').hidden = m !== 'week';
        document.getElementById('groupDay').hidden = m !== 'day';
        document.getElementById('groupCustom').hidden = m !== 'custom';
    }

    document.querySelectorAll('input[name="mode"]').forEach(function (r) { r.addEventListener('change', syncGroups); });

    /* One-click "Generate Today": selects Day mode, pins the date to today, and
       runs the same submit flow as the main button. */
    document.getElementById('btnToday').addEventListener('click', function () {
        document.querySelector('input[name="mode"][value="day"]').checked = true;
        document.getElementById('day_date').value = document.getElementById('genForm').dataset.today;
        syncGroups();
        document.getElementById('genForm').dispatchEvent(new Event('submit', { cancelable: true }));
    });

    function periodInfo() {
        const m = mode();
        const year = m === 'year' ? document.getElementById('year').value : document.getElementById('year2').value;
        if (m === 'year') {
            return { start: year + '-01-01', end: year + '-12-31', label: year };
        }
        if (m === 'month') {
            const mon = document.getElementById('month').value.padStart(2, '0');
            const last = new Date(Date.UTC(+year, +document.getElementById('month').value, 0)).getUTCDate();
            return { start: year + '-' + mon + '-01', end: year + '-' + mon + '-' + String(last).padStart(2, '0'), label: MONTHS[+document.getElementById('month').value - 1] + ' ' + year };
        }
        if (m === 'week') {
            const ws = document.getElementById('week_start').value;
            const end = new Date(new Date(ws + 'T00:00:00').getTime() + 6 * 86400000);
            const endStr = end.toISOString().slice(0, 10);
            return { start: ws, end: endStr, label: ws + ' to ' + endStr };
        }
        if (m === 'day') {
            const d = document.getElementById('day_date').value;
            return { start: d, end: d, label: d };
        }
        const s = document.getElementById('start_date').value;
        const e = document.getElementById('end_date').value;
        return { start: s, end: e, label: s + ' to ' + e };
    }

    function estimate() {
        const p = periodInfo();
        const days = (new Date(p.end + 'T00:00:00') - new Date(p.start + 'T00:00:00')) / 86400000 + 1;
        const target = Number(document.getElementById('daily_trips_target').value) || 15;
        const scenario = document.getElementById('demand_scenario').value;
        const scenarioFactor = scenario === 'low' ? 0.6 : (scenario === 'high' ? 1.4 : 1);
        const rowsPerTrip = 22;
        return Math.round(days * target * scenarioFactor * rowsPerTrip);
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderTable(rows, cols) {
        if (!rows.length) {
            const empty = document.createElement('div');
            empty.className = 'summary';
            empty.textContent = '(no rows returned)';
            return empty;
        }
        const wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        const table = document.createElement('table');
        const thead = '<thead><tr>' + cols.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead>';
        table.innerHTML = thead + '<tbody>' + rows.map(function (r) {
            return '<tr>' + cols.map(function (c) { return '<td>' + esc(r[c]) + '</td>'; }).join('') + '</tr>';
        }).join('') + '</tbody>';
        wrap.appendChild(table);
        return wrap;
    }

    document.getElementById('genForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const p = periodInfo();
        const est = estimate();
        if (!confirm('You are about to generate approximately ' + est.toLocaleString() + ' passenger trip records for ' + p.label + '. Continue?')) {
            return;
        }
        rcGenArmNotify();
        const btn = document.getElementById('btn');
        btn.disabled = true;
        btn.textContent = 'Generating...';
        const result = document.getElementById('result');
        result.hidden = false;
        result.className = '';
        result.textContent = 'Generating, please wait...';

        const fd = new FormData(document.getElementById('genForm'));
        if (mode() === 'year') {
            /* Whole years are generated month by month (see runYearChunked):
               one January-December request exceeds web-gateway timeouts and
               memory limits on shared hosting. */
            runYearChunked(fd, p, btn, result);
            return;
        }
        runGeneration(fd, p, btn, result);
    });

    /* One period generation (day / week / month / custom). */
    function runGeneration(fd, p, btn, result) {
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function (r) { return r.json().catch(function () { throw new Error('Invalid server response'); }); })
            .then(function (data) {
                if (data.success) {
                    result.className = 'ok';
                    result.innerHTML = '';
                    const summary = document.createElement('div');
                    summary.className = 'summary';
                    let text = data.message + '\nPeriod: ' + data.period;
                    const s = data.summary;
                    if (s) {
                        text += '\nScenario: ' + (s.demand_scenario || 'normal') + ' · Daily trips target: ' + (s.daily_trips_target > 0 ? s.daily_trips_target : 'automatic (13-18)') + ' · Diesel: PHP ' + (s.diesel_price ?? '-') + '/L' + (s.diesel_price_source ? ' (' + s.diesel_price_source + ')' : '');
                        text += '\n\nBuses: ' + s.buses_used + ' of ' + s.fleet_size + ' (' + s.avg_trips_per_bus + ' trips/bus/day), ' + s.drivers_used + ' drivers'
                            + '\nTrips: ' + s.trips + ' (delayed ' + s.trips_delayed + ', cancelled ' + s.trips_cancelled + ')'
                            + ((s.today_future_trips_skipped ?? 0) > 0 ? ' · ' + s.today_future_trips_skipped + ' today trip(s) left out because their time is still ahead' : '')
                            + '\nBuses without driver today: ' + (s.buses_without_driver ?? 0) + ' · driver replacements: ' + (s.driver_replacements ?? 0)
                            + '\nOperational trips (bus_trips): ' + (s.bus_trips ?? 0)
                            + '\nPassenger trip rows (passenger_trips): ' + (s.passenger_trip_rows ?? '-')
                            + ' · made up of ' + s.passenger_records + ' passenger rides (average ' + s.avg_passengers_per_trip + ' per trip) and ' + s.cargo_records + ' cargo rides'
                            + '\nUnique passengers: ' + s.unique_passengers
                            + '\nUnique passenger accounts: ' + (s.unique_passenger_accounts ?? s.passenger_accounts ?? '-')
                            + ' · by type: ' + Object.keys(s.accounts_by_type || {}).map(function (k) { return k + ' ' + s.accounts_by_type[k]; }).join(', ')
                            + '\nSkipped records: ' + (s.skipped_records ?? 0) + (s.skipped_reasons && Object.keys(s.skipped_reasons).length ? ' (' + Object.keys(s.skipped_reasons).map(function (k) { return k + ' ' + s.skipped_reasons[k]; }).join(', ') + ')' : '')
                            + '\nActivity audit events written: ' + (s.audit_events ?? 0) + ' (queue publication, departures, arrivals, fare payments, remittances, discrepancies)'
                            + '\nDashboard reconciliation: pending verification ' + (s.dashboard && s.dashboard.pending_verification != null ? s.dashboard.pending_verification : '-')
                            + ' · verified ' + (s.dashboard && s.dashboard.verified != null ? s.dashboard.verified : '-')
                            + ' · rejected ' + (s.dashboard && s.dashboard.rejected != null ? s.dashboard.rejected : '-')
                            + ' · boarding rows in period ' + (s.dashboard && s.dashboard.trip_records_range != null ? s.dashboard.trip_records_range : '-')
                            + ' · boarding rows today ' + (s.dashboard && s.dashboard.trip_records_today != null ? s.dashboard.trip_records_today : '-')
                            + ' · Boarded Today ' + (s.dashboard && s.dashboard.boarded_today != null ? s.dashboard.boarded_today : '-')
                            + '\nWeekly travel plans: students 5-6 school days, work-week schedules for workers, market rhythms for vendors, varied commuter frequencies - matched onto each day\'s scheduled trips'
                            + '\nPassenger accounts (passengers): ' + (s.passenger_accounts ?? '-')
                            + ' (' + (s.passenger_accounts_created ?? 0) + ' created, ' + (s.passenger_accounts_reused ?? 0) + ' reused, ' + (s.passenger_accounts_reissued ?? 0) + ' codes reissued)'
                            + ' · previous demo output replaced: ' + (s.demo_rides_replaced ?? 0) + ' rides, ' + (s.demo_bus_trips_replaced ?? 0) + ' bus trips'
                            + '\nGross revenue: PHP ' + s.gross_revenue + ' (avg fare PHP ' + s.avg_fare + ')'
                            + '\nExpenses: fuel PHP ' + s.fuel_expense + ' + fixed PHP ' + s.fixed_costs.total + ' + other PHP ' + s.other_expenses + ' = PHP ' + s.total_expenses
                            + '\nNet profit: PHP ' + s.net_profit
                            + '\nOdometer/KPL records (fuel_logs): ' + (s.odo_records ?? s.fuel_logs) + ' · Refuel transactions (fuel_refuels): ' + s.refuel_events
                            + '\nPayments: ' + s.paid + ' paid, ' + s.pending + ' pending, ' + s.rejected + ' rejected'
                            + '\nPayment methods: ' + Object.keys(s.payment_methods || {}).map(function (k) { return k + ' ' + s.payment_methods[k]; }).join(', ')
                            + '\nRemittance: ' + s.remittance.Remitted + ' remitted, ' + s.remittance.Pending + ' pending, ' + s.remittance.Discrepancy + ' discrepancy'
                            + '\nSettled earlier records: ' + (s.remittance_settled ?? 0)
                            + '\nDriver remittance logs: ' + (s.remittance_logs ?? 0) + ' (' + (s.discrepancy_cases ?? 0) + ' discrepancies, ' + (s.discrepancy_resolved ?? 0) + ' resolved, ' + (s.remittance_logs_backfilled ?? 0) + ' backfilled)'
                            + '\nInspections (simulated): ' + s.inspections.minor + ' minor, ' + s.inspections.significant + ' significant'
                            + '\nFuel purchased: ' + s.fuel_liters + ' L, PHP ' + s.fuel_cost + ' (' + s.fuel_full + ' full tank, ' + s.fuel_partials + ' partial), avg ' + s.avg_kpl + ' km/L'
                            + '\nFuel coverage: ' + ((s.fuel_coverage && s.fuel_coverage.operating_buses) ?? 0) + ' operating buses · '
                            + ((s.fuel_coverage && s.fuel_coverage.buses_with_fuel_records) ?? 0) + ' with fuel records · '
                            + ((s.fuel_coverage && s.fuel_coverage.buses_with_refuels) ?? 0) + ' with refuels · '
                            + ((s.fuel_coverage && s.fuel_coverage.operating_missing_fuel && s.fuel_coverage.operating_missing_fuel.length) ? ('missing: ' + s.fuel_coverage.operating_missing_fuel.join(', ')) : 'missing: none');
                        if (s.fuel_coverage && s.fuel_coverage.never_operated && s.fuel_coverage.never_operated.length) {
                            text += '\nNever operated (' + s.fuel_coverage.never_operated.length + ' of ' + s.fleet_size + '): ' + s.fuel_coverage.never_operated.join(', ') + ' - no trips, so no fuel history or historical KPL for these';
                        }
                        const cov = s.coverage_totals;
                        if (cov && cov.dates != null) {
                            text += '\nDispatch coverage: ' + cov.dates + ' date(s) · '
                                + cov.trips + ' trips · '
                                + cov.drivers_on_trips + ' drivers on trips · '
                                + cov.buses_on_trips + ' buses on trips · '
                                + cov.lineup_buses + ' lineup buses · '
                                + cov.terminal_drivers + ' terminal drivers · '
                                + 'bus target met ' + cov.bus_targets_achieved + '/' + cov.dates + ' · '
                                + 'driver target met ' + cov.driver_targets_achieved + '/' + cov.dates;
                            if (cov.limitations && Object.keys(cov.limitations).length) {
                                text += '\nCoverage limitations: ' + Object.keys(cov.limitations).map(function (k) { return k + ' x' + cov.limitations[k]; }).join(' · ');
                            }
                        }
                        if (Array.isArray(s.daily_coverage) && s.daily_coverage.length) {
                            text += '\nPer-date coverage:';
                            s.daily_coverage.forEach(function (c) {
                                text += '\n  ' + c.date + ': ' + c.trips + ' trips · drivers on trips ' + c.drivers_on_trips
                                    + ' · buses on trips ' + c.buses_on_trips
                                    + ' · lineup ' + c.lineup_buses
                                    + ' · terminal drivers ' + c.terminal_drivers
                                    + ' · passengers ' + c.passengers
                                    + ' · fares PHP ' + c.fares
                                    + ' · bus ' + c.bus_coverage
                                    + ' · driver ' + c.driver_coverage;
                            });
                        }
                    }
                    text += '\n\nInserted into the passengers, passenger_trips, fuel_logs, and fuel_refuels tables — refresh your admin/passenger trip history pages to see them there too.'
                        + '\nA representative sample of generated rows is shown below.';
                    summary.textContent = text;
                    result.appendChild(summary);
                    result.appendChild(renderTable(data.rows || [], data.columns || []));
                    rcGenAlert(
                        'success',
                        'Generation complete — ' + (data.period || p.label),
                        (data.count ?? 0) + ' records · ' + (data.trips ?? 0) + ' trips · ' + (data.summary && data.summary.unique_passenger_accounts != null ? data.summary.unique_passenger_accounts : '-') + ' unique accounts'
                    );
                    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    result.className = 'err';
                    result.textContent = 'Error: ' + (data.message || 'Unknown error');
                    rcGenAlert('error', 'Generation failed — ' + p.label, data.message || 'Unknown error');
                }
            })
            .catch(function (err) {
                result.className = 'err';
                result.textContent = 'Error: ' + err.message;
                rcGenAlert('error', 'Generation failed — ' + p.label, err.message);
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Generate Data';
            });
    }

    /* Whole-year generation, one month per request: a single January-December
       run exceeds web-gateway timeouts and memory limits on shared hosting.
       Each month is a normal, proven-sized run; re-running the year replaces
       the months it regenerates instead of duplicating them. */
    function runYearChunked(fd, p, btn, result) {
        const year = (p.start || '').slice(0, 4);
        const monthNames = MONTHS;
        let index = 0;
        let lines = [];
        let totalRecords = 0;
        let totalTrips = 0;
        let totalNewAccounts = 0;

        function finish() {
            result.className = 'ok';
            result.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'summary';
            div.textContent = 'Year ' + year + ' generated month by month.\n\n' + lines.join('\n')
                + '\n\nTotals: ' + totalRecords.toLocaleString() + ' records · ' + totalTrips.toLocaleString() + ' trips · ' + totalNewAccounts.toLocaleString() + ' new passenger accounts.';
            result.appendChild(div);
            btn.disabled = false;
            btn.textContent = 'Generate Data';
            rcGenAlert('success', 'Generation complete — ' + year, totalRecords.toLocaleString() + ' records · ' + totalTrips.toLocaleString() + ' trips · ' + totalNewAccounts.toLocaleString() + ' new accounts');
            result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function fail(monthName, message) {
            btn.disabled = false;
            btn.textContent = 'Generate Data';
            result.className = 'err';
            result.textContent = 'Error generating ' + monthName + ' ' + year + ': ' + message
                + (lines.length ? '\n\nMonths already generated:\n' + lines.join('\n') : '')
                + '\n\nYou can re-run the year; already generated months are replaced, not duplicated.';
            rcGenAlert('error', 'Generation failed — ' + monthName + ' ' + year, message);
        }

        function next() {
            if (index >= 12) {
                finish();
                return;
            }
            const month = index + 1;
            index++;
            const monthName = monthNames[month - 1];
            btn.textContent = 'Generating ' + monthName + ' (' + month + '/12)...';
            result.hidden = false;
            result.className = '';
            result.textContent = 'Generating ' + monthName + ' ' + year + ' (' + month + '/12), please wait...'
                + (lines.length ? '\n\n' + lines.join('\n') : '');

            const body = new FormData();
            fd.forEach(function (v, k) { body.append(k, v); });
            body.set('mode', 'month');
            body.set('year2', year);
            body.set('month', String(month));

            fetch(window.location.href, { method: 'POST', body: body })
                .then(function (r) { return r.json().catch(function () { throw new Error('Invalid server response — the request likely timed out.'); }); })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Unknown error');
                    }
                    const s = data.summary || {};
                    totalRecords += (data.count || 0);
                    totalTrips += (data.trips || 0);
                    totalNewAccounts += (s.passenger_accounts_created || 0);
                    lines.push(monthName + ': ' + (data.count || 0).toLocaleString() + ' records · ' + (data.trips || 0) + ' trips · +' + (s.passenger_accounts_created || 0) + ' new accounts');
                    next();
                })
                .catch(function (err) {
                    fail(monthName, err.message);
                });
        }

        next();
    }
})();
</script>
<script>
(function () {
    var liveBtn = document.getElementById('liveBtn');
    var liveCleanBtn = document.getElementById('liveCleanBtn');
    var liveAuto = document.getElementById('live_auto');
    var liveScale = document.getElementById('live_scale');
    var liveResult = document.getElementById('liveResult');
    var liveTimer = null;
    var liveBusy = false;

    function renderLive(data) {
        if (!data || data.success !== true) {
            liveResult.textContent = 'Live simulation error: ' + ((data && data.message) || 'Unknown error');
            return;
        }
        if (data.cleanup) {
            var removed = Object.keys(data.cleanup).map(function (k) { return k + '=' + data.cleanup[k]; }).join(', ');
            liveResult.textContent = 'Simulator rows removed (' + removed + ').' + (data.message ? '\n' + data.message : '');
            return;
        }

        if (data.diagnostics) {
            var diag = data.diagnostics;
            var diagLines = [data.message || 'Setup diagnostics'];
            Object.keys(diag).forEach(function (k) {
                var v = Array.isArray(diag[k]) ? (diag[k].join(', ') || '(none)') : diag[k];
                diagLines.push('  ' + k + ': ' + v);
            });
            liveResult.textContent = diagLines.join('\n');
            return;
        }
        var trips = Array.isArray(data.trips) ? data.trips : (data.snapshot ? [data.snapshot] : []);
        if (!trips.length) {
            liveResult.textContent = data.message || 'No simulated trips yet.';
            return;
        }
        var lines = [data.message || ''];
        trips.forEach(function (s) {
            var t = s.trip || {};
            var written = s.written
                ? ' | +' + (s.written.events || 0) + ' ev, +' + (s.written.gps || 0) + ' gps, +' + (s.written.passengers || 0) + ' pax'
                : '';
            lines.push('BUS ' + (t.bus_id ?? '-')
                + '   DRIVER ' + (t.driver_id ?? '-')
                + '   TRIP #' + (t.trip_id ?? '-')
                + '   [' + (t.trip_status ?? '-') + ' / ' + (t.stage ?? '-') + ']'
                + '   ' + (t.scheduled_departure ?? '--:--') + ' -> ' + (t.planned_arrival ?? '--:--')
                + written);
            lines.push('    ROUTE: ' + (t.route_name ?? '-')
                + '   DIRECTION: ' + (t.direction ?? '-')
                + '   ROUTE STOPS: ' + (t.route_stops_count ?? '-')
                + '   delay ' + (t.delay_minutes ?? 0) + 'm');
            lines.push('    STOP: ' + (t.current_stop ?? '-') + ' -> ' + (t.next_stop ?? '-')
                + '   pax: ' + (t.boarded_passengers ?? 0) + ' boarded, ' + (t.onboard_passengers ?? 0) + ' onboard, ' + (t.dropped_off_passengers ?? 0) + ' dropped'
                + '   insp: ' + (t.inspector_id ?? '-') + ' (' + (t.inspector_assignment ?? '-') + ')'
                + (t.handoff_stop ? ' handoff@' + (t.handoff_stop ?? '') : ''));
            lines.push('    LAT: ' + (t.current_latitude ?? 'null')
                + '   LNG: ' + (t.current_longitude ?? 'null')
                + '   SPEED: ' + (t.speed_kph ?? 0) + ' km/h'
                + '   GPS SOURCE: ' + (t.gps_source ?? 'none')
                + '   GPS AT: ' + (t.gps_updated_at ?? 'none')
                + '   GPS: ' + ((t.gps_valid ?? false) ? 'OK' : 'No GPS'));
        });
        (data.warnings || []).forEach(function (w) { lines.push('WARN: ' + w); });
        liveResult.textContent = lines.join('\n');
    }

    function liveRequest(extra) {
        if (liveBusy) { return; }
        liveBusy = true;
        liveBtn.disabled = true;
        liveCleanBtn.disabled = true;
        var body = new URLSearchParams();
        body.set('mode', 'live');
        body.set('live_scale', liveScale.value || '10');
        body.set('live_tick', '0');
        body.set('live_advance', '1');
        body.set('live_restart', '1');
        var windowBuses = document.getElementById('window_buses');
        if (windowBuses && windowBuses.value.trim() !== '') { body.set('window_buses', windowBuses.value.trim()); }
        Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(renderLive)
        .catch(function (err) { liveResult.textContent = 'Live simulation error: ' + err.message; })
        .finally(function () { liveBusy = false; liveBtn.disabled = false; liveCleanBtn.disabled = false; });
    }

    liveBtn.addEventListener('click', function () { liveRequest(); });
    var liveDiagBtn = document.getElementById('liveDiagBtn');
    if (liveDiagBtn) {
        liveDiagBtn.addEventListener('click', function () { liveRequest({ live_diag: '1', live_advance: '0' }); });
    }
    liveCleanBtn.addEventListener('click', function () {
        if (liveTimer) { clearInterval(liveTimer); liveTimer = null; liveAuto.checked = false; }
        liveRequest({ live_cleanup: '1', live_cleanup_only: '1', live_advance: '0' });
    });
    liveAuto.addEventListener('change', function () {
        if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
        if (liveAuto.checked) { liveRequest(); liveTimer = setInterval(function () { liveRequest(); }, 5000); }
    });
})();
</script>
<script>
(function () {
    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var archBtn = document.getElementById('archBtn');
    var archResult = document.getElementById('archResult');
    var archYear = document.getElementById('arch_year');
    var archFrom = document.getElementById('arch_month_from');
    var archTo = document.getElementById('arch_month_to');
    if (!archBtn || !archResult || !archYear || !archFrom || !archTo) { return; }

    /* Year presets: June-December 2025, June-September 2026, whole year
       otherwise. The user can still fine-tune either month after choosing. */
    archYear.addEventListener('change', function () {
        if (archYear.value === '2026') {
            archFrom.value = '6';
            archTo.value = '9';
        } else if (archYear.value === '2025') {
            archFrom.value = '6';
            archTo.value = '12';
        } else {
            archFrom.value = '1';
            archTo.value = '12';
        }
    });

    archBtn.addEventListener('click', function () {
        var year = archYear.value;
        var from = parseInt(archFrom.value, 10);
        var to = parseInt(archTo.value, 10);
        if (from > to) {
            alert('The "from" month cannot be after the "to" month.');
            return;
        }
        if (year === '2026' && to > 9) {
            alert('For 2026, archive data is only available for January to September.');
            return;
        }
        var periodLabel = (from === to)
            ? MONTHS[from - 1] + ' ' + year
            : ((from === 1 && to === 12) ? year : MONTHS[from - 1] + '-' + MONTHS[to - 1] + ' ' + year);
        if (!confirm('Generate archive history rows in passenger_trip_history for ' + periodLabel + '? Existing ARCV- rows for that period will be replaced.')) {
            return;
        }
        rcGenArmNotify();
        archBtn.disabled = true;
        archResult.textContent = 'Generating archive history, please wait...';
        var body = new URLSearchParams();
        body.set('mode', 'archive');
        body.set('arch_year', year);
        body.set('arch_month_from', String(from));
        body.set('arch_month_to', String(to));
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: body.toString()
        })
        .then(function (r) { return r.json().catch(function () { throw new Error('Invalid server response'); }); })
        .then(function (data) {
            if (data.success) {
                var s = data.summary || {};
                archResult.textContent = data.message
                    + '\nPeriod: ' + (data.period || '-')
                    + '\nRows (passenger_trip_history): ' + (s.trip_records ?? data.count ?? '-')
                    + ' · manifest nickname / "Last, First" rows: ' + (s.nickname_rows ?? 0)
                    + '\nPassengers: ' + (s.passenger_records ?? '-') + ' · cargo: ' + (s.cargo_records ?? '-')
                    + '\nGross fares: PHP ' + (s.gross_revenue ?? '-')
                    + '\nPayment methods: ' + Object.keys(s.payment_methods || {}).map(function (k) { return k + ' ' + s.payment_methods[k]; }).join(', ')
                    + '\nRemittance: ' + ((s.remittance && s.remittance.Remitted) || 0) + ' remitted, ' + ((s.remittance && s.remittance.Pending) || 0) + ' pending (today and future dates stay pending)'
                    + '\nSettled earlier records: ' + (s.remittance_settled ?? 0)
                    + '\n\nNote: archive rows are imported manifests stored in passenger_trip_history — they do not create passenger accounts or live passenger_trips. Use Generate Data (main) to populate the Admin Passengers page.';
                rcGenAlert(
                    'success',
                    'Archive generation complete — ' + (data.period || periodLabel),
                    (s.trip_records ?? data.count ?? 0) + ' rows · ' + (s.passenger_records ?? 0) + ' passengers · ' + (s.cargo_records ?? 0) + ' cargo'
                );
                archResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                archResult.textContent = 'Error: ' + (data.message || 'Unknown error');
                rcGenAlert('error', 'Archive generation failed — ' + periodLabel, data.message || 'Unknown error');
            }
        })
        .catch(function (err) {
            archResult.textContent = 'Error: ' + err.message;
            rcGenAlert('error', 'Archive generation failed — ' + periodLabel, err.message);
        })
        .finally(function () { archBtn.disabled = false; });
    });
})();
</script>
</body>
</html>
