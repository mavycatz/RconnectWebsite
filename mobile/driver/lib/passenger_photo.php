<?php

if (!function_exists('save_passenger_photo')) {

    /**
     * Decodes a `data:image/jpeg;base64,...` passenger photo payload, stores
     * the JPEG under the app's uploads directory, and returns its relative
     * URL. Returns `null` when the payload is missing/invalid/oversized.
     *
     * @param string|null $dataUri  Passenger photo data URI submitted by the client.
     * @param string      $recordId Record identifier used for the file name.
     * @return string|null Relative URL like `passenger_photos/<recordId>.jpg`.
     */
    function save_passenger_photo($dataUri, $recordId)
    {
        if (!is_string($dataUri) || $dataUri === '') {
            return null;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/is', $dataUri, $matches)) {
            return null;
        }

        $base64 = preg_replace('/\s+/', '', $matches[2]);
        if ($base64 === '' || preg_match('/^[A-Za-z0-9+\/=]+$/', $base64) !== 1) {
            return null;
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        // Client caps photos at ~500 KB; allow a small margin for header drift.
        if (strlen($bytes) > 700000) {
            return null;
        }

        $uploadDir = dirname(__DIR__) . '/passenger_photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$recordId) . '.jpg';
        $destination = $uploadDir . $filename;

        if (file_put_contents($destination, $bytes) === false) {
            return null;
        }

        return 'passenger_photos/' . $filename;
    }
}