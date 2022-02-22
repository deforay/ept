<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . "CronInit.php");

$schedule = new \Crunz\Schedule();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$timezone = (!empty($conf->timezone) ? $conf->timezone : "UTC");

// Evaluate Shipment

$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/../scheduled-jobs/evaluate-shipment.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Running Shipment evaluations');

return $schedule;
