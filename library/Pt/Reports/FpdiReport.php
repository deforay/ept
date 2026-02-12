<?php

use setasign\Fpdi\Tcpdf\Fpdi;

class Pt_Reports_FpdiReport extends Fpdi
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
    public $shipmentAttributes = "";

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->generalModel = new Pt_Commons_General();
    }
    public function setParams($resultStatus, $dateTime, $config = "", $watermark, $reportType, $layout, $scheme = "", $schemeType = "", $approveTxt = "", $staticFooterHtml = "", $shipmentAttributes = "")
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
        $this->shipmentAttributes = $shipmentAttributes;

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
                $this->writeHTML("Individual Participant Results Report", true, false, true, false, 'C');
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
        $effectiveDate = $this->shipmentAttributes['effectiveDate'] ?? null;
        $reportVersion = $this->shipmentAttributes['report_version'] ?? Pt_Commons_SchemeConfig::get($this->schemeType . '.reportVersion');
        $reportDate = Pt_Commons_DateUtility::humanReadableDateFormat($showTime);
        $completeFooterHtml = '<table>';
        $completeFooterHtml .= '<tr>';
        if ($this->layout != 'zimbabwe') {
            $completeFooterHtml .= '<td><br><div style="text-align:center; font-size:10px; margin-top:10px;">Report generated on ' . $reportDate . $finalizeReport . '</div></td>';
        } else if ($this->layout == 'zimbabwe' && isset($effectiveDate) && !empty($effectiveDate)) {
            $completeFooterHtml .= '<td><br><div style="text-align:left; font-size:10px; margin-top:10px;">Effective Date ' . $effectiveDate . '</div></td>';
        }
        if (isset($reportVersion) && !empty($reportVersion)) {
            $completeFooterHtml .= '<td><br><div style="text-align:center; font-size:10px; margin-top:10px;">' . $reportVersion . '</div></td>';
        }
        $completeFooterHtml .= '<td><br><div style="text-align:right; font-size:10px; margin-top:10px;">Page ' . $this->getAliasNumPage() . ' | ' . $this->getAliasNbPages() . '</div></td>';
        $completeFooterHtml .= '</tr>';
        $completeFooterHtml .= '</table>';

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
