<?php

require_once __DIR__ . '/../cli-bootstrap.php';

$schedule = new \Crunz\Schedule();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$timezone = date_default_timezone_get();
if (!empty($conf->timezone)) {
    $timezone = $conf->timezone;
}
$phpPath = !empty($conf->php->path) ? $conf->php->path : PHP_BINARY;

// Generate Shipment Reports

$schedule->run($phpPath . " " . SCHEDULED_JOBS_FOLDER . "/generate-shipment-reports.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Generating Shipment reports');

// DB Backup
$schedule->run($phpPath . " " . VENDOR_BIN . "/db-tools backup")
    ->cron('45 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Backing Up Database');

// Weekly config backup: snapshot application.ini (not under VCS; carries DB
// creds + mail DSNs) into backups/config/. Skips when unchanged, prunes old
// copies. Runs Sundays 01:00, just after the nightly DB backup.
$schedule->run($phpPath . " " . BIN_PATH . "/backup-config.php --quiet")
    ->cron('0 1 * * 0') // 01:00 every Sunday
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Backing up application.ini config');

// Daily binlog purge
$schedule->run($phpPath . " " . VENDOR_BIN . "/db-tools purge-binlogs --days=7")
    ->cron('5 4 * * *') // 04:05 am daily
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('DB Tools: purge MySQL binary logs older than 7 days');

// Housekeeping: prune grow-unbounded log/queue tables + stale FS artifacts.
// Runs after the nightly backup so anything pruned is already captured.
// Retention policy lives in bin/housekeeping.php; audit tables are excluded.
$schedule->run($phpPath . " " . BIN_PATH . "/housekeeping.php --quiet")
    ->cron('30 3 * * *') // 03:30 am daily
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Housekeeping: prune transient tables and stale files');

// Send Email Alerts
$schedule->run($phpPath . " " . SCHEDULED_JOBS_FOLDER . "/send-emails.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Sending Emails');

// Run scheduled tasks
$schedule->run($phpPath . " " . SCHEDULED_JOBS_FOLDER . "/execute-job-queue.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Executing Jobs');

// Reset Stale Jobs (Shipments & Reports)
$schedule->run($phpPath . " " . SCHEDULED_JOBS_FOLDER . "/reset-stale-jobs.php")
    ->everyFifteenMinutes()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Resetting stale processing jobs');

// Validate participant + data_manager email addresses (syntax + MX), batched.
// Runs every 4 hours; processes oldest-checked rows first, re-checks failed
// rows after the recheck-days window so transient domain failures get retried.
$schedule->run($phpPath . " " . BIN_PATH . "/check-participant-emails.php --quiet")
    ->cron('15 */4 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Checking participant + DM email validity');

// Process bounce inbox (IMAP). No-ops if email.bounce.host is empty in
// application.ini. Read-only by default; UID-tracked idempotency means
// re-runs are safe.
$schedule->run($phpPath . " " . BIN_PATH . "/process-bounces.php --quiet")
    ->everyThirtyMinutes()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Processing email bounces');

// Process shipment deadlines once the response deadline has passed: flip
// response_switch 'on' -> 'off' at the exact response_deadline instant (interpreted
// in the cutoff timezone) and queue an evaluation for each closed shipment so results
// are ready for human review (reports + finalization stay manual). Runs every minute
// so the switch closes close to the set time. Skips finalized shipments. Idempotent.
$schedule->run($phpPath . " " . SCHEDULED_JOBS_FOLDER . "/process-shipment-deadlines.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Processing shipment deadlines (close switch + queue evaluation)');

return $schedule;
