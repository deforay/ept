<?php
// scheduled-jobs/generate-shipment-reports.php


require_once __DIR__ . '/../cli-bootstrap.php';


use Symfony\Component\Process\Process;

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);
//error_reporting(E_ALL);


$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);

$isCli = php_sapi_name() === 'cli';

// Flags for testing

$options = getopt("sp", ["worker", "shipment:", "offset:", "limit:", "procs:", "reportType:", "force", "lockTtl:"]);

// if -s then ONLY generate summary report

// if -p then ONLY generate participant reports

if (isset($options['s'])) {
    $skipParticipantReports = true;
    $skipSummaryReport = false;
} elseif (isset($options['p'])) {
    $skipSummaryReport = true;
    $skipParticipantReports = false;
} else {
    $skipSummaryReport = false;
    $skipParticipantReports = false;
}

$isWorker = isset($options['worker']);
$workerShipmentId = $options['shipment'] ?? null;
$workerOffset = isset($options['offset']) ? (int) $options['offset'] : 0;
$workerLimit = isset($options['limit']) ? (int) $options['limit'] : 0;
$workerReportType = $options['reportType'] ?? null;
$force = isset($options['force']);
$lockTtlMinutes = isset($options['lockTtl']) ? max(0, (int) $options['lockTtl']) : 0;
$procs = isset($options['procs']) ? (int) $options['procs'] : Pt_Commons_MiscUtility::getCpuCount();
if ($procs < 1) {
    $procs = 1;
}

/**
 * Prevent concurrent master runs for the same shipment.
 * Running two masters at once can keep deleting the same output folder and make progress appear stuck at 0.
 *
 * Workers are not locked (they are expected to run concurrently under a single master).
 */
$acquireShipmentLock = static function (int $shipmentId) {
    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "ept-generate-shipment-reports-{$shipmentId}.lock";
    // Use a lock file that works even when the file is owned by another user (e.g. cron as root).
    // Prefer read/write so we can write the PID, but fall back to read-only for locking.
    $handle = @fopen($lockPath, 'c');
    if (!is_resource($handle)) {
        if (is_file($lockPath)) {
            $handle = @fopen($lockPath, 'r');
        }
        if (!is_resource($handle)) {
            return null;
        }
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return null;
    }
    // If we have write access, store the PID for easier debugging.
    $meta = stream_get_meta_data($handle);
    $mode = (string) ($meta['mode'] ?? '');
    $canWrite = str_contains($mode, '+') || str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, 'x') || str_contains($mode, 'c');
    if ($canWrite) {
        @ftruncate($handle, 0);
        @fwrite($handle, (string) getmypid());
    }
    return $handle;
};

/**
 * Read lock metadata (best-effort): PID stored in file (if writable), mtime, and path.
 */
$getShipmentLockInfo = static function (int $shipmentId): array {
    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "ept-generate-shipment-reports-{$shipmentId}.lock";
    $pid = null;
    $mtime = null;
    if (is_file($lockPath)) {
        $mtime = @filemtime($lockPath) ?: null;
        $contents = @file_get_contents($lockPath);
        if ($contents !== false) {
            $parsed = (int) trim($contents);
            if ($parsed > 0) {
                $pid = $parsed;
            }
        }
    }
    return ['path' => $lockPath, 'pid' => $pid, 'mtime' => $mtime];
};

/**
 * Check if a PID is running (best-effort, cross-platform-ish).
 */
$isPidRunning = static function (?int $pid): bool {
    if (!$pid || $pid < 2) {
        return false;
    }
    if (function_exists('posix_kill')) {
        // Signal 0 does not kill; it checks for existence/permission.
        return @posix_kill($pid, 0);
    }
    // Fallback: if /proc exists, check for /proc/<pid>.
    $procPath = "/proc/{$pid}";
    if (@is_dir($procPath)) {
        return true;
    }
    return false;
};

// Parallel processing uses Symfony Process which relies on `proc_open`.
// On some Ubuntu hardening configs (or in restricted shared hosting), `proc_open` can be disabled.
if (!$isWorker && $procs > 1) {
    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    $procOpenAvailable = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    if (!$procOpenAvailable) {
        fwrite(STDERR, "Parallel processing disabled: PHP function `proc_open` is not available. Falling back to 1 process.\n");
        $procs = 1;
    }
}

/**
 * Include a PHP template file with an explicit variable context.
 * This avoids relying on ambient variables from the caller scope (especially inside closures).
 */
$includeWithContext = static function (string $file, array $context): void {
    if (!is_file($file)) {
        throw new RuntimeException("Template not found: {$file}");
    }

    (static function () use ($file, $context): void{
        extract($context, EXTR_OVERWRITE);
        require $file;
    })();
};


