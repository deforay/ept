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
$schedule->run(VENDOR_BIN . "/db-tools backup")
    ->cron('45 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Backing Up Database');

// Daily binlog purge
$schedule->run(VENDOR_BIN . "/db-tools purge-binlogs --days=7")
    ->cron('5 4 * * *') // 04:05 am daily
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('DB Tools: purge MySQL binary logs older than 7 days');

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

return $schedule;
