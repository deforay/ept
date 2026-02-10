<?php
// scheduled-jobs/generate-shipment-reports.php
//
// Generates PDF reports for shipments (participant + summary reports).
//
// =============================================================================
// HOW THIS SCRIPT WORKS
// =============================================================================
//
// When you run this script, it processes shipments and generates PDF reports.
// For each shipment, it:
//   1. setupShipment()      - acquires lock, prepares directories
//   2. participantReports() - generates individual participant PDFs
//   3. summaryReport()      - generates the summary PDF
//   4. completeShipmentReports()   - creates ZIP, sends notifications
//
// PARALLEL PROCESSING (--procs flag):
//   A shipment can have thousands of participants, each needing a PDF.
//   To speed this up, participant report generation can run in parallel
//   across multiple CPU cores. Summary reports are always single-threaded
//   (there's only one per shipment).
//
//   Example with --procs=4 and 2000 participants:
//   - Main process runs setupShipment()
//   - Main process splits participants into 4 chunks (500 each)
//   - Main process spawns 4 subprocesses, one per chunk
//   - Subprocesses generate participant PDFs in parallel
//   - Main process waits for all subprocesses to finish
//   - Main process runs summaryReport() and completeShipmentReports()
//
//   The --worker flag is internal - it tells a subprocess which chunk to process.
//   You don't need to use --worker directly; the script handles it automatically.
//
// =============================================================================
// USAGE
// =============================================================================
//
//   php generate-shipment-reports.php [options]
//
// Options:
//   -s                Only generate summary report (skip participant reports)
//   -p                Only generate participant reports (skip summary report)
//   --shipment=ID     Process specific shipment ID instead of queue
//   --procs=N         Number of parallel worker processes (default: CPU count)
//   --force           Proceed even if shipment appears locked
//   --lockTtl=N       Minutes before a stale lock can be overridden
//   --debug           Enable verbose debug output (forces --procs=1)
//   --worker          Internal: run as subprocess for parallel processing
//   --offset=N        Internal: worker start offset
//   --limit=N         Internal: worker batch size
//   --reportType=X    Internal: report type for worker

require_once __DIR__ . '/../cli-bootstrap.php';

use Symfony\Component\Process\Process;

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

// Global reference for signal handler cleanup
$GLOBALS['_reportGenerator'] = null;

// Register signal handlers for graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function (int $signal) {
        $isCli = php_sapi_name() === 'cli';
        $signalName = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGHUP => 'SIGHUP',
            default => "Signal $signal"
        };
        ReportGenerator::warn("Received $signalName, cleaning up...", $isCli);

        // Release lock via destructor
        if (isset($GLOBALS['_reportGenerator'])) {
            $GLOBALS['_reportGenerator'] = null;
        }

        exit(128 + $signal);
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_signal(SIGHUP, $signalHandler);
}

// =============================================================================
// CONFIGURATION CLASS
// =============================================================================

/**
 * Holds all configuration values needed for report generation.
 * Consolidates loading from config files and database.
 */
class ReportConfig
{
    // Report layout settings
    public string $header;
    public ?string $instituteAddressPosition;
    public ?string $reportComment;
    public ?string $logo;
    public ?string $logoRight;
    public ?string $layout;
    public ?string $templateTopMargin;
    public ?string $reportFormat;

    // Instance settings
    public ?string $instance;
    public ?string $passPercentage;
    public ?string $trainingInstance;
    public ?string $watermark;

    // Custom fields
    public ?string $customField1;
    public ?string $customField2;
    public ?string $haveCustom;

    // Feature flags
    public ?string $enabledAdminEmailReminder;
    public ?string $evaluateOnFinalized;
    public ?string $feedbackOption;
    public ?string $jobCompletionAlertStatus;
    public ?string $jobCompletionAlertMails;

    // Paths
    public string $downloadDirectory;
    public string $reportsPath;

    // Services (injected for convenience)
    public Application_Service_Reports $reportService;
    public Application_Service_Common $commonService;
    public Application_Service_Schemes $schemeService;
    public Application_Service_Shipments $shipmentService;
    public Application_Service_Evaluation $evalService;
    public Application_Service_SystemAdmin $adminService;
    public Pt_Commons_General $generalModel;
    public Zend_Config_Ini $customConfig;
    public Zend_Config_Ini $appConfig;

    // Scheme-specific data
    public ?array $recencyAssay;

    public static function load(): self
    {
        $reportsConfig = new self();

        // Initialize services
        $reportsConfig->reportService = new Application_Service_Reports();
        $reportsConfig->commonService = new Application_Service_Common();
        $reportsConfig->schemeService = new Application_Service_Schemes();
        $reportsConfig->shipmentService = new Application_Service_Shipments();
        $reportsConfig->evalService = new Application_Service_Evaluation();
        $reportsConfig->adminService = new Application_Service_SystemAdmin();
        $reportsConfig->generalModel = new Pt_Commons_General();

        // Load config files
        $reportsConfig->appConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $reportsConfig->customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);

        // Report layout settings
        $reportsConfig->header = $reportsConfig->reportService->getReportConfigValue('report-header');
        $reportsConfig->instituteAddressPosition = $reportsConfig->reportService->getReportConfigValue('institute-address-postition');
        $reportsConfig->reportComment = $reportsConfig->reportService->getReportConfigValue('report-comment');
        $reportsConfig->logo = $reportsConfig->reportService->getReportConfigValue('logo');
        $reportsConfig->logoRight = $reportsConfig->reportService->getReportConfigValue('logo-right');
        $reportsConfig->layout = $reportsConfig->reportService->getReportConfigValue('report-layout');
        $reportsConfig->templateTopMargin = $reportsConfig->reportService->getReportConfigValue('template-top-margin');
        $reportsConfig->reportFormat = $reportsConfig->reportService->getReportConfigValue('report-format');

        // Instance settings
        $reportsConfig->instance = $reportsConfig->commonService->getConfig('instance');
        $reportsConfig->passPercentage = $reportsConfig->commonService->getConfig('pass_percentage');
        $reportsConfig->trainingInstance = $reportsConfig->commonService->getConfig('training_instance');
        $reportsConfig->watermark = null;
        if (isset($reportsConfig->trainingInstance) && $reportsConfig->trainingInstance === 'yes') {
            $reportsConfig->watermark = $reportsConfig->commonService->getConfig('training_instance_text');
        }

        // Custom fields
        $reportsConfig->customField1 = $reportsConfig->commonService->getConfig('custom_field_1');
        $reportsConfig->customField2 = $reportsConfig->commonService->getConfig('custom_field_2');
        $reportsConfig->haveCustom = $reportsConfig->commonService->getConfig('custom_field_needed');

        // Feature flags
        $reportsConfig->enabledAdminEmailReminder = $reportsConfig->commonService->getConfig('enable_admin_email_notification');
        $reportsConfig->evaluateOnFinalized = $reportsConfig->commonService->getConfig('evaluate_before_generating_reports');
        $reportsConfig->feedbackOption = $reportsConfig->commonService->getConfig('participant_feedback');
        $reportsConfig->jobCompletionAlertStatus = $reportsConfig->commonService->getConfig('job_completion_alert_status');
        $reportsConfig->jobCompletionAlertMails = $reportsConfig->commonService->getConfig('job_completion_alert_mails');

        // Paths
        $reportsConfig->downloadDirectory = realpath(DOWNLOADS_FOLDER);
        $reportsConfig->reportsPath = $reportsConfig->downloadDirectory . DIRECTORY_SEPARATOR . 'reports';

