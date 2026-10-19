-- Migration for version 7.6.31
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.31' WHERE `config` = 'app_version';

-- r_testkitnames has no primary key on installs set up from sql/init.sql after 7.3.5,
-- which added the key only on installs migrated through 7.2.2. Lookups by kit id then
-- scan the whole table. Add the key on every install.
--
-- Idempotent: on a second run MySQL reports a multiple primary key (1068), which
-- migrate.php treats as already applied.
ALTER TABLE `r_testkitnames` ADD PRIMARY KEY (`TestKitName_ID`);
