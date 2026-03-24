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
        $this->resultStatus       = $resultStatus;
        $this->dateTime           = $dateTime;
        $this->config             = $config;
        $this->watermark          = $watermark ?? '';
        $this->reportType         = $reportType;
        $this->layout             = $layout;
        $this->scheme             = $scheme;
        $this->schemeType         = $schemeType;
        $this->approveTxt         = $approveTxt;
        $this->staticFooterHtml   = $staticFooterHtml;
        $this->shipmentAttributes = $shipmentAttributes;

        $reportService     = new Application_Service_Reports();
        $commonService     = new Application_Service_Common();
        $reportFormat      = $reportService->getReportConfigValue('report-format');
        $templateTopMargin = $reportService->getReportConfigValue('template-top-margin');
        $this->instance    = $commonService->getConfig('instance');
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
            $this->SetAlpha(0.2);
            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(211, 211, 211);
            $this->RotatedText(25, 190, $this->watermark, 45);
            $this->SetAlpha(1);
        }
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c  = cos($angle);
            $s  = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
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

    public function Footer()
    {
        $shipmentService = new Application_Service_Shipments();

        // Guard: shipmentAttributes may be an empty string (default) instead of an array.
        // Only attempt array access when it is actually an array.
        $attrs = is_array($this->shipmentAttributes)
            ? $this->shipmentAttributes
            : (is_string($this->shipmentAttributes) && !empty($this->shipmentAttributes)
                ? (json_decode($this->shipmentAttributes, true) ?? [])
                : []);
        $shipmentId    = $attrs['shipment_id'] ?? null;
        $effectiveDate = $attrs['effectiveDate']
            ?? ($shipmentId ? $shipmentService->getShipmentAttributes($shipmentId, 'effectiveDate') : null);
        $reportVersion = $attrs['reportVersion']
            ?? ($shipmentId ? $shipmentService->getShipmentAttributes($shipmentId, 'reportVersion') : null);
        $showTime   = $this->dateTime ?? date("Y-m-d H:i:s");
        $reportDate = Pt_Commons_DateUtility::humanReadableDateFormat($showTime);
        $pageNumber = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();

        $finalizeReport = (isset($this->resultStatus) && trim($this->resultStatus) == "finalized")
            ? " | {$this->reportType} REPORT | FINALIZED "
            : " | {$this->reportType} REPORT ";

        // Base cell style — single line height, no wrapping issues
        $td = 'font-size:9px; vertical-align:middle; padding:1px 3px;';

        $completeFooterHtml = '';

        // -------------------------------------------------------------------
        // MALAWI
        // Single bordered row with 5 equal columns (20% each):
        //   reportVersion | Serology report form V.1 | effectiveDate | Survey number 0124 | Page X of Y
        // -------------------------------------------------------------------
        if ($this->layout == 'malawi') {

            $border = 'border:1px solid #000;';
            $completeFooterHtml  = '<table style="width:100%; border-collapse:collapse; ' . $border . '">';
            $completeFooterHtml .= '<tr>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:left;   border-right:0px solid #fff;">' . htmlspecialchars($reportVersion  ?? '') . '</td>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:center; border-right:0px solid #fff;">Serology report form V.1</td>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:center; border-right:0px solid #fff;">' . htmlspecialchars($effectiveDate ?? '') . '</td>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:center; border-right:0px solid #fff;">Survey number 0124</td>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:right;">' . $pageNumber . '</td>';
            $completeFooterHtml .= '</tr>';
            $completeFooterHtml .= '</table>';

            // -------------------------------------------------------------------
            // ZIMBABWE
            // 3 columns: Effective Date (left) | Report Version (center) | Page (right)
            // -------------------------------------------------------------------
        } elseif ($this->layout == 'zimbabwe') {

            $completeFooterHtml  = '<table style="width:100%; border-collapse:collapse;">';
            $completeFooterHtml .= '<tr>';
            $completeFooterHtml .= '<td style="width:33.33%; ' . $td . ' text-align:left;">Effective Date ' . htmlspecialchars($effectiveDate ?? '') . '</td>';
            $completeFooterHtml .= '<td style="width:33.33%; ' . $td . ' text-align:center;">' . htmlspecialchars($reportVersion ?? '') . '</td>';
            $completeFooterHtml .= '<td style="width:33.33%; ' . $td . ' text-align:right;">' . $pageNumber . '</td>';
            $completeFooterHtml .= '</tr>';
            $completeFooterHtml .= '</table>';

            // -------------------------------------------------------------------
            // DEFAULT
            // 3 columns: (empty) | Report generated on ... (center) | Page (right)
            // -------------------------------------------------------------------
        } else {

            $completeFooterHtml  = '<table style="width:100%; border-collapse:collapse;">';
            $completeFooterHtml .= '<tr>';
            $completeFooterHtml .= '<td style="width:40%; ' . $td . ' text-align:left;"></td>';
            $completeFooterHtml .= '<td style="width:40%; ' . $td . ' text-align:center;">Report generated on ' . $reportDate . $finalizeReport . '</td>';
            $completeFooterHtml .= '<td style="width:20%; ' . $td . ' text-align:right;">' . $pageNumber . '</td>';
            $completeFooterHtml .= '</tr>';
            $completeFooterHtml .= '</table>';
        }

        // Philippines: disclaimer line above the footer row
        if (!empty($this->instance) && $this->instance == 'philippines') {
            $text = (!empty($this->approveTxt))
                ? "This document has been reviewed and validated by EQA officers and authorized personnel of {$this->approveTxt}"
                : "This document has been reviewed and validated by EQA officers.";
            $completeFooterHtml = '<div style="text-align:center; font-size:7px;">' . $text . '</div>' . $completeFooterHtml;
        }

        // Optional static footer content prepended above everything
        if (!empty($this->staticFooterHtml)) {
            $completeFooterHtml = $this->staticFooterHtml . $completeFooterHtml;
        }

        // Malawi is a single line so needs less room at the bottom
        $this->SetY($this->layout == 'malawi' ? -12 : -18);
        $this->SetFont('freesans', '', 7, '', true);
        $this->writeHTML($completeFooterHtml, true, false, false, false, '');
    }
}
