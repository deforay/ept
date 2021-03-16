<?php

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', 'production');

// Define CRON PATH 
defined('CRON_PATH')
    || define('CRON_PATH', realpath(dirname(__FILE__)));


// Define path to u directory
defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', realpath(dirname(__FILE__) . '/../public/uploads'));
// Define path to u directory
defined('TEMP_UPLOAD_PATH')
    || define('TEMP_UPLOAD_PATH', realpath(dirname(__FILE__) . '/../public/temporary'));



defined('DOWNLOADS_FOLDER')
    || define('DOWNLOADS_FOLDER', realpath(dirname(__FILE__) . '/../downloads'));


defined('PARTICIPANT_REPORT_LAYOUT')
    || define('PARTICIPANT_REPORT_LAYOUT', realpath(dirname(__FILE__) . '/../scheduled-jobs/report-layouts/participant-layouts'));
defined('SUMMARY_REPORT_LAYOUT')
    || define('SUMMARY_REPORT_LAYOUT', realpath(dirname(__FILE__) . '/../scheduled-jobs/report-layouts/summary-layouts'));


// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    realpath(APPLICATION_PATH . '/../vendor'),
    get_include_path(),
)));

require_once('vendor/autoload.php');

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

Zend_Session::$_unitTestEnabled = true;

$application->bootstrap();
