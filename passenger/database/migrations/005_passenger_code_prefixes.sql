-- Passenger code scheme: PREFIX-NNNN (REG/STD/SEN/PWD/TCH/NUR/VCH/PRO/COO/MEM/SPC/OTH).
--
-- Runtime behavior (PassengerCodeService):
--   * new passengers receive a code on creation;
--   * existing codes (AX..., RCP-..., SIM-...) are preserved as-is;
--   * only empty/NULL codes are backfilled in small batches;
--   * a unique index is added only when the table has no duplicate codes.
--
-- This file documents the optional uniqueness guarantee for deployers.

-- Only run when the duplicate check below returns no rows:
--   SELECT passenger_code FROM passengers
--   WHERE passenger_code IS NOT NULL AND passenger_code <> ''
--   GROUP BY passenger_code HAVING COUNT(*) > 1 LIMIT 1;

ALTER TABLE `passengers`
    ADD UNIQUE KEY `uniq_passenger_code` (`passenger_code`);
