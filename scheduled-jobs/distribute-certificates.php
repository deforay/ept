<?php

require_once __DIR__ . '/../cli-bootstrap.php';

$cliOptions = getopt("b:f:");
$batchId = !empty($cliOptions['b']) ? (int) $cliOptions['b'] : null;
$folderPath = !empty($cliOptions['f']) ? $cliOptions['f'] : null;

// Without a batch or a folder there is nothing approved to distribute. The
// old default copied every certificate folder, approved or not.
if (empty($batchId) && empty($folderPath)) {
    echo "Usage: php distribute-certificates.php -b <batch id> | -f <certificate folder>\n";
    exit(1);
}

$certificateBatchesModel = new Application_Model_DbTable_CertificateBatches();
$batch = null;

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    // Mode 1: Batch-tracked workflow (automated)
    if ($batchId) {
        // Load the batch record
        $batch = $certificateBatchesModel->getBatch($batchId);

        if (!$batch) {
            Pt_Commons_LoggerUtility::logError("Batch not found: $batchId");
            exit(1);
        }

        // Verify status is 'approved'
        if ($batch['status'] !== 'approved') {
            Pt_Commons_LoggerUtility::logWarning("Batch status must be 'approved' to distribute. Current status: " . $batch['status']);
            $certificateBatchesModel->updateStatus($batchId, 'failed', [
                'error_message' => "Cannot distribute batch with status: " . $batch['status']
            ]);
            exit(1);
        }

        // Get the folder_path from the batch
        $folderPath = $batch['folder_path'];

        if (empty($folderPath) || !is_dir($folderPath)) {
            Pt_Commons_LoggerUtility::logError("Invalid folder path: $folderPath");
            $certificateBatchesModel->updateStatus($batchId, 'failed', [
                'error_message' => "Invalid or missing folder path: $folderPath"
            ]);
            exit(1);
        }

        // Update status to indicate distribution in progress
        $certificateBatchesModel->updateStatus($batchId, 'distributing');

        echo "Starting certificate distribution for batch: $batchId\n";
    }
    // Mode 2: Manual workflow (folder path specified directly)
    else {
        if (!is_dir($folderPath)) {
            Pt_Commons_LoggerUtility::logError("Invalid folder path: $folderPath");
            exit(1);
        }

        echo "Starting manual certificate distribution\n";
    }

    echo "Source folder: $folderPath\n\n";

    $stats = ['distributed' => 0, 'skipped' => 0, 'errors' => 0];

    // generate-certificates.php writes manifest.json with the Lab ID of every
    // file. Lab IDs can contain hyphens, so the file name alone cannot tell
    // where the Lab ID ends. Folders from before the manifest fall back to
    // the {uid}-{SCHEME}-{batch name} naming, matched from the right.
    $entries = [];
    $manifestFile = $folderPath . DIRECTORY_SEPARATOR . 'manifest.json';
    if (is_file($manifestFile)) {
        foreach ((array) json_decode((string) file_get_contents($manifestFile), true) as $entry) {
            $entries[] = [
                'path' => $folderPath . DIRECTORY_SEPARATOR . $entry['file'],
                'uid' => (string) $entry['unique_identifier'],
            ];
        }
    } else {
        // Older folders were named after the batch. A batch renamed after
        // generation keeps the old name in the folder and the file names.
        $names = array_unique(array_filter([$batch['batch_name'] ?? '', basename(rtrim($folderPath, DIRECTORY_SEPARATOR))]));
        $suffix = '-(?:' . implode('|', array_map(fn ($n) => preg_quote($n, '/'), $names)) . ')';
        foreach (['excellence', 'participation'] as $type) {
            $typeFolder = $folderPath . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
            $files = array_merge(glob($typeFolder . '*.pdf') ?: [], glob($typeFolder . '*.docx') ?: []);
            foreach ($files as $file) {
                if (preg_match('/^(.+)-[^-]+' . $suffix . '\.(pdf|docx)$/i', basename($file), $matches)) {
                    $entries[] = ['path' => $file, 'uid' => $matches[1]];
                } else {
                    echo "Skipped (could not extract participant ID): " . basename($file) . "\n";
                    $stats['skipped']++;
                }
            }
        }
    }

    foreach ($entries as $entry) {
        $fileName = basename($entry['path']);
        if (!is_file($entry['path'])) {
            Pt_Commons_LoggerUtility::logError("Certificate file missing: {$entry['path']}");
            $stats['errors']++;
            continue;
        }

        $participantFolder = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $entry['uid'] . DIRECTORY_SEPARATOR . "certificates";
        if (!is_dir($participantFolder) && !@mkdir($participantFolder, 0777, true)) {
            Pt_Commons_LoggerUtility::logError("Failed to create directory: $participantFolder");
            $stats['errors']++;
            continue;
        }

        $destPath = $participantFolder . DIRECTORY_SEPARATOR . $fileName;
        if (copy($entry['path'], $destPath)) {
            $stats['distributed']++;
            echo "Copied: $fileName -> $participantFolder\n";
        } else {
            Pt_Commons_LoggerUtility::logError("Failed to copy: {$entry['path']} -> $destPath");
            $stats['errors']++;
        }
    }

    // Print distribution summary
    echo "\n=== Distribution Summary ===\n";
    echo "Certificates distributed: {$stats['distributed']}\n";
    echo "Skipped:                  {$stats['skipped']}\n";
    echo "Errors:                   {$stats['errors']}\n";

    // Update batch status to distributed (only for batch-tracked workflow)
    if ($batchId) {
        $problems = $stats['errors'] + $stats['skipped'];
        $certificateBatchesModel->updateStatus($batchId, 'distributed', [
            'distributed_on' => new Zend_Db_Expr('NOW()'),
            'error_message' => $problems > 0
                ? "{$stats['distributed']} certificate(s) distributed. {$problems} could not be distributed. See the log for details."
                : null,
        ]);
    }

    echo "\nDistribution completed successfully!\n";

} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}", [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Update batch status on failure (only for batch-tracked workflow)
    if ($batchId) {
        $certificateBatchesModel->updateStatus($batchId, 'failed', [
            'error_message' => $e->getMessage()
        ]);
    }

    exit(1);
}
