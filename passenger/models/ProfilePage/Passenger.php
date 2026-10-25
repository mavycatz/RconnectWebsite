<?php

class Profile {

    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    public function getById($id){
        $stmt = $this->db->prepare("SELECT * FROM passengers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getOutstandingBalance($passenger_id){
        // Shared helper: a continuation chain counts once (its carried balance
        // is folded into the continuation row), so the profile total matches
        // the continuation payment summary instead of double counting.
        $rows = $this->outstandingTrips($passenger_id);

        return array_map(static function (array $row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'fare' => (float) ($row['fare'] ?? 0),
                'total_due' => (float) ($row['total_due'] ?? 0),
                'gcash_amount' => $row['gcash_amount'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'boarding_point' => $row['boarding_point'] ?? null,
                'destination' => $row['destination'] ?? null,
                'boarding_time' => $row['boarding_time'] ?? null,
            ];
        }, $rows);
    }

    public function getTotalOutstanding($passenger_id){
        $total = 0.0;

        foreach ($this->outstandingTrips($passenger_id) as $row) {
            $total += (float) ($row['total_due'] ?? 0);
        }

        return round($total, 2);
    }

    private function outstandingTrips($passenger_id){
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';

            return \ContinuationJourneyService::outstandingTrips($this->db, $passenger_id);
        } catch (\Throwable $e) {
            $this->logError('outstanding helper failed: ' . $e->getMessage());
            return [];
        }
    }

    private function logError($message){
        try {
            error_log('Profile: ' . $message);

            $logDir = dirname(__DIR__, 3) . '/storage/logs';
            if (is_dir($logDir)) {
                @file_put_contents(
                    $logDir . '/passenger_errors.log',
                    date('c') . ' | profile | ' . $message . PHP_EOL,
                    FILE_APPEND
                );
            }
        } catch (\Throwable $e) {
            // Never let logging break the page.
        }
    }
}