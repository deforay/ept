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
$schedule->run($phpPath . " " . BIN_PATH . "/db-tools.php backup")
    ->cron('45 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Backing Up Database');


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

return $schedule;
