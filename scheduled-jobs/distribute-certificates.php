<?php

require_once __DIR__ . '/../cli-bootstrap.php';

$cliOptions = getopt("b:f:");
$batchId = !empty($cliOptions['b']) ? (int) $cliOptions['b'] : null;
$folderPath = !empty($cliOptions['f']) ? $cliOptions['f'] : null;

// Default fallback: legacy certificates folder (TEMP_UPLOAD_PATH/certificates)
if (empty($batchId) && empty($folderPath)) {
    $folderPath = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'certificates';
    echo "No parameters specified - using default path: $folderPath\n";
}

$certificateBatchesModel = new Application_Model_DbTable_CertificateBatches();

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    // Mode 1: Batch-tracked workflow (automated)
    if ($batchId) {
        // Load the batch record
        $batch = $certificateBatchesModel->getBatch($batchId);

        if (!$batch) {
            error_log("Batch not found: $batchId");
            exit(1);
        }

        // Verify status is 'approved'
        if ($batch['status'] !== 'approved') {
            error_log("Batch status must be 'approved' to distribute. Current status: " . $batch['status']);
            $certificateBatchesModel->updateStatus($batchId, 'failed', [
                'error_message' => "Cannot distribute batch with status: " . $batch['status']
            ]);
            exit(1);
        }

        // Get the folder_path from the batch
        $folderPath = $batch['folder_path'];

        if (empty($folderPath) || !is_dir($folderPath)) {
            error_log("Invalid folder path: $folderPath");
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
            error_log("Invalid folder path: $folderPath");
            exit(1);
        }

        echo "Starting manual certificate distribution\n";
    }

    echo "Source folder: $folderPath\n\n";

    $excellenceFolder = $folderPath . DIRECTORY_SEPARATOR . 'excellence';
    $participationFolder = $folderPath . DIRECTORY_SEPARATOR . 'participation';

    $stats = ['distributed' => 0, 'skipped' => 0, 'errors' => 0];

    /**
     * Process PDF files from a folder and copy them to participant download folders
     *
     * @param string $folder The source folder containing PDF files
     * @param array &$stats Reference to stats array for tracking
     * @return void
     */
    function distributeCertificatesFromFolder($folder, &$stats)
    {
        if (!is_dir($folder)) {
            echo "Folder not found: $folder\n";
            return;
        }

        // Scan for PDF files (also check for DOCX in case they weren't converted)
        $pdfFiles = glob($folder . DIRECTORY_SEPARATOR . '*.pdf');
        $docxFiles = glob($folder . DIRECTORY_SEPARATOR . '*.docx');
        $allFiles = array_merge($pdfFiles, $docxFiles);

        foreach ($allFiles as $file) {
            $fileName = basename($file);

            // Extract participant UID from filename
            // Format is typically: {participantCode}_...pdf or {participantCode}-...pdf
            // The participant code is everything before the first dash or underscore
            if (preg_match('/^([^_-]+)/', $fileName, $matches)) {
                $participantUID = $matches[1];

                // Create participant download folder if it doesn't exist
                $participantFolder = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $participantUID;

                if (!is_dir($participantFolder)) {
                    if (!@mkdir($participantFolder, 0777, true)) {
                        error_log("Failed to create directory: $participantFolder");
                        $stats['errors']++;
                        continue;
                    }
                }

                // Copy the file to the participant folder
                $destPath = $participantFolder . DIRECTORY_SEPARATOR . $fileName;

                if (copy($file, $destPath)) {
                    $stats['distributed']++;
                    echo "Copied: $fileName -> $participantFolder\n";
                } else {
                    error_log("Failed to copy: $file -> $destPath");
                    $stats['errors']++;
                }
            } else {
                echo "Skipped (could not extract participant ID): $fileName\n";
                $stats['skipped']++;
            }
        }
    }

    // Process excellence certificates
    echo "=== Processing Excellence Certificates ===\n";
    distributeCertificatesFromFolder($excellenceFolder, $stats);

    // Process participation certificates
    echo "\n=== Processing Participation Certificates ===\n";
    distributeCertificatesFromFolder($participationFolder, $stats);

    // Print distribution summary
    echo "\n=== Distribution Summary ===\n";
    echo "Certificates distributed: {$stats['distributed']}\n";
    echo "Skipped:                  {$stats['skipped']}\n";
    echo "Errors:                   {$stats['errors']}\n";

    // Update batch status to distributed (only for batch-tracked workflow)
    if ($batchId) {
        $certificateBatchesModel->updateStatus($batchId, 'distributed', [
            'distributed_on' => new Zend_Db_Expr('NOW()')
        ]);
    }

    echo "\nDistribution completed successfully!\n";

} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());

    // Update batch status on failure (only for batch-tracked workflow)
    if ($batchId) {
        $certificateBatchesModel->updateStatus($batchId, 'failed', [
            'error_message' => $e->getMessage()
        ]);
    }

    exit(1);
}
