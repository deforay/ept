<?php

const APP_VERSION = '7.7.5';

// PHP2Shell (CVE-2026-109618, CVE-2026-109619) is fixed in 8.4.27 and 8.5.12. Composer's
// platform check rejects anything below 8.4.27 but checks only that lowest bound, so the
// 8.5 floor is enforced here. Keep in step with "php" in composer.json.
// Ubuntu 26.04 ships PHP 8.5.4 with the PHP2Shell patches backported from package revision
// 0ubuntu1.6 (CVE-2026-109618_109619.patch), so that build passes. PHP_VERSION reports only
// "8.5.4", so read the installed package version from the first line of its changelog,
// for example "php8.5 (8.5.4-0ubuntu1.6) resolute-security; urgency=medium".
$isPatchedUbuntuPhp = false;
if (PHP_VERSION_ID >= 80500 && PHP_VERSION_ID < 80512) {
    $ubuntuChangelog = @gzopen('/usr/share/doc/php8.5-common/changelog.Debian.gz', 'rb');
    if ($ubuntuChangelog !== false) {
        $ubuntuRelease = (string) gzgets($ubuntuChangelog, 200);
        gzclose($ubuntuChangelog);
        $isPatchedUbuntuPhp = preg_match('/^php8\.5 \(8\.5\.4-0ubuntu(\d+(?:\.\d+)*)\)/', $ubuntuRelease, $ubuntuRevision) === 1
            && version_compare($ubuntuRevision[1], '1.6', '>=');
    }
}
if (PHP_VERSION_ID >= 80500 && PHP_VERSION_ID < 80512 && !$isPatchedUbuntuPhp) {
    $phpFloorMessage = 'ePT requires PHP 8.4.27 or later, or PHP 8.5.12 or later. This server runs PHP ' . PHP_VERSION . '.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $phpFloorMessage . PHP_EOL);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $phpFloorMessage;
    }
    exit(1);
}
unset($isPatchedUbuntuPhp, $ubuntuChangelog, $ubuntuRelease, $ubuntuRevision);

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
