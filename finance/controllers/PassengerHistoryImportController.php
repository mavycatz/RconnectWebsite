<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/Database.php';

use App\Core\Database;

header('Content-Type: application/json');

try {

    $db = Database::get();

    /*
    |--------------------------------------------------------------------------
    | VALIDATE CSV FILE
    |--------------------------------------------------------------------------
    */

    if (

        !isset($_FILES['passenger_history_csv']) ||

        $_FILES['passenger_history_csv']['error'] !== 0

    ) {

        throw new Exception(
            'Please upload a valid CSV file.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FILE SIZE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (

        $_FILES['passenger_history_csv']['size']

        > (10 * 1024 * 1024)

    ) {

        throw new Exception(
            'CSV file is too large. Maximum is 10MB.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FILE INFO
    |--------------------------------------------------------------------------
    */

    $tmpPath =
        $_FILES['passenger_history_csv']['tmp_name'];

    $fileName =
        $_FILES['passenger_history_csv']['name'];

    $extension = strtolower(

        pathinfo(
            $fileName,
            PATHINFO_EXTENSION
        )
    );

    if ($extension !== 'csv') {

        throw new Exception(
            'Only CSV files are supported.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN CSV
    |--------------------------------------------------------------------------
    */

    $handle = fopen($tmpPath, 'r');

    if (!$handle) {

        throw new Exception(
            'Unable to open CSV file.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER VALIDATION
    |--------------------------------------------------------------------------
    */

    $header = fgetcsv($handle);

    if (!$header) {

        throw new Exception(
            'CSV header is missing.'
        );
    }

    $requiredColumns = [

        'passenger_name',
        'passenger_type',
        'boarding_point',
        'destination',
        'bus_number',
        'fare'

    ];

    foreach ($requiredColumns as $column) {

        if (!in_array($column, $header)) {

            throw new Exception(
                "Missing required column: {$column}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $db->beginTransaction();

    $inserted = 0;

    /*
    |--------------------------------------------------------------------------
    | INSERT PASSENGER HISTORY
    |--------------------------------------------------------------------------
    */

    while (($row = fgetcsv($handle)) !== false) {

        /*
        |--------------------------------------------------------------------------
        | SKIP EMPTY ROWS
        |--------------------------------------------------------------------------
        */

        if (count(array_filter($row)) === 0) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | TIME VALIDATION
        |--------------------------------------------------------------------------
        */

        $timeOfDeparture = null;
        $timeOfArrival = null;

        if (!empty(trim($row[15] ?? ''))) {
            $raw = trim($row[15]);
            $dt = DateTime::createFromFormat('H:i:s', $raw);
            if (!$dt) $dt = DateTime::createFromFormat('H:i', $raw);
            if (!$dt) $dt = DateTime::createFromFormat('His', $raw);
            if (!$dt) {
                throw new Exception(
                    "Invalid time_of_departure format '{$raw}' at row {$inserted} (expected HH:MM:SS)"
                );
            }
            $timeOfDeparture = $dt->format('H:i:s');
        }

        if (!empty(trim($row[16] ?? ''))) {
            $raw = trim($row[16]);
            $dt = DateTime::createFromFormat('H:i:s', $raw);
            if (!$dt) $dt = DateTime::createFromFormat('H:i', $raw);
            if (!$dt) $dt = DateTime::createFromFormat('His', $raw);
            if (!$dt) {
                throw new Exception(
                    "Invalid time_of_arrival format '{$raw}' at row {$inserted} (expected HH:MM:SS)"
                );
            }
            $timeOfArrival = $dt->format('H:i:s');
        }

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE CHECK
        |--------------------------------------------------------------------------
        */

        $checkSQL = "

            SELECT COUNT(*)

            FROM passenger_trip_history

            WHERE

                passenger_name = :passenger_name

                AND trip_id = :trip_id

                AND trip_date = :trip_date

        ";

        $checkStmt =
            $db->prepare($checkSQL);

        $checkStmt->execute([

            ':passenger_name' =>

                trim($row[0] ?? ''),

            ':trip_id' =>

                !empty(trim($row[9] ?? ''))

                    ? (int)$row[9]

                    : null,

            ':trip_date' =>

                !empty(trim($row[14] ?? ''))

                    ? trim($row[14])

                    : null

        ]);

        if ($checkStmt->fetchColumn() > 0) {

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT PASSENGER HISTORY
        |--------------------------------------------------------------------------
        */

        $sql = "

            INSERT INTO passenger_trip_history (

                passenger_name,
                passenger_type,
                student_id,
                senior_pwd_id,
                beneficiary_id,
                coop_disc_id,
                boarding_point,
                destination,
                bus_number,
                trip_id,
                fare,
                payment_method,
                remarks,
                date_booked,
                trip_date,
                time_of_departure,
                time_of_arrival

            )

            VALUES (

                :passenger_name,
                :passenger_type,
                :student_id,
                :senior_pwd_id,
                :beneficiary_id,
                :coop_disc_id,
                :boarding_point,
                :destination,
                :bus_number,
                :trip_id,
                :fare,
                :payment_method,
                :remarks,
                :date_booked,
                :trip_date,
                :time_of_departure,
                :time_of_arrival

            )

        ";

        $stmt =
            $db->prepare($sql);

        $stmt->execute([

            ':passenger_name' =>

                trim($row[0] ?? ''),

            ':passenger_type' =>

                trim($row[1] ?? ''),

            ':student_id' =>

                !empty(trim($row[2] ?? ''))

                    ? trim($row[2])

                    : null,

            ':senior_pwd_id' =>

                !empty(trim($row[3] ?? ''))

                    ? trim($row[3])

                    : null,

            ':beneficiary_id' =>

                !empty(trim($row[4] ?? ''))

                    ? trim($row[4])

                    : null,

            ':coop_disc_id' =>

                !empty(trim($row[5] ?? ''))

                    ? trim($row[5])

                    : null,

            ':boarding_point' =>

                trim($row[6] ?? ''),

            ':destination' =>

                trim($row[7] ?? ''),

            ':bus_number' =>

                trim($row[8] ?? ''),

            ':trip_id' =>

                !empty(trim($row[9] ?? ''))

                    ? (int)$row[9]

                    : null,

            ':fare' =>

                !empty(trim($row[10] ?? ''))

                    ? (float)$row[10]

                    : 0,

            ':payment_method' =>

                trim($row[11] ?? ''),

            ':remarks' =>

                !empty(trim($row[12] ?? ''))

                    ? trim($row[12])

                    : null,

            ':date_booked' =>

                !empty(trim($row[13] ?? ''))

                    ? trim($row[13])

                    : null,

            ':trip_date' =>

                !empty(trim($row[14] ?? ''))

                    ? trim($row[14])

                    : null,

            ':time_of_departure' =>

                $timeOfDeparture,

            ':time_of_arrival' =>

                $timeOfArrival

        ]);

        $inserted++;
    }

    fclose($handle);

/*
|--------------------------------------------------------------------------
| GET INSERTED EVIDENCE ID
|--------------------------------------------------------------------------
*/

$evidenceId = (int)$db->lastInsertId();

/*
|--------------------------------------------------------------------------
| UPDATE IMPORTED HISTORY TRIP IDS
|--------------------------------------------------------------------------
*/

$updateHistorySQL = "

    UPDATE passenger_trip_history

    SET trip_id = :trip_id

    WHERE trip_id IS NULL

    OR trip_id = 0

";

$updateHistoryStmt =
    $db->prepare($updateHistorySQL);

$updateHistoryStmt->execute([

    ':trip_id' => $evidenceId

]);
    /*
    |--------------------------------------------------------------------------
    | SAVE OPERATIONAL EVIDENCE
    |--------------------------------------------------------------------------
    */

    if (!empty($_POST['evidence'])) {

        $evidence =
            $_POST['evidence'];

        $paoSignature = null;

        $driverSignature = null;

        /*
        |--------------------------------------------------------------------------
        | UPLOAD DIRECTORIES
        |--------------------------------------------------------------------------
        */

        $uploadRoot =

            $_SERVER['DOCUMENT_ROOT']

            . '/uploads/';

        $uploadDir =

            $uploadRoot

            . 'signatures/';

        if (!is_dir($uploadRoot)) {

            mkdir(
                $uploadRoot,
                0777,
                true
            );
        }

        if (!is_dir($uploadDir)) {

            mkdir(
                $uploadDir,
                0777,
                true
            );
        }

        /*
        |--------------------------------------------------------------------------
        | .HTACCESS SECURITY
        |--------------------------------------------------------------------------
        */

        $htaccessPath =
            $uploadDir . '.htaccess';

        if (!file_exists($htaccessPath)) {

            file_put_contents(

                $htaccessPath,

"Options -Indexes

<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8)$\">
    Deny from all
</FilesMatch>"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ALLOWED MIME TYPES
        |--------------------------------------------------------------------------
        */

        $allowedMimeTypes = [

            'image/jpeg',
            'image/png',
            'image/webp'

        ];


        /*
        |--------------------------------------------------------------------------
        | PAO SIGNATURE
        |--------------------------------------------------------------------------
        */

        if (

            isset($_FILES['pao_signature']) &&

            $_FILES['pao_signature']['error'] === 0

        ) {

            $fileMime = mime_content_type(

                $_FILES['pao_signature']['tmp_name']
            );

            if (

                !in_array(
                    $fileMime,
                    $allowedMimeTypes
                )

            ) {

                throw new Exception(
                    'Invalid PAO signature file type.'
                );
            }

            $ext = strtolower(

                pathinfo(

                    $_FILES['pao_signature']['name'],

                    PATHINFO_EXTENSION
                )
            );

            $paoSignature =

                uniqid('pao_', true)

                . '.'

                . $ext;

            move_uploaded_file(

                $_FILES['pao_signature']['tmp_name'],

                $uploadDir . $paoSignature
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DRIVER SIGNATURE
        |--------------------------------------------------------------------------
        */

        if (

            isset($_FILES['driver_signature']) &&

            $_FILES['driver_signature']['error'] === 0

        ) {

            $fileMime = mime_content_type(

                $_FILES['driver_signature']['tmp_name']
            );

            if (

                !in_array(
                    $fileMime,
                    $allowedMimeTypes
                )

            ) {

                throw new Exception(
                    'Invalid driver signature file type.'
                );
            }

            $ext = strtolower(

                pathinfo(

                    $_FILES['driver_signature']['name'],

                    PATHINFO_EXTENSION
                )
            );

            $driverSignature =

                uniqid('driver_', true)

                . '.'

                . $ext;

            move_uploaded_file(

                $_FILES['driver_signature']['tmp_name'],

                $uploadDir . $driverSignature
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT EVIDENCE
        |--------------------------------------------------------------------------
        */

/*
|--------------------------------------------------------------------------
| VALIDATE TRIP ID
|--------------------------------------------------------------------------
*/

if (

    empty(
        trim(
            $evidence['trip_id']
            ?? ''
        )
    )

) {

    throw new Exception(
        'Please select a Trip Operation Report.'
    );
}
        $evidenceSQL = "

            INSERT INTO trip_operation_evidences (

                trip_id,

                terminal_code_1,
                terminal_code_2,

                departure_time,
                arrival_time,

                total_passengers,

                pao_name,
                pao_signature,

                driver_name,
                driver_signature,

                remarks

            )

            VALUES (

                :trip_id,

                :terminal_code_1,
                :terminal_code_2,

                :departure_time,
                :arrival_time,

                :total_passengers,

                :pao_name,
                :pao_signature,

                :driver_name,
                :driver_signature,

                :remarks

            )

        ";

        $evidenceStmt =
            $db->prepare($evidenceSQL);

        $evidenceStmt->execute([

            ':trip_id' =>

                !empty(
                    trim(
                        $evidence['trip_id']
                        ?? ''
                    )
                )

                    ? (int)$evidence['trip_id']

                    : null,

            ':terminal_code_1' =>

                !empty(
                    trim(
                        $evidence['terminal_code_1']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['terminal_code_1']
                    )

                    : null,

            ':terminal_code_2' =>

                !empty(
                    trim(
                        $evidence['terminal_code_2']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['terminal_code_2']
                    )

                    : null,

            ':departure_time' =>

                !empty(
                    trim(
                        $evidence['departure_time']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['departure_time']
                    )

                    : null,

            ':arrival_time' =>

                !empty(
                    trim(
                        $evidence['arrival_time']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['arrival_time']
                    )

                    : null,

            ':total_passengers' =>

                !empty(
                    trim(
                        $evidence['total_passengers']
                        ?? ''
                    )
                )

                    ? (int)$evidence['total_passengers']

                    : 0,

            ':pao_name' =>

                !empty(
                    trim(
                        $evidence['pao_name']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['pao_name']
                    )

                    : null,

            ':pao_signature' =>

                $paoSignature,

            ':driver_name' =>

                !empty(
                    trim(
                        $evidence['driver_name']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['driver_name']
                    )

                    : null,

            ':driver_signature' =>

                $driverSignature,

            ':remarks' =>

                !empty(
                    trim(
                        $evidence['remarks']
                        ?? ''
                    )
                )

                    ? trim(
                        $evidence['remarks']
                    )

                    : null

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $db->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'message' =>

            $inserted

            . ' passenger history records imported successfully.'

    ]);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (

        isset($db) &&

        $db->inTransaction()

    ) {

        $db->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage(),

        'line' => $e->getLine(),

        'file' => basename($e->getFile())

    ]);
}