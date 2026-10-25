<?php
declare(strict_types=1);
require_once __DIR__ . '/PassengerAccountSchema.php';
require_once __DIR__ . '/PassengerCodeService.php';

final class PassengerAccountService
{
    private static function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')); }
    public static function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string)$status))) {
            'SUSPENDED' => 'SUSPENDED', 'BLOCKED', 'ARCHIVED' => 'BLOCKED', default => 'ACTIVE',
        };
    }

    /**
     * Canonical discount-verification states shared by registration, the OCR
     * scanner and the admin review screens. Returns null for unknown values.
     */
    public static function normalizeVerification(?string $value): ?string
    {
        $key = strtolower(trim(str_replace([' ', '-'], '_', (string)$value)));
        return match ($key) {
            'pending' => 'pending',
            'verified', 'approved', 'verify' => 'verified',
            'needs_review', 'needsreview', 'review' => 'needs_review',
            'expired' => 'expired',
            'rejected', 'reject' => 'rejected',
            default => null,
        };
    }
    public static function effectiveStatus(array $row): string
    {
        if (in_array(strtoupper((string)($row['status'] ?? '')), ['BLOCKED', 'ARCHIVED'], true)) return 'BLOCKED';
        $status = self::normalizeStatus($row['account_status'] ?? $row['status'] ?? 'ACTIVE');
        if ($status === 'SUSPENDED') {
            $now = self::now()->format('Y-m-d H:i:s');
            if (!empty($row['suspension_starts_at']) && $row['suspension_starts_at'] > $now) return 'ACTIVE';
            if (!empty($row['suspension_ends_at']) && $row['suspension_ends_at'] <= $now) return 'ACTIVE';
        }
        return $status;
    }
    public static function notice(array $row): string
    {
        $status = self::effectiveStatus($row);
        if ($status === 'BLOCKED') return 'Your Rconnect account has been blocked. Please contact the cooperative administrator for further assistance.';
        if ($status === 'SUSPENDED') {
            $until = !empty($row['suspension_ends_at']) ? (new DateTimeImmutable($row['suspension_ends_at']))->format('F j, Y g:i A') : 'reviewed by the administrator';
            return 'Your Rconnect account has been temporarily suspended until ' . $until . '. Reason: ' . ($row['status_reason'] ?? '') . '. Please contact the cooperative administrator if you believe this is a mistake.';
        }
        return 'Your Rconnect account has been successfully reactivated. You can now access Rconnect services.';
    }
    private static function row(PDO $db, int $id, bool $lock = false): array
    {
        $stmt = $db->prepare('SELECT * FROM passengers WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Passenger account not found.');
        return $row;
    }
    public static function state(PDO $db, int $id): array
    {
        PassengerAccountSchema::ensure($db);
        $row = self::row($db, $id);
        if (self::normalizeStatus($row['account_status'] ?? $row['status']) === 'SUSPENDED'
            && !empty($row['suspension_ends_at']) && $row['suspension_ends_at'] <= self::now()->format('Y-m-d H:i:s')
            && !in_array(strtoupper((string)$row['status']), ['BLOCKED', 'ARCHIVED'], true)) {
            $own = !$db->inTransaction(); if ($own) $db->beginTransaction();
            try {
                $row = self::row($db, $id, true);
                if (self::normalizeStatus($row['account_status'] ?? $row['status']) === 'SUSPENDED'
                    && !empty($row['suspension_ends_at']) && $row['suspension_ends_at'] <= self::now()->format('Y-m-d H:i:s')
                    && !in_array(strtoupper((string)$row['status']), ['BLOCKED', 'ARCHIVED'], true)) {
                    self::storeStatus($db, $id, 'ACTIVE', 'Suspension expired', null, null, '', ['id' => null, 'username' => 'System', 'role' => 'system']);
                    self::event($db, $id, 'expire', 'SUSPENDED', 'ACTIVE', 'Suspension expired', '', null, null, ['id' => null, 'username' => 'System', 'role' => 'system']);
                    $row = self::row($db, $id);
                }
                if ($own) $db->commit();
            } catch (Throwable $e) { if ($own && $db->inTransaction()) $db->rollBack(); throw $e; }
        }
        $row['effective_account_status'] = self::effectiveStatus($row);
        return $row;
    }
    public static function assertCanUse(PDO $db, int $id): array
    {
        $row = self::state($db, $id);
        if ($row['effective_account_status'] !== 'ACTIVE') throw new DomainException(self::notice($row));
        return $row;
    }

    /**
     * Whether the passenger record behind an identity still exists.
     *
     * A false answer means the session is stale (the account was removed), not
     * that the service is unavailable; callers use it to report an expired
     * session instead of the misleading "unavailable" reason. An unreadable
     * check returns true so the real state() call surfaces the problem.
     */
    public static function exists(PDO $db, int $id): bool
    {
        try {
            $stmt = $db->prepare('SELECT id FROM passengers WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return true;
        }
    }
    public static function change(PDO $db, int $id, string $action, array $input, array $actor): array
    {
        self::admin($actor);
        PassengerAccountSchema::ensure($db);
        if (!in_array($action, ['suspend', 'block', 'reactivate', 'unblock'], true)) throw new InvalidArgumentException('Invalid restriction action.');
        if (in_array($action, ['suspend', 'block'], true) && (string)($input['confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Confirm the restriction before applying it.');
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '' && in_array($action, ['suspend', 'block'], true)) throw new InvalidArgumentException('A restriction reason is required.');
        if (mb_strlen($reason) > 255) throw new InvalidArgumentException('Reason must be at most 255 characters.');
        $reason = $reason ?: ($action === 'unblock' ? 'Account unblocked after administrator review' : 'Account reactivated after administrator review');
        $remarks = trim((string)($input['remarks'] ?? ''));
        if (mb_strlen($remarks) > 2000) throw new InvalidArgumentException('Remarks must be at most 2000 characters.');
        $start = $end = null;
        if ($action === 'suspend') {
            $hours = filter_var($input['duration_hours'] ?? null, FILTER_VALIDATE_INT);
            if (!$hours || $hours < 1 || $hours > 8760) throw new InvalidArgumentException('Select a suspension duration between 1 hour and 365 days.');
            $raw = trim((string)($input['starts_at'] ?? ''));
            $date = $raw === '' ? self::now() : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, new DateTimeZone('Asia/Manila'));
            if (!$date || ($raw !== '' && $date->format('Y-m-d\TH:i') !== $raw)) throw new InvalidArgumentException('Invalid suspension start date.');
            $start = $date->format('Y-m-d H:i:s'); $end = $date->modify("+$hours hours")->format('Y-m-d H:i:s');
            if ($end <= self::now()->format('Y-m-d H:i:s')) throw new InvalidArgumentException('Suspension must end in the future.');
        }
        $own = !$db->inTransaction(); if ($own) $db->beginTransaction();
        try {
            $row = self::row($db, $id, true); $old = self::effectiveStatus($row);
            if ($old === 'BLOCKED' && $action !== 'unblock') throw new DomainException('Blocked accounts require the Unblock Passenger action.');
            if ($action === 'unblock' && $old !== 'BLOCKED') throw new DomainException('This passenger is not blocked.');
            $legacyStatus = strtolower(trim((string)($row['status'] ?? '')));
            if ($action === 'reactivate' && $old === 'ACTIVE' && self::normalizeStatus($row['account_status'] ?? $row['status']) !== 'SUSPENDED' && $legacyStatus !== 'pending') {
                throw new DomainException('This passenger is already active.');
            }
            $new = $action === 'suspend' ? 'SUSPENDED' : ($action === 'block' ? 'BLOCKED' : 'ACTIVE');
            self::storeStatus($db, $id, $new, $reason, $start, $end, $remarks, $actor);
            self::event($db, $id, $action, $old, $new, $reason, $remarks, $start, $end, $actor);
            if ($own) $db->commit();
            return self::row($db, $id);
        } catch (Throwable $e) { if ($own && $db->inTransaction()) $db->rollBack(); throw $e; }
    }
    private static function storeStatus(PDO $db, int $id, string $status, string $reason, ?string $start, ?string $end, string $remarks, array $actor): void
    {
        $legacy = $status === 'SUSPENDED' && $start > self::now()->format('Y-m-d H:i:s') ? 'Active' : ucfirst(strtolower($status));
        $db->prepare('UPDATE passengers SET account_status = ?, status = ?, status_reason = ?, suspension_starts_at = ?, suspension_ends_at = ?, restriction_remarks = ?, status_updated_at = ?, status_updated_by = ? WHERE id = ?')
            ->execute([$status, $legacy, $reason, $start, $end, $remarks, self::now()->format('Y-m-d H:i:s'), $actor['id'] ?? null, $id]);
    }
    private static function event(PDO $db, int $id, string $action, string $old, string $new, string $reason, string $remarks, ?string $start, ?string $end, array $actor, bool $notify = true, ?string $messageOverride = null): void
    {
        $time = self::now()->format('Y-m-d H:i:s');
        $db->prepare('INSERT INTO passenger_account_events (passenger_id, action, old_status, new_status, reason, remarks, starts_at, ends_at, actor_id, actor_name, actor_role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $action, $old, $new, $reason, $remarks, $start, $end, $actor['id'] ?? null, $actor['username'] ?? 'Administrator', $actor['role'], $time]);
        $eventId = (int)$db->lastInsertId();
        if (!$notify) return;
        $message = $messageOverride ?? self::notice(['account_status' => $new, 'status' => ucfirst(strtolower($new)), 'status_reason' => $reason, 'suspension_ends_at' => $end]);
        $db->prepare('INSERT INTO passenger_account_notifications (passenger_id, event_id, message, created_at) VALUES (?, ?, ?, ?)')->execute([$id, $eventId, $message, $time]);
    }
    public static function admin(array $actor): void
    {
        if (empty($actor['id']) || !in_array(strtolower((string)($actor['role'] ?? '')), ['admin', 'super_admin'], true)) throw new DomainException('Administrator access required.');
    }
    public static function history(PDO $db, int $id): array
    {
        PassengerAccountSchema::ensure($db);
        $stmt = $db->prepare('SELECT * FROM passenger_account_events WHERE passenger_id = ? ORDER BY id DESC LIMIT 200'); $stmt->execute([$id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function appeal(PDO $db, int $id, string $message): void
    {
        $message = trim($message);
        if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) throw new InvalidArgumentException('Appeal must contain 10–2000 characters.');
        $db->beginTransaction();
        try {
            $row = self::row($db, $id, true);
            if (self::effectiveStatus($row) === 'ACTIVE') throw new DomainException('There is no current restriction to appeal.');
            $stmt = $db->prepare("SELECT id FROM passenger_account_events WHERE passenger_id = ? AND action IN ('suspend', 'block') ORDER BY id DESC LIMIT 1"); $stmt->execute([$id]);
            $eventId = (int)$stmt->fetchColumn();
            // Legacy restrictions may predate the event table; zero is a stable legacy episode.
            $db->prepare('INSERT INTO passenger_account_appeals (passenger_id, event_id, message, created_at) VALUES (?, ?, ?, ?)')->execute([$id, $eventId, $message, self::now()->format('Y-m-d H:i:s')]);
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); if ($e instanceof PDOException && $e->getCode() === '23000') throw new DomainException('An appeal has already been submitted for this restriction.'); throw $e; }
    }
    public static function resolveCode(PDO $db, string $code): ?array
    {
        PassengerAccountSchema::ensure($db);
        $stmt = $db->prepare('SELECT p.* FROM passengers p WHERE p.passenger_code = ? OR p.id IN (SELECT passenger_id FROM passenger_code_history WHERE passenger_code = ?) LIMIT 1');
        $stmt->execute([$code, $code]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public static function claimIdentity(PDO $db, int $id, string $email, string $phone): void
    {
        $email = self::canonicalEmail($email);
        $phone = self::canonicalPhone($phone);

        // Live-account duplicate guard. Deleted/soft-deleted accounts do not
        // hold an identity: a passenger whose row was removed (or whose account
        // was deactivated as deleted) must be able to register again.
        $stmt = $db->prepare("SELECT id FROM passengers WHERE id <> ? AND LOWER(COALESCE(status, '')) <> 'deleted' AND LOWER(COALESCE(account_status, '')) <> 'deleted' AND ((? <> '' AND LOWER(TRIM(email)) = ?) OR (? <> '' AND " . self::phoneMatch('phone') . " = ?)) LIMIT 1");
        $stmt->execute([$id, $email, $email, $phone, $phone]);
        if ($stmt->fetchColumn() !== false) throw new DomainException('An account with this email or mobile number already exists.');
        foreach (['email' => $email, 'phone' => $phone] as $kind => $value) {
            if ($value === '') continue;
            $db->prepare('INSERT IGNORE INTO passenger_identity_claims (identity_kind, identity_value, passenger_id) VALUES (?, ?, ?)')->execute([$kind, $value, $id]);
            $check = $db->prepare('SELECT passenger_id FROM passenger_identity_claims WHERE identity_kind = ? AND identity_value = ? FOR UPDATE'); $check->execute([$kind, $value]);
            $owner = (int)$check->fetchColumn();
            if ($owner !== $id) {
                // A claim may point to a passenger that was later deleted from
                // the table (test/seed cleanup), soft-deleted, or that no
                // longer carries the identity because its profile email/phone
                // was changed afterwards (the old value's claim is stale). Such
                // claims are released and reassigned to the new account instead
                // of permanently blocking the email/phone. Only a live account
                // that still holds the identity blocks the registration.
                if ($owner <= 0 || !self::claimOwnerHolds($db, $owner, $kind, $value)) {
                    $db->prepare('UPDATE passenger_identity_claims SET passenger_id = ? WHERE identity_kind = ? AND identity_value = ?')->execute([$id, $kind, $value]);
                    continue;
                }
                error_log('Identity claim blocked registration: ' . $kind . ' is still held by live account #' . $owner);
                throw new DomainException('An account with this email or mobile number already exists.');
            }
        }
    }

    /**
     * Whether the claimed passenger row still exists, is not soft-deleted, and
     * still carries the claimed identity in its own email/phone columns. A row
     * that changed its profile email/phone no longer holds the old value, so
     * the old claim is stale and free to reassign.
     */
    private static function claimOwnerHolds(PDO $db, int $passengerId, string $kind, string $value): bool
    {
        if (!self::livePassenger($db, $passengerId)) {
            return false;
        }

        try {
            if ($kind === 'email') {
                $stmt = $db->prepare('SELECT id FROM passengers WHERE id = ? AND LOWER(TRIM(email)) = ? LIMIT 1');
                $stmt->execute([$passengerId, $value]);
                return $stmt->fetchColumn() !== false;
            }

            $stmt = $db->prepare('SELECT id FROM passengers WHERE id = ? AND ' . self::phoneMatch('phone') . ' = ? LIMIT 1');
            $stmt->execute([$passengerId, $value]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            // Unverifiable ownership must not silently steal the identity.
            return true;
        }
    }

    /**
     * Keep identity claims in step with a profile email/phone change: the
     * values the account no longer holds are released so the old email or
     * number can be registered again, and the new values are claimed (stale
     * claims are reassigned, live owners still block).
     */
    public static function syncIdentityClaims(PDO $db, int $id, string $oldEmail, string $oldPhone, string $newEmail, string $newPhone): void
    {
        $pairs = [
            ['email', self::canonicalEmail($oldEmail), self::canonicalEmail($newEmail)],
            ['phone', self::canonicalPhone($oldPhone), self::canonicalPhone($newPhone)],
        ];

        foreach ($pairs as [$kind, $old, $new]) {
            if ($old !== '' && $old !== $new) {
                $db->prepare('DELETE FROM passenger_identity_claims WHERE identity_kind = ? AND identity_value = ? AND passenger_id = ?')
                    ->execute([$kind, $old, $id]);
            }
        }

        self::claimIdentity($db, $id, $newEmail, $newPhone);
    }

    private static function canonicalEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function canonicalPhone(string $phone): string
    {
        $clean = (string) preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '63') && strlen($clean) === 12) $clean = '0' . substr($clean, 2);
        return $clean;
    }

    /** SQL folding a stored phone column into canonical 09XXXXXXXXX form. */
    private static function phoneMatch(string $column = 'phone'): string
    {
        $c = "REPLACE(REPLACE(REPLACE(REPLACE($column,'+63','0'),' ',''),'-',''),'(','')";
        return "CASE WHEN LENGTH($c) = 12 AND LEFT($c, 2) = '63' THEN CONCAT('0', SUBSTRING($c, 3)) ELSE $c END";
    }

    /** True when a passenger row still exists and is not soft-deleted. */
    private static function livePassenger(PDO $db, int $passengerId): bool
    {
        $stmt = $db->prepare("SELECT id FROM passengers WHERE id = ? AND LOWER(COALESCE(status, '')) <> 'deleted' AND LOWER(COALESCE(account_status, '')) <> 'deleted' LIMIT 1");
        $stmt->execute([$passengerId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Audit record for a discount document that was automatically verified
     * during registration. Written into the same passenger_account_events /
     * notification tables used by administrator classification() so the whole
     * verification history stays in one place. Non-verified outcomes need no
     * row here: passengers.discount_status already mirrors them for admins.
     * This never replaces administrator permissions — only classification()
     * (admin-guarded) can change an already-created account's discount state.
     */
    public static function recordAutoVerification(PDO $db, int $id, array $assessment): void
    {
        if ((string) ($assessment['status'] ?? 'verified') !== 'verified') {
            return;
        }
        $reason = trim((string) ($assessment['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Discount document matched the declared details during registration.';
        }
        self::event($db, $id, 'auto_verify', 'pending', 'verified', $reason, '', null, null, ['id' => null, 'username' => 'System', 'role' => 'system'], true,
            'Discounted fares are now active on your account.');
    }

    /**
     * Apply a system (non-administrator) document-verification outcome to a
     * passenger whose uploaded ID was validated automatically - a profile
     * re-submission, or any path where the document is already on file.
     *
     * A "verified" outcome takes effect immediately: the discount status,
     * verified_at and the matching classification prefix are all set, so no
     * manual approval is needed. Unreadable, mismatched, expired or underage
     * documents are left at their review state for the administrator queue.
     *
     * Unlike classification() this is deliberately not admin-guarded: the whole
     * point is that a clean document needs no manual approval. It never touches
     * account restrictions and only reclassifies through the same
     * effectiveType() logic used everywhere else, so fare calculations are
     * unchanged.
     */
    public static function applyDocumentVerification(PDO $db, int $id, array $assessment): string
    {
        $status = self::normalizeVerification($assessment['status'] ?? 'pending') ?? 'pending';
        if (!in_array($status, ['verified', 'needs_review', 'expired', 'pending'], true)) {
            $status = 'pending';
        }

        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $row = self::row($db, $id, true);
            $oldStatus = strtolower(trim((string)($row['discount_status'] ?? 'pending'))) ?: 'pending';

            if ($status === 'verified') {
                $db->prepare('UPDATE passengers SET discount_status = ?, verified_at = ? WHERE id = ?')
                    ->execute([$status, self::now()->format('Y-m-d H:i:s'), $id]);
                $reason = trim((string)($assessment['reason'] ?? ''));
                if ($reason === '') {
                    $reason = 'Discount document matched the declared details and was automatically verified.';
                }
                self::event($db, $id, 'auto_verify', $oldStatus, 'verified', $reason, '', null, null,
                    ['id' => null, 'username' => 'System', 'role' => 'system'], true,
                    'Discounted fares are now active on your account.');
            } else {
                $db->prepare('UPDATE passengers SET discount_status = ? WHERE id = ?')->execute([$status, $id]);
            }

            // Reissue the code to match whatever the document now proves: the
            // discount prefix when verified, the standard prefix otherwise.
            $code = PassengerCodeService::reclassify($db, $id);

            if ($own) $db->commit();
            return (string)$code;
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    public static function classification(PDO $db, int $id, string $type, string $verification, array $actor): string
    {
        self::admin($actor);
        $allowed = ['Regular', 'Student', 'Senior', 'PWD', 'Teacher', 'Nurse', 'Voucher', 'Custom', 'Special', 'Cooperative', 'Member'];
        $verification = self::normalizeVerification($verification);
        if (!in_array($type, $allowed, true) || $verification === null) {
            throw new InvalidArgumentException('Invalid classification or verification status.');
        }
        $row = self::row($db, $id, true); $oldType = PassengerCodeService::effectiveType($row);
        if ($verification === 'verified' && in_array($type, ['Student', 'Senior', 'PWD'], true)
            && ($oldType !== $type || !in_array(strtolower((string)$row['discount_status']), ['verified', 'approved'], true))) {
            if (empty($row['discount_id'])) throw new DomainException('Review an uploaded classification document before approving a discounted passenger type.');
            if ($type === 'Senior' && !empty($row['date_of_birth']) && (new DateTimeImmutable($row['date_of_birth']))->diff(self::now())->y < 60) throw new DomainException('Senior Citizen eligibility requires age 60 or older.');
        }
        $db->prepare('UPDATE passengers SET discount_type = ?, discount_status = ?, verified_at = CASE WHEN ? = ? THEN ? ELSE verified_at END WHERE id = ?')
            ->execute([$type, $verification, $verification, 'verified', self::now()->format('Y-m-d H:i:s'), $id]);
        $updated = self::row($db, $id); $newType = PassengerCodeService::effectiveType($updated);
        $expectedPrefix = PassengerCodeService::prefixForType($newType);
        $currentPrefix = strtoupper((string)strstr((string)($updated['passenger_code'] ?? ''), '-', true));
        $mismatch = $currentPrefix !== '' && $currentPrefix !== $expectedPrefix;
        $code = ($oldType !== $newType || $mismatch ? PassengerCodeService::reclassify($db, $id) : PassengerCodeService::assignIfMissing($db, $id, $newType));
        // Clear passenger-facing messages for each review outcome. Pending
        // submissions need no notice because nothing changed for the account.
        $outcomes = [
            'verified' => 'Your discount document has been verified. Discounted fares now apply to your account.',
            'rejected' => 'Your discount document was not approved. Please submit a valid document in your profile or contact the cooperative administrator.',
            'needs_review' => 'Your discount document needs review. An administrator will check it; discounted fares remain locked until it is verified.',
            'expired' => 'Your document appears outdated. Please submit a current document in your profile to restore discount eligibility.',
        ];
        $notify = isset($outcomes[$verification]);
        self::event($db, $id, 'classification', self::effectiveStatus($row), self::effectiveStatus($row), 'Classification review: ' . $type . ' (' . $verification . '), effective type ' . $oldType . ' → ' . $newType, '', null, null, $actor, $notify, $outcomes[$verification] ?? null);
        return (string)$code;
    }
}
