<?php

require_once __DIR__ . '/../cli-bootstrap.php';

// Whitelist of allowed job scripts
const ALLOWED_JOB_SCRIPTS = [
    'generate-certificates.php',
    'evaluate-shipments.php',
    'distribute-certificates.php',
    'bulk-reset-passwords.php',
];

/**
 * Validates and sanitizes a job command from the database.
 * Returns the safe command string or false if invalid.
 *
 * @param string $job The job command from the database
 * @param string $jobsDir The directory containing job scripts
 * @return string|false The validated command or false if invalid
 */
function validateJobCommand($job, $jobsDir)
{
    // Parse the job command - extract the script name and arguments
    // Expected format: "script-name.php -arg1 value1 -arg2 value2"
    if (!preg_match('/^([a-zA-Z0-9_-]+\.php)(\s+.*)?$/', $job, $matches)) {
        Pt_Commons_LoggerUtility::logError("Invalid job format: " . $job);
        return false;
    }

    $scriptName = $matches[1];
    $arguments = isset($matches[2]) ? trim($matches[2]) : '';

    // Verify the script is in the whitelist
    if (!in_array($scriptName, ALLOWED_JOB_SCRIPTS, true)) {
        Pt_Commons_LoggerUtility::logError("Script not in whitelist: " . $scriptName);
        return false;
    }

    // Verify the script file exists
    $scriptPath = $jobsDir . DIRECTORY_SEPARATOR . $scriptName;
    if (!file_exists($scriptPath)) {
        Pt_Commons_LoggerUtility::logError("Script file does not exist: " . $scriptPath);
        return false;
    }

    // Validate arguments format - only allow expected patterns
    // Arguments should be in format: -s 'value' -c 'value' (already escaped with escapeshellarg)
    // Pattern: optional whitespace, dash, letter(s), whitespace, single-quoted value or plain numbers
    if (!empty($arguments)) {
        if (!preg_match("/^(\s*-[a-z]+\s+'[^']*'|\s*-[a-z]+\s+[0-9,]+)+$/i", $arguments)) {
            Pt_Commons_LoggerUtility::logError("Invalid arguments format: " . $arguments);
            return false;
        }
    }

    return $scriptName . ($arguments ? ' ' . $arguments : '');
}

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $phpPath = !empty($conf->php->path) ? $conf->php->path : PHP_BINARY;

    $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $scheduledResult = $scheduledDb->fetchAll("status = 'pending'");
    $jobsDir = realpath(APPLICATION_PATH . "/../scheduled-jobs");

    if (!empty($scheduledResult)) {
        foreach ($scheduledResult as $key => $sj) {
            $jobId = intval($sj['job_id']);

            // Validate the job command before execution
            $validatedCommand = validateJobCommand($sj['job'], $jobsDir);
            if ($validatedCommand === false) {
                $db->update('scheduled_jobs', ['status' => "failed"], "job_id = " . $jobId);
                Pt_Commons_LoggerUtility::logWarning("Skipping invalid job (ID: {$jobId}): " . $sj['job']);
                continue;
            }

            $db->update('scheduled_jobs', ['status' => "processing"], "job_id = " . $jobId);

            // Build the full command with escaped PHP path and validated job command
            $fullCommand = escapeshellarg($phpPath) . " " . $jobsDir . DIRECTORY_SEPARATOR . $validatedCommand;
            $output = [];
            $returnCode = 0;
            exec($fullCommand . ' 2>&1', $output, $returnCode);

            // Honor the child's exit code: a non-zero status means the job script failed
            // (e.g. an exception during evaluation). Marking it 'completed' regardless hid
            // real failures, so mark it 'failed' and log the captured output for diagnosis.
            if ($returnCode !== 0) {
                $db->update('scheduled_jobs', ["completed_on" => new Zend_Db_Expr('now()'), "status" => "failed"], "job_id = " . $jobId);
                Pt_Commons_LoggerUtility::logError("Scheduled job {$jobId} failed (exit {$returnCode}): {$validatedCommand}", [
                    'output' => implode("\n", $output),
                ]);
            } else {
                $db->update('scheduled_jobs', ["completed_on" => new Zend_Db_Expr('now()'), "status" => "completed"], "job_id = " . $jobId);
            }
        }
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
