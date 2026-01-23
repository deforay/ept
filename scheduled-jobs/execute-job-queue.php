<?php

require_once __DIR__ . '/../cli-bootstrap.php';

// Whitelist of allowed job scripts
const ALLOWED_JOB_SCRIPTS = [
    'generate-certificates.php',
    'evaluate-shipments.php',
];

/**
 * Validates and sanitizes a job command from the database.
 * Returns the safe command string or false if invalid.
 *
 * @param string $job The job command from the database
 * @param string $jobsDir The directory containing job scripts
 * @return string|false The validated command or false if invalid
 */
function validateJobCommand($job, $jobsDir) {
    // Parse the job command - extract the script name and arguments
    // Expected format: "script-name.php -arg1 value1 -arg2 value2"
    if (!preg_match('/^([a-zA-Z0-9_-]+\.php)(\s+.*)?$/', $job, $matches)) {
        error_log("Invalid job format: " . $job);
        return false;
    }

    $scriptName = $matches[1];
    $arguments = isset($matches[2]) ? trim($matches[2]) : '';

    // Verify the script is in the whitelist
    if (!in_array($scriptName, ALLOWED_JOB_SCRIPTS, true)) {
        error_log("Script not in whitelist: " . $scriptName);
        return false;
    }

    // Verify the script file exists
    $scriptPath = $jobsDir . DIRECTORY_SEPARATOR . $scriptName;
    if (!file_exists($scriptPath)) {
        error_log("Script file does not exist: " . $scriptPath);
        return false;
    }

    // Validate arguments format - only allow expected patterns
    // Arguments should be in format: -s 'value' -c 'value' (already escaped with escapeshellarg)
    // Pattern: optional whitespace, dash, letter(s), whitespace, single-quoted value or plain numbers
    if (!empty($arguments)) {
        if (!preg_match("/^(\s*-[a-z]+\s+'[^']*'|\s*-[a-z]+\s+[0-9,]+)+$/i", $arguments)) {
            error_log("Invalid arguments format: " . $arguments);
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
                error_log("Skipping invalid job (ID: {$jobId}): " . $sj['job']);
                continue;
            }

            $db->update('scheduled_jobs', ['status' => "processing"], "job_id = " . $jobId);

            // Build the full command with escaped PHP path and validated job command
            $fullCommand = escapeshellarg($phpPath) . " " . $jobsDir . DIRECTORY_SEPARATOR . $validatedCommand;
            exec($fullCommand);

            $db->update('scheduled_jobs', ["completed_on" => new Zend_Db_Expr('now()'), "status" => "completed"], "job_id = " . $jobId);
        }
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	error_log($e->getTraceAsString());
}
