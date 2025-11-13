-- Migration for version 7.3.1

ALTER TABLE `temp_mail`
    ADD COLUMN `failure_reason` TEXT NULL AFTER `status`;

UPDATE `system_config` SET `value` = '7.3.1' WHERE `config` = 'app_version';
