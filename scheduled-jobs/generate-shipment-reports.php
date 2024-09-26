<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php';

use PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf;
use setasign\Fpdi\Tcpdf\Fpdi;

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);
//error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);


// Flags for testing
$options = getopt("sp");

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
        $this->watermark = $watermark;
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
                if (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'tb']) && $this->layout == 'zimbabwe') {
                    $this->Image($image_file, 88, 15, 25, '', '', '', 'C', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'dts' && $this->layout == 'jamaica') {
                    $this->Image($image_file, 90, 13, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
                    $this->Image($image_file, 10, 2, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } elseif ($this->schemeType == 'vl' && $this->layout == 'myanmar') {
                    $this->Image($image_file, 10, 05, 22, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                } else {
                    $this->Image($image_file, 10, 8, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            }
        }
        $screening = "";
        if (isset($this->dtsPanelType) && !empty($this->dtsPanelType)) {
            $screening = " - " . ucwords($this->dtsPanelType);
        }
        // Set font
        $this->SetFont('helvetica', '', 10);
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
                $html .= '<br/><br/><span style="font-weight: bold;text-align:center;font-size:12;">Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>';
                //$htmlTitle = '<span style="font-weight: bold;text-align:center;font-size:12;">Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:13;text-align:center;">All Participants Summary Report</span>';
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span>';
            }
        } elseif ($this->schemeType == 'eid' && $this->layout != 'zimbabwe') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;font-size:11;">' . $this->header . '</span><br/>';
            if (isset($this->config) && $this->config != "") {
                $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>
                <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
                }
                //$htmlTitle = '<span style="font-weight: bold;text-align:center;font-size:13;">Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:13;text-align:center;">All Participants Summary Report</span>';
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report</span>';
            }
        } elseif ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
            $this->SetFont('helvetica', '', 10);
            $html = '<div style="font-weight: bold;text-align:center;background-color:black;color:white;height:100px;"><span style="text-align:center;font-size:11;">' . $this->header . ' | INDIVIDUAL FINAL REPORT</span></div>';
        } elseif (($this->schemeType == 'recency' || $this->schemeType == 'dts') && $this->layout != 'zimbabwe' && $this->layout != 'myanmar') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            }
            $html .= '<br>Proficiency Testing Report - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report ' . $screening . '</span>';
        } elseif ($this->schemeType == 'dts' && $this->layout == 'myanmar') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>';
            $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
            }
            $html .= '<br><br>Proficiency Testing Report - ' . $this->scheme_name . '</span>';
        } elseif ($this->schemeType == 'dts' && $this->layout == 'jamaica') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            /* if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            } */
        } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid', 'tb']) && $this->layout == 'zimbabwe') {
            if ($this->schemeType != 'tb') {
                $this->SetFont('helvetica', '', 10);
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
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report - SARS-CoV-2</span>';
        } elseif ($this->schemeType == 'generic-test') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report -  ' . $this->scheme_name . '</span>';
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span>';
            }
        } else {
            $this->SetFont('helvetica', '', 11);
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
            $this->writeHTMLCell(0, 0, 10, 40, $html, 0, 0, 0, true, 'J', true);
        } elseif (in_array($this->schemeType, ['recency', 'dts', 'vl', 'eid']) && $this->layout == 'zimbabwe') {
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
        //Put the watermark
        $this->SetFont('', 'B', 120);
        $this->SetTextColor(230, 228, 198);
        $this->RotatedText(25, 190, $this->watermark, 45);
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
        if (isset($this->dateTime) && $this->dateTime != '') {
            $showTime = $this->dateTime;
        } else {
            $showTime = date("Y-m-d H:i:s");
        }
        // Position at 15 mm from bottom
        $this->SetY(-18);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        //$this->Cell(0, 10, "Report generated at :".date("d-M-Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        //$this->Cell(0, 10, "Report generated on ".date("d M Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
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
            $this->SetFont('helvetica', '', 10);
            if ($this->schemeType == 'tb') {
                $this->SetFont('helvetica', '', 9);
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
            $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage() . ' | ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
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
        $this->watermark = $watermark;
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
                $this->Image($imagePath, 90, 28, 20, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
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
        $this->SetFont('helvetica', '', 10);
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
        if ($this->schemeType == 'vl'  && $this->layout != 'zimbabwe') {
            if (isset($this->config) && $this->config != "") {
                if ($this->layout == 'myanmar') {
                    $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . $this->config->instituteName . '</span>
                    <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                    if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                        $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . $instituteAddress . '</span><br><br><span style="font-weight: bold;text-align:center;font-size:12;">Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>';
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
                //$htmlTitle = '<span style="font-weight: bold;text-align:center;font-size:12;">Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:13;text-align:center;">All Participants Summary Report</span>';
            } else {
                $html .= '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        } elseif ($this->schemeType == 'eid'  && $this->layout != 'zimbabwe') {
            $this->SetFont('helvetica', '', 10);
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
        } elseif ($this->schemeType == 'recency'  && $this->layout != 'zimbabwe') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Recency using - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'covid19') {
            $this->SetFont('helvetica', '', 10);
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
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
            $this->writeHTMLCell(0, 0, 15, 5, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 30, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'dts' && $this->layout != 'zimbabwe'  && $this->layout != 'myanmar') {
            $this->writeHTMLCell(0, 0, 10, 25, '<span style="font-weight: bold;text-align:center;">' . 'Proficiency Testing Program - ' . $this->scheme_name . ' </span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report ' . $screening . '</span>', 0, 0, 0, true, 'J', true);
            if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                $htmlInAdd = '<span style="font-weight: normal;text-align:center;">' . $instituteAddress . '</span>';
                $this->writeHTMLCell(0, 0, 15, 15, $htmlInAdd, 0, 0, 0, true, 'J', true);
            }
            $this->SetFont('helvetica', '', 10);
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
            $this->SetFont('helvetica', '', 10);
            if ($this->schemeType == 'dts') {
                if ($this->layout == 'myanmar') {
                    $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Antibody Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Summary Report ' . $screening . '</span>';
                } else {
                    $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Antibody Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report ' . $screening . '</span>';
                }
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            }
            if ($this->schemeType == 'tb') {
                // $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
            } else {
                $this->writeHTMLCell(0, 0, 15, 10, $html, 0, 0, 0, true, 'J', true);
                $html = '<hr/>';
                $this->writeHTMLCell(0, 0, 10, 50, $html, 0, 0, 0, true, 'J', true);
            }
        }

        if (isset($this->watermark) && $this->watermark != "") {
            //Put the watermark
            $this->SetFont('', 'B', 120);
            $this->SetTextColor(230, 228, 198);
            $this->RotatedText(25, 190, $this->watermark, 45);
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
        $this->SetFont('helvetica', '', 7);
        // Page number
        $this->writeHTML("<hr>", true, false, true, false, "");
        if ($this->instituteAddressPosition == "footer" && isset($instituteAddress) && $instituteAddress != "") {
            $this->writeHTML($instituteAddress, true, false, true, false, "L");
        }
        if (($this->schemeType == 'eid' || $this->schemeType == 'vl') && isset($this->config) && $this->config != "" && $this->layout != 'zimbabwe') {
            $effectiveDate = new DateTime($showTime);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 10, 'Effective Date:' . $effectiveDate->format('M Y'), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        } else {
            $effectiveDate = new DateTime($showTime);
            $effectiveMonthYear = ($this->schemeType == 'tb') ? "June 2022" : $effectiveDate->format('M Y');
            if ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
                $this->SetFont('helvetica', '', 9);
                if (isset($this->issuingAuthority) && !empty($this->issuingAuthority)) {
                    $html = '<table><tr><td><span style="text-align:left;">Form : ILB-500-F29A</span></td><td><span style="text-align:center;">Issuing Authority : ' . $this->issuingAuthority . '</span></td><td><span style="text-align:right;">Effective Date : ' . $effectiveMonthYear . '</span></td></tr></table>';
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

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->generalModel = new Pt_Commons_General();
    }
    public function setParams($resultStatus, $dateTime, $config, $watermark, $reportType, $layout, $scheme = "")
    {
        $this->resultStatus = $resultStatus;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->watermark = $watermark;
        $this->reportType = $reportType;
        $this->layout = $layout;
        $this->scheme = $scheme;

        $reportService = new Application_Service_Reports();
        $reportFormat = $reportService->getReportConfigValue('report-format');
        $templateTopMargin = $reportService->getReportConfigValue('template-top-margin');

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
            $this->useImportedPage($template, 7, -10);
        }
        $reportType = $this->reportType;
        if (isset($this->scheme) && !empty($this->scheme) && $this->PageNo() == 1) {
            if (isset($this->templateTopMargin) && !empty($this->templateTopMargin)) {
                $this->SetY($this->templateTopMargin - 10);
            } else {
                $this->SetY(32);
            }
            $this->SetFont('helvetica', 'B', 10);
            $this->writeHTML("Proficiency Testing Program for " . $this->scheme, true, false, true, false, 'C');
        }
        if (isset($this->reportType) && !empty($this->reportType) && strtolower($this->reportType) == 'summary' && $this->PageNo() == 1) {
            $this->writeHTML("<br>Summary Results Report", true, false, true, false, 'C');
        } elseif (strtolower($this->reportType) == 'individual' && $this->PageNo() == 1) {
            $this->writeHTML("<br>Individual Participant Results Report", true, false, true, false, 'C');
        }

        //Put the watermark
        $this->SetFont('', 'B', 120);
        $this->SetTextColor(230, 228, 198);
        $this->RotatedText(25, 190, $this->watermark, 45);
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
        if (isset($this->resultStatus) && trim($this->resultStatus) == "finalized") {
            $finalizeReport = ' | ' . $this->reportType . ' REPORT | FINALIZED ';
        } else {
            $finalizeReport = ' | ' . $this->reportType . ' REPORT ';
        }
        if (isset($this->dateTime) && $this->dateTime != '') {
            $showTime = $this->dateTime;
        } else {
            $showTime = date("Y-m-d H:i:s");
        }
        // Position at 15 mm from bottom
        $this->SetY(-10);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        // $this->writeHTML("<hr>", true, false, true, false, '');
        $this->writeHTML("Report generated on " . $this->generalModel->humanReadableDateFormat($showTime) . $finalizeReport, true, false, true, false, 'C');
        $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage() . ' | ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
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
        //Put the watermark
        $this->SetFont('helvetica', 'B', 50);
        $this->SetTextColor(230, 228, 198);
        $this->RotatedText(67, 109, $this->waterMarkText, 45);

        if (is_null($this->_tplIdx)) {
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


    $limit = 3;
    $sQuery = $db->select()
        ->from(['eq' => 'evaluation_queue'])
        ->joinLeft(['s' => 'shipment'], 's.shipment_id=eq.shipment_id', ['shipment_code', 'scheme_type', 'shipment_attributes', 'pt_co_ordinator_name'])
        ->joinLeft(['sa' => 'system_admin'], 'eq.requested_by=sa.admin_id', ['saname' => new Zend_Db_Expr("CONCAT(sa.first_name,' ',sa.last_name)")])
        ->where("eq.status=?", 'pending')
        ->limit($limit);
    $evalResult = $db->fetchAll($sQuery);

    $reportService = new Application_Service_Reports();
    $commonService = new Application_Service_Common();
    $schemeService = new Application_Service_Schemes();
    $evalService = new Application_Service_Evaluation();
    if (!empty($evalResult)) {
        $header = $reportService->getReportConfigValue('report-header');
        $instituteAddressPosition = $reportService->getReportConfigValue('institute-address-postition');
        $reportComment = $reportService->getReportConfigValue('report-comment');
        $logo = $reportService->getReportConfigValue('logo');
        $logoRight = $reportService->getReportConfigValue('logo-right');
        $layout = $reportService->getReportConfigValue('report-layout');
        $templateTopMargin = $reportService->getReportConfigValue('template-top-margin');

        $passPercentage = $commonService->getConfig('pass_percentage');
        $trainingInstance = $commonService->getConfig('training_instance');
        $watermark = null;
        if (isset($trainingInstance) && $trainingInstance === 'yes') {
            $watermark = $commonService->getConfig('training_instance_text');
        }

        $customField1 = $commonService->getConfig('custom_field_1');
        $customField2 = $commonService->getConfig('custom_field_2');
        $haveCustom = $commonService->getConfig('custom_field_needed');
        $evaluatOnFinalized = $commonService->getConfig('evaluate_before_generating_reports');
        $feedbackOption = $commonService->getConfig('feed_back_option');
        $recencyAssay = $schemeService->getRecencyAssay();
        $downloadDirectory = realpath(DOWNLOADS_FOLDER);
        $reportsPath = $downloadDirectory . DIRECTORY_SEPARATOR . 'reports';

        foreach ($evalResult as $evalRow) {
            if (($evalRow['report_type'] == 'finalized' || $evalRow['report_type'] == 'generateReport') && $evaluatOnFinalized == "yes") {
                $customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
                $shipmentId = $evalRow['shipment_id'];

                $timeStart = microtime(true);
                $shipmentResult = $evalService->getShipmentToEvaluate($shipmentId, true);
                $timeEnd = microtime(true);

                $executionTime = ($timeEnd - $timeStart) / 60;
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
                    $emailContent = 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated <br><br> Please click on this link to see ' . $conf->domain .  $link;
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

            $db->update('evaluation_queue', array('status' => $reportTypeStatus, 'last_updated_on' => new Zend_Db_Expr('now()')), 'id=' . $evalRow['id']);
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
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('scheme_type'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('is_user_configured'))
                ->where("spm.shipment_id = ?", $evalRow['shipment_id'])
                ->group('spm.shipment_id');
            // die($pQuery);
            $totParticipantsRes = $db->fetchRow($pQuery);
            $resultStatus = $evalRow['report_type'];
            $limit = 500;
            if ($skipParticipantReports === false) {
                for ($offset = 0; $offset <= $totParticipantsRes['reported_count']; $offset += $limit) {
                    if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                        $totParticipantsRes['scheme_type'] = 'generic-test';
                    }

                    // continue; // for testing
                    $resultArray = $evalService->getIndividualReportsDataForPDF($evalRow['shipment_id'], $limit, $offset);
                    // file_put_contents('data.json',json_encode($resultArray));
                    $endValue = $offset + ($limit - 1);
                    // $endValue = $offset + 49;
                    if ($endValue > $totParticipantsRes['reported_count']) {
                        $endValue = $totParticipantsRes['reported_count'];
                    }

                    $bulkfileNameVal = $offset . '-' . $endValue;
                    if (!empty($resultArray)) {
                        // this is the default layout
                        $participantLayoutFile = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                        // let us check if there is a custom layout file present for this scheme
                        if (!empty($layout)) {
                            $customLayoutFileLocation = PARTICIPANT_REPORTS_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $totParticipantsRes['scheme_type'] . '.phtml';
                            if (file_exists($customLayoutFileLocation)) {
                                $participantLayoutFile = $customLayoutFileLocation;
                            }
                        }
                        // echo $participantLayoutFile;
                        include($participantLayoutFile);
                    }
                }
            }
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
                            if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                                $resultArray['shipment']['scheme_type'] = 'generic-test';
                            }
                            // this is the default layout
                            $summaryLayoutFile = SUMMARY_REPORT_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                            // let us check if there is a custom layout file present for this scheme
                            if (!empty($layout)) {
                                $customLayoutFileLocation = SUMMARY_REPORT_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                                if (file_exists($customLayoutFileLocation)) {
                                    $summaryLayoutFile = $customLayoutFileLocation;
                                }
                            }
                            include($summaryLayoutFile);
                        }
                    }
                } else {
                    // SUMMARY REPORT
                    $resultArray = $evalService->getSummaryReportsDataForPDF($evalRow['shipment_id']);
                    $responseResult = $evalService->getResponseReports($evalRow['shipment_id']);
                    $participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id']);
                    $correctivenessArray = $reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id']);
                    if (!empty($resultArray)) {
                        if (isset($totParticipantsRes['is_user_configured']) && $totParticipantsRes['is_user_configured'] == 'yes') {
                            $resultArray['shipment']['scheme_type'] = 'generic-test';
                        }
                        // this is the default layout
                        $summaryLayoutFile = SUMMARY_REPORT_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                        // let us check if there is a custom layout file present for this scheme
                        if (!empty($layout)) {
                            $customLayoutFileLocation = SUMMARY_REPORT_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $resultArray['shipment']['scheme_type'] . '.phtml';
                            if (file_exists($customLayoutFileLocation)) {
                                $summaryLayoutFile = $customLayoutFileLocation;
                            }
                        }
                        include($summaryLayoutFile);
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
            $id = $db->update('shipment', array('status' => $reportCompletedStatus, 'feedback_expiry_date' => $feedbackExpiryDate, 'report_in_queue' => 'no', 'updated_by_admin' => (int) $evalRow['requested_by'], 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $evalRow['shipment_id']);


            if ($id > 0 && $reportCompletedStatus == 'finalized') {
                $authNameSpace = new Zend_Session_Namespace('administrators');
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Finalized shipment - " . $evalRow['shipment_code'], "shipment");
            }

            $db->update('evaluation_queue', $update, 'id=' . $evalRow['id']);
            $db->insert('notify', array('title' => 'Reports Generated', 'description' => 'Reports for Shipment ' . $evalRow['shipment_code'] . ' are ready for download', 'link' => $link));

            /* New report push notification start */
            $pushContent = $commonService->getPushTemplateByPurpose('report');

            $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
            $replace = array('', $evalRow['shipment_code'], $evalRow['scheme_type'], '', '');
            $title = str_replace($search, $replace, $pushContent['notify_title']);
            $msgBody = str_replace($search, $replace, $pushContent['notify_body']);
            if (isset($pushContent['data_msg']) && $pushContent['data_msg'] != '') {
                $dataMsg = str_replace($search, $replace, $pushContent['data_msg']);
            } else {
                $dataMsg = '';
            }
            $notifyType = ($evalRow['report_type'] = 'generateReport') ? 'individual_reports' : 'summary_reports';
            $commonService->insertPushNotification($title, $msgBody, $dataMsg, $pushContent['icon'], $evalRow['shipment_id'], 'new-reports', $notifyType);

            $notParticipatedMailContent = $commonService->getEmailTemplate('not_participant_report_mail');
            $subQuery = $db->select()
                ->from(array('s' => 'shipment'), array('shipment_code', 'scheme_type'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
                ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
                ->where("s.shipment_id=?", $evalRow['shipment_id'])
                ->group('dm.dm_id');
            $subResult = $db->fetchAll($subQuery);
            foreach ($subResult as $row) {
                $db->update('data_manager', array('push_status' => 'pending'), 'dm_id = ' . $row['dm_id']);
                /* New shipment mail alert start */
                $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
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

                /* New shipment mail alert end */
            }
            /* New report push notification end */
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/generate-shipment-reports.php');
}
