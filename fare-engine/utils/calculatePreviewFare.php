<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;

function calculatePreviewFare(
    $routeName,
    $from,
    $to,
    $type = "regular"
){

    $conn = Database::get();
    
    // =========================
// SESSION
// =========================

$sessionPath =
    $_SERVER['DOCUMENT_ROOT']
    . '/storage/sessions';

if(!is_dir($sessionPath)){
    mkdir(
        $sessionPath,
        0777,
        true
    );
}

ini_set(
    'session.save_path',
    $sessionPath
);

if(
    session_status() ===
    PHP_SESSION_NONE
){
    session_start();
}
// =========================
// GET PASSENGER TYPE
// =========================

$type = 'regular';

if(
    !empty($_SESSION['passenger_id'])
){

    $stmt =
        $conn->prepare("

            SELECT
                discount_type
            FROM passengers
            WHERE id = ?
            LIMIT 1

        ");

    $stmt->execute([
        $_SESSION['passenger_id']
    ]);

    $discountType =
        $stmt->fetchColumn();

    if(
        !empty($discountType)
    ){

        $type =
            strtolower(
                trim(
                    $discountType
                )
            );

    }

}

    // =========================
    // NORMALIZER
    // =========================

    $normalize = function($value){

        $value =
            strtolower(
                (string)$value
            );

        $value =
            str_replace(
                [
                    ', nueva ecija'
                ],
                '',
                $value
            );

        $value =
            preg_replace(
                '/[^a-z0-9 ]/',
                ' ',
                $value
            );

        $value =
            preg_replace(
                '/\s+/',
                ' ',
                $value
            );

        return trim(
            $value
        );

    };

    // =========================
    // LOAD FARE PRICING (source of truth for destinations and fares)
    // =========================

    $routeName = trim($routeName);

    $stmt = $conn->prepare("
        SELECT
            id,
            destination,
            display_order,
            regular_fare,
            student_fare,
            special_fare
        FROM fare_pricing
        WHERE route_name = ?
        ORDER BY display_order ASC
    ");
    $stmt->execute([$routeName]);
    $farePricing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($farePricing)){
        return [
            'success' => false,
            'fare' => 0,
            'message' => 'No fare data found for route'
        ];
    }

    // =========================
    // FIND STOP IN FARE PRICING
    // =========================

    $findStop = function(
        $location
    ) use (
        $farePricing,
        $normalize
    ){

        $value = $normalize($location);

        foreach($farePricing as $fp){
            if($normalize($fp['destination']) === $value){
                return $fp;
            }
        }

        foreach($farePricing as $fp){
            $fpNorm = $normalize($fp['destination']);
            if(
                strpos($value, $fpNorm) !== false ||
                strpos($fpNorm, $value) !== false
            ){
                return $fp;
            }
        }

        foreach($farePricing as $fp){
            $fpNorm = $normalize($fp['destination']);
            $words = explode(' ', $fpNorm);
            foreach($words as $word){
                if(strlen($word) < 4){ continue; }
                if(strpos($value, $word) !== false){
                    return $fp;
                }
            }
        }

        foreach($farePricing as $fp){
            $fpNorm = $normalize($fp['destination']);
            $words = explode(' ', $value);
            foreach($words as $word){
                if(strlen($word) < 4){ continue; }
                if(strpos($fpNorm, $word) !== false){
                    return $fp;
                }
            }
        }

        return null;
    };

    $fromStop = $findStop($from);
    $toStop = $findStop($to);

    if(!$fromStop || !$toStop){
        return [
            'success' => false,
            'fare' => 0,
            'message' => 'Stop not found',
            'debug' => [
                'routeName' => $routeName,
                'from' => $from,
                'to' => $to,
                'fromStop' => $fromStop,
                'toStop' => $toStop,
                'availableStops' =>
                    array_column($farePricing, 'destination')
            ]
        ];
    }

    // =========================
    // CALCULATE FARE
    // =========================

    $fareColumn = match (strtolower($type)) {
        'student', 'senior', 'senior citizen', 'pwd' => 'student_fare',
        'teacher', 'nurse', 'special' => 'special_fare',
        default => 'regular_fare'
    };

    $boardingFare = (float)($fromStop[$fareColumn] ?? 15);
    $destinationFare = (float)($toStop[$fareColumn] ?? 15);

    // Rule 1: Boarding at terminal (display_order = 1) → fare is destination fare
    if((int)$fromStop['display_order'] === 1){
        $fare = $destinationFare;
    } else {
        // Rule 2: Boarding after terminal → fare = destination - boarding (min ₱15)
        $fare = max($destinationFare - $boardingFare, 15);
    }

    return [
        'success' => true,
        'fare' => $fare,
        'debug' => [
            'routeName' => $routeName,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'boardingDisplayOrder' => (int)$fromStop['display_order'],
            'boardingFare' => $boardingFare,
            'destinationFare' => $destinationFare,
            'fare' => $fare,
            'fromStop' => $fromStop,
            'toStop' => $toStop
        ]
    ];
}