        // Scheme-specific data
        $reportsConfig->recencyAssay = $reportsConfig->schemeService->getRecencyAssay();

        return $reportsConfig;
    }
}

// =============================================================================
// COMMAND LINE OPTIONS
// =============================================================================

/**
 * Parsed command-line options.
 */
class ReportJobOptions
{
    public bool $isCli;
    public bool $isSubProcess;
    public bool $skipParticipantReports;
    public bool $skipSummaryReport;
    public bool $force;
    public bool $debug;
    public int $procs;
    public int $lockTtlMinutes;

    // Worker-specific options
    public ?string $shipmentId;
    public int $workerOffset;
    public int $workerLimit;
    public ?string $reportType;

    public static function parseCliOptions(): self
    {
        $opts = new self();
        $options = getopt("sp", ["worker", "shipment:", "offset:", "limit:", "procs:", "reportType:", "force", "lockTtl:", "debug"]);

        $opts->isCli = php_sapi_name() === 'cli';
        $opts->isSubProcess = isset($options['worker']);
        $opts->force = isset($options['force']);
        $opts->debug = isset($options['debug']);

        // Report type flags: -s = summary only, -p = participant only
        if (isset($options['s'])) {
            $opts->skipParticipantReports = true;
            $opts->skipSummaryReport = false;
        } elseif (isset($options['p'])) {
            $opts->skipSummaryReport = true;
            $opts->skipParticipantReports = false;
        } else {
            $opts->skipSummaryReport = false;
            $opts->skipParticipantReports = false;
        }

        // Worker options
        $opts->shipmentId = $options['shipment'] ?? null;
        $opts->workerOffset = isset($options['offset']) ? (int) $options['offset'] : 0;
        $opts->workerLimit = isset($options['limit']) ? (int) $options['limit'] : 0;
        $opts->reportType = $options['reportType'] ?? null;
        $opts->lockTtlMinutes = isset($options['lockTtl']) ? max(0, (int) $options['lockTtl']) : 0;

        // Process count
        $opts->procs = isset($options['procs']) ? (int) $options['procs'] : Pt_Commons_MiscUtility::getCpuCount();
        if ($opts->procs < 1) {
            $opts->procs = 1;
        }

        // Debug mode forces sequential processing
        if (!$opts->isSubProcess && $opts->debug) {
            $opts->procs = 1;
            ReportGenerator::log("Debug mode enabled: forcing --procs=1 for verbose output.", $opts->isCli);
        }

        // Check if proc_open is available for parallel processing
        if (!$opts->isSubProcess && $opts->procs > 1) {
            $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
            $procOpenAvailable = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
            if (!$procOpenAvailable) {
                ReportGenerator::warn("Parallel processing disabled: PHP function `proc_open` is not available. Falling back to 1 process.", $opts->isCli);
                $opts->procs = 1;
            }
        }

        return $opts;
    }

    public function isManualShipmentMode(): bool
    {
        return !$this->isSubProcess && !empty($this->shipmentId);
    }
}

// =============================================================================
// REPORT GENERATOR CLASS
// =============================================================================

/**
 * Main report generation orchestrator.
 *
 * Usage:
 *   foreach ($generator->getShipmentsToProcess() as $shipment) {
 *       $generator->setupShipment($shipment);
 *       $generator->participantReports();
 *       $generator->summaryReport();
 *       $generator->completeShipmentReports();
 *   }
 */
class ReportGenerator
{
    private Zend_Db_Adapter_Abstract $db;
    private ReportConfig $config;
    private ReportJobOptions $opts;

    // Current shipment state (set by setupShipment, used by other methods)
    private ?array $currentShipment = null;
    private ?array $currentParticipantCounts = null;
    private ?array $currentGeneTypes = null;
    private $currentShipmentLock = null;
    private ?array $evaluatedShipments = [];

