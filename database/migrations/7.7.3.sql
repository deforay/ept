-- Migration for version 7.7.3
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.3' WHERE `config` = 'app_version';

-- Audit log entries say what changed and what they are about, not only that
-- something happened.
--
--   entity_type, entity_id, entity_label  the record the entry is about, such
--       as a shipment, participant, scheme or test kit. The label keeps a
--       readable name after the record is renamed or deleted.
--   details        JSON: changed fields with before and after values, facts
--                  such as the pass mark an evaluation used, and lists such as
--                  the participants removed in one bulk action.
--   important      1 for changes that can alter scores or results.
--   actor_id       the admin or data manager id, which survives email changes.
--   on_behalf_of   the data manager account an admin used through View as.
--   request_id     groups the entries written by one request.
--   route          HTTP method and module/controller/action, or the CLI script.
--   channel        web, api or cli.
--
-- migrate.php re-applies the current version on every run and treats
-- duplicate column and duplicate key errors as already applied.

ALTER TABLE `audit_log`
    ADD COLUMN `entity_type` VARCHAR(32) NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `audit_log`
    ADD COLUMN `entity_id` VARCHAR(64) NULL DEFAULT NULL AFTER `entity_type`;

ALTER TABLE `audit_log`
    ADD COLUMN `entity_label` VARCHAR(255) NULL DEFAULT NULL AFTER `entity_id`;

ALTER TABLE `audit_log`
    ADD COLUMN `details` JSON NULL DEFAULT NULL AFTER `entity_label`;

ALTER TABLE `audit_log`
    ADD COLUMN `important` TINYINT(1) NOT NULL DEFAULT 0 AFTER `details`;

ALTER TABLE `audit_log`
    ADD COLUMN `actor_id` INT NULL DEFAULT NULL AFTER `created_by_role`;

ALTER TABLE `audit_log`
    ADD COLUMN `on_behalf_of` VARCHAR(256) NULL DEFAULT NULL AFTER `actor_id`;

ALTER TABLE `audit_log`
    ADD COLUMN `request_id` CHAR(16) NULL DEFAULT NULL AFTER `session_hash`;

ALTER TABLE `audit_log`
    ADD COLUMN `route` VARCHAR(255) NULL DEFAULT NULL AFTER `request_id`;

ALTER TABLE `audit_log`
    ADD COLUMN `channel` VARCHAR(16) NULL DEFAULT NULL AFTER `route`;

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_entity` (`entity_type`, `entity_id`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_important` (`important`, `created_on`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_request` (`request_id`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_on_behalf_of` (`on_behalf_of`);
