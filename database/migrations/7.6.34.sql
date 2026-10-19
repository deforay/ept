-- Migration for version 7.6.34
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.34' WHERE `config` = 'app_version';

-- Certificate designer: where each detail prints on an uploaded design, as
-- JSON. A template with a layout prints the details onto the design. A
-- template without one is a fillable PDF form, filled as before.
--
-- Idempotent: on a second run MySQL reports a duplicate column (1060),
-- which migrate.php treats as already applied.
ALTER TABLE `certificate_templates`
    ADD COLUMN `e_layout` TEXT NULL COMMENT 'JSON layout of the excellence certificate details' AFTER `e_detected_fields`;
ALTER TABLE `certificate_templates`
    ADD COLUMN `p_layout` TEXT NULL COMMENT 'JSON layout of the participation certificate details' AFTER `p_detected_fields`;
