-- Migration for version 7.4.9
-- Amit Dugar - May 2026
-- 1) Per-shipment control for auto-closing the response switch at the deadline.
--    Default 'yes' — auto-close is the standard behaviour, and ADD COLUMN ... DEFAULT
--    'yes' sets every existing shipment to opted-in too. Admins can switch an
--    individual shipment to 'no' to keep the old "allow response after due date"
--    behaviour (late responses accepted, just flagged) for that shipment.
-- 2) Drop the dead 'response_after_evaluate' global_config row — its UI was removed
--    and it was never read by any application logic.

UPDATE `system_config` SET `value` = '7.4.9' WHERE `config` = 'app_version';

ALTER TABLE `shipment`
    ADD COLUMN `auto_close_at_deadline` ENUM('yes','no') NOT NULL DEFAULT 'yes' AFTER `response_deadline`;

DELETE FROM `global_config` WHERE `name` = 'response_after_evaluate';
