-- Baggage category support over the existing cargo storage.
-- Baggage live rows are passengers.type='other' (+ passenger_trips);
-- archived rows are passenger_trip_history.type='Other'.
-- Runtime behavior (Admin\Services\Passenger\BaggageSchema) applies the same
-- columns lazily; this file documents the additive migration.

ALTER TABLE `passengers`             ADD COLUMN `baggage_category` VARCHAR(50) NULL;
ALTER TABLE `passenger_trip_history` ADD COLUMN `baggage_category` VARCHAR(50) NULL;

-- Accepted categories: Box, Carton, Luggage, Sack, Bundle, Plastic, Other.
-- Existing rows remain NULL and display as "not categorized".
