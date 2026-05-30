<?php

// scheduled-jobs/bulk-reset-passwords.php
//
// Picked up by execute-job-queue.php. Reads a JSON payload file from
// TEMP_UPLOAD_PATH and runs the bulk reset + email queue via
// Application_Service_DataManagers::bulkResetPasswordsFromAdmin().
//
// Usage: php bulk-reset-passwords.php -f 'bulk-reset-XXXX.json'

ini_set('memory_limit', '-1');
set_time_limit(0);

require_once __DIR__ . '/../cli-bootstrap.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $opts = getopt('f:');
    $fileName = isset($opts['f']) ? basename((string) $opts['f']) : '';
    if ($fileName === '' || !preg_match('/^bulk-reset-[A-Za-z0-9_.-]+\.json$/', $fileName)) {
        fwrite(STDERR, "bulk-reset-passwords: invalid or missing -f payload filename\n");
        exit(1);
    }

    $payloadPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($payloadPath)) {
        fwrite(STDERR, "bulk-reset-passwords: payload file not found: $payloadPath\n");
        exit(1);
    }

    $payload = json_decode((string) file_get_contents($payloadPath), true);
    if (!is_array($payload)) {
        fwrite(STDERR, "bulk-reset-passwords: payload not valid JSON\n");
        exit(1);
    }

    $service = new Application_Service_DataManagers();
    $summary = $service->bulkResetPasswordsFromAdmin($payload);

    echo sprintf(
        "bulk-reset: total=%d updated=%d emailed=%d skipped=%d\n",
        (int) ($summary['total'] ?? 0),
        (int) ($summary['updated'] ?? 0),
        (int) ($summary['emailed'] ?? 0),
        is_array($summary['skipped'] ?? null) ? count($summary['skipped']) : 0
    );

    @unlink($payloadPath);
    exit(0);
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError('bulk-reset-passwords failed: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
