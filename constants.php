<?php

const APP_VERSION = '7.3.0';

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', 'production');

defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

const WEB_ROOT = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';
const UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads';
const TEMP_UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary';
const DB_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'database';
const APPLICATION_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'application';
const DOWNLOADS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'downloads';
const CRON_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs';
const SCHEDULED_JOBS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs';
const PARTICIPANT_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/participant-layouts';
const SUMMARY_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/summary-layouts';
