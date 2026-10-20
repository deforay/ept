<?php

// check-mail-setup.php
//
// Signs in to the mail server with the saved settings and keeps the result, as
// Verify Email Setup does, but sends no email. Runs every 10 minutes and checks
// every hour while email works, every run while it is failing. A confirmed
// failure pauses scheduled-jobs/send-emails.php, and the next passing check
// resumes it. Does nothing while email is not set up.

require_once __DIR__ . '/../cli-bootstrap.php';

const CHECK_EVERY_SECONDS_WHEN_WORKING = 3600;
const CHECK_EVERY_SECONDS_WHEN_FAILING = 600;
// Cron does not start exactly on the minute, so allow a little early
const SCHEDULE_SLACK_SECONDS = 60;

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $problem = Application_Service_Common::mailDeliveryProblem();
    if (($problem['reason'] ?? '') === 'not_configured') {
        return;
    }
    $last = Application_Service_Common::lastMailVerification();
    $interval = $problem !== null ? CHECK_EVERY_SECONDS_WHEN_FAILING : CHECK_EVERY_SECONDS_WHEN_WORKING;
    $force = in_array('--force', $argv ?? [], true);
    if (!$force && $last !== null && time() - (int) $last['at'] < $interval - SCHEDULE_SLACK_SECONDS) {
        return;
    }

    $result = Application_Service_Common::verifyMailSetup('', '', true);
    if (stream_isatty(STDOUT)) {
        echo ($result['ok'] ? 'Email works: ' : 'Email does not work: ') . $result['steps']['login']['message'] . PHP_EOL;
    }
    if (!$result['ok'] && ($last === null || !empty($last['ok']))) {
        // Log only the change from working to failing, not every failing run
        Pt_Commons_LoggerUtility::logWarning('check-mail-setup: email stopped working; the queue is paused until a check passes. ' . $result['steps']['login']['message']);
    } elseif ($result['ok'] && $last !== null && empty($last['ok'])) {
        Pt_Commons_LoggerUtility::logInfo('check-mail-setup: email works again; the queue resumes.');
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError('check-mail-setup: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}