try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $generalModel = new Pt_Commons_General();

    $reportService = new Application_Service_Reports();
    $commonService = new Application_Service_Common();
    $schemeService = new Application_Service_Schemes();
    $shipmentService = new Application_Service_Shipments();
    $evalService = new Application_Service_Evaluation();
    $adminService = new Application_Service_SystemAdmin();
    $header = $reportService->getReportConfigValue('report-header');
    $instituteAddressPosition = $reportService->getReportConfigValue('institute-address-postition');
    $reportComment = $reportService->getReportConfigValue('report-comment');
    $logo = $reportService->getReportConfigValue('logo');
    $logoRight = $reportService->getReportConfigValue('logo-right');
    $layout = $reportService->getReportConfigValue('report-layout');
    $templateTopMargin = $reportService->getReportConfigValue('template-top-margin');
    $instance = $commonService->getConfig('instance');
    $passPercentage = $commonService->getConfig('pass_percentage');
    $trainingInstance = $commonService->getConfig('training_instance');
    $watermark = null;
    if (isset($trainingInstance) && $trainingInstance === 'yes') {
        $watermark = $commonService->getConfig('training_instance_text');
    }

    $customField1 = $commonService->getConfig('custom_field_1');
    $customField2 = $commonService->getConfig('custom_field_2');
    $haveCustom = $commonService->getConfig('custom_field_needed');
    $enabledAdminEmailReminder = $commonService->getConfig('enable_admin_email_notification');
    $evaluatOnFinalized = $commonService->getConfig('evaluate_before_generating_reports');
    $feedbackOption = $commonService->getConfig('participant_feedback');
    $recencyAssay = $schemeService->getRecencyAssay();
    $downloadDirectory = realpath(DOWNLOADS_FOLDER);
    $reportsPath = $downloadDirectory . DIRECTORY_SEPARATOR . 'reports';

    $manualShipmentMode = !$isWorker && !empty($workerShipmentId);
    $manualShipmentLock = null;
    if ($manualShipmentMode) {
        $manualShipmentLock = $acquireShipmentLock((int) $workerShipmentId);
        if ($manualShipmentLock === null) {
            $info = $getShipmentLockInfo((int) $workerShipmentId);
            $running = $isPidRunning($info['pid']);
            $ageMinutes = ($info['mtime'] ? (int) floor((time() - $info['mtime']) / 60) : null);

            if ($force) {
                $ttlOk = ($lockTtlMinutes > 0 && $ageMinutes !== null && $ageMinutes >= $lockTtlMinutes && !$running);
                if ($ttlOk && is_string($info['path']) && $info['path'] !== '' && is_file($info['path'])) {
                    // Best-effort cleanup for stale lock files (does not break a held flock).
                    @unlink($info['path']);
                    $manualShipmentLock = $acquireShipmentLock((int) $workerShipmentId);
                    if (is_resource($manualShipmentLock)) {
                        // Successfully re-acquired after removing a stale file.
                        goto lock_acquired_manual;
                    }
                }
                fwrite(
                    STDERR,
                    "WARNING: Shipment {$workerShipmentId} appears locked (lock file: {$info['path']}). " .
                    "Proceeding due to --force" .
                    ($ttlOk ? " (lockTtl={$lockTtlMinutes}m, age={$ageMinutes}m)." : ".") .
                    " This can corrupt output if another job is actually running.\n"
                );
            } else {
                $details = "lock file: {$info['path']}";
                if ($info['pid']) {
                    $details .= ", pid: {$info['pid']}" . ($running ? " (running)" : " (not running)");
                }
                if ($ageMinutes !== null) {
                    $details .= ", age: {$ageMinutes}m";
                }
                fwrite(STDERR, "Another report generation process is already running for shipment {$workerShipmentId} ({$details}). Exiting.\n");
                fwrite(STDERR, "If this is stale: check the PID (ps -fp <pid>) and remove the lock file, or rerun with --force.\n");
                exit(1);
            }
        }
    }
    lock_acquired_manual:

    if ($isWorker) {
        if (empty($workerShipmentId) || $workerLimit < 1) {
            error_log("Worker mode requires --shipment and --limit arguments");
            exit(1);
        }

        $shipmentId = (int) $workerShipmentId;
        $resultStatus = $workerReportType ?? 'generateReport';

        $shipmentRow = $db->fetchRow(
            $db->select()
                ->from(
                    ['s' => 'shipment'],
                    [
                        'shipment_code',
                        'scheme_type',
                        'shipment_attributes',
                        'pt_co_ordinator_name',
                        'distribution_id',
                        'date_finalised' => new Zend_Db_Expr('NULL')
                    ]
                )
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name', 'is_user_configured'])
                ->where("s.shipment_id = ?", $shipmentId)
        );

        if (empty($shipmentRow)) {
            error_log("Shipment $shipmentId not found for worker");
            exit(1);
        }

        $evalRow = [
            ...$shipmentRow,
            'shipment_id' => $shipmentId,
            'report_type' => $resultStatus
        ];

        if (isset($evalRow['scheme_type']) && $evalRow['scheme_type'] == 'covid19') {
            $allGeneTypes = $schemeService->getAllCovid19GeneTypeResponseWise();
        }

        $shipmentCodePath = $reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'];
        if (!is_dir($shipmentCodePath)) {
            mkdir($shipmentCodePath, 0777, true);
        }

        $pQuery = $db->select()->from(
            ['spm' => 'shipment_participant_map'],
            [
                'custom_field_1',
                'custom_field_2',
                'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date > '1970-01-01' OR IFNULL(is_pt_test_not_performed, 'no') not like 'yes')")
            ]
        )
            ->joinLeft(['res' => 'r_results'], 'res.result_id=spm.final_result', [])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['scheme_type', 'distribution_id'])
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['is_user_configured'])
            ->where("spm.shipment_id = ?", $shipmentId)
            ->group('spm.shipment_id');

        $totParticipantsRes = $db->fetchRow($pQuery);
        if (!is_array($totParticipantsRes)) {
            exit(0);
        }
        $reportedCount = isset($totParticipantsRes['reported_count']) ? (int) $totParticipantsRes['reported_count'] : 0;

        if ($reportedCount > 0 && $workerOffset <= $reportedCount) {
            if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                $totParticipantsRes['scheme_type'] = 'generic-test';
            }

            $resultArray = $evalService->getIndividualReportsDataForPDF($shipmentId, $workerLimit, $workerOffset);
            if ($layout == 'zimbabwe' && isset($totParticipantsRes['distribution_id'])) {
                $shipmentsUnderDistro = $shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $shipmentId)[0];
            }

            $endValue = $workerOffset + ($workerLimit - 1);
            if ($endValue > $reportedCount) {
                $endValue = $reportedCount;
            }

            $bulkfileNameVal = $workerOffset . "-" . $endValue;
            if (!empty($resultArray)) {
                $participantLayoutFile = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                if (!empty($layout)) {
                    $customLayoutFileLocation = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                    if (file_exists($customLayoutFileLocation)) {
                        $participantLayoutFile = $customLayoutFileLocation;
                    }
                }
                $includeWithContext($participantLayoutFile, [
                    'reportService' => $reportService,
                    'schemeService' => $schemeService,
                    'shipmentService' => $shipmentService,
                    'commonService' => $commonService,
                    'config' => $customConfig,
                    'reportFormat' => $reportService->getReportConfigValue('report-format'),
                    'recencyAssay' => $recencyAssay,
                    'allGeneTypes' => isset($allGeneTypes) ? $allGeneTypes : null,
                    'downloadDirectory' => $downloadDirectory,
                    'trainingInstance' => $trainingInstance,
                    'evalRow' => $evalRow,
                    'resultArray' => $resultArray,
                    'totParticipantsRes' => $totParticipantsRes,
                    'reportsPath' => $reportsPath,
                    'resultStatus' => $resultStatus,
                    'layout' => $layout,
                    'header' => $header,
                    'instituteAddressPosition' => $instituteAddressPosition,
                    'reportComment' => $reportComment,
                    'logo' => $logo,
                    'logoRight' => $logoRight,
                    'templateTopMargin' => $templateTopMargin,
                    'instance' => $instance,
                    'passPercentage' => $passPercentage,
                    'watermark' => $watermark,
                    'customField1' => $customField1,
                    'customField2' => $customField2,
                    'haveCustom' => $haveCustom,
                    'bulkfileNameVal' => $bulkfileNameVal,
                    'shipmentsUnderDistro' => isset($shipmentsUnderDistro) ? $shipmentsUnderDistro : null,
                ]);
            }
        }
        exit(0);
    }

    $queueLimit = 3;
    if ($manualShipmentMode) {
        $manualReportType = $workerReportType ?? 'generateReport';
        $shipmentRow = $db->select()
            ->from(
                ['s' => 'shipment'],
                [
                    'shipment_id',
                    'shipment_code',
                    'scheme_type',
                    'shipment_attributes',
                    'pt_co_ordinator_name',
                    'distribution_id',
                    'date_finalised' => new Zend_Db_Expr('NULL')
                ]
            )
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name', 'is_user_configured'])
            ->where("s.shipment_id = ?", $workerShipmentId);
        $manualEvalRow = $db->fetchRow($shipmentRow);
        if (empty($manualEvalRow)) {
            throw new Exception("Shipment {$workerShipmentId} not found.");
        }
        $manualEvalRow['report_type'] = $manualReportType;
        $manualEvalRow['saname'] = '';
        $manualEvalRow['requested_by'] = 0;
        $manualEvalRow['id'] = null;
        $evalResult = [$manualEvalRow];
    } else {
        $sQuery = $db->select()
            ->from(['eq' => 'queue_report_generation'])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=eq.shipment_id', ['shipment_code', 'scheme_type', 'shipment_attributes', 'pt_co_ordinator_name'])
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
            ->joinLeft(['sa' => 'system_admin'], 'eq.requested_by=sa.admin_id', ['saname' => new Zend_Db_Expr("CONCAT(sa.first_name,' ',sa.last_name)")])
            ->where("eq.status=?", 'pending')
            ->limit($queueLimit);
        $evalResult = $db->fetchAll($sQuery);
    }
    if (!empty($evalResult)) {

        $evaluatedShipments = [];

        foreach ($evalResult as $evalRow) {
            $shipmentIdForLock = isset($evalRow['shipment_id']) ? (int) $evalRow['shipment_id'] : 0;
            $shipmentLock = null;
            if ($shipmentIdForLock > 0) {
                // In manual mode we already acquired a lock for this shipment; reuse it instead of trying to lock twice.
                if ($manualShipmentMode && $shipmentIdForLock === (int) $workerShipmentId && is_resource($manualShipmentLock)) {
                    $shipmentLock = $manualShipmentLock;
                } else {
                    $shipmentLock = $acquireShipmentLock($shipmentIdForLock);
                    if ($shipmentLock === null) {
                        $info = $getShipmentLockInfo($shipmentIdForLock);
                        $running = $isPidRunning($info['pid']);
                        $ageMinutes = ($info['mtime'] ? (int) floor((time() - $info['mtime']) / 60) : null);

                        if ($force) {
                            $ttlOk = ($lockTtlMinutes > 0 && $ageMinutes !== null && $ageMinutes >= $lockTtlMinutes && !$running);
                            if ($ttlOk && is_string($info['path']) && $info['path'] !== '' && is_file($info['path'])) {
                                @unlink($info['path']);
                                $shipmentLock = $acquireShipmentLock($shipmentIdForLock);
                                if (is_resource($shipmentLock)) {
                                    // Continue with a clean, re-acquired lock.
                                    goto lock_acquired_queue;
                                }
                            }
                            fwrite(
                                STDERR,
                                "WARNING: Shipment {$shipmentIdForLock} appears locked (lock file: {$info['path']}). " .
                                "Proceeding due to --force. This can corrupt output if another job is actually running.\n"
                            );
                        } else {
                            $details = "lock file: {$info['path']}";
                            if ($info['pid']) {
                                $details .= ", pid: {$info['pid']}" . ($running ? " (running)" : " (not running)");
                            }
                            if ($ageMinutes !== null) {
                                $details .= ", age: {$ageMinutes}m";
                            }
                            fwrite(STDERR, "Shipment {$shipmentIdForLock} is already being processed by another report generation job ({$details}). Skipping.\n");
                            continue;
                        }
                    }
                }
            }
            lock_acquired_queue:
            if (($evalRow['report_type'] == 'finalized' || $evalRow['report_type'] == 'generateReport') && $evaluatOnFinalized == "yes") {
                $customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
                $shipmentId = $evalRow['shipment_id'];

                if (!isset($evaluatedShipments[$shipmentId])) {
                    $timeStart = microtime(true);
                    $shipmentResult = $evalService->getShipmentToEvaluate($shipmentId, true);
                    $timeEnd = microtime(true);
                    $executionTime = ($timeEnd - $timeStart) / 60;
                    $evaluatedShipments[$shipmentId] = [
                        'shipmentResult' => $shipmentResult,
                        'executionTime' => $executionTime
                    ];
                } else {
                    $shipmentResult = $evaluatedShipments[$shipmentId]['shipmentResult'];
                    $executionTime = $evaluatedShipments[$shipmentId]['executionTime'];
                }

                $link = "/admin/evaluate/shipment/sid/" . base64_encode($shipmentResult[0]['shipment_id']);
                $db->insert('notify', [
                    'title' => 'Shipment Evaluated',
                    'description' => 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated in ' . round($executionTime, 2) . ' mins',
                    'link' => $link
                ]);

                if (
                    isset($customConfig->jobCompletionAlert->status)
                    && $customConfig->jobCompletionAlert->status == "yes"
                    && isset($customConfig->jobCompletionAlert->mails)
                    && !empty($customConfig->jobCompletionAlert->mails)
                ) {
                    $emailSubject = "ePT | Shipment Evaluated";
                    $emailContent = 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated <br><br> Please click on this link to see ' . $conf->domain . $link;
                    $emailContent .= "<br><br><br><small>This is a system generated email</small>";
                    $commonService->insertTempMail($customConfig->jobCompletionAlert->mails, null, null, $emailSubject, $emailContent);
                }
            }
            if (isset($evalRow['shipment_code']) && $evalRow['shipment_code'] != "" && !empty($evalRow['shipment_code'])) {

                $shipmentCodePath = $reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'];
                if (file_exists($shipmentCodePath)) {
                    $generalModel->rmdirRecursive($shipmentCodePath);
                    mkdir($shipmentCodePath, 0777, true);
                }
                if (file_exists($reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip")) {
                    unlink($reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip");
                }
            }
            // For Identify the geny types for covid-19 test type

            if (isset($evalRow['scheme_type']) && $evalRow['scheme_type'] == 'covid19') {
                $allGeneTypes = $schemeService->getAllCovid19GeneTypeResponseWise();
            }

            $reportTypeStatus = 'not-evaluated';
            if ($evalRow['report_type'] == 'generateReport') {
                $reportTypeStatus = 'not-evaluated';
            } elseif ($evalRow['report_type'] == 'finalized') {
                $reportTypeStatus = 'not-finalized';
            }

            if (!empty($evalRow['id'])) {
                $db->update('queue_report_generation', array('status' => $reportTypeStatus, 'last_updated_on' => new Zend_Db_Expr('now()')), 'id=' . $evalRow['id']);
            }
            if (!file_exists($reportsPath)) {
                $commonService->makeDirectory($reportsPath);
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $db->select()->from(
                array('spm' => 'shipment_participant_map'),
                array(
                    'custom_field_1',
                    'custom_field_2',
                    'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                    'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date > '1970-01-01' OR IFNULL(is_pt_test_not_performed, 'no') not like 'yes')")
                )
            )
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', [])
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('scheme_type', 'distribution_id'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('is_user_configured'))
                ->where("spm.shipment_id = ?", $evalRow['shipment_id'])
                ->group('spm.shipment_id');
            // die($pQuery);

            $totParticipantsRes = $db->fetchRow($pQuery);
            $resultStatus = $evalRow['report_type'];
            $reportedCount = isset($totParticipantsRes['reported_count']) ? (int) $totParticipantsRes['reported_count'] : 0;
            $chunkSize = 500;
            $getFileCount = function () use ($reportsPath, $evalRow) {
                return count(glob($reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . DIRECTORY_SEPARATOR . '*.pdf'));
            };
            $participantProgressBar = null;

            $participantLayoutContextBase = [
                'reportService' => $reportService,
                'schemeService' => $schemeService,
                'shipmentService' => $shipmentService,
                'commonService' => $commonService,
                'config' => $customConfig,
                'reportFormat' => $reportService->getReportConfigValue('report-format'),
                'recencyAssay' => $recencyAssay,
                'allGeneTypes' => isset($allGeneTypes) ? $allGeneTypes : null,
                'downloadDirectory' => $downloadDirectory,
                'trainingInstance' => $trainingInstance,
                'evalRow' => $evalRow,
                'totParticipantsRes' => $totParticipantsRes,
                'reportsPath' => $reportsPath,
                'resultStatus' => $resultStatus,
                'layout' => $layout,
                'header' => $header,
                'instituteAddressPosition' => $instituteAddressPosition,
                'reportComment' => $reportComment,
                'logo' => $logo,
                'logoRight' => $logoRight,
                'templateTopMargin' => $templateTopMargin,
                'instance' => $instance,
                'passPercentage' => $passPercentage,
                'watermark' => $watermark,
                'customField1' => $customField1,
                'customField2' => $customField2,
                'haveCustom' => $haveCustom,
            ];

            $generateParticipantChunks = function () use ($evalService, $shipmentService, $totParticipantsRes, $layout, $evalRow, $reportedCount, $chunkSize, $getFileCount, &$participantProgressBar, $includeWithContext, $participantLayoutContextBase) {
                $lastCount = $getFileCount();
                for ($offset = 0; $offset <= $reportedCount; $offset += $chunkSize) {
                    Pt_Commons_MiscUtility::updateHeartbeat('queue_report_generation', 'shipment_id', $evalRow['shipment_id']);
                    if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                        $totParticipantsRes['scheme_type'] = 'generic-test';
                    }

                    $resultArray = $evalService->getIndividualReportsDataForPDF($evalRow['shipment_id'], $chunkSize, $offset);
                    if ($layout == 'zimbabwe') {
                        $shipmentsUnderDistro = $shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $evalRow['shipment_id'])[0];
                    }

                    $endValue = $offset + ($chunkSize - 1);
                    if ($endValue > $reportedCount) {
                        $endValue = $reportedCount;
                    }

                    $bulkfileNameVal = "$offset-$endValue";
                    if (!empty($resultArray)) {
                        $participantLayoutFile = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                        if (!empty($layout)) {
                            $customLayoutFileLocation = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                            if (file_exists($customLayoutFileLocation)) {
                                $participantLayoutFile = $customLayoutFileLocation;
                            }
                        }

                        $context = $participantLayoutContextBase;
                        $context['resultArray'] = $resultArray;
                        $context['bulkfileNameVal'] = $bulkfileNameVal;
                        $context['shipmentsUnderDistro'] = isset($shipmentsUnderDistro) ? $shipmentsUnderDistro : null;
                        $includeWithContext($participantLayoutFile, $context);

                        $newCount = $getFileCount();
                        $delta = $newCount - $lastCount;
                        if ($delta > 0 && $participantProgressBar) {
                            Pt_Commons_MiscUtility::spinnerAdvance($participantProgressBar, $delta);
                        }
                        $lastCount = $newCount;
                    }
                }
            };

            if ($skipParticipantReports === false && $reportedCount > 0) {
                // Use single-line output; multi-line progress bars don't repaint reliably in some IDE consoles.
                $participantProgressBar = Pt_Commons_MiscUtility::spinnerStart($reportedCount, "Generating participant reports...", '█', '░', '█', 'cyan', false);
                if ($procs <= 1) {
                    $generateParticipantChunks();
                    Pt_Commons_MiscUtility::spinnerFinish($participantProgressBar);
                } else {
                    $batchSize = (int) ceil($reportedCount / $procs);
                    if ($batchSize < 1) {
                        $batchSize = 1;
                    }

                    $phpBinary = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' ? PHP_BINARY : 'php';
                    $processes = [];
                    for ($offset = 0; $offset <= $reportedCount; $offset += $batchSize) {
                        try {
                            // Use the current PHP binary to avoid PATH issues (common in cron/Ubuntu).
                            $cmd = [$phpBinary, __FILE__, "--worker", "--shipment", $evalRow['shipment_id'], "--offset", $offset, "--limit", $batchSize, "--reportType", $resultStatus];
                            //error_log("Starting worker: " . implode(' ', $cmd));

                            $process = new Process($cmd);
                            $process->setTimeout(null);
                            $process->start();

                            $processes[] = ['process' => $process, 'offset' => $offset];
                        } catch (Throwable $t) {
                            error_log("Failed to start worker for offset {$offset}: " . $t->getMessage());
                        }
                    }

                    if (empty($processes)) {
                        fwrite(STDERR, "Failed to start any worker processes; falling back to sequential generation.\n");
                        $generateParticipantChunks();
                        Pt_Commons_MiscUtility::spinnerFinish($participantProgressBar);
                    } else {
                        $lastCount = $getFileCount();
                        $lastProgressRedrawAt = microtime(true);
                        while (count($processes) > 0) {
                            foreach ($processes as $key => $procData) {
                                $process = $procData['process'];
                                $offset = $procData['offset'];
                                if (!$process->isRunning()) {
                                    try {
                                        if (!$process->isSuccessful()) {
                                            $exitCode = $process->getExitCode();
                                            $err = trim((string) $process->getErrorOutput());
                                            $msg = "Worker failed (offset {$offset}" . ($exitCode !== null ? ", exit {$exitCode}" : "") . ")";
                                            if ($err !== '') {
                                                $msg .= ": " . preg_replace('/\\s+/', ' ', $err);
                                            }
                                            error_log($msg);
                                            if ($isCli && $participantProgressBar) {
                                                Pt_Commons_MiscUtility::spinnerPausePrint($participantProgressBar, static function () use ($msg): void {
                                                    fwrite(STDERR, $msg . PHP_EOL);
                                                });
                                            }
                                        } else {
                                            $out = trim($process->getOutput());
                                            if ($out !== '') {
                                                error_log("Worker completed (offset {$offset}) output: " . $out);
                                            }
                                        }
                                    } catch (Throwable $t) {
                                        error_log("Worker crashed while waiting (offset {$offset}): " . $t->getMessage());
                                    }
                                    unset($processes[$key]);
                                }
                            }
                            $currentCount = $getFileCount();
                            $delta = $currentCount - $lastCount;
                            if ($delta > 0) {
                                Pt_Commons_MiscUtility::spinnerAdvance($participantProgressBar, $delta);
                                $lastCount = $currentCount;
                            }

                            // Redraw periodically even when no new PDFs exist yet so `%elapsed%` updates.
                            if ((microtime(true) - $lastProgressRedrawAt) >= 1.0) {
                                $participantProgressBar->display();
                                $lastProgressRedrawAt = microtime(true);
                            }

                            usleep(150000);
                        }

                        $participantPdfs = glob($reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . DIRECTORY_SEPARATOR . '*.pdf');
                        if (empty($participantPdfs)) {
                            $msg = "Parallel generation produced no participant PDFs. Retrying sequentially.";
                            error_log($msg);
                            if ($isCli && $participantProgressBar) {
                                Pt_Commons_MiscUtility::spinnerPausePrint($participantProgressBar, static function () use ($msg): void {
                                    fwrite(STDERR, $msg . PHP_EOL);
                                });
                            }
                            $generateParticipantChunks();
                        }
                        Pt_Commons_MiscUtility::spinnerFinish($participantProgressBar);
                    }
                }
            }

            // Some layouts (e.g., Zimbabwe) expect distribution-level context even for summary generation.
            // Compute once here so both summary branches can use it.
            $shipmentsUnderDistro = null;
            if ($layout == 'zimbabwe' && isset($totParticipantsRes['distribution_id'])) {
                $shipmentsUnderDistro = $shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $evalRow['shipment_id'])[0] ?? null;
            }

            $summaryLayoutContextBase = [
                'reportService' => $reportService,
                'schemeService' => $schemeService,
                'shipmentService' => $shipmentService,
                'commonService' => $commonService,
                'evalService' => $evalService,
                'trainingInstance' => $trainingInstance,
                'evalRow' => $evalRow,
                'reportsPath' => $reportsPath,
                'resultStatus' => $resultStatus,
                'layout' => $layout,
                'header' => $header,
                'instituteAddressPosition' => $instituteAddressPosition,
                'reportComment' => $reportComment,
                'logo' => $logo,
                'logoRight' => $logoRight,
                'templateTopMargin' => $templateTopMargin,
                'instance' => $instance,
                'passPercentage' => $passPercentage,
                'watermark' => $watermark,
                'customField1' => $customField1,
                'customField2' => $customField2,
                'haveCustom' => $haveCustom,
                'shipmentsUnderDistro' => $shipmentsUnderDistro,
            ];

            $panelTestType = "";
            if ($skipSummaryReport === false) {
                $shipmentAttribute = json_decode($evalRow['shipment_attributes'], true);
                $noOfTests = (isset($shipmentAttribute['dtsTestPanelType']) && $shipmentAttribute['dtsTestPanelType'] == 'yes') ? ['screening', 'confirmatory'] : null;
                if (isset($noOfTests) && !empty($noOfTests) && $noOfTests != null && $evalRow['scheme_type'] == 'dts') {
                    foreach ($noOfTests as $panelTestType) {
                        // SUMMARY REPORT

                        $resultArray = $evalService->getSummaryReportsDataForPDF($evalRow['shipment_id'], $panelTestType);
                        $responseResult = $evalService->getResponseReports($evalRow['shipment_id'], $panelTestType);
                        $participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id'], $panelTestType);
                        $correctivenessArray = $reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id'], $panelTestType);
                        if (!empty($resultArray)) {
                            Pt_Commons_MiscUtility::updateHeartbeat('queue_report_generation', 'shipment_id', $evalRow['shipment_id']);
                            if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                                $resultArray['shipment']['scheme_type'] = 'generic-test';
                            }
                            // this is the default layout

                            $summaryLayoutFile = SUMMARY_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                            // let us check if there is a custom layout file present for this scheme

                            if (!empty($layout)) {
                                $customLayoutFileLocation = SUMMARY_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                                if (file_exists($customLayoutFileLocation)) {
                                    $summaryLayoutFile = $customLayoutFileLocation;
                                }
                            }
                            $context = $summaryLayoutContextBase;
                            $context['resultArray'] = $resultArray;
                            $context['responseResult'] = $responseResult;
                            $context['participantPerformance'] = $participantPerformance;
                            $context['correctivenessArray'] = $correctivenessArray;
                            $context['panelTestType'] = $panelTestType;
                            $includeWithContext($summaryLayoutFile, $context);
                        }
                    }
                } else {
                    // SUMMARY REPORT
                    $resultArray = $evalService->getSummaryReportsDataForPDF($evalRow['shipment_id']);
                    $responseResult = $evalService->getResponseReports($evalRow['shipment_id']);
                    $participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id']);
                    $correctivenessArray = $reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id']);
                    if (!empty($resultArray)) {
                        Pt_Commons_MiscUtility::updateHeartbeat('queue_report_generation', 'shipment_id', $evalRow['shipment_id']);
                        if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                            $resultArray['shipment']['scheme_type'] = 'generic-test';
                        }
                        // this is the default layout

                        $summaryLayoutFile = SUMMARY_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                        // let us check if there is a custom layout file present for this scheme

                        if (!empty($layout)) {
                            $customLayoutFileLocation = SUMMARY_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                            if (file_exists($customLayoutFileLocation)) {
                                $summaryLayoutFile = $customLayoutFileLocation;
                            }
                        }
                        $context = $summaryLayoutContextBase;
                        $context['resultArray'] = $resultArray;
                        $context['responseResult'] = $responseResult;
                        $context['participantPerformance'] = $participantPerformance;
                        $context['correctivenessArray'] = $correctivenessArray;
                        $includeWithContext($summaryLayoutFile, $context);
                    }
                }
            }
            $generalModel->zipFolder($shipmentCodePath, $reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip");

            $feedbackExpiryDate = null;
            $reportCompletedStatus = 'evaluated';
            $notifyType = 'individual_reports';
            if ($evalRow['report_type'] == 'generateReport') {
                $reportCompletedStatus = 'evaluated';
                $notifyType = 'individual_reports';
                $link = '/reports/distribution/shipment/sid/' . base64_encode($evalRow['shipment_id']);
            } elseif ($evalRow['report_type'] == 'finalized') {
                $reportCompletedStatus = 'finalized';
                $notifyType = 'summary_reports';
                //$link = '/reports/distribution/finalize/sid/' . base64_encode($evalRow['shipment_id']);

                $link = '/reports/shipments';
                $feedbackExpiryDate = date('Y-m-d', strtotime("+56 days"));
            }

            if (
                isset($customConfig->jobCompletionAlert->status)
                && $customConfig->jobCompletionAlert->status == "yes"
                && isset($customConfig->jobCompletionAlert->mails)
                && !empty($customConfig->jobCompletionAlert->mails)
            ) {
                $emailSubject = "ePT | Reports for " . $evalRow['shipment_code'];
                $emailContent = "Reports for Shipment " . $evalRow['shipment_code'] . " have been generated. <br><br> Please click on this link to see " . $conf->domain . $link;
                $emailContent .= "<br><br><br><small>This is a system generated email</small>";
                $commonService->insertTempMail($customConfig->jobCompletionAlert->mails, null, null, $emailSubject, $emailContent);
            }
            $update = array(
                'status' => $reportCompletedStatus,
                'last_updated_on' => new Zend_Db_Expr('now()')
            );
            if ($evalRow['report_type'] == 'finalized' && $evalRow['date_finalised'] == '') {
                $update['date_finalised'] = new Zend_Db_Expr('now()');
            }
            $feedbackExpiryDate = (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') ? $feedbackExpiryDate : null;

            $db->update('shipment', array(
                'status' => $reportCompletedStatus,
                'feedback_expiry_date' => $feedbackExpiryDate,
                'report_in_queue' => 'no',
                'updated_by_admin' => (int) $evalRow['requested_by'],
                'updated_on_admin' => new Zend_Db_Expr('now()'),
                'previous_status' => null,
                'processing_started_at' => null,
                'last_heartbeat' => null
            ), "shipment_id = " . $evalRow['shipment_id']);

            // `$evalRow['id']` is the queue_report_generation row id (null in manual mode).
            if (!empty($evalRow['id']) && $reportCompletedStatus == 'finalized') {
                $authNameSpace = new Zend_Session_Namespace('administrators');
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Finalized shipment - " . $evalRow['shipment_code'], "shipment");
            }

            if (!empty($evalRow['id'])) {
                $db->update('queue_report_generation', $update, 'id=' . $evalRow['id']);
            }
            if ($enabledAdminEmailReminder == 'yes') {
                $queueResults = $db->fetchRow($db->select()
                    ->from('queue_report_generation')
                    ->where("shipment_id = ?", $evalRow['shipment_id']));
                /* Zend_Debug::dump($queueResults);
                die; */
                $adminDetails = $adminService->getSystemAdminDetails($queueResults['initated_by']);
                if (isset($adminDetails) && !empty($adminDetails) && $adminDetails['primary_email'] != "") {
                    $link = $conf->domain . '/reports/distribution/shipment/sid/' . base64_encode($evalRow['shipment_id']);
                    $subject = 'Shipment report for ' . $evalRow['shipment_code'] . ' has been generated';
                    $message = 'Hello, ' . $adminDetails['first_name'] . ', <br>
                     Shipment report for ' . $evalRow['shipment_code'] . ' has been generated successfully. Kindly click the below link to check the report or copy paste into the brower address bar.<br>
                     <a href="' . $link . '">' . $link . '</a>.';

                    $commonService->insertTempMail($adminDetails['primary_email'], null, null, $subject, $message, 'ePT System', 'ePT System Admin');
                }
            }
            $db->insert('notify', array('title' => 'Reports Generated', 'description' => 'Reports for Shipment ' . $evalRow['shipment_code'] . ' are ready for download', 'link' => $link));

            $notifyType = ($evalRow['report_type'] = 'generateReport') ? 'individual_reports' : 'summary_reports';
            $notParticipatedMailContent = $commonService->getEmailTemplate('not_participant_report_mail');
            $subQuery = $db->select()
                ->from(array('s' => 'shipment'), array('shipment_code', 'scheme_type'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
                ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email'))
                ->where("s.shipment_id=?", $evalRow['shipment_id'])
                ->group('dm.dm_id');
            $subResult = $db->fetchAll($subQuery);
            foreach ($subResult as $row) {
                /* New shipment mail alert start */
                $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##', );
                $replace = array($row['participantName'], $row['shipment_code'], $row['scheme_type'], '', '');
                $content = !empty($notParticipatedMailContent['mail_content']) ? $notParticipatedMailContent['mail_content'] : null;
                $message = !empty($content) ? str_replace($search, $replace, $content) : null;
                $subject = !empty($notParticipatedMailContent['mail_subject']) ? $notParticipatedMailContent['mail_subject'] : '';
                $fromEmail = !empty($notParticipatedMailContent['mail_from']) ? $notParticipatedMailContent['mail_from'] : null;
                $fromFullName = !empty($notParticipatedMailContent['from_name']) ? $notParticipatedMailContent['from_name'] : null;
                $toEmail = !empty($row['primary_email']) ? $row['primary_email'] : null;
                $cc = !empty($notParticipatedMailContent['mail_cc']) ? $notParticipatedMailContent['mail_cc'] : null;
                $bcc = !empty($notParticipatedMailContent['mail_bcc']) ? $notParticipatedMailContent['mail_bcc'] : null;

                if ($toEmail != null && $fromEmail != null && $subject != null && $message != null) {
                    $commonService->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
                }
            }

            if (is_resource($shipmentLock)) {
                flock($shipmentLock, LOCK_UN);
                fclose($shipmentLock);
            }
        }
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
}
