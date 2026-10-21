<?php

const APP_VERSION = '7.7.0';

// Shipment statuses that indicate temporary/in-progress states (not milestones)
const SHIPMENT_EPHEMERAL_STATUSES = ['draft', 'ready', 'queued', 'processing', 'pending'];

// Shared first-login password for logins created without one (bulk imports, direct-participant-login)
const DEFAULT_PASSWORD = 'ept1@)(*&^'; // NOSONAR

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', 'production');

defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

const APPLICATION_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'application';
const WEB_ROOT = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';
const BIN_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'bin';
const DB_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'database';
const DOWNLOADS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'downloads';
const CRON_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs';
const SCHEDULED_JOBS_FOLDER = ROOT_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs';
const BACKUP_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'backups';
const VENDOR_BIN = ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';

const PARTICIPANT_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/participant-layouts';
const SUMMARY_REPORTS_LAYOUT = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'report-layouts/summary-layouts';

const TEMP_UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary';
const UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads';

// Folders TCPDF 7 may read report images from. Real paths, so uploads and
// temporary files still match when a server keeps them on another disk.
defined('K_ALLOWED_PATHS')
    || define('K_ALLOWED_PATHS', array_values(array_filter(array_map('realpath', [WEB_ROOT, UPLOAD_PATH, TEMP_UPLOAD_PATH, DOWNLOADS_FOLDER]))));
