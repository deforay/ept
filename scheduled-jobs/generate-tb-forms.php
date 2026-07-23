<?php

// scheduled-jobs/generate-tb-forms.php

ini_set('memory_limit', '-1');

require_once __DIR__ . '/../cli-bootstrap.php';

use setasign\Fpdi\Fpdi;


$shortopts = "s:h";
$longopts = ["worker", "offset:", "limit:", "procs:", "files-only", "help"];
$cliOptions = getopt($shortopts, $longopts);

if (isset($cliOptions['h']) || isset($cliOptions['help'])) {
    echo <<<HELP
Generate TB participant forms (PDF) for a shipment.

Usage:
  php scheduled-jobs/generate-tb-forms.php [options]

Options:
  -s <shipmentId>   Shipment to generate forms for.
                    Defaults to the latest TB shipment if omitted.
  --files-only      Generate the PDFs with credentials, but do NOT reset
                    participant passwords in the DB. Passwords are deterministic,
                    so the printed values stay valid; this also skips the
                    per-run password-reset audit log entries. Safe for re-prints.
  --procs <n>       Number of parallel worker processes (default: CPU count).
  -h, --help        Show this help and exit.

Internal (used when the master spawns workers — not for manual use):
  --worker          Run in worker mode.
  --offset <n>      Participant offset for this worker's batch.
  --limit <n>       Participant count for this worker's batch.

Examples:
  php scheduled-jobs/generate-tb-forms.php -s 123
  php scheduled-jobs/generate-tb-forms.php -s 123 --files-only
  php scheduled-jobs/generate-tb-forms.php -s 123 --procs 4

HELP;
    exit(0);
}

$shipmentsToGenarateForm = $cliOptions['s'] ?? null;
$isWorker = isset($cliOptions['worker']);
$offset = $cliOptions['offset'] ?? 0;
$limit = $cliOptions['limit'] ?? 0;
$procs = $cliOptions['procs'] ?? Pt_Commons_MiscUtility::getCpuCount();
// --files-only: generate the PDFs with credentials, but do NOT reset participant
// passwords in the DB (passwords are deterministic, so the printed value stays
// valid) and skip the per-run password-reset audit log entries.
$filesOnly = isset($cliOptions['files-only']);

$generalModel = new Pt_Commons_General();

