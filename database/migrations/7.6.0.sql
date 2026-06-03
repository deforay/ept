-- Migration for version 7.6.0
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.0' WHERE `config` = 'app_version';

-- Release marker only -- no schema changes in this version. 7.6.0 records the
-- toolchain bump: PHP floor 8.2 -> 8.4 (composer platform pinned 8.4.1),
-- symfony/console+mailer+uid 7.4 -> 8.x, amitdugar/db-tools 2.x -> 3.x, and
-- reproducible lockfile-pinned npm installs (npm ci). No DDL or seeds required.
-- migrate.php re-applies the current version on every run; the single UPDATE
-- above is idempotent, so this file is safe to re-run.
