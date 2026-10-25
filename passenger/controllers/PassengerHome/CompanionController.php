<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';

use App\Core\Database;

/**
 * Companion Registration endpoints for the passenger module.
 *
 * Routes (dispatched from passenger/index.php):
 *   GET    /passenger/companions  -> saved profiles for the current passenger
 *   POST   /passenger/companions/create -> save a profile
 *   POST   /passenger/companions/delete -> remove a profile
 *   POST   /passenger/companions/attach -> attach companions to the passenger's active trip
 */
class CompanionController
{
    public static function handle(string $route): void
    {
        if (!isset($_SESSION['passenger_id'])) {
            self::fail('AUTH_REQUIRED', 'Not logged in', 401);
        }

        $passengerId = (int) $_SESSION['passenger_id'];

        $conn = Database::tryGet();
        if (!$conn) {
            self::fail('DB_UNAVAILABLE', 'We could not reach the service right now. Please try again in a moment.', 503);
        }

        try {
            ensureCompanionSchema($conn);
        } catch (\Throwable $e) {
            // Additive schema — proceed even if it could not be ensured.
        }

        switch ($route) {
            case 'companions/party':
                $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                $input = $method === 'GET' ? $_GET : self::input();
                $response = self::partyRequest($conn, $passengerId, $method, $input);
                http_response_code($response['http_status']);
                unset($response['http_status']);
                echo json_encode($response);
                exit;

            case 'companions':
                self::list($conn, $passengerId);
            break;

            case 'companions/create':
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                    self::fail('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
                }
                self::create($conn, $passengerId);
            break;

            case 'companions/delete':
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                    self::fail('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
                }
                self::delete($conn, $passengerId);
            break;

            case 'companions/attach':
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                    self::fail('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
                }
                self::attach($conn, $passengerId);
            break;

            case 'companions/attached':
                self::attached($conn, $passengerId);
            break;

            default:
                self::fail('NOT_FOUND', 'Endpoint not found.', 404);
        }
    }

    private static function list(PDO $conn, int $passengerId): void
    {
        echo json_encode([
            'success' => true,
            'profiles' => CompanionService::profiles($conn, $passengerId),
        ]);
        exit;
    }

    private static function create(PDO $conn, int $passengerId): void
    {
        $data = self::input();
        if (!is_array($data)) {
            self::fail('INVALID_JSON', 'Invalid request.', 400);
        }

        if (!passenger_rate_limit('companion_save', (string) $passengerId, 30, 300, 0)) {
            self::fail('RATE_LIMITED', 'Too many requests. Please wait a moment and try again.', 429);
        }

        $companion = [
            'full_name' => (string) ($data['full_name'] ?? ''),
            'companion_type' => (string) ($data['companion_type'] ?? ''),
            'declared_id_number' => (string) ($data['declared_id_number'] ?? ''),
        ];

        $error = CompanionService::validateCompanion($companion);
        if ($error !== null) {
            self::fail('INVALID_COMPANION', $error, 422);
        }

        $result = CompanionService::saveProfile(
            $conn,
            $passengerId,
            $companion['full_name'],
            strtolower(trim($companion['companion_type'])),
            $companion['declared_id_number']
        );

        if (!$result['success']) {
            self::fail('SAVE_FAILED', $result['error'] ?? 'We could not save the companion.', 500);
        }

        echo json_encode(['success' => true, 'profile' => $result['profile']]);
        exit;
    }