$dbConf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($dbConf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    // Resolve the shipment to work on. Workers always receive -s from the master,
    // so this interactive/fallback logic only runs in master mode.
    if (empty($shipmentsToGenarateForm) && !$isWorker) {
        // Pull the most recent TB shipments so the user can pick one (or accept
        // the latest as the default).
        $recentShipments = $db->fetchAll(
            $db->select()->from(['s' => 'shipment'], ['shipment_id', 'shipment_code'])
                ->where("s.scheme_type = 'tb'")
                ->order("s.shipment_id DESC")
                ->limit(10)
        );

        if (empty($recentShipments)) {
            fwrite(STDERR, "No TB shipments found in the database.\n");
            exit(1);
        }

        $latestId = $recentShipments[0]['shipment_id'];

        if (stream_isatty(STDIN)) {
            // Interactive terminal: list recent shipments and prompt, default latest.
            echo "Recent TB shipments:\n";
            foreach ($recentShipments as $s) {
                $marker = ($s['shipment_id'] == $latestId) ? '  (latest)' : '';
                echo "  {$s['shipment_id']}\t{$s['shipment_code']}$marker\n";
            }
            echo "\nEnter shipment ID to generate TB forms [default: $latestId]: ";
            $input = trim((string) fgets(STDIN));
            $shipmentsToGenarateForm = ($input === '') ? $latestId : $input;
        } else {
            // Non-interactive (cron/pipe): keep prior behaviour — latest TB shipment.
            $shipmentsToGenarateForm = $latestId;
        }
    }

    if (empty($shipmentsToGenarateForm)) {
        $msg = "Please specify the shipment id with the -s flag";
        Pt_Commons_LoggerUtility::logError($msg);
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }

    if ($isWorker) {
        // --- WORKER MODE ---
        $sQuery = $db->select()
            ->from(['s' => 'shipment'])
            ->joinLeft(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', ['spm.map_id'])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=spm.participant_id', ["p.participant_id", "p.unique_identifier"])
            ->where("s.shipment_id = ?", $shipmentsToGenarateForm)
            ->group("p.participant_id")
            ->order("p.unique_identifier ASC")
            ->limit($limit, $offset);

        $tbResult = $db->fetchAll($sQuery);
        $tbDb = new Application_Model_Tb();

        // No progress bar in worker to avoid cluttering stdout/stderr.
        // Each participant is isolated in its own try/catch so a single failed form
        // (e.g. a transient PDF error) doesn't abandon the rest of this worker's
        // batch — previously one throw here silently dropped 100+ participants.
        foreach ($tbResult as $row) {
            try {
                $tbDb->generateFormPDF($row['shipment_id'], $row['participant_id'], true, true, !$filesOnly);
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logError(
                    "TB form generation failed for participant {$row['participant_id']} (shipment {$row['shipment_id']}): " . $e->getMessage()
                );
            }
            echo "[PROGRESS]" . PHP_EOL;
        }
        exit(0);
    } else {
        // --- MASTER MODE ---

        // 1. Get Total Count and Shipment Code
        $sQuery = $db->select()
            ->from(['s' => 'shipment'], ['shipment_code'])
            ->joinLeft(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', [])
            ->where("s.shipment_id = ?", $shipmentsToGenarateForm)
            ->group("spm.participant_id"); // distinct participants

        $participants = $db->fetchAll($sQuery);
        $totalParticipants = count($participants);

        if ($totalParticipants === 0) {
            $msg = "No participants found for shipment $shipmentsToGenarateForm";
            Pt_Commons_LoggerUtility::logWarning($msg);
            fwrite(STDERR, $msg . "\n");
            // Release the queued-flag so the shipment page doesn't show
            // "Generating TB Forms..." for a run that produced nothing.
            $db->update('shipment', ['tb_form_generated' => 'no'], "shipment_id = " . (int) $shipmentsToGenarateForm . " AND tb_form_generated = 'queued'");
            exit(1);
        }

        $shipmentCode = $participants[0]['shipment_code'];
        $folderPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $shipmentCode;

        // 2. Prepare Directory
        if (is_dir($folderPath)) {
            Pt_Commons_General::rmdirRecursive($folderPath);
        }
        mkdir($folderPath, 0777, true);
        if (file_exists("$folderPath.zip")) {
            unlink("$folderPath.zip");
        }

        // 3. Spawn Workers
        $batchSize = ceil($totalParticipants / $procs);
        $processes = [];
        $spinner = Pt_Commons_MiscUtility::spinnerStart($totalParticipants, "Spawning $procs workers for $totalParticipants participants...");

        for ($i = 0; $i < $procs; $i++) {
            $currentOffset = $i * $batchSize;
            // Ensure we don't exceed total
            if ($currentOffset >= $totalParticipants)
                break;

            $cmd = ["php", __FILE__, "-s", $shipmentsToGenarateForm, "--worker", "--offset", $currentOffset, "--limit", $batchSize];
            if ($filesOnly) {
                $cmd[] = "--files-only";
            }

            $process = new \Symfony\Component\Process\Process($cmd);
            $process->setTimeout(null); // Disable timeout
            $process->start();

            $processes[] = $process;
        }

        // 4. Wait for Workers
        while (count($processes) > 0) {
            foreach ($processes as $key => $process) {
                // Check for output
                $output = $process->getIncrementalOutput();
                if (!empty($output)) {
                    $progressCount = substr_count($output, "[PROGRESS]");
                    if ($progressCount > 0) {
                        Pt_Commons_MiscUtility::spinnerAdvance($spinner, $progressCount);
                    }
                }

                if (!$process->isRunning()) {
                    // Process finished
                    if (!$process->isSuccessful()) {
                        Pt_Commons_LoggerUtility::logError("Worker failed: " . $process->getErrorOutput());
                    }

                    unset($processes[$key]);
                }
            }
            usleep(100000); // 100ms
        }
        Pt_Commons_MiscUtility::spinnerFinish($spinner);

        // 5. Merge PDFs
        // Drop any 0-byte/empty files (a form whose generation aborted mid-write)
        // so a corrupt entry can't break the whole merge with Fpdi.
        $pdfsToMerge = array_values(array_filter(
            glob($folderPath . DIRECTORY_SEPARATOR . "*.pdf"),
            static fn($f) => filesize($f) > 0
        ));

        // Surface a shortfall instead of silently producing an incomplete booklet:
        // one generated form per participant is expected.
        $generatedCount = count($pdfsToMerge);
        if ($generatedCount < $totalParticipants) {
            $missing = $totalParticipants - $generatedCount;
            $warn = "Only $generatedCount of $totalParticipants participant forms were generated ($missing missing/empty). See the error log for per-participant failures.";
            Pt_Commons_LoggerUtility::logWarning($warn);
            fwrite(STDERR, "WARNING: $warn\n");
        }

        if (!empty($pdfsToMerge)) {
            // Update DB status
            $db->update(
                'shipment',
                [
                    'tb_form_generated' => 'yes',
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ],
                "shipment_id = $shipmentsToGenarateForm"
            );

            echo "Merging " . count($pdfsToMerge) . " PDFs...\n";

            $batchSize = 50;
            $batchFiles = array_chunk($pdfsToMerge, $batchSize);
            $intermediateFiles = [];

            foreach ($batchFiles as $files) {
                $pdf = new Fpdi();
                foreach ($files as $file) {
                    $pageCount = $pdf->setSourceFile($file);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);
                        if ($size['width'] > $size['height']) {
                            $pdf->AddPage('L', [$size['width'], $size['height']]);
                        } else {
                            $pdf->AddPage('P', [$size['width'], $size['height']]);
                        }
                        $pdf->useTemplate($templateId);
                    }
                }
                $intermediateFile = $folderPath . DIRECTORY_SEPARATOR . 'intermediate_' . uniqid() . '.pdf';
                $pdf->Output($intermediateFile, "F");
                $intermediateFiles[] = $intermediateFile;
                unset($pdf);
            }

            $finalPdf = new Fpdi();
            foreach ($intermediateFiles as $intermediateFile) {
                $pageCount = $finalPdf->setSourceFile($intermediateFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $finalPdf->importPage($pageNo);
                    $size = $finalPdf->getTemplateSize($templateId);
                    if ($size['width'] > $size['height']) {
                        $finalPdf->AddPage('L', [$size['width'], $size['height']]);
                    } else {
                        $finalPdf->AddPage('P', [$size['width'], $size['height']]);
                    }
                    $finalPdf->useTemplate($templateId);
                }
                unlink($intermediateFile);
            }

            $finalPdfPath = $folderPath . DIRECTORY_SEPARATOR . $shipmentCode . '-TB-Participant-Forms.pdf';
            $finalPdf->Output($finalPdfPath, "F");

            $generalModel->zipFolder($folderPath, $folderPath . "-TB-FORMS.zip");
            echo "Done. File generated at: $finalPdfPath\n";
        } else {
            // Nothing to merge: release the queued-flag so the shipment page
            // offers "Generate TB Forms" again instead of a stuck spinner.
            $db->update('shipment', ['tb_form_generated' => 'no'], "shipment_id = " . (int) $shipmentsToGenarateForm . " AND tb_form_generated = 'queued'");
            fwrite(STDERR, "No participant forms were generated; nothing to merge.\n");
            exit(1);
        }
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    // Master-mode crash: release the queued-flag (workers never own it). Guarded —
    // the DB adapter itself may be what failed.
    try {
        if (!$isWorker && !empty($shipmentsToGenarateForm) && isset($db)) {
            $db->update('shipment', ['tb_form_generated' => 'no'], "shipment_id = " . (int) $shipmentsToGenarateForm . " AND tb_form_generated = 'queued'");
        }
    } catch (Throwable $ignored) {
        // Flag stays queued; the migration-era heal or a re-queue clears it.
    }
    exit(1);
}
