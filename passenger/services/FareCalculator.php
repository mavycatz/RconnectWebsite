<?php
class FareCalculator {
    private $fares;

    public function __construct($fares) {
        $this->fares = $fares;
    }

    public function calculate($fromId, $toId, $passengerType) {
        foreach ($this->fares as $fare) {
            if (($fare['route_from_id'] == $fromId && $fare['route_to_id'] == $toId) ||
                ($fare['route_from_id'] == $toId && $fare['route_to_id'] == $fromId)) {

                switch ($passengerType) {
                    case 'Student': return $fare['fare_student'];
                    case 'Senior': return $fare['fare_special'];
                    default: return $fare['fare_regular'];
                }
            }
        }
        return 0; // no fare found
    }
}
