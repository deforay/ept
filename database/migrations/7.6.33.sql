-- Migration for version 7.6.33
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.33' WHERE `config` = 'app_version';

-- The certificate batch code sets 'distributing', 'cancelled' and 'rejected',
-- but the status column never listed them. Under strict SQL mode the update
-- failed, so cancel and reject did nothing. Otherwise the status was saved as
-- an empty string and the batch showed no status.
--
-- Idempotent: MODIFY to the same definition is a no-op.
ALTER TABLE `certificate_batches` MODIFY `status` ENUM('pending','generating','generated','approved','distributing','distributed','failed','cancelled','rejected') DEFAULT 'pending';