    private static function delete(PDO $conn, int $passengerId): void
    {
        $data = self::input();
        if (!is_array($data)) {
            self::fail('INVALID_JSON', 'Invalid request.', 400);
        }

        $profileId = (int) ($data['id'] ?? 0);
        if ($profileId <= 0) {
            self::fail('INVALID_PROFILE', 'Invalid companion.', 422);
        }

        if (!CompanionService::deleteProfile($conn, $passengerId, $profileId)) {
            self::fail('NOT_FOUND', 'Companion not found.', 404);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    private static function attach(PDO $conn, int $passengerId): void
    {
        $data = self::input();
        if (!is_array($data)) {
            self::fail('INVALID_JSON', 'Invalid request.', 400);
        }

        if (!passenger_rate_limit('companion_attach', (string) $passengerId, 30, 300, 0)) {
            self::fail('RATE_LIMITED', 'Too many requests. Please wait a moment and try again.', 429);
        }

        $tripId = (int) ($data['trip_id'] ?? 0);
        if ($tripId <= 0) {
            self::fail('NO_ACTIVE_TRIP', 'No active trip found. Please scan the Bus QR first.', 409);
        }

        // Only an in-progress trip owned by this passenger can take companions.
        $stmt = $conn->prepare("
            SELECT pt.*, p.full_name AS passenger_name, p.discount_type
            FROM passenger_trips pt
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            WHERE pt.id = ? AND pt.passenger_id = ?
            AND pt.dropoff_time IS NULL
            AND (
                bt.id IS NULL
                OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
            )
            LIMIT 1
        ");
        $stmt->execute([$tripId, $passengerId]);
        $primary = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$primary) {
            self::fail('NO_ACTIVE_TRIP', 'No active trip found. Please scan the Bus QR first.', 409);
        }

        $tripStatus = strtolower((string) ($primary['trip_status'] ?? ''));
        if ($tripStatus === 'completed' || $tripStatus === 'cancelled') {
            self::fail('NO_ACTIVE_TRIP', 'This trip is already complete.', 409);
        }

        $normalized = CompanionService::normalizeCompanions($data['companions'] ?? []);
        if ($normalized['error'] !== null) {
            self::fail('INVALID_COMPANION', $normalized['error'], 422);
        }

        if (count($normalized['companions']) === 0) {
            self::fail('EMPTY_COMPANIONS', 'Please add at least one companion.', 422);
        }

        if (empty($_SESSION['is_one_time'])) {
            try {
                $current = RegularPartyService::read($conn, $passengerId, $tripId);
                $selection = $current['companions'];
                $skipped = [];
                $addedNames = [];
                foreach ($normalized['companions'] as $companion) {
                    $duplicate = false;
                    foreach ($selection as $selected) {
                        if (mb_strtolower($selected['full_name']) === mb_strtolower($companion['full_name'])) { $duplicate = true; break; }
                    }
                    if ($duplicate) { $skipped[] = ['name' => $companion['full_name'], 'reason' => 'duplicate']; continue; }
                    $selection[] = $companion;
                    $addedNames[] = $companion['full_name'];
                }
                $party = RegularPartyService::save($conn, $passengerId, $selection, $current['revision'], $tripId);
                $rows = RegularPartyService::travellerRows($conn, $tripId);
                $added = array_values(array_filter($rows, static fn(array $row): bool => in_array($row['passenger_name'], $addedNames, true)));
                echo json_encode($party + ['added' => $added, 'skipped' => $skipped,
                    'total_fare' => array_sum(array_column($added, 'fare')),
                    'profiles' => CompanionService::profiles($conn, $passengerId)]);
                exit;
            } catch (RegularPartyException $e) {
                self::fail($e->errorCode, $e->getMessage(), $e->httpStatus);
            }
        }

        $conn->beginTransaction();
        try {
            $attached = CompanionService::attachToTrip($conn, $primary, $passengerId, $normalized['companions'], [
                'registration_type' => 'regular',
                'token' => 'attach-' . $primary['id'],
            ]);
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Companion attach transaction failed: ' . $e->getMessage());
            self::fail('SAVE_FAILED', 'We could not add the companion. Please try again.', 500);
        }

        $profiles = CompanionService::profiles($conn, $passengerId);

        $partyTotal = CompanionService::partyOnlineAmountCentavos($conn, $primary);
        $partyPeso = $partyTotal / 100;

        $rows = CompanionService::companionRows($conn, (int) $primary['id']);

        echo json_encode([
            'success' => true,
            'trip_id' => (int) $primary['id'],
            'added' => $attached['added'],
            'skipped' => $attached['skipped'],
            'total_fare' => $attached['total_fare'],
            'party_total' => round($partyPeso, 2),
            'profiles' => $profiles,
            'companions' => CompanionService::companionsPayload($rows),
            'breakdown' => CompanionService::partyBreakdown($conn, $primary, $rows),
        ]);
        exit;
    }

    private static function attached(PDO $conn, int $passengerId): void
    {
        $tripId = (int) ($_GET['trip_id'] ?? 0);
        if ($tripId <= 0) {
            self::fail('INVALID_TRIP', 'Invalid trip.', 422);
        }

        $stmt = $conn->prepare("
            SELECT pt.*, p.full_name AS passenger_name, p.discount_type
            FROM passenger_trips pt
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            WHERE pt.id = ? AND pt.passenger_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tripId, $passengerId]);
        $primary = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$primary) {
            self::fail('NOT_FOUND', 'Trip not found.', 404);
        }

        $rows = CompanionService::companionRows($conn, $tripId);

        echo json_encode([
            'success' => true,
            'trip_id' => $tripId,
            'companions' => CompanionService::companionsPayload($rows),
            'breakdown' => CompanionService::partyBreakdown($conn, $primary, $rows),
        ]);
        exit;
    }

    private static function input(): ?array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    /** Same request validation used by the HTTP route and its integration fixtures. */
    public static function partyRequest(PDO $conn, int $passengerId, string $method, ?array $input): array
    {
        try {
            if (!in_array($method, ['GET', 'POST'], true)) {
                throw new RegularPartyException('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
            }
            if ($input === null) {
                throw new RegularPartyException('INVALID_JSON', 'Invalid request.', 400);
            }
            $tripId = !empty($input['trip_id']) ? (int) $input['trip_id'] : null;
            if ($method === 'GET') {
                $result = RegularPartyService::read($conn, $passengerId, $tripId, $input);
            } else {
                if (!isset($input['companions'], $input['revision']) || !is_array($input['companions'])
                    || filter_var($input['revision'], FILTER_VALIDATE_INT) === false || (int) $input['revision'] < 0) {
                    throw new RegularPartyException('INVALID_PARTY', 'Companions and a nonnegative revision are required.', 422);
                }
                $result = RegularPartyService::save($conn, $passengerId, $input['companions'], (int) $input['revision'], $tripId, $input);
            }
            return ['http_status' => 200] + $result;
        } catch (RegularPartyException $e) {
            return ['http_status' => $e->httpStatus, 'success' => false, 'code' => $e->errorCode, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('Regular party request failed: ' . $e->getMessage());
            return ['http_status' => 500, 'success' => false, 'code' => 'PARTY_FAILED', 'message' => 'We could not update your companions. Please try again.'];
        }
    }

    private static function fail(string $code, string $message, int $status = 400): void
    {
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ]);
        exit;
    }
}
