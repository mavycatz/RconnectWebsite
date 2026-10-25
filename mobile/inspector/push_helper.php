<?php
/**
 * push_helper.php
 *
 * Reusable helpers for sending Expo push notifications.
 *
 * Database relationship:
 *
 * bus_trips
 *     ↓ bus_trips.id = inspector_assignments.trip_id
 *
 * inspector_assignments
 *     ↓ inspector_assignments.inspector_id
 *
 * push_tokens
 *     ↓
 * expo_push_token
 *
 * No Firebase credentials are required in this PHP helper.
 * Expo Push Service handles delivery to FCM.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';


/**
 * Send one or more push messages through Expo Push Service.
 *
 * @param array $messages
 * @return array|null
 */
function sendExpoPushMessages(array $messages): ?array
{
    if (empty($messages)) {
        return null;
    }

    $ch = curl_init(EXPO_PUSH_URL);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($messages),
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        error_log('[PUSH] CURL error: ' . $error);
        return null;
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        error_log('[PUSH] Invalid Expo response: ' . $response);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            '[PUSH] Expo HTTP error ' .
            $httpCode . ': ' .
            $response
        );
    }

    return $decoded;
}


/**
 * Send a push notification to one inspector.
 *
 * @param string $inspectorId
 * @param string $title
 * @param string $body
 * @param array $data
 * @return void
 */
function sendPushToInspector(
    string $inspectorId,
    string $title,
    string $body,
    array $data = []
): void {
    $db = new Database();
    $conn = $db->connect();

    $stmt = $conn->prepare(
        "SELECT expo_push_token
         FROM push_tokens
         WHERE inspector_id = ?
           AND expo_push_token IS NOT NULL
           AND expo_push_token != ''
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('[PUSH] Failed to prepare inspector token query: ' . $conn->error);
        return;
    }

    $stmt->bind_param("s", $inspectorId);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    if (!$row || empty($row['expo_push_token'])) {
        error_log(
            '[PUSH] No registered push token for inspector: ' .
            $inspectorId
        );
        return;
    }

    $message = [
        'to' => $row['expo_push_token'],
        'title' => $title,
        'body' => $body,
        'data' => $data,
        'channelId' => 'default',
        'sound' => 'default',
    ];

    sendExpoPushMessages([$message]);
}


/**
 * Send a push notification to inspectors.
 *
 * If $busId is provided:
 *     Sends only to inspectors who are actively assigned
 *     to today's Departed trip for that bus.
 *
 * If $busId is null:
 *     Sends to every inspector with a registered push token.
 *
 * @param string $title
 * @param string $body
 * @param array $data
 * @param string|null $busId
 * @return void
 */
function sendPushToAllInspectors(
    string $title,
    string $body,
    array $data = [],
    ?string $busId = null
): void {
    $db = new Database();
    $conn = $db->connect();

    /*
     * ---------------------------------------------------------
     * CASE 1:
     * Send to inspectors assigned to a specific bus.
     * ---------------------------------------------------------
     *
     * Relationship:
     *
     * bus_trips.id
     *      =
     * inspector_assignments.trip_id
     *
     * inspector_assignments.inspector_id
     *      =
     * push_tokens.inspector_id
     */
    if ($busId !== null && $busId !== '') {

        $sql = "
            SELECT DISTINCT pt.expo_push_token
            FROM push_tokens pt

            INNER JOIN inspector_assignments ia
                ON ia.inspector_id = pt.inspector_id

            INNER JOIN bus_trips bt
                ON bt.id = ia.trip_id

            WHERE ia.bus_id = ?
              AND ia.status = 'Active'
              AND bt.status = 'Departed'
              AND bt.trip_date = CURDATE()

              AND pt.expo_push_token IS NOT NULL
              AND pt.expo_push_token != ''
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log(
                '[PUSH] Failed to prepare bus inspector query: ' .
                $conn->error
            );
            return;
        }

        $stmt->bind_param("s", $busId);

    } else {

        /*
         * -----------------------------------------------------
         * CASE 2:
         * Send to every inspector with a registered token.
         * -----------------------------------------------------
         */

        $sql = "
            SELECT DISTINCT expo_push_token
            FROM push_tokens
            WHERE expo_push_token IS NOT NULL
              AND expo_push_token != ''
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log(
                '[PUSH] Failed to prepare all-inspector query: ' .
                $conn->error
            );
            return;
        }
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $messages = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row['expo_push_token'])) {
            continue;
        }

        $messages[] = [
            'to' => $row['expo_push_token'],
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channelId' => 'default',
            'sound' => 'default',
        ];
    }

    $stmt->close();

    /*
     * Nothing to send.
     */
    if (empty($messages)) {
        error_log('[PUSH] No registered inspector tokens found.');
        return;
    }

    /*
     * Send all notifications through Expo.
     */
    sendExpoPushMessages($messages);
}