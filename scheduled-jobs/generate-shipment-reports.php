<?php
// scheduled-jobs/generate-shipment-reports.php


require_once __DIR__ . '/../cli-bootstrap.php';

use setasign\Fpdi\Tcpdf\Fpdi;
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

if ($isCli) {
    echo "Using $procs processes to generate reports" . PHP_EOL;
}

/**
 * Include a PHP template file with an explicit variable context.
 * This avoids relying on ambient variables from the caller scope (especially inside closures).
 */
$includeWithContext = static function (string $file, array $context): void {
    if (!is_file($file)) {
        throw new RuntimeException("Template not found: {$file}");
    }

    (static function () use ($file, $context): void {
        extract($context, EXTR_OVERWRITE);
        require $file;
    })();
};


class IndividualPDF extends Fpdi
{
    public $scheme_name = '';
    public $header = '';
    public $angle = '';
    public $logo = '';
    public $logoRight = '';
    public $resultStatus = '';
    public $schemeType = '';
    public $layout = '';
    public $dateTime = '';
    public $config = null;
    public $watermark = '';
    public $dateFinalised = '';
    public $instituteAddressPosition = '';
    public $issuingAuthority = '';
    public $dtsPanelType = '';
    public $generalModel = null;


    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType, $layout, $datetime = "", $conf = "", $watermark = "", $dateFinalised = "", $instituteAddressPosition = "", $issuingAuthority = "", $dtsPanelType = "")
    {
        $this->generalModel = new Pt_Commons_General();
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->layout = $layout;
        $this->dateTime = $datetime;
        $this->config = $conf;
        $this->watermark = $watermark ?? '';
        $this->dateFinalised = $dateFinalised;
        $this->instituteAddressPosition = $instituteAddressPosition;
        $this->issuingAuthority = $issuingAuthority;
        $this->dtsPanelType = $dtsPanelType;
    }

    //Page header

    public function Header()
    {
        // Logo

        //$image_file = K_PATH_IMAGES.'logo_example.jpg';

        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                if (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'tb', 'generic-test']) && $this->layout == 'zimbabwe') {
                    $this->Image($image_file, 88, 15, 25, 0, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'dts' && $this->layout == 'jamaica') {
                    $this->Image($image_file, 90, 10, 15, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
                    $this->Image($image_file, 10, 2, 25, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'vl' && $this->layout == 'myanmar') {
                    $this->Image($image_file, 10, 05, 22, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } else {
                    $this->Image($image_file, 10, 8, 25, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            }
        }
        $screening = "";
        if (isset($this->dtsPanelType) && !empty($this->dtsPanelType)) {
            $screening = " - " . ucwords($this->dtsPanelType);
        }
        // Set font

        $this->SetFont('freesans', '', 10, '', true);
        //$this->header = nl2br(trim($this->header));

        //$this->header = preg_replace('/<br>$/', "", $this->header);


        if (isset($this->config->instituteAddress) && $this->config->instituteAddress != "") {
            $instituteAddress = nl2br(stripcslashes(trim($this->config->instituteAddress)));
        } else {
            $instituteAddress = null;
        }
        if (isset($this->config->additionalInstituteDetails) && $this->config->additionalInstituteDetails != "") {
            $additionalInstituteDetails = nl2br(stripcslashes(trim($this->config->additionalInstituteDetails)));
        } else {
            $additionalInstituteDetails = null;
        }
        if ($this->schemeType == 'vl' && $this->layout != 'zimbabwe') {
            if (isset($this->config) && $this->config != "") {
                $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>

                <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                }
                $html .= '<br/><br/><span style="font-weight: bold;text-align:center;font-size:12px;">Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>';
                //$htmlTitle = '<span style="font-weight: bold;text-align:center;font-size:12px;">Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:13;text-align:center;">All Participants Summary Report</span>';

            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span>';
            }
        } elseif ($this->schemeType == 'eid' && $this->layout != 'zimbabwe') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;font-size:11;">' . $this->header . '</span><br/>';
            if (isset($this->config) && $this->config != "") {
                $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>

                <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                }
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report</span>';
            }
        } elseif ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<div style="font-weight: bold;text-align:center;background-color:black;color:white;height:100px;"><span style="text-align:center;font-size:11;">' . $this->header . ' | INDIVIDUAL FINAL REPORT</span></div>';
        } elseif (($this->schemeType == 'recency' || $this->schemeType == 'dts') && $this->layout != 'zimbabwe' && $this->layout != 'myanmar' && $this->layout != 'jamaica') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            }
            $html .= '<br>Proficiency Testing Report - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report ' . $screening . '</span>';
        } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>';
            $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
            }
            $html .= '<br><br>Proficiency Testing Report - ' . $this->scheme_name . '</span>';
        } elseif ($this->schemeType == 'dts' && $this->layout == 'jamaica') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            /* if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {

                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';

            } */
        } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'tb', 'generic-test']) && $this->layout == 'zimbabwe') {
            if ($this->schemeType != 'tb') {
                $this->SetFont('freesans', '', 10, '', true);
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
                $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
                $htmlInAdd = '<span style="font-weight: normal;text-align:right;">' . $instituteAddress . '</span>';
                $this->writeHTMLCell(0, 0, 15, 20, $htmlInAdd, 0, 0, 0, true, 'J', true);
                $htmlInDetails = '<span style="font-weight: normal;text-align:left;">' . $additionalInstituteDetails . '</span>';
                $this->writeHTMLCell(0, 0, 10, 20, $htmlInDetails, 0, 0, 0, true, 'J', true);
            }
            if ($this->schemeType == 'dts') {
                $this->writeHTMLCell(0, 0, 10, 40, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Report - Rapid HIV Serology Test</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'recency') {
                $this->writeHTMLCell(0, 0, 10, 40, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Report Rapid Test for Recent Infection (RTRI)</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'vl') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'eid') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for HIV-1 Early Infant Diagnosis Using Dried Blood Spots</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'tb') {
                // $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for Tuberculosis</span>', 0, 0, 0, true, 'J', true);

            }
            if ($this->schemeType != 'tb') {
                $finalized = (!empty($this->resultStatus) && $this->resultStatus == 'finalized') ? 'FINAL ' : '';
                $finalizeReport = '<span style="font-weight: normal;text-align:center;">' . $finalized . ' INDIVIDUAL REPORT ' . $screening . '</span>';
                $this->writeHTMLCell(0, 0, 10, 45, $finalizeReport, 0, 0, 0, true, 'J', true);
            }
        } elseif ($this->schemeType == 'covid19') {
            $this->SetFont('freesans', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report - SARS-CoV-2</span>';
        } elseif ($this->schemeType == 'generic-test') {
            $this->SetFont('freesans', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report -  ' . $this->scheme_name . '</span>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            }
        } else {
            $this->SetFont('freesans', '', 11);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report -  ' . $this->scheme_name . '</span>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            }
        }

        if ($this->schemeType == 'vl' && $this->layout != 'zimbabwe') {
            if ($this->layout == 'myanmar') {
                $this->writeHTMLCell(0, 0, 10, 05, $html, 0, 0, 0, true, 'J', true);
            } else {
                $this->writeHTMLCell(0, 0, 27, 10, $html, 0, 0, 0, true, 'J', true);
            }
            $html = '<hr/>';
            $mt = 30;
            if ($this->layout == 'myanmar') {
                $mt = 35;
            }
            $this->writeHTMLCell(0, 0, 10, $mt, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'dts' && $this->layout == 'jamaica') {
            $this->writeHTMLCell(0, 0, 15, 5, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $html .= '<br><span style="font-weight: bold; font-size:11;text-align:center;">Proficiency Testing Report - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report ' . $screening . '</span>';
            $this->writeHTMLCell(0, 0, 10, 28, $html, 0, 0, 0, true, 'J', true);
        } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'generic-test']) && $this->layout == 'zimbabwe') {
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
        } else {
            if ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType != 'tb' && ($this->schemeType != 'dts' && $this->layout != 'myanmar')) {
                $this->writeHTMLCell(0, 0, 27, 20, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 40, $html, 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
                $this->writeHTMLCell(0, 0, 27, 5, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 28, $html, 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'dts' && $this->layout == 'default') {
                $this->writeHTMLCell(0, 0, 27, 8, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 38, $html, 0, 0, 0, true, 'J', true);
            } else {
                $this->writeHTMLCell(0, 0, 27, 8, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        }

        if (isset($this->watermark) && $this->watermark != "") {
            $this->SetAlpha(0.2); // Set transparency

            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(211, 211, 211);
            $this->RotatedText(25, 190, $this->watermark, 45);
            $this->SetAlpha(1); // Reset transparency

        }
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1)
            $x = $this->x;
        if ($y == -1)
            $y = $this->y;
        if ($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin

        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    // Page footer

    public function Footer()
    {
        $finalizeReport = "";
        if (isset($this->resultStatus) && trim($this->resultStatus) == "finalized") {
            $finalizeReport = ' | INDIVIDUAL REPORT | FINALIZED ';
        } else {
            $finalizeReport = ' | INDIVIDUAL REPORT ';
        }

        $showTime = $this->dateTime ?? date("Y-m-d H:i:s");
        // Position at 15 mm from bottom

        $this->SetY(-15);
        // Set font

        $this->SetFont('freesans', '', 7);
        // Page number

        if ($this->schemeType == 'eid' || $this->schemeType == 'vl' || $this->schemeType == 'tb') {
            $this->writeHTML("<hr>", true, false, true, false, '');
            if ($this->instituteAddressPosition == "footer" && isset($instituteAddress) && $instituteAddress != "") {
                $this->writeHTML($instituteAddress, true, false, true, false, "L");
            }
        }
        $effectiveDate = new DateTime($showTime);
        if (($this->schemeType == 'eid' || $this->schemeType == 'vl' || $this->schemeType == 'tb') && isset($this->config) && $this->config != "" && $this->layout != 'zimbabwe') {
            // $this->Cell(0, 10, 'ILB-', 0, false, 'L', 0, '', 0, false, 'T', 'M');

            // $this->Ln();

            $effectiveMonthYear = ($this->schemeType == 'tb') ? "March 2022" : $effectiveDate->format('M Y');
            $this->SetFont('freesans', '', 10);
            if ($this->schemeType == 'tb') {
                $this->SetFont('freesans', '', 9);
                if (isset($this->issuingAuthority) && !empty($this->issuingAuthority)) {
                    $html = '<table><tr><td><span style="text-align:left;">Form : ILB-500-F29A</span></td><td><span style="text-align:center;">Issuing Authority : ' . $this->issuingAuthority . '</span></td><td><span style="text-align:right;">Effective Date : ' . $effectiveMonthYear . '</span></td></tr></table>';
                    $this->writeHTML($html, true, false, true, false, '');
                }
                $this->Cell(0, 6, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            } else {
            }
        } else {
            // if (isset($this->layout) && $this->layout == 'zimbabwe') {

            // $this->Cell(0, 6, 'Effective Date:' . $effectiveDate->format('M Y'), 0, false, 'L', 0, '', 0, false, 'T', 'M');

            // $this->writeHTML("<hr>", true, false, true, false, '');

            // $this->writeHTML("NATIONAL MICROBIOLOGY REFERENCE LABORATORY EXTERNAL QUALITY ASSURANCE SURVEY <br><span style='color:red;'>*** All the contents of this report are strictly confidential ***</span>", true, false, true, false, 'C');

            // }

            if (isset($this->layout) && $this->layout == 'zimbabwe') {
                $this->writeHTML("NATIONAL MICROBIOLOGY REFERENCE LABORATORY EXTERNAL QUALITY ASSURANCE SURVEY <br><span style='color:red;'>*** All the contents of this report are strictly confidential ***</span>", true, false, true, false, 'C');
            } else {
                $this->writeHTML("Report generated on " . $this->generalModel->humanReadableDateFormat($showTime) . $finalizeReport, true, false, true, false, 'C');
            }
        }
        if ($this->schemeType != 'tb') {
            $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }
    }
}

class SummaryPDF extends Fpdi
{
    public $angle = 0;
    public $scheme_name = "";
    public $header = "";
    public $logo = "";
    public $logoRight = "";
    public $resultStatus = "";
    public $schemeType = "";
    public $layout = "";
    public $dateTime = "";
    public $config = null;
    public $watermark = "";
    public $dateFinalised = "";
    public $instituteAddressPosition = "";
    public $issuingAuthority = "";
    public $dtsPanelType = "";
    public $generalModel = null;
    public $tbTestType = null;


    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType, $datetime = "", $conf = "", $watermark = "", $dateFinalised = "", $instituteAddressPosition = "", $layout = "", $issuingAuthority = "", $dtsPanelType = "", $tbTestType = "")
    {
        $this->generalModel = new Pt_Commons_General();
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->layout = $layout;
        $this->dateTime = $datetime;
        $this->config = $conf;
        $this->watermark = $watermark ?? '';
        $this->dateFinalised = $dateFinalised;
        $this->instituteAddressPosition = $instituteAddressPosition;
        $this->issuingAuthority = $issuingAuthority;
        $this->dtsPanelType = $dtsPanelType;
        $this->tbTestType = $tbTestType;
    }

    //Page header

    public function Header()
    {
        // Logo

        $imagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;

        if (trim($this->logo) !== "" && file_exists($imagePath)) {
            $isSchemeTypeDTS = $this->schemeType == 'dts';
            $isConfigSet = isset($this->config) && $this->config != "";
            if ($isSchemeTypeDTS && $this->layout == 'jamaica') {
                $this->Image($imagePath, 90, 10, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid']) && $this->layout == 'zimbabwe') {
                $this->Image($imagePath, 88, 15, 25, '', '', '', 'C', false, 300, '', false, false, 0, false, false, false);
            } elseif ($isConfigSet && $this->layout != 'zimbabwe') {
                if (isset($this->tbTestType) && !empty($this->tbTestType) && $this->tbTestType == 'microscopy') {
                    $this->Image($imagePath, 85, 15, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } elseif (isset($this->tbTestType) && !empty($this->tbTestType) && $this->tbTestType != 'microscopy') {
                    // $this->Image($imagePath, 10, 8, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);

                } else {
                    $this->Image($imagePath, 10, 3, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            } else {
                $this->Image($imagePath, 10, 8, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }

        // Set font

        $this->SetFont('freesans', '', 10);
        $screening = "";
        if (isset($this->dtsPanelType) && !empty($this->dtsPanelType)) {
            $screening = " - " . ucwords($this->dtsPanelType);
        }
        $html = $htmlTitle = '';
        if (isset($this->config->instituteAddress) && $this->config->instituteAddress != "") {
            $instituteAddress = nl2br(trim($this->config->instituteAddress));
        } else {
            $instituteAddress = null;
        }
        if (isset($this->config->additionalInstituteDetails) && $this->config->additionalInstituteDetails != "") {
            $additionalInstituteDetails = nl2br(trim($this->config->additionalInstituteDetails));
        } else {
            $additionalInstituteDetails = null;
        }
        if ($this->schemeType == 'vl' && $this->layout != 'zimbabwe') {
            if (isset($this->config) && $this->config != "") {
                if ($this->layout == 'myanmar') {
                    $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>

                    <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                    if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                        $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span><br><br><span style="font-weight: bold;text-align:center;font-size:12px;">Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>';
                    }
                    $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
                    $html = '<hr/>';
                    $this->writeHTMLCell(0, 0, 10, 35, $html, 0, 0, 0, true, 'J', true);
                } else {
                    $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>

                    <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                    if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                        $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                    }
                    $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
                    $html = '<hr/>';
                    $this->writeHTMLCell(0, 0, 10, 35, $html, 0, 0, 0, true, 'J', true);
                }
                //$htmlTitle = '<span style="font-weight: bold;text-align:center;font-size:12px;">Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:13;text-align:center;">All Participants Summary Report</span>';

            } else {
                $html .= '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        } elseif ($this->schemeType == 'eid' && $this->layout != 'zimbabwe') {
            $this->SetFont('freesans', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;font-size:11;">' . $this->header . '</span><br/>';
            if (isset($this->config) && $this->config != "") {
                $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>

                <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                }
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Results Report</span>';
            }
            $this->writeHTMLCell(0, 0, 15, 20, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 40, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
            if (isset($this->tbTestType) && !empty($this->tbTestType) && $this->tbTestType != 'microscopy') {
                $html = '<div style="font-weight: bold;text-align:center;background-color:black;color:white;height:100px;"><span style="text-align:center;font-size:11;">' . $this->header . ' | FINAL SUMMARY REPORT</span></div>';
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            } elseif ($this->tbTestType == 'microscopy') {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
                $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $htmlInAdd = '<span style="font-weight: normal;text-align:right;">' . $instituteAddress . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 20, $htmlInAdd, 0, 0, 0, true, 'J', true);
                }
                if ($this->instituteAddressPosition == "header" && isset($additionalInstituteDetails) && $additionalInstituteDetails != "") {
                    $htmlInDetails = '<span style="font-weight: normal;text-align:left;">' . $additionalInstituteDetails . '</span>';
                    $this->writeHTMLCell(0, 0, 10, 20, $htmlInDetails, 0, 0, 0, true, 'J', true);
                }
                $html = '<span style="font-weight: bold;text-align:center;">Proficiency Testing Program -' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
                $this->writeHTMLCell(0, 0, 15, 35, $html, 0, 0, 0, true, 'J', true);
                $this->writeHTMLCell(0, 0, 10, 45, "<hr>", 0, 0, 0, true, 'J', true);
            }
        } elseif ($this->schemeType == 'recency' && $this->layout != 'zimbabwe') {
            $this->SetFont('freesans', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Recency using - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'covid19') {
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program -' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
            $this->writeHTMLCell(0, 0, 20, 25, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program - ' . $this->scheme_name . '</span>', 0, 0, 0, true, 'J', true);
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $htmlInAdd = '<span style="font-weight: normal;text-align:center;">' . $instituteAddress . '</span>';
                $this->writeHTMLCell(0, 0, 15, 12, $htmlInAdd, 0, 0, 0, true, 'J', true);
            }
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            $this->writeHTMLCell(0, 0, 15, 5, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 30, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'dts' && $this->layout != 'zimbabwe' && $this->layout != 'myanmar' && $this->layout != 'jamaica') {
            $this->writeHTMLCell(0, 0, 10, 25, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program - ' . $this->scheme_name . ' </span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report ' . $screening . '</span>', 0, 0, 0, true, 'J', true);
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $htmlInAdd = '<span style="font-weight: normal;text-align:center;">' . $instituteAddress . '</span>';
                $this->writeHTMLCell(0, 0, 15, 15, $htmlInAdd, 0, 0, 0, true, 'J', true);
            }
            $this->SetFont('freesans', '', 10, '', true);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            $this->writeHTMLCell(0, 0, 15, 8, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 35, $html, 0, 0, 0, true, 'J', true);
        } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'tb']) && $this->layout == 'zimbabwe') {
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
            if ($this->schemeType != 'tb') {
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $htmlInAdd = '<span style="font-weight: normal;text-align:right;">' . $instituteAddress . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 20, $htmlInAdd, 0, 0, 0, true, 'J', true);
                }
                if ($this->instituteAddressPosition == "header" && isset($additionalInstituteDetails) && $additionalInstituteDetails != "") {
                    $htmlInDetails = '<span style="font-weight: normal;text-align:left;">' . $additionalInstituteDetails . '</span>';
                    $this->writeHTMLCell(0, 0, 10, 20, $htmlInDetails, 0, 0, 0, true, 'J', true);
                }
            }
            if ($this->schemeType == 'dts') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Report - Rapid HIV and Recency Dried Tube Specimen</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'recency') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Report Rapid Test for Recent Infection (RTRI)</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'vl') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'eid') {
                $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for HIV-1 Early Infant Diagnosis Using Dried Blood Spots</span>', 0, 0, 0, true, 'J', true);
            } elseif ($this->schemeType == 'tb') {
                // $this->writeHTMLCell(0, 0, 10, 39, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program for Tuberculosis</span>', 0, 0, 0, true, 'J', true);

            } elseif ($this->schemeType == 'generic-test') {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>' . $this->scheme_name . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                }
                $this->writeHTMLCell(0, 0, 10, 20, $html, 0, 0, 0, true, 'J', true);
            }
            if ($this->schemeType != 'tb') {
                $finalized = (!empty($this->resultStatus) && $this->resultStatus == 'finalized') ? 'FINAL ' : '';
                $finalizeReport = '<span style="font-weight: normal;text-align:center;">' . $finalized . 'SUMMARY REPORT</span>';
                $this->writeHTMLCell(0, 0, 15, 45, $finalizeReport, 0, 0, 0, true, 'J', true);

                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        } else {
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using '.$this->scheme_name.'</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';

            $this->SetFont('freesans', '', 10, '', true);
            if ($this->schemeType == 'dts') {
                if ($this->layout == 'myanmar') {
                    $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Antibody Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Summary Report ' . $screening . '</span>';
                } else if ($this->layout == 'jamaica') {
                    $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 5, $html, 0, 0, 0, true, 'J', true);
                    $html = '<hr/>';
                    $html .= '<br><span style="font-weight: bold;font-size:11;text-align:center;">' . 'Proficiency Testing Program - ' . $this->scheme_name . ' </span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report ' . $screening . '</span>';
                    $this->writeHTMLCell(0, 0, 10, 28, $html, 0, 0, 0, true, 'J', true);
                } else {
                    $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Antibody Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report ' . $screening . '</span>';
                }
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            }
            if ($this->layout != 'jamaica') {
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        }

        if (isset($this->watermark) && $this->watermark != "") {
            $this->SetAlpha(0.2); // Set transparency

            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(211, 211, 211);
            $this->RotatedText(25, 190, $this->watermark, 45);
            $this->SetAlpha(1); // Reset transparency

        }
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin

        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    // Page footer

    public function Footer()
    {
        $finalizeReport = "";
        $isLayoutZimbabwe = ($this->layout == 'zimbabwe');
        if (isset($this->resultStatus) && trim($this->resultStatus) == "finalized") {
            $finalizeReport = ' | SUMMARY REPORT | FINALIZED ';
        } else {
            $finalizeReport = ' | SUMMARY REPORT ';
        }
        if (isset($this->dateTime) && $this->dateTime != '') {
            $showTime = $this->dateTime;
        } else {
            $showTime = date("Y-m-d H:i:s");
        }
        // Position at 15 mm from bottom

        $this->SetY(-18);
        // Set font

        $this->SetFont('freesans', '', 7, '', true);
        // Page number

        $this->writeHTML("<hr>", true, false, true, false, "");
        if ($this->instituteAddressPosition == "footer" && isset($instituteAddress) && $instituteAddress != "") {
            $this->writeHTML($instituteAddress, true, false, true, false, "L");
        }
        if (($this->schemeType == 'eid' || $this->schemeType == 'vl') && isset($this->config) && $this->config != "" && $this->layout != 'zimbabwe') {
            $effectiveDate = new DateTime($showTime);
            $this->SetFont('freesans', '', 10, '', true);
            $this->Cell(0, 10, 'Effective Date:' . $effectiveDate->format('M Y'), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        } else {
            $effectiveDate = new DateTime($showTime);
            $effectiveMonthYear = ($this->schemeType == 'tb') ? "June 2022" : $effectiveDate->format('M Y');
            if ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
                $this->SetFont('freesans', '', 9, '', true);
                if (isset($this->issuingAuthority) && !empty($this->issuingAuthority)) {
                    $html = "<table><tr><td><span style=\"text-align:left;\">Form : ILB-500-F29A</span></td><td><span style=\"text-align:center;\">Issuing Authority : {$this->issuingAuthority}</span></td><td><span style=\"text-align:right;\">Effective Date : $effectiveMonthYear</span></td></tr></table>";
                    $this->writeHTML($html, true, false, true, false, '');
                }
                $this->Cell(0, 6, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
            if (isset($this->layout) && $isLayoutZimbabwe) {
                $this->writeHTML("NATIONAL MICROBIOLOGY REFERENCE LABORATORY EXTERNAL QUALITY ASSURANCE SURVEY <br><span style='color:red;'>*** All the contents of this report are strictly confidential ***</span>", true, false, true, false, 'C');
            } elseif ($this->schemeType != 'tb') {
                $this->Cell(0, 10, "Report generated on " . $this->generalModel->humanReadableDateFormat($showTime) . $finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }
        if ($this->schemeType != 'tb') {
            $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage() . ' | ' . $this->getAliasNbPages() . "    ", 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }
    }
}

// Extend the FPDI class to create custom Header and Footer

class FPDIReport extends Fpdi
{
    public $resultStatus = "";
    public $dateTime = "";
    public $watermark = "";
    public $angle = "";
    public $config = "";
    public $generalModel;
    public $reportType = "";
    public $template = "";
    public $layout = "";
    public $scheme = "";
    public $templateTopMargin = "";
    public $schemeType = "";
    public $approveTxt = "";
    public $instance = "";
    public $staticFooterHtml = "";

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->generalModel = new Pt_Commons_General();
    }
    public function setParams($resultStatus, $dateTime, $config, $watermark, $reportType, $layout, $scheme = "", $schemeType = "", $approveTxt = "", $staticFooterHtml = "")
    {
        $this->resultStatus = $resultStatus;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->watermark = $watermark ?? '';
        $this->reportType = $reportType;
        $this->layout = $layout;
        $this->scheme = $scheme;
        $this->schemeType = $schemeType;
        $this->approveTxt = $approveTxt;
        $this->staticFooterHtml = $staticFooterHtml;

        $reportService = new Application_Service_Reports();
        $commonService = new Application_Service_Common();
        $reportFormat = $reportService->getReportConfigValue('report-format');
        $templateTopMargin = $reportService->getReportConfigValue('template-top-margin');
        $this->instance = $commonService->getConfig('instance');
        $this->templateTopMargin = $templateTopMargin;
        if (!empty($reportFormat) && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $reportFormat)) {
            $this->template = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $reportFormat;
        }
    }

    public function Header()
    {
        if (!empty($this->template) && $this->template != "") {
            $this->setSourceFile($this->template);
            $template = $this->ImportPage(1);
            $this->useImportedPage($template);
        }
        if (isset($this->scheme) && !empty($this->scheme) && $this->PageNo() == 1) {
            if (isset($this->templateTopMargin) && !empty($this->templateTopMargin)) {
                $this->SetY($this->templateTopMargin);
            } else {
                $this->SetY(32);
            }
            if ($this->layout != 'malawi') {
                $this->SetFont('freesans', 'B', 10);
                // $this->writeHTML("Proficiency Testing Program for " . $this->scheme, true, false, true, false, 'C');

            }
        }
        if ($this->layout != 'malawi' && $this->layout != 'zimbabwe') {
            if (isset($this->reportType) && !empty($this->reportType) && strtolower($this->reportType) == 'summary' && $this->PageNo() == 1) {
                $this->writeHTML("<br>All Participants Results Report", true, false, true, false, 'C');
            } elseif (strtolower($this->reportType) == 'individual' && $this->PageNo() == 1 && $this->schemeType != 'dts') {
                $this->writeHTML("<br>Individual Participant Results Report", true, false, true, false, 'C');
            }
        }

        if (isset($this->watermark) && $this->watermark != "") {
            $this->SetAlpha(0.2); // Set transparency

            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(211, 211, 211);
            $this->RotatedText(25, 190, $this->watermark, 45);
            $this->SetAlpha(1); // Reset transparency

        }
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin

        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    // Page footer

    public function Footer()
    {
        // Build complete footer HTML in one go

        $completeFooterHtml = "";

        // Add static footer content if provided

        if (!empty($this->staticFooterHtml)) {
            $completeFooterHtml .= $this->staticFooterHtml;
        }

        // Add dynamic content to the same HTML block

        $finalizeReport = "";
        if (isset($this->resultStatus) && trim($this->resultStatus) == "finalized") {
            $finalizeReport = " | {$this->reportType} REPORT | FINALIZED ";
        } else {
            $finalizeReport = " | {$this->reportType} REPORT ";
        }
        $showTime = $this->dateTime ?? date("Y-m-d H:i:s");

        // Append dynamic content to footer HTML

        $reportDate = $this->generalModel->humanReadableDateFormat($showTime);
        $completeFooterHtml .= '<br><div style="text-align:center; font-size:7px; margin-top:3px;">Report generated on ' . $reportDate . $finalizeReport . '</div>';

        // Append page numbers

        $completeFooterHtml .= '<div style="text-align:right; font-size:7px; margin-top:2px;">Page ' . $this->getAliasNumPage() . ' | ' . $this->getAliasNbPages() . '</div>';

        // Handle special cases

        if (isset($this->instance) && !empty($this->instance) && $this->instance == 'philippines') {
            if (isset($this->approveTxt) && !empty($this->approveTxt)) {
                $text = "This document has been reviewed and validated by EQA officers and authorized personnel of {$this->approveTxt}";
            } else {
                $text = "This document has been reviewed and validated by EQA officers.";
            }
            $completeFooterHtml = '<div style="text-align:center; font-size:7px;">' . $text . '</div>' . $completeFooterHtml;
        }

        // Output complete footer in single call

        $this->SetY(-25);
        $this->SetFont('freesans', '', 7, '', true);
        $this->writeHTML($completeFooterHtml, true, false, false, false, '');
    }
}
class PDF_Rotate extends FPDI
{
    public $angle = 0;
    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }

        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

class Watermark extends PDF_Rotate
{
    private $waterMarkText = null;
    public $_tplIdx;
    public $numPages;

    public function __construct($waterMarkText)
    {
        $this->waterMarkText = $waterMarkText;
    }

    public function Header()
    {
        global $fullPathToFile;
        if (isset($this->waterMarkText) && $this->waterMarkText != "") {
            //Put the watermark

            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(230, 228, 198);
            $this->RotatedText(25, 190, $this->waterMarkText, 45);
        }

        if (null !== $this->_tplIdx) {
            // THIS IS WHERE YOU GET THE NUMBER OF PAGES

            $this->numPages = $this->setSourceFile($fullPathToFile);
            $this->_tplIdx = $this->importPage(1);
        }
        $this->useTemplate($this->_tplIdx, 0, 0, 200);
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin

        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
        //$this->SetAlpha(0.7);

    }
}
class Pdf_concat extends FPDI
{
    public $files = [];
    public function setFiles($files)
    {
        $this->files = $files;
    }
    public function concat()
    {
        foreach ($this->files as $file) {
            $pagecount = $this->setSourceFile($file);
            for ($i = 1; $i <= $pagecount; $i++) {
                $tplidx = $this->ImportPage($i);
                $s = $this->getTemplatesize($tplidx);
                $this->AddPage('P', array($s['w'], $s['h']));
                $this->useTemplate($tplidx);
            }
        }
    }
}

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

            if ($id > 0 && $reportCompletedStatus == 'finalized') {
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
