<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . "CronInit.php");

$schedule = new \Crunz\Schedule();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$timezone = (!empty($conf->timezone) ? $conf->timezone : "UTC");
$phpPath = (!empty($conf->php->path) ? $conf->php->path : PHP_BINARY);

// Generate Shipment Reports

$schedule->run($phpPath . " " . APPLICATION_PATH . "/../scheduled-jobs/generate-shipment-reports.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Generating Shipment reports');


// Send Email Alerts
$schedule->run($phpPath . " " . APPLICATION_PATH . "/../scheduled-jobs/send-emails.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Sending Emails');

// Send Email Alerts
$schedule->run($phpPath . " " . APPLICATION_PATH . "/../scheduled-jobs/execute-job-queue.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Executing Jobs');

return $schedule;

