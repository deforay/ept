<?php

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', 'production');

// Define CRON PATH
defined('CRON_PATH')
    || define('CRON_PATH', realpath(dirname(__FILE__)));


defined('ROOT_PATH')
    || define('ROOT_PATH', dirname(__DIR__, 1));

const WEB_ROOT = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';
const UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads';
const TEMP_UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary';
const DB_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'database';
const APPLICATION_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'application';
const DOWNLOADS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'downloads';
const SCHEDULED_JOBS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs';
const PARTICIPANT_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/participant-layouts';
const SUMMARY_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/summary-layouts';


// Ensure library/ is on include_path

set_include_path(implode(PATH_SEPARATOR, [
    realpath(ROOT_PATH . '/vendor'),
    realpath(ROOT_PATH . '/library'),
    get_include_path(),
]));

require_once(APPLICATION_PATH . '/../vendor/autoload.php');

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

Zend_Session::$_unitTestEnabled = true;

$application->bootstrap();
