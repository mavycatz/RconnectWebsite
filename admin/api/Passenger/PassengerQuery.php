<?php
declare(strict_types=1);

/**
 * Shared passenger-list filtering and sorting for the admin Passenger
 * Management page.
 *
 * Both the JSON list action and the CSV export run through these helpers so
 * the visible rows, the matching count, and the export always agree.
 *
 * Built for large data sets: the archive aggregates are loaded with a single
 * grouped pass (no per-passenger correlated subqueries) and no per-passenger
 * balance calculation runs here.
 */

if (!function_exists('passenger_query_normalize_filters')) {

    /**
     * Normalize raw request parameters into a single filter state.
     */
    function passenger_query_normalize_filters(array $input): array
    {
        $pick = static function ($key) use ($input) {
            $value = $input[$key] ?? '';
            return is_string($value) ? trim($value) : '';
        };

        $status = $pick('status');
        $archived = (($input['archived'] ?? '0') === '1') || strcasecmp($status, 'Archived') === 0;
        if ($archived) {
            $status = 'Archived';
        }

        return [
            'search' => $pick('search'),
            'status' => $status,
            'account' => $pick('account'),
            'discount_type' => $pick('discount_type'),
            'verification' => $pick('verification') !== '' ? $pick('verification') : $pick('discount_status'),
            'date_from' => passenger_query_valid_date($pick('date_from')),
            'date_to' => passenger_query_valid_date($pick('date_to')),
            'payment' => $pick('payment'),
            'trip_activity' => $pick('trip_activity'),
            'boarded_today' => $pick('boarded_today') === '1',
            'archived' => $archived,
        ];
    }

    function passenger_query_valid_date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * Verification status label used by the list, the export, and filters.
     * Imported history rows have no verification.
     */
    function passenger_query_verification_label(array $row): string
    {
        if (($row['source'] ?? '') !== 'live') {
            return '';
        }

        $value = strtolower(trim((string) ($row['discount_status'] ?? '')));
        if ($value === '' || $value === 'pending') {
            return 'Pending';
        }
        if ($value === 'verified' || $value === 'approved') {
            return 'Verified';
        }
        if ($value === 'rejected') {
            return 'Rejected';
        }
        if ($value === 'expired') {
            return 'Expired';
        }
        if ($value === 'needs_review') {
            return 'Needs Review';
        }

        return ucfirst($value);
    }

    /**
     * Account kind label shared by the list and the export.
     */
    function passenger_query_account_label(array $row): string
    {
        $name = (string) ($row['full_name'] ?? '');
        if (stripos($name, 'walk-in') !== false) {
            return 'Walk-in';
        }
        if (($row['record_type'] ?? '') === 'Other') {
            return 'Other';
        }

        return ($row['source'] ?? '') === 'imported' ? 'No Account' : 'Has Account';
    }

    /**
     * Payment state for the list badges.
     *
     * - fully_paid: at least one live trip recorded.
     * - no_trips: no live or imported trips at all.
     * Imported history rows are labelled separately.
     */
    function passenger_query_payment_state(array $row): string
    {
        if (($row['source'] ?? '') !== 'live') {
            return 'imported';
        }

        if ((int) ($row['finished_trip_count'] ?? 0) > 0
            || (int) ($row['trip_count'] ?? 0) > 0
            || (int) ($row['history_trip_count'] ?? 0) > 0) {
            return 'fully_paid';
        }

        return 'no_trips';
    }

    function passenger_query_payment_label(array $row): string
    {
        return match ($row['payment_state'] ?? '') {
            'fully_paid' => 'Fully Paid',
            'no_trips' => 'No Trips',
            default => 'Imported',
        };
    }

    /**
     * Fetch live + imported passenger rows. All filtering happens in PHP
     * afterwards so live and imported rows obey exactly the same rules.
     */
    function passenger_query_fetch_rows(PDO $db, array $filters): array
    {
        /* One grouped pass over the archive instead of a correlated subquery
           per passenger keeps the directory fast on large data sets. */
        $historyAgg = [];
        try {
            foreach ($db->query(
                "SELECT LOWER(TRIM(passenger_name)) AS k, COUNT(*) AS c, COALESCE(SUM(fare),0) AS f
                 FROM passenger_trip_history
                 GROUP BY k"
            )->fetchAll(PDO::FETCH_ASSOC) as $agg) {
                $key = (string) ($agg['k'] ?? '');
                if ($key !== '') {
                    $historyAgg[$key] = ['c' => (int) $agg['c'], 'f' => (float) $agg['f']];
                }
            }
        } catch (Throwable $e) {
            $historyAgg = [];
        }

        $liveRows = $db->query("
            SELECT
                p.id AS raw_id, p.full_name, p.email, p.phone,
                COALESCE(p.discount_type, 'Regular') AS discount_type,
                p.discount_status, p.status, p.status_reason,
                p.status_updated_at, p.status_updated_by,
                p.account_status, p.suspension_starts_at, p.suspension_ends_at, p.restriction_remarks,
                p.last_login, p.failed_login_attempts,
                p.blocked_until, p.verified_at,
                p.discount_percent, p.voucher_description,
                p.id_image, p.discount_id,
                p.passenger_code,
                DATE(p.created_at) AS registered_date,
                COUNT(pt.id) AS trip_count,
                SUM(pt.fare) AS total_fare,
                MAX(pt.boarding_time) AS last_trip,
                SUM(CASE WHEN (
                        pt.dropoff_time IS NOT NULL
                        OR LOWER(COALESCE(pt.trip_status, '')) = 'completed'
                        OR LOWER(COALESCE(bt.status, '')) IN ('completed', 'cancelled')
                    ) THEN 1 ELSE 0 END) AS finished_trip_count,
                EXISTS (SELECT 1 FROM passenger_trips pt2 WHERE pt2.passenger_id = p.id AND DATE(pt2.boarding_time) = CURDATE()) AS boarded_today,
                'live' AS source,
                'Passenger' AS record_type
            FROM passengers p
            LEFT JOIN passenger_trips pt ON pt.passenger_id = p.id
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            GROUP BY p.id
            ORDER BY p.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $live = [];
        if (!class_exists('PassengerAccountService')) {
            $accountService = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/passenger/services/PassengerAccountService.php';
            if (is_file($accountService)) {
                require_once $accountService;
            }
        }
        $liveNames = [];
        foreach ($liveRows as $row) {
            $pid = (int) $row['raw_id'];
            unset($row['raw_id']);
            $row['id'] = 'P' . $pid;
            // Effective status reflects automatic suspension expiry without
            // waiting for the account's next write action.
            $legacy = strtolower(trim((string) ($row['status'] ?? '')));
            if (class_exists('PassengerAccountService')) {
                $effective = PassengerAccountService::effectiveStatus($row);
                $row['effective_status'] = $effective;
                if ($effective === 'BLOCKED') {
                    $row['status'] = 'Blocked';
                } elseif ($effective === 'SUSPENDED') {
                    $row['status'] = 'Suspended';
                } elseif ($legacy === 'pending') {
                    $row['status'] = 'Pending';
                } else {
                    $row['status'] = 'Active';
                }
            }
            $nameKey = strtolower(trim((string) ($row['full_name'] ?? '')));
            $liveNames[$nameKey] = true;
            $agg = $historyAgg[$nameKey] ?? null;
            $row['history_trip_count'] = $agg ? $agg['c'] : 0;
            $row['history_total_fare'] = $agg ? $agg['f'] : 0.0;
            $row['total_trips'] = (int) $row['trip_count'] + (int) $row['history_trip_count'];
            $row['finished_trip_count'] = (int) ($row['finished_trip_count'] ?? 0);
            $row['boarded_today'] = (int) ($row['boarded_today'] ?? 0);
            $row['verification'] = passenger_query_verification_label($row);
            $row['payment_state'] = passenger_query_payment_state($row);
            $row['account_label'] = passenger_query_account_label($row);
            $live[] = $row;
        }

        $historyRows = $db->query("
            SELECT
                pth.passenger_name AS full_name,
                '' AS email, '' AS phone,
                CASE
                    WHEN MAX(NULLIF(pth.senior_pwd_id, '')) IS NOT NULL THEN 'Senior'
                    WHEN MAX(NULLIF(pth.student_id, '')) IS NOT NULL THEN 'Student'
                    WHEN MAX(NULLIF(pth.beneficiary_id, '')) IS NOT NULL THEN 'Beneficiary'
                    WHEN MAX(NULLIF(pth.coop_disc_id, '')) IS NOT NULL THEN 'Cooperative Discount'
                    ELSE 'Regular'
                END AS discount_type,
                '' AS discount_status, 'Active' AS status,
                NULL AS status_reason, NULL AS status_updated_at, NULL AS status_updated_by,
                NULL AS last_login, 0 AS failed_login_attempts,
                NULL AS blocked_until, NULL AS verified_at,
                NULL AS discount_percent, NULL AS voucher_description,
                NULL AS id_image, NULL AS discount_id,
                NULL AS passenger_code,
                NULL AS registered_date,
                0 AS trip_count, 0 AS total_fare,
                MAX(pth.trip_date) AS last_trip,
                COUNT(*) AS history_trip_count,
                COALESCE(SUM(pth.fare),0) AS history_total_fare,
                'imported' AS source,
                MAX(pth.type) AS record_type
            FROM passenger_trip_history pth
            GROUP BY pth.passenger_name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $imported = [];
        foreach ($historyRows as $row) {
            /* Names that already exist as live accounts stay live-only. */
            if (isset($liveNames[strtolower(trim((string) ($row['full_name'] ?? '')))])) {
                continue;
            }
            $row['id'] = 'HIST-' . $row['full_name'];
            $row['total_trips'] = (int) $row['history_trip_count'];
            $row['finished_trip_count'] = 0;
            $row['boarded_today'] = 0;
            $row['verification'] = '';
            $row['payment_state'] = 'imported';
            $row['account_label'] = passenger_query_account_label($row);
            $imported[] = $row;
        }

        return array_merge($live, $imported);
    }

    /**
     * Apply every filter to the already-fetched rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    function passenger_query_apply_filters(array $rows, array $filters): array
    {
        $search = mb_strtolower((string) $filters['search']);
        $status = mb_strtolower((string) $filters['status']);
        $archived = (bool) ($filters['archived'] ?? false);
        $account = (string) $filters['account'];
        $type = mb_strtolower((string) $filters['discount_type']);
        $verification = strtolower((string) $filters['verification']);
        $dateFrom = (string) $filters['date_from'];
        $dateTo = (string) $filters['date_to'];
        $payment = (string) $filters['payment'];
        $tripActivity = (string) $filters['trip_activity'];

        $result = [];
        foreach ($rows as $row) {
            $name = (string) ($row['full_name'] ?? '');

            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['id'] ?? ''),
                    $name,
                    (string) ($row['email'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['passenger_code'] ?? ''),
                    (string) ($row['discount_type'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['record_type'] ?? ''),
                    (string) ($row['verification'] ?? ''),
                    (string) ($row['account_label'] ?? ''),
                ]));
                if (mb_strpos($haystack, $search) === false) {
                    continue;
                }
            }

            if ($status !== '') {
                $rowStatus = mb_strtolower(trim((string) ($row['status'] ?? '')));
                // Match the stats cards, which count NULL/empty status as Active.
                if ($rowStatus === '') {
                    $rowStatus = 'active';
                }
                if ($rowStatus !== $status) {
                    continue;
                }
            }

            // Default view hides archived passengers; the Archived filter
            // (status) or the legacy archived=1 parameter opts in.
            if ($status === '' && !$archived && strtolower(trim((string) ($row['status'] ?? ''))) === 'archived') {
                continue;
            }

            if ($account === 'has_account') {
                if (($row['source'] ?? '') !== 'live' || stripos($name, 'walk-in') !== false) {
                    continue;
                }
            } elseif ($account === 'no_account') {
                if (($row['source'] ?? '') !== 'imported' || ($row['record_type'] ?? '') === 'Other') {
                    continue;
                }
            } elseif ($account === 'walkin') {
                if (stripos($name, 'walk-in') === false) {
                    continue;
                }
            } elseif ($account === 'other') {
                if (($row['record_type'] ?? '') !== 'Other') {
                    continue;
                }
            }

            if ($type !== '') {
                $rowType = mb_strtolower(trim((string) ($row['discount_type'] ?? '')));
                if ($type === 'other') {
                    if (in_array($rowType, ['regular', 'student', 'senior', 'pwd', ''], true)) {
                        continue;
                    }
                } elseif ($rowType !== $type) {
                    continue;
                }
            }

            if ($verification !== '') {
                $label = strtolower((string) ($row['verification'] ?? ''));
                $matches = match ($verification) {
                    'approved', 'verified' => $label === 'verified',
                    'pending' => $label === 'pending',
                    'rejected' => $label === 'rejected',
                    'needs_review', 'needs review' => $label === 'needs review',
                    'expired' => $label === 'expired',
                    default => strtolower(trim((string) ($row['discount_status'] ?? ''))) === $verification,
                };
                if (!$matches) {
                    continue;
                }
            }

            if ($dateFrom !== '' || $dateTo !== '') {
                $registered = (string) ($row['registered_date'] ?? '');
                if ($registered === '') {
                    continue; // imported rows carry no registration date
                }
                if ($dateFrom !== '' && $registered < $dateFrom) {
                    continue;
                }
                if ($dateTo !== '' && $registered > $dateTo) {
                    continue;
                }
            }

            $state = (string) ($row['payment_state'] ?? '');
            if ($payment === 'fully_paid' && $state !== 'fully_paid') {
                continue;
            }

            $trips = (int) ($row['total_trips'] ?? 0);
            if ($tripActivity === 'has' && $trips <= 0) {
                continue;
            }
            if ($tripActivity === 'none' && $trips > 0) {
                continue;
            }

            if (!empty($filters['boarded_today']) && (int) ($row['boarded_today'] ?? 0) !== 1) {
                continue;
            }

            $result[] = $row;
        }

        return array_values($result);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    function passenger_query_sort_rows(array $rows, string $sortBy, string $sortDir): array
    {
        $keys = [
            'p.id' => 'id',
            'p.full_name' => 'full_name',
            'p.discount_type' => 'discount_type',
            'p.discount_status' => 'verification',
            'p.status' => 'status',
            'p.created_at' => 'registered_date',
            'p.passenger_code' => 'passenger_code',
            'trip_count' => 'total_trips',
            'last_trip' => 'last_trip',
        ];
        $key = $keys[$sortBy] ?? 'id';
        $dir = strtoupper($sortDir) === 'ASC' ? 1 : -1;
        $numeric = in_array($key, ['total_trips', 'id'], true);

        usort($rows, static function ($a, $b) use ($key, $dir, $numeric) {
            $va = $a[$key] ?? null;
            $vb = $b[$key] ?? null;

            // Imported rows have no registration date or balance: keep them last.
            $aMissing = $va === null || $va === '';
            $bMissing = $vb === null || $vb === '';
            if ($aMissing || $bMissing) {
                if ($aMissing && $bMissing) {
                    return 0;
                }
                return $aMissing ? 1 : -1;
            }

            if ($numeric) {
                if ($key === 'id') {
                    $va = (int) preg_replace('/\D+/', '', (string) $va);
                    $vb = (int) preg_replace('/\D+/', '', (string) $vb);
                } else {
                    $va = (float) $va;
                    $vb = (float) $vb;
                }
                return $dir * ($va <=> $vb);
            }

            return $dir * strcmp(mb_strtolower((string) $va), mb_strtolower((string) $vb));
        });

        return $rows;
    }

    /**
     * Fetch, filter, and sort in one call.
     *
     * @return array<int,array<string,mixed>>
     */
    function passenger_query_run(PDO $db, array $filters, string $sortBy, string $sortDir, ?int $page = null, ?int $perPage = null, ?int &$total = null): array
    {
        $rows = passenger_query_fetch_rows($db, $filters);
        $rows = passenger_query_apply_filters($rows, $filters);
        $rows = passenger_query_sort_rows($rows, $sortBy, $sortDir);

        $total = count($rows);

        if ($page !== null && $perPage !== null && $perPage > 0) {
            $page = max(1, $page);
            $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
        }

        return $rows;
    }

    function passenger_query_export_header(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Contact',
            'Passenger Type',
            'Verification',
            'Account Status',
            'Account Type',
            'Trips',
            'Registered',
            'Payment State',
        ];
    }

    function passenger_query_export_row(array $row): array
    {
        return [
            $row['id'] ?? '',
            $row['full_name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['discount_type'] ?? '',
            $row['verification'] ?? '',
            $row['status'] ?? '',
            $row['account_label'] ?? '',
            (int) ($row['total_trips'] ?? 0),
            $row['registered_date'] ?? '',
            passenger_query_payment_label($row),
        ];
    }
}
