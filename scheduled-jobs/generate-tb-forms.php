<?php

// scheduled-jobs/generate-tb-forms.php

ini_set('memory_limit', '-1');

require_once __DIR__ . '/../cli-bootstrap.php';

use setasign\Fpdi\Fpdi;


$shortopts = "s:";
$longopts = ["worker", "offset:", "limit:", "procs:"];
$cliOptions = getopt($shortopts, $longopts);

$shipmentsToGenarateForm = $cliOptions['s'] ?? null;
$isWorker = isset($cliOptions['worker']);
$offset = $cliOptions['offset'] ?? 0;
$limit = $cliOptions['limit'] ?? 0;
$procs = $cliOptions['procs'] ?? Pt_Commons_MiscUtility::getCpuCount();

$generalModel = new Pt_Commons_General();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    // Fallback logic for shipment ID (only needed if not passed, but workers must receive it)
    if (empty($shipmentsToGenarateForm)) {
        $sQuery = $db->select()->from(['s' => 'shipment'], ['shipment_id'])
            ->where("s.scheme_type = 'tb'")
            ->order("s.shipment_id DESC")
            ->limit(1);
        $shipmentsToGenarateForm = $db->fetchOne($sQuery);
    }

    if (empty($shipmentsToGenarateForm)) {
        error_log("Please specify the shipment ids with the -s flag");
        exit();
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

        // No progress bar in worker to avoid cluttering stdout/stderr
        foreach ($tbResult as $row) {
            $tbDb->generateFormPDF($row['shipment_id'], $row['participant_id'], true, true);
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
            error_log("No participants found for shipment $shipmentsToGenarateForm");
            exit();
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
                        error_log("Worker failed: " . $process->getErrorOutput());
                    }

                    unset($processes[$key]);
                }
            }
            usleep(100000); // 100ms
        }
        Pt_Commons_MiscUtility::spinnerFinish($spinner);

        // 5. Merge PDFs
        $pdfsToMerge = glob($folderPath . DIRECTORY_SEPARATOR . "*.pdf");

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
        }
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
}