    public function __construct(ReportConfig $config, ReportJobOptions $opts)
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->config = $config;
        $this->opts = $opts;
    }

    public function __destruct()
    {
        // Release lock if still held (e.g., if exception occurred)
        if (is_resource($this->currentShipmentLock)) {
            flock($this->currentShipmentLock, LOCK_UN);
            fclose($this->currentShipmentLock);
            $this->currentShipmentLock = null;
        }
    }

    // -------------------------------------------------------------------------
    // STATIC UTILITIES (logging, locking, template rendering)
    // -------------------------------------------------------------------------

    public static function log(string $msg, bool $isCli): void
    {
        if ($isCli) {
            Pt_Commons_MiscUtility::console()->writeln($msg);
        } else {
            error_log($msg);
        }
    }

    public static function error(string $msg, bool $isCli): void
    {
        if ($isCli) {
            Pt_Commons_MiscUtility::console()->getErrorOutput()->writeln("<error> ERROR </error> {$msg}");
        } else {
            error_log($msg);
        }
    }

    public static function warn(string $msg, bool $isCli): void
    {
        if ($isCli) {
            Pt_Commons_MiscUtility::console()->getErrorOutput()->writeln("<comment>WARNING:</comment> {$msg}");
        } else {
            error_log($msg);
        }
    }

    public static function debugLog(string $msg, bool $isCli, bool $debug): void
    {
        if (!$debug) {
            return;
        }
        self::log("<fg=gray>{$msg}</>", $isCli);
    }

    public static function acquireShipmentLock(int $shipmentId)
    {
        $lockPath = self::lockPath($shipmentId);
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
        $meta = stream_get_meta_data($handle);
        $mode = (string) ($meta['mode'] ?? '');
        $canWrite = str_contains($mode, '+') || str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, 'x') || str_contains($mode, 'c');
        if ($canWrite) {
            @ftruncate($handle, 0);
            @fwrite($handle, (string) getmypid());
        }
        return $handle;
    }

    public static function getShipmentLockInfo(int $shipmentId): array
    {
        $lockPath = self::lockPath($shipmentId);
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
    }

    public static function isPidRunning(?int $pid): bool
    {
        if (!$pid || $pid < 2) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        $procPath = "/proc/{$pid}";
        return @is_dir($procPath);
    }

    public static function includeWithContext(string $file, array $context): void
    {
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$file}");
        }
        (static function () use ($file, $context): void {
            extract($context, EXTR_OVERWRITE);
            require $file;
        })();
    }

    private static function lockPath(int $shipmentId): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "ept-generate-shipment-reports-{$shipmentId}.lock";
    }

    // -------------------------------------------------------------------------
    // PUBLIC API METHODS
    // -------------------------------------------------------------------------

    /**
     * Get list of shipments to process (from queue or manual mode).
     * Use this with setupShipment() for fine-grained control.
     *
     * @return array List of shipment rows to process
     */
    public function getShipmentsToProcess(): array
    {
        return $this->fetchShipmentsToProcess();
    }

    /**
     * Set up processing for a single shipment.
     * Must be called before participantReports() or summaryReport().
     *
     * @param array $evalRow Shipment data from getShipmentsToProcess()
     * @param resource|null $manualShipmentLock Optional pre-acquired lock for manual mode
     * @return bool True if setup succeeded, false if shipment should be skipped
     */
    public function setupShipment(array $evalRow, $manualShipmentLock = null): bool
    {
        $shipmentId = isset($evalRow['shipment_id']) ? (int) $evalRow['shipment_id'] : 0;

        // Acquire lock for this shipment
        $shipmentLock = $this->tryAcquireShipmentLock($shipmentId, $manualShipmentLock);
        if ($shipmentLock === false) {
            return false; // Skip - couldn't acquire lock
        }

        $this->currentShipment = $evalRow;
        $this->currentShipmentLock = $shipmentLock;

        // Evaluate shipment if needed
        $this->evaluateShipmentIfNeeded($evalRow, $this->evaluatedShipments);

        // Prepare output directory
        if (!empty($evalRow['shipment_code'])) {
            $this->prepareShipmentOutputDirectory($evalRow);
        }

        // Load COVID-19 gene types if needed
        $this->currentGeneTypes = null;
        if (isset($evalRow['scheme_type']) && $evalRow['scheme_type'] == 'covid19') {
            $this->currentGeneTypes = $this->config->schemeService->getAllCovid19GeneTypeResponseWise();
        }

        // Update queue status
        $this->updateQueueStatus($evalRow, 'processing');

        // Ensure reports directory exists
        if (!file_exists($this->config->reportsPath)) {
            $this->config->commonService->makeDirectory($this->config->reportsPath);
        }

        // Get participant data
        $this->currentParticipantCounts = $this->fetchParticipantCounts($shipmentId);

        return true;
    }

    /**
     * Generate participant reports for the current shipment.
     * Requires setupShipment() to be called first.
     */
    public function participantReports(): void
    {
        if ($this->currentShipment === null) {
            throw new RuntimeException('participantReports() called without setupShipment()');
        }

        $evalRow = $this->currentShipment;
        $totParticipantsRes = $this->currentParticipantCounts;
        $resultStatus = $evalRow['report_type'];
        $reportedCount = isset($totParticipantsRes['reported_count']) ? (int) $totParticipantsRes['reported_count'] : 0;

        if ($this->opts->skipParticipantReports) {
            if ($this->opts->isCli) {
                Pt_Commons_MiscUtility::console()->writeln("  <fg=yellow>-</> Participant reports: <comment>skipped</comment> (-s flag)");
            }
            return;
        }

        $this->generateParticipantReports($evalRow, $totParticipantsRes, $this->currentGeneTypes, $reportedCount, $resultStatus);
    }

    /**
     * Generate summary report for the current shipment.
     * Requires setupShipment() to be called first.
     */
    public function summaryReport(): void
    {
        if ($this->currentShipment === null) {
            throw new RuntimeException('summaryReport() called without setupShipment()');
        }

        $evalRow = $this->currentShipment;
        $totParticipantsRes = $this->currentParticipantCounts;
        $resultStatus = $evalRow['report_type'];

        if ($this->opts->skipSummaryReport) {
            if ($this->opts->isCli) {
                Pt_Commons_MiscUtility::console()->writeln("  <fg=yellow>-</> Summary report: <comment>skipped</comment> (-p flag)");
            }
            return;
        }

        $this->generateSummaryReport($evalRow, $totParticipantsRes, $resultStatus);
    }

    /**
     * Finalize processing for the current shipment (ZIP, notifications, cleanup).
     * Requires setupShipment() to be called first.
     */
    public function completeShipmentReports(): void
    {
        if ($this->currentShipment === null) {
            throw new RuntimeException('completeShipmentReports() called without setupShipment()');
        }

        $evalRow = $this->currentShipment;
        $totParticipantsRes = $this->currentParticipantCounts;

        // Create ZIP archive
        $this->createZipArchive($evalRow);

        // Send notifications and update status
        $this->completeShipmentProcessing($evalRow, $totParticipantsRes);

        // Release lock
        if (is_resource($this->currentShipmentLock)) {
            flock($this->currentShipmentLock, LOCK_UN);
            fclose($this->currentShipmentLock);
        }

        // Clear current shipment state
        $this->currentShipment = null;
        $this->currentParticipantCounts = null;
        $this->currentGeneTypes = null;
        $this->currentShipmentLock = null;
    }

    // -------------------------------------------------------------------------
    // SUBPROCESS MODE (for parallel participant report generation)
    // -------------------------------------------------------------------------

    /**
     * Subprocess entry point: Generate participant PDFs for a specific chunk.
     * Called automatically by participantReports() when --procs > 1.
     */
    public function runAsSubProcess(): void
    {
        if (empty($this->opts->shipmentId) || $this->opts->workerLimit < 1) {
            self::log("Subprocess mode requires --shipment and --limit arguments", $this->opts->isCli);
            exit(1);
        }

        $shipmentId = (int) $this->opts->shipmentId;
        $resultStatus = $this->opts->reportType ?? 'generateReport';

        // Fetch shipment details
        $shipmentRow = $this->fetchShipmentForWorker($shipmentId);
        if (empty($shipmentRow)) {
            self::log("Shipment $shipmentId not found for worker", $this->opts->isCli);
            exit(1);
        }

        $evalRow = [
            ...$shipmentRow,
            'shipment_id' => $shipmentId,
            'report_type' => $resultStatus
        ];

        // Load COVID-19 gene types if needed
        $allGeneTypes = null;
        if (isset($evalRow['scheme_type']) && $evalRow['scheme_type'] == 'covid19') {
            $allGeneTypes = $this->config->schemeService->getAllCovid19GeneTypeResponseWise();
        }

        // Ensure output directory exists
        $shipmentCodePath = $this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'];
        if (!is_dir($shipmentCodePath)) {
            mkdir($shipmentCodePath, 0777, true);
        }

        // Get participant counts
        $totParticipantsRes = $this->fetchParticipantCounts($shipmentId);
        if (!is_array($totParticipantsRes)) {
            exit(0);
        }

        $reportedCount = isset($totParticipantsRes['reported_count']) ? (int) $totParticipantsRes['reported_count'] : 0;

        if ($reportedCount > 0 && $this->opts->workerOffset <= $reportedCount) {
            $this->generateParticipantChunk(
                $evalRow,
                $totParticipantsRes,
                $allGeneTypes,
                $this->opts->workerOffset,
                $this->opts->workerLimit,
                $reportedCount,
                $resultStatus
            );
        }

        exit(0);
    }

    /**
     * Generate PDFs for a single chunk of participants.
     */
    private function generateParticipantChunk(
        array $evalRow,
        array $totParticipantsRes,
        ?array $allGeneTypes,
        int $offset,
        int $limit,
        int $reportedCount,
        string $resultStatus
    ): void {
        if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
            $totParticipantsRes['scheme_type'] = 'generic-test';
        }

        self::debugLog(
            "Worker fetching participants for shipment {$evalRow['shipment_id']} (offset {$offset}, limit {$limit})...",
            $this->opts->isCli,
            $this->opts->debug
        );

        $tFetchStart = microtime(true);
        $resultArray = $this->config->evalService->getIndividualReportsDataForPDF($evalRow['shipment_id'], $limit, $offset);
        self::debugLog(
            "Worker fetched in " . round((microtime(true) - $tFetchStart) * 1000) . " ms; rows=" . count($resultArray['shipment'] ?? []),
            $this->opts->isCli,
            $this->opts->debug
        );

        // Get distribution shipments for Zimbabwe layout
        $shipmentsUnderDistro = null;
        if ($this->config->layout == 'zimbabwe' && isset($totParticipantsRes['distribution_id'])) {
            $shipmentsUnderDistro = $this->config->shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $evalRow['shipment_id'])[0];
        }

        $endValue = min($offset + ($limit - 1), $reportedCount);
        $bulkfileNameVal = "{$offset}-{$endValue}";

        if (!empty($resultArray)) {
            $participantLayoutFile = $this->resolveLayoutFile('participant', $totParticipantsRes['scheme_type']);

            self::debugLog(
                "Worker rendering PDFs for offset {$offset} using " . basename($participantLayoutFile) . "...",
                $this->opts->isCli,
                $this->opts->debug
            );

            $tRenderStart = microtime(true);
            $context = $this->buildParticipantLayoutContext($evalRow, $totParticipantsRes, $resultStatus, $allGeneTypes);
            $context['resultArray'] = $resultArray;
            $context['bulkfileNameVal'] = $bulkfileNameVal;
            $context['shipmentsUnderDistro'] = $shipmentsUnderDistro;

            self::includeWithContext($participantLayoutFile, $context);
            self::debugLog(
                "Worker rendered PDFs in " . round((microtime(true) - $tRenderStart) * 1000) . " ms",
                $this->opts->isCli,
                $this->opts->debug
            );

            // Mark reports as generated
            $this->markReportsGenerated($resultArray);
        }
    }

    // -------------------------------------------------------------------------
    // PARTICIPANT REPORT GENERATION
    // -------------------------------------------------------------------------

    /**
     * Generate all participant reports for a shipment (sequential or parallel).
     */
    private function generateParticipantReports(
        array $evalRow,
        array $totParticipantsRes,
        ?array $allGeneTypes,
        int $reportedCount,
        string $resultStatus
    ): void {
        if ($reportedCount <= 0) {
            if ($this->opts->isCli) {
                Pt_Commons_MiscUtility::console()->writeln("  <fg=yellow>-</> Participant reports: <comment>skipped</comment> (no participants reported)");
            }
            return;
        }

        $chunkSize = 500;
        $progressBar = $this->startParticipantProgressBar($reportedCount);

        if ($this->opts->procs <= 1) {
            $this->generateParticipantReportsSequential($evalRow, $totParticipantsRes, $allGeneTypes, $reportedCount, $resultStatus, $chunkSize, $progressBar);
        } else {
            $this->generateParticipantReportsParallel($evalRow, $reportedCount, $resultStatus, $progressBar);
        }

        if ($progressBar) {
            Pt_Commons_MiscUtility::spinnerFinish($progressBar);
        }

        if ($this->opts->isCli) {
            $pdfCount = count(glob($this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . DIRECTORY_SEPARATOR . '*.pdf'));
            Pt_Commons_MiscUtility::console()->writeln("  <fg=green>✓</> Participant reports: <comment>{$pdfCount}</comment> PDFs generated");
        }
    }

    /**
     * Generate participant reports sequentially (single process).
     */
    private function generateParticipantReportsSequential(
        array $evalRow,
        array $totParticipantsRes,
        ?array $allGeneTypes,
        int $reportedCount,
        string $resultStatus,
        int $chunkSize,
        $progressBar
    ): void {
        $lastCount = $this->getPdfCount($evalRow['shipment_code']);

        for ($offset = 0; $offset <= $reportedCount; $offset += $chunkSize) {
            Pt_Commons_MiscUtility::updateHeartbeat('queue_report_generation', 'shipment_id', $evalRow['shipment_id']);

            if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                $totParticipantsRes['scheme_type'] = 'generic-test';
            }

            self::debugLog(
                "Fetching participants for shipment {$evalRow['shipment_id']} (offset {$offset}, limit {$chunkSize})...",
                $this->opts->isCli,
                $this->opts->debug
            );

            $tFetchStart = microtime(true);
            $resultArray = $this->config->evalService->getIndividualReportsDataForPDF($evalRow['shipment_id'], $chunkSize, $offset);
            self::debugLog(
                "Fetched in " . round((microtime(true) - $tFetchStart) * 1000) . " ms; rows=" . count($resultArray['shipment'] ?? []),
                $this->opts->isCli,
                $this->opts->debug
            );

            // Get distribution shipments for Zimbabwe layout
            $shipmentsUnderDistro = null;
            if ($this->config->layout == 'zimbabwe') {
                $shipmentsUnderDistro = $this->config->shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $evalRow['shipment_id'])[0];
            }

            $endValue = min($offset + ($chunkSize - 1), $reportedCount);
            $bulkfileNameVal = "{$offset}-{$endValue}";

            if (!empty($resultArray)) {
                $participantLayoutFile = $this->resolveLayoutFile('participant', $totParticipantsRes['scheme_type']);

                $context = $this->buildParticipantLayoutContext($evalRow, $totParticipantsRes, $resultStatus, $allGeneTypes);
                $context['resultArray'] = $resultArray;
                $context['bulkfileNameVal'] = $bulkfileNameVal;
                $context['shipmentsUnderDistro'] = $shipmentsUnderDistro;

                self::debugLog(
                    "Rendering PDFs for offset {$offset} using " . basename($participantLayoutFile) . "...",
                    $this->opts->isCli,
                    $this->opts->debug
                );

                $tRenderStart = microtime(true);
                self::includeWithContext($participantLayoutFile, $context);
                self::debugLog(
                    "Rendered PDFs in " . round((microtime(true) - $tRenderStart) * 1000) . " ms",
                    $this->opts->isCli,
                    $this->opts->debug
                );

                // Mark reports as generated
                $this->markReportsGenerated($resultArray);

                // Update progress bar
                $newCount = $this->getPdfCount($evalRow['shipment_code']);
                $delta = $newCount - $lastCount;
                if ($delta > 0 && $progressBar) {
                    Pt_Commons_MiscUtility::spinnerAdvance($progressBar, $delta);
                }
                $lastCount = $newCount;
            }
        }
    }

    /**
     * Generate participant reports in parallel using worker subprocesses.
     */
    private function generateParticipantReportsParallel(
        array $evalRow,
        int $reportedCount,
        string $resultStatus,
        $progressBar
    ): void {
        $batchSize = (int) ceil($reportedCount / $this->opts->procs);
        if ($batchSize < 1) {
            $batchSize = 1;
        }

        $phpBinary = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $processes = [];

        // Start worker processes
        for ($offset = 0; $offset <= $reportedCount; $offset += $batchSize) {
            try {
                $cmd = [
                    $phpBinary,
                    __FILE__,
                    "--worker",
                    "--shipment",
                    $evalRow['shipment_id'],
                    "--offset",
                    $offset,
                    "--limit",
                    $batchSize,
                    "--reportType",
                    $resultStatus
                ];

                $process = new Process($cmd);
                $process->setTimeout(null);
                $process->start();

                $processes[] = ['process' => $process, 'offset' => $offset];
            } catch (Throwable $t) {
                self::log("Failed to start worker for offset {$offset}: " . $t->getMessage(), $this->opts->isCli);
            }
        }

        if (empty($processes)) {
            self::error("Failed to start any worker processes; falling back to sequential generation.", $this->opts->isCli);
            // Note: Caller should handle fallback
            return;
        }

        // Monitor workers and update progress
        $this->monitorWorkerProcesses($processes, $evalRow, $progressBar);

        // Verify PDFs were created
        $participantPdfs = glob($this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . DIRECTORY_SEPARATOR . '*.pdf');
        if (empty($participantPdfs)) {
            $msg = "Parallel generation produced no participant PDFs. Retrying sequentially.";
            if ($this->opts->isCli && $progressBar) {
                Pt_Commons_MiscUtility::spinnerPausePrint($progressBar, static function () use ($msg): void {
                    Pt_Commons_MiscUtility::console()->getErrorOutput()->writeln("<comment>WARNING:</comment> {$msg}");
                });
            } else {
                self::warn($msg, $this->opts->isCli);
            }
            // Fallback to sequential - caller should handle this
        }
    }

    /**
     * Monitor running worker processes and update progress bar.
     */
    private function monitorWorkerProcesses(array &$processes, array $evalRow, $progressBar): void
    {
        $lastCount = $this->getPdfCount($evalRow['shipment_code']);
        $lastProgressRedrawAt = microtime(true);

        while (count($processes) > 0) {
            foreach ($processes as $key => $procData) {
                $process = $procData['process'];
                $offset = $procData['offset'];

                if (!$process->isRunning()) {
                    $this->handleWorkerCompletion($process, $offset, $progressBar);
                    unset($processes[$key]);
                }
            }

            // Update progress
            $currentCount = $this->getPdfCount($evalRow['shipment_code']);
            $delta = $currentCount - $lastCount;
            if ($delta > 0 && $progressBar) {
                Pt_Commons_MiscUtility::spinnerAdvance($progressBar, $delta);
                $lastCount = $currentCount;
                $lastProgressRedrawAt = microtime(true);
            } elseif ($progressBar && (microtime(true) - $lastProgressRedrawAt) >= 1.0) {
                // Redraw periodically for elapsed time updates (advance by 0)
                Pt_Commons_MiscUtility::spinnerAdvance($progressBar, 0);
                $lastProgressRedrawAt = microtime(true);
            }

            usleep(150000);
        }
    }

    /**
     * Handle completion of a worker process.
     */
    private function handleWorkerCompletion(Process $process, int $offset, $progressBar): void
    {
        try {
            if (!$process->isSuccessful()) {
                $exitCode = $process->getExitCode();
                $err = trim((string) $process->getErrorOutput());
                $msg = "Worker failed (offset {$offset}" . ($exitCode !== null ? ", exit {$exitCode}" : "") . ")";
                if ($err !== '') {
                    $msg .= ": " . preg_replace('/\\s+/', ' ', $err);
                }

                if ($this->opts->isCli && $progressBar) {
                    Pt_Commons_MiscUtility::spinnerPausePrint($progressBar, static function () use ($msg): void {
                        Pt_Commons_MiscUtility::console()->getErrorOutput()->writeln("<error> FAIL </error> {$msg}");
                    });
                } else {
                    self::error($msg, $this->opts->isCli);
                }
            } else {
                $out = trim($process->getOutput());
                if ($out !== '') {
                    self::log("Worker completed (offset {$offset}) output: " . $out, $this->opts->isCli);
                }
            }
        } catch (Throwable $t) {
            self::log("Worker crashed while waiting (offset {$offset}): " . $t->getMessage(), $this->opts->isCli);
        }
    }

    // -------------------------------------------------------------------------
    // SUMMARY REPORT GENERATION
    // -------------------------------------------------------------------------

    /**
     * Generate summary report for a shipment.
     */
    private function generateSummaryReport(array $evalRow, array $totParticipantsRes, string $resultStatus): void
    {
        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("  Generating summary report...");
        }

        // Get distribution shipments for Zimbabwe layout
        $shipmentsUnderDistro = null;
        if ($this->config->layout == 'zimbabwe' && isset($totParticipantsRes['distribution_id'])) {
            $shipmentsUnderDistro = $this->config->shipmentService->getShipmentInReports($totParticipantsRes['distribution_id'], $evalRow['shipment_id'])[0] ?? null;
        }

        $shipmentAttribute = json_decode($evalRow['shipment_attributes'], true);
        $noOfTests = (isset($shipmentAttribute['dtsTestPanelType']) && $shipmentAttribute['dtsTestPanelType'] == 'yes')
            ? ['screening', 'confirmatory']
            : null;

        // DTS scheme with multiple panel types
        if (isset($noOfTests) && !empty($noOfTests) && $evalRow['scheme_type'] == 'dts') {
            foreach ($noOfTests as $panelTestType) {
                $this->generateSummaryForPanel($evalRow, $totParticipantsRes, $resultStatus, $shipmentsUnderDistro, $panelTestType);
            }
        } else {
            // Standard summary report
            $this->generateSummaryForPanel($evalRow, $totParticipantsRes, $resultStatus, $shipmentsUnderDistro, null);
        }

        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("  <fg=green>✓</> Summary report generated");
        }
    }

    /**
     * Generate summary report for a specific panel type (or null for standard).
     */
    private function generateSummaryForPanel(
        array $evalRow,
        array $totParticipantsRes,
        string $resultStatus,
        ?array $shipmentsUnderDistro,
        ?string $panelTestType
    ): void {
        $resultArray = $this->config->evalService->getSummaryReportsDataForPDF($evalRow['shipment_id'], $panelTestType);
        $responseResult = $this->config->evalService->getResponseReports($evalRow['shipment_id'], $panelTestType);
        $participantPerformance = $this->config->reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id'], $panelTestType);
        $correctivenessArray = $this->config->reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id'], $panelTestType);

        if (empty($resultArray)) {
            return;
        }

        Pt_Commons_MiscUtility::updateHeartbeat('queue_report_generation', 'shipment_id', $evalRow['shipment_id']);

        if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
            $resultArray['shipment']['scheme_type'] = 'generic-test';
        }

        $summaryLayoutFile = $this->resolveLayoutFile('summary', $resultArray['shipment']['scheme_type']);

        $context = $this->buildSummaryLayoutContext($evalRow, $resultStatus, $shipmentsUnderDistro);
        $context['resultArray'] = $resultArray;
        $context['responseResult'] = $responseResult;
        $context['participantPerformance'] = $participantPerformance;
        $context['correctivenessArray'] = $correctivenessArray;
        $context['panelTestType'] = $panelTestType ?? '';

        self::includeWithContext($summaryLayoutFile, $context);
    }

    // -------------------------------------------------------------------------
    // HELPER METHODS
    // -------------------------------------------------------------------------

    /**
     * Resolve the layout file path for participant or summary reports.
     */
    private function resolveLayoutFile(string $type, string $schemeType): string
    {
        $baseDir = ($type === 'participant') ? PARTICIPANT_REPORTS_LAYOUT : SUMMARY_REPORTS_LAYOUT;

        // Default layout
        $layoutFile = $baseDir . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $schemeType . '.phtml';

        // Check for custom layout
        if (!empty($this->config->layout)) {
            $customLayoutFile = $baseDir . DIRECTORY_SEPARATOR . $this->config->layout . DIRECTORY_SEPARATOR . $schemeType . '.phtml';
            if (file_exists($customLayoutFile)) {
                $layoutFile = $customLayoutFile;
            }
        }

        return $layoutFile;
    }

    /**
     * Build context array for participant layout templates.
     */
    private function buildParticipantLayoutContext(
        array $evalRow,
        array $totParticipantsRes,
        string $resultStatus,
        ?array $allGeneTypes
    ): array {
        return [
            'reportService' => $this->config->reportService,
            'schemeService' => $this->config->schemeService,
            'shipmentService' => $this->config->shipmentService,
            'commonService' => $this->config->commonService,
            'config' => $this->config->customConfig,
            'reportFormat' => $this->config->reportFormat,
            'recencyAssay' => $this->config->recencyAssay,
            'allGeneTypes' => $allGeneTypes,
            'downloadDirectory' => $this->config->downloadDirectory,
            'trainingInstance' => $this->config->trainingInstance,
            'evalRow' => $evalRow,
            'totParticipantsRes' => $totParticipantsRes,
            'reportsPath' => $this->config->reportsPath,
            'resultStatus' => $resultStatus,
            'layout' => $this->config->layout,
            'header' => $this->config->header,
            'instituteAddressPosition' => $this->config->instituteAddressPosition,
            'reportComment' => $this->config->reportComment,
            'logo' => $this->config->logo,
            'logoRight' => $this->config->logoRight,
            'templateTopMargin' => $this->config->templateTopMargin,
            'instance' => $this->config->instance,
            'passPercentage' => $this->config->passPercentage,
            'watermark' => $this->config->watermark,
            'customField1' => $this->config->customField1,
            'customField2' => $this->config->customField2,
            'haveCustom' => $this->config->haveCustom,
        ];
    }

    /**
     * Build context array for summary layout templates.
     */
    private function buildSummaryLayoutContext(array $evalRow, string $resultStatus, ?array $shipmentsUnderDistro): array
    {
        return [
            'reportService' => $this->config->reportService,
            'schemeService' => $this->config->schemeService,
            'shipmentService' => $this->config->shipmentService,
            'commonService' => $this->config->commonService,
            'evalService' => $this->config->evalService,
            'trainingInstance' => $this->config->trainingInstance,
            'evalRow' => $evalRow,
            'reportsPath' => $this->config->reportsPath,
            'resultStatus' => $resultStatus,
            'layout' => $this->config->layout,
            'header' => $this->config->header,
            'instituteAddressPosition' => $this->config->instituteAddressPosition,
            'reportComment' => $this->config->reportComment,
            'logo' => $this->config->logo,
            'logoRight' => $this->config->logoRight,
            'templateTopMargin' => $this->config->templateTopMargin,
            'instance' => $this->config->instance,
            'passPercentage' => $this->config->passPercentage,
            'watermark' => $this->config->watermark,
            'customField1' => $this->config->customField1,
            'customField2' => $this->config->customField2,
            'haveCustom' => $this->config->haveCustom,
            'shipmentsUnderDistro' => $shipmentsUnderDistro,
        ];
    }

    /**
     * Fetch shipment details for worker mode.
     */
    private function fetchShipmentForWorker(int $shipmentId): ?array
    {
        return $this->db->fetchRow(
            $this->db->select()
                ->from(
                    ['s' => 'shipment'],
                    [
                        'shipment_code',
                        'scheme_type',
                        'shipment_attributes',
                        'pt_co_ordinator_name',
                        'distribution_id'
                    ]
                )
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name', 'is_user_configured'])
                ->joinLeft(['qrg' => 'queue_report_generation'], 's.shipment_id=qrg.shipment_id', ['date_finalised'])
                ->where("s.shipment_id = ?", $shipmentId)
        );
    }

    /**
     * Fetch participant counts for a shipment.
     */
    private function fetchParticipantCounts(int $shipmentId): ?array
    {
        return $this->db->fetchRow(
            $this->db->select()->from(
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
                ->group('spm.shipment_id')
        );
    }

    /**
     * Fetch shipments to process from queue or manual mode.
     */
    private function fetchShipmentsToProcess(): array
    {
        if ($this->opts->isManualShipmentMode()) {
            $manualReportType = $this->opts->reportType ?? 'generateReport';
            $shipmentRow = $this->db->select()
                ->from(
                    ['s' => 'shipment'],
                    [
                        'shipment_id',
                        'shipment_code',
                        'scheme_type',
                        'shipment_attributes',
                        'pt_co_ordinator_name',
                        'distribution_id'
                    ]
                )
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name', 'is_user_configured'])
                ->joinLeft(['qrg' => 'queue_report_generation'], 's.shipment_id=qrg.shipment_id', ['date_finalised'])
                ->where("s.shipment_id = ?", $this->opts->shipmentId);

            $evalRow = $this->db->fetchRow($shipmentRow);
            if (empty($evalRow)) {
                throw new Exception("Shipment {$this->opts->shipmentId} not found.");
            }

            $evalRow['report_type'] = $manualReportType;
            $evalRow['saname'] = '';
            $evalRow['requested_by'] = 0;
            $evalRow['id'] = null;

            return [$evalRow];
        }

        // Fetch from queue
        $queueLimit = 3;
        return $this->db->fetchAll(
            $this->db->select()
                ->from(['eq' => 'queue_report_generation'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=eq.shipment_id', ['shipment_code', 'scheme_type', 'shipment_attributes', 'pt_co_ordinator_name'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->joinLeft(['sa' => 'system_admin'], 'eq.requested_by=sa.admin_id', ['saname' => new Zend_Db_Expr("CONCAT(sa.first_name,' ',sa.last_name)")])
                ->where("eq.status=?", 'pending')
                ->limit($queueLimit)
        );
    }

    /**
     * Acquire lock for manual shipment mode.
     * @return resource|null Lock handle or null
     */
    private function acquireManualShipmentLock()
    {
        $lock = self::acquireShipmentLock((int) $this->opts->shipmentId);

        if ($lock === null) {
            $info = self::getShipmentLockInfo((int) $this->opts->shipmentId);
            $running = self::isPidRunning($info['pid']);
            $ageMinutes = ($info['mtime'] ? (int) floor((time() - $info['mtime']) / 60) : null);

            if ($this->opts->force) {
                $ttlOk = ($this->opts->lockTtlMinutes > 0 && $ageMinutes !== null && $ageMinutes >= $this->opts->lockTtlMinutes && !$running);
                if ($ttlOk && is_string($info['path']) && $info['path'] !== '' && is_file($info['path'])) {
                    @unlink($info['path']);
                    $lock = self::acquireShipmentLock((int) $this->opts->shipmentId);
                    if (is_resource($lock)) {
                        return $lock;
                    }
                }
                self::warn(
                    "Shipment {$this->opts->shipmentId} appears locked (lock file: {$info['path']}). " .
                        "Proceeding due to --force" .
                        ($ttlOk ? " (lockTtl={$this->opts->lockTtlMinutes}m, age={$ageMinutes}m)." : ".") .
                        " This can corrupt output if another job is actually running.",
                    $this->opts->isCli
                );
                return null;
            }

            $details = "lock file: {$info['path']}";
            if ($info['pid']) {
                $details .= ", pid: {$info['pid']}" . ($running ? " (running)" : " (not running)");
            }
            if ($ageMinutes !== null) {
                $details .= ", age: {$ageMinutes}m";
            }
            self::error("Another report generation process is already running for shipment {$this->opts->shipmentId} ({$details}). Exiting.", $this->opts->isCli);
            self::log("If this is stale: check the PID (ps -fp <pid>) and remove the lock file, or rerun with --force.", $this->opts->isCli);
            exit(1);
        }

        return $lock;
    }

    /**
     * Try to acquire lock for a shipment in the processing loop.
     * @return resource|false Lock handle or false if should skip
     */
    private function tryAcquireShipmentLock(int $shipmentId, $manualShipmentLock)
    {
        if ($shipmentId <= 0) {
            return null;
        }

        // Reuse existing lock in manual mode
        if ($this->opts->isManualShipmentMode() && $shipmentId === (int) $this->opts->shipmentId && is_resource($manualShipmentLock)) {
            return $manualShipmentLock;
        }

        $lock = self::acquireShipmentLock($shipmentId);
        if (is_resource($lock)) {
            return $lock;
        }

        // Lock acquisition failed
        $info = self::getShipmentLockInfo($shipmentId);
        $running = self::isPidRunning($info['pid']);
        $ageMinutes = ($info['mtime'] ? (int) floor((time() - $info['mtime']) / 60) : null);

        if ($this->opts->force) {
            $ttlOk = ($this->opts->lockTtlMinutes > 0 && $ageMinutes !== null && $ageMinutes >= $this->opts->lockTtlMinutes && !$running);
            if ($ttlOk && is_string($info['path']) && $info['path'] !== '' && is_file($info['path'])) {
                @unlink($info['path']);
                $lock = self::acquireShipmentLock($shipmentId);
                if (is_resource($lock)) {
                    return $lock;
                }
            }
            self::warn(
                "Shipment {$shipmentId} appears locked (lock file: {$info['path']}). " .
                    "Proceeding due to --force. This can corrupt output if another job is actually running.",
                $this->opts->isCli
            );
            return null;
        }

        $details = "lock file: {$info['path']}";
        if ($info['pid']) {
            $details .= ", pid: {$info['pid']}" . ($running ? " (running)" : " (not running)");
        }
        if ($ageMinutes !== null) {
            $details .= ", age: {$ageMinutes}m";
        }
        self::warn("Shipment {$shipmentId} is already being processed by another report generation job ({$details}). Skipping.", $this->opts->isCli);

        return false;
    }

    /**
     * Evaluate shipment if configured to do so.
     */
    private function evaluateShipmentIfNeeded(array $evalRow, array &$evaluatedShipments): void
    {
        if (($evalRow['report_type'] != 'finalized' && $evalRow['report_type'] != 'generateReport') || $this->config->evaluateOnFinalized != "yes") {
            return;
        }

        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("Evaluating shipment ID: <comment>{$evalRow['shipment_id']}</comment>");
        }

        $shipmentId = $evalRow['shipment_id'];

        if (!isset($evaluatedShipments[$shipmentId])) {
            $timeStart = microtime(true);
            $shipmentResult = $this->config->evalService->getShipmentToEvaluate($shipmentId, true);
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
        $this->db->insert('notify', [
            'title' => 'Shipment Evaluated',
            'description' => 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated in ' . round($executionTime, 2) . ' mins',
            'link' => $link
        ]);

        if (
            isset($this->config->jobCompletionAlertStatus)
            && $this->config->jobCompletionAlertStatus == "yes"
            && isset($this->config->jobCompletionAlertMails)
            && !empty($this->config->jobCompletionAlertMails)
        ) {
            $emailSubject = "ePT | Shipment Evaluated";
            $emailContent = 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated <br><br> Please click on this link to see ' . $this->config->appConfig->domain . $link;
            $emailContent .= "<br><br><br><small>This is a system generated email</small>";
            $this->config->commonService->insertTempMail($this->config->jobCompletionAlertMails, null, null, $emailSubject, $emailContent);
        }
    }

    /**
     * Prepare the output directory for a shipment.
     */
    private function prepareShipmentOutputDirectory(array $evalRow): void
    {
        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("");
            Pt_Commons_MiscUtility::console()->writeln("<info>Processing shipment:</info> <comment>{$evalRow['shipment_code']}</comment> (ID: {$evalRow['shipment_id']})");
        }

        $shipmentCodePath = $this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'];
        if (file_exists($shipmentCodePath)) {
            Pt_Commons_General::rmdirRecursive($shipmentCodePath);
            mkdir($shipmentCodePath, 0777, true);
        }
        if (file_exists($this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip")) {
            unlink($this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip");
        }
    }

    /**
     * Update queue status for a shipment.
     */
    private function updateQueueStatus(array $evalRow, string $phase): void
    {
        $reportTypeStatus = 'not-evaluated';
        if ($evalRow['report_type'] == 'generateReport') {
            $reportTypeStatus = 'not-evaluated';
        } elseif ($evalRow['report_type'] == 'finalized') {
            $reportTypeStatus = 'not-finalized';
        }

        if (!empty($evalRow['id'])) {
            $this->db->update('queue_report_generation', [
                'status' => $reportTypeStatus,
                'last_updated_on' => new Zend_Db_Expr('now()'),
                'processing_started_at' => new Zend_Db_Expr('now()')
            ], 'id=' . $evalRow['id']);
        }

        // Update shipment status to processing
        if (!empty($evalRow['shipment_id'])) {
            $this->db->update('shipment', [
                'status' => 'processing',
                'updated_on_admin' => new Zend_Db_Expr('now()')
            ], 'shipment_id=' . $evalRow['shipment_id']);
        }
    }

    /**
     * Mark participant reports as generated in the database.
     */
    private function markReportsGenerated(array $resultArray): void
    {
        if (empty($resultArray['shipment'])) {
            return;
        }

        $mapIds = array_map(fn($row) => (int) $row['map_id'], $resultArray['shipment']);
        $mapIds = array_values(array_unique(array_filter($mapIds)));

        if (!empty($mapIds)) {
            $this->db->update('shipment_participant_map', ['report_generated' => 'yes'], 'map_id IN (' . implode(',', $mapIds) . ')');
        }
    }

    /**
     * Create ZIP archive of generated reports.
     */
    private function createZipArchive(array $evalRow): void
    {
        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("  Creating ZIP archive...");
        }

        $shipmentCodePath = $this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'];
        $this->config->generalModel->zipFolder($shipmentCodePath, $this->config->reportsPath . DIRECTORY_SEPARATOR . $evalRow['shipment_code'] . ".zip");

        if ($this->opts->isCli) {
            Pt_Commons_MiscUtility::console()->writeln("  <fg=green>✓</> ZIP created: <comment>{$evalRow['shipment_code']}.zip</comment>");
        }
    }

    /**
     * Complete shipment processing - update status, send notifications.
     */
    private function completeShipmentProcessing(array $evalRow, array $totParticipantsRes): void
    {
        $feedbackExpiryDate = null;
        $reportCompletedStatus = 'evaluated';
        $notifyType = 'individual_reports';

        if ($evalRow['report_type'] == 'generateReport') {
            $reportCompletedStatus = 'reports generated';
            $notifyType = 'individual_reports';
            $link = '/reports/distribution/shipment/sid/' . base64_encode($evalRow['shipment_id']);
        } elseif ($evalRow['report_type'] == 'finalized') {
            $reportCompletedStatus = 'finalized';
            $notifyType = 'summary_reports';
            $link = '/reports/shipments';
            $feedbackExpiryDate = date('Y-m-d', strtotime("+56 days"));
        }

        // Send completion alert email
        if (
            isset($this->config->jobCompletionAlertStatus)
            && $this->config->jobCompletionAlertStatus == "yes"
            && isset($this->config->jobCompletionAlertMails)
            && !empty($this->config->jobCompletionAlertMails)
        ) {
            $emailSubject = "ePT | Reports for " . $evalRow['shipment_code'];
            $emailContent = "Reports for Shipment " . $evalRow['shipment_code'] . " have been generated. <br><br> Please click on this link to see " . $this->config->appConfig->domain . $link;
            $emailContent .= "<br><br><br><small>This is a system generated email</small>";
            $this->config->commonService->insertTempMail($this->config->jobCompletionAlertMails, null, null, $emailSubject, $emailContent);
        }

        // Update queue record
        $update = [
            'status' => $reportCompletedStatus,
            'last_updated_on' => new Zend_Db_Expr('now()'),
            'processing_started_at' => null,
            'last_heartbeat' => null,
            'previous_status' => null
        ];
        if ($evalRow['report_type'] == 'finalized' && $evalRow['date_finalised'] == '') {
            $update['date_finalised'] = new Zend_Db_Expr('now()');
        }

        $feedbackExpiryDate = (isset($this->config->feedbackOption) && !empty($this->config->feedbackOption) && $this->config->feedbackOption == 'yes') ? $feedbackExpiryDate : null;

        // Update shipment status and milestone timestamps
        $shipmentUpdate = [
            'status' => $reportCompletedStatus,
            'feedback_expiry_date' => $feedbackExpiryDate,
            'report_in_queue' => 'no',
            'updated_by_admin' => (int) $evalRow['requested_by'],
            'updated_on_admin' => new Zend_Db_Expr('now()'),
            'previous_status' => null,
            'processing_started_at' => null,
            'last_heartbeat' => null
        ];

        // Set milestone timestamps based on report type
        if ($evalRow['report_type'] == 'generateReport') {
            $shipmentUpdate['reports_generated_at'] = new Zend_Db_Expr('now()');
        } elseif ($evalRow['report_type'] == 'finalized') {
            $shipmentUpdate['finalized_at'] = new Zend_Db_Expr('now()');
        }

        $this->db->update('shipment', $shipmentUpdate, "shipment_id = " . $evalRow['shipment_id']);

        // Add audit log for finalized shipments
        if (!empty($evalRow['id']) && $reportCompletedStatus == 'finalized') {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Finalized shipment - " . $evalRow['shipment_code'], "shipment");
        }

        // Update queue record
        if (!empty($evalRow['id'])) {
            $this->db->update('queue_report_generation', $update, 'id=' . $evalRow['id']);
        }

        // Send admin reminder email
        if ($this->config->enabledAdminEmailReminder == 'yes' && !empty($evalRow['initated_by'])) {
            $adminDetails = $this->config->adminService->getSystemAdminDetails($evalRow['initated_by']);
            if (isset($adminDetails) && !empty($adminDetails) && $adminDetails['primary_email'] != "") {
                $link = $this->config->appConfig->domain . '/reports/distribution/shipment/sid/' . base64_encode($evalRow['shipment_id']);
                $subject = 'Shipment report for ' . $evalRow['shipment_code'] . ' has been generated';
                $message = 'Hello, ' . $adminDetails['first_name'] . ', <br>
                 Shipment report for ' . $evalRow['shipment_code'] . ' has been generated successfully. Kindly click the below link to check the report or copy paste into the brower address bar.<br>
                 <a href="' . $link . '">' . $link . '</a>.';

                $this->config->commonService->insertTempMail($adminDetails['primary_email'], null, null, $subject, $message, 'ePT System', 'ePT System Admin');
            }
        }

        // Add notification
        $this->db->insert('notify', ['title' => 'Reports Generated', 'description' => 'Reports for Shipment ' . $evalRow['shipment_code'] . ' are ready for download', 'link' => $link]);

        // Send participant notification emails
        $this->sendParticipantNotificationEmails($evalRow);
    }

    /**
     * Send notification emails to data managers about reports.
     */
    private function sendParticipantNotificationEmails(array $evalRow): void
    {
        $notParticipatedMailContent = $this->config->commonService->getEmailTemplate('not_participant_report_mail');

        $subQuery = $this->db->select()
            ->from(['s' => 'shipment'], ['shipment_code', 'scheme_type'])
            ->join(['spm' => 'shipment_participant_map'], 'spm.shipment_id=s.shipment_id', ['map_id'])
            ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id=spm.participant_id', ['dm_id'])
            ->join(['p' => 'participant'], 'p.participant_id=pmm.participant_id', ['participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")])
            ->join(['dm' => 'data_manager'], 'pmm.dm_id=dm.dm_id', ['primary_email'])
            ->where("s.shipment_id=?", $evalRow['shipment_id'])
            ->group('dm.dm_id');

        $subResult = $this->db->fetchAll($subQuery);

        foreach ($subResult as $row) {
            $search = ['##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##'];
            $replace = [$row['participantName'], $row['shipment_code'], $row['scheme_type'], '', ''];
            $content = !empty($notParticipatedMailContent['mail_content']) ? $notParticipatedMailContent['mail_content'] : null;
            $message = !empty($content) ? str_replace($search, $replace, $content) : null;
            $subject = !empty($notParticipatedMailContent['mail_subject']) ? $notParticipatedMailContent['mail_subject'] : '';
            $fromEmail = !empty($notParticipatedMailContent['mail_from']) ? $notParticipatedMailContent['mail_from'] : null;
            $fromFullName = !empty($notParticipatedMailContent['from_name']) ? $notParticipatedMailContent['from_name'] : null;
            $toEmail = !empty($row['primary_email']) ? $row['primary_email'] : null;
            $cc = !empty($notParticipatedMailContent['mail_cc']) ? $notParticipatedMailContent['mail_cc'] : null;
            $bcc = !empty($notParticipatedMailContent['mail_bcc']) ? $notParticipatedMailContent['mail_bcc'] : null;

            if ($toEmail != null && $fromEmail != null && $subject != null && $message != null) {
                $this->config->commonService->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
            }
        }
    }

    /**
     * Get count of PDFs generated for a shipment.
     */
    private function getPdfCount(string $shipmentCode): int
    {
        return count(glob($this->config->reportsPath . DIRECTORY_SEPARATOR . $shipmentCode . DIRECTORY_SEPARATOR . '*.pdf'));
    }

    /**
     * Start progress bar for participant report generation.
     */
    private function startParticipantProgressBar(int $reportedCount)
    {
        if (!$this->opts->isCli) {
            return null;
        }

        // Skip progress bar in debug mode so logs are visible
        if ($this->opts->debug) {
            self::log("  Generating participant reports ({$reportedCount} participants)...", $this->opts->isCli);
            return null;
        }

        return Pt_Commons_MiscUtility::spinnerStart($reportedCount, "  Participant reports ({$reportedCount} participants)...", '█', '░', '█', 'cyan', true);
    }
}

// =============================================================================
// MAIN ENTRY POINT
// =============================================================================

try {
    $cliOpts = ReportJobOptions::parseCliOptions();
    $currentConfig = ReportConfig::load();
    $reportGenerator = new ReportGenerator($currentConfig, $cliOpts);

    // Register for signal handler cleanup
    $GLOBALS['_reportGenerator'] = $reportGenerator;

    // -------------------------------------------------------------------------
    // SUBPROCESS MODE (internal - for parallel participant report generation)
    // -------------------------------------------------------------------------
    // When --procs > 1, participantReports() spawns subprocesses to generate
    // PDFs in parallel. Each subprocess handles a chunk of participants.
    //
    if ($cliOpts->isSubProcess) {
        $reportGenerator->runAsSubProcess();
        exit(0);
    }

    // -------------------------------------------------------------------------
    // MAIN PROCESS
    // -------------------------------------------------------------------------
    // Fetches shipments from queue (or single shipment via --shipment flag)
    // and runs the 4-step report generation process for each.
    //
    $shipments = $reportGenerator->getShipmentsToProcess();
    if (empty($shipments)) {
        exit(0);
    }

    if ($cliOpts->isCli) {
        Pt_Commons_MiscUtility::console()->writeln(
            "<info>Report Generation</info> Using up to <comment>{$cliOpts->procs}</comment> parallel processes"
        );
    }

    foreach ($shipments as $shipment) {
        $reportGenerator->setupShipment($shipment);     // Lock, evaluate, prepare directories
        $reportGenerator->participantReports();                  // Generate individual PDFs (uses subprocesses when --procs > 1)
        $reportGenerator->summaryReport();                       // Generate summary PDF (single-threaded, one per shipment)
        $reportGenerator->completeShipmentReports();             // ZIP, notifications, release lock
    }
} catch (Exception $e) {
    $isCli = php_sapi_name() === 'cli';
    ReportGenerator::error("{$e->getFile()}:{$e->getLine()} : {$e->getMessage()}", $isCli);
    ReportGenerator::log("<fg=gray>{$e->getTraceAsString()}</>", $isCli);
} finally {
    // Always release lock, even on exception
    if (isset($reportGenerator)) {
        unset($reportGenerator);
    }
    $GLOBALS['_reportGenerator'] = null;
}
