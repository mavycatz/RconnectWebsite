<?php

/**
 * Continuation journey endpoints for logged-in passengers.
 *
 * GET  /passenger/trip/continuation — missed-destination state, remaining
 *      destinations with fares, and the carried-balance summary.
 * POST /passenger/trip/continue     — create the continuation segment
 *      (idempotent by source journey + continuation token).
 *
 * The service never completes a trip on its own: the source segment is only
 * finalized when the passenger creates the continuation, and its original
 * destination, fare, and payment status are never modified.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

class ContinuationController
{
    private $conn;
    private $passengerId;

    public function __construct()
    {
        if (!isset($_SESSION['passenger_id'])) {
            $this->send([
                'success' => false,
                'code' => 'NOT_AUTHENTICATED',
                'message' => 'Not authenticated',
            ], 401);
        }

        $this->passengerId = (int) $_SESSION['passenger_id'];

        $this->conn = Database::tryGet();
        if ($this->conn === null) {
            $this->send([
                'success' => false,
                'code' => 'DB_UNAVAILABLE',
                'message' => 'We could not reach the service right now. Please try again in a moment.',
            ], 503);
        }

        try {
            ensureTripFlowSchema($this->conn);
        } catch (\Throwable $e) {
            // The continuation columns are additive; a schema failure must
            // not crash the endpoint (the service degrades on its own).
        }
    }

    /**
     * Missed-destination state and remaining destinations.
     */
    public function status()
    {
        $trip = $this->activeTrip();

        if (!$trip) {
            $this->send([
                'success' => false,
                'code' => 'NO_ACTIVE_TRIP',
                'message' => 'No in-progress trip found.',
            ]);
        }

        try {
            $this->send(ContinuationJourneyService::statusPayload($this->conn, $trip));
        } catch (\Throwable $e) {
            error_log('Passenger continuation status failed: ' . $e->getMessage());
            $this->send([
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'We could not load your continuation options right now. Please try again.',
            ], 500);
        }
    }

    /**
     * Create the continuation segment for the missed destination.
     */
    public function create()
    {
        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);

        if (!is_array($input)) {
            $this->send([
                'success' => false,
                'code' => 'INVALID_JSON',
                'message' => 'Invalid request. Please try again.',
            ]);
        }

        $trip = $this->activeTrip();

        if (!$trip) {
            $this->send([
                'success' => false,
                'code' => 'NO_ACTIVE_TRIP',
                'message' => 'No in-progress trip found.',
            ], 409);
        }

        if (empty($trip['destination_passed_alerted'])) {
            $this->send([
                'success' => false,
                'code' => 'NO_MISSED_DESTINATION',
                'message' => 'Your destination has not been passed, so there is nothing to continue.',
            ], 409);
        }

        $newDestinationId = (int) ($input['new_destination_id'] ?? 0);
        $token = (string) ($input['continuation_token'] ?? '');

        try {
            $result = ContinuationJourneyService::createContinuation(
                $this->conn,
                $trip,
                $newDestinationId,
                $token
            );
        } catch (\Throwable $e) {
            error_log('Passenger continuation create failed: ' . $e->getMessage());
            $result = [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'We could not start your continuation journey. Please try again.',
            ];
        }

        $status = 200;
        if (empty($result['success'])) {
            $status = in_array(
                (string) ($result['code'] ?? ''),
                ['SAVE_FAILED', 'SERVER_ERROR'],
                true
            ) ? 500 : 422;
        }

        $this->send($result, $status);
    }

    /**
     * The newest in-progress journey for the authenticated passenger.
     */
    private function activeTrip()
    {
        return ContinuationJourneyService::activeTripForPassenger($this->conn, $this->passengerId);
    }

    private function send(array $body, $status = 200)
    {
        http_response_code((int) $status);
        echo json_encode($body);
        exit;
    }
}
