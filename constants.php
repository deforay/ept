<?php

const APP_VERSION = '7.3.6';

// Shipment statuses that indicate temporary/in-progress states (not milestones)
const SHIPMENT_EPHEMERAL_STATUSES = ['draft', 'ready', 'queued', 'processing', 'pending'];

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

loadRootEnvFile(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');

/**
 * Load root .env values into the current PHP process for CLI and web entry points.
 * Existing environment variables win so real process-level secrets are not overwritten.
 */
function loadRootEnvFile(string $envFile): void
{
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        if (str_starts_with($trimmedLine, 'export ')) {
            $trimmedLine = trim(substr($trimmedLine, 7));
        }

        if (!str_contains($trimmedLine, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmedLine, 2);
        $key = trim($key);
        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        if (getenv($key) !== false) {
            continue;
        }

        $value = trim($value);
        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
