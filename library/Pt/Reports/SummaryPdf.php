<?php

use setasign\Fpdi\Tcpdf\Fpdi;

class Pt_Reports_SummaryPdf extends Fpdi
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
    public $preHeaderText = "";

    public function setPreHeaderText($text)
    {
        $this->preHeaderText = $text;
    }

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
        $instituteName = $this->generalModel->getConfig('institute_name') ?? null;
        $instituteAddress = $this->generalModel->getConfig('institute_address') ?? null;
        $additionalInstituteDetails = $this->generalModel->getConfig('additional_institute_details') ?? null;
        if ($this->schemeType == 'vl' && $this->layout != 'zimbabwe') {
            if (isset($instituteName) && $instituteName != "") {
                if ($this->layout == 'myanmar') {
                    $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . nl2br(stripcslashes(trim($instituteName))) . '</span>

                    <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                    if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                        $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span><br><br><span style="font-weight: bold;text-align:center;font-size:12px;">Proficiency Testing Program for HIV-1 Viral Load using Dried Tube Specimen</span>';
                    }
                    $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
                    $html = '<hr/>';
                    $this->writeHTMLCell(0, 0, 10, 35, $html, 0, 0, 0, true, 'J', true);
                } else {
                    $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . nl2br(stripcslashes(trim($instituteName))) . '</span>

                    <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                    if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                        $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
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
            if (isset($instituteName) && $instituteName != "") {
                $html = '<span style="font-weight: bold;text-align:center;font-size:18px;">' . nl2br(stripcslashes(trim($instituteName))) . '</span>

                <br/><span style="font-weight: bold;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($this->header))) . '</span>';
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
                }
            } else {
                $html = '<span style="font-weight: bold;text-align:center;"><span style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Results Report</span>';
            }
            $this->writeHTMLCell(0, 0, 15, 20, $html, 0, 0, 0, true, 'J', true);
            $html = '<hr/>';
            $this->writeHTMLCell(0, 0, 10, 40, $html, 0, 0, 0, true, 'J', true);
        } elseif ($this->schemeType == 'tb' && $this->layout != 'zimbabwe') {
            if (isset($this->tbTestType) && !empty($this->tbTestType) && $this->tbTestType != 'microscopy') {
                $yPosition = 10;
                if (isset($this->preHeaderText) && !empty($this->preHeaderText)) {
                    $preHtml = '<span style="text-align:center;color:#777777;">' . $this->preHeaderText . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 5, $preHtml, 0, 0, 0, true, 'J', true);
                    $yPosition = 12;
                }
                $html = '<div style="font-weight: bold;text-align:center;background-color:#777777;color:white;height:100px;"><span style="text-align:center;font-size:11;">' . $this->header . ': FINAL SUMMARY REPORT</span></div>';
                $this->writeHTMLCell(0, 0, 15, $yPosition, $html, 0, 0, 0, true, 'J', true);
            } elseif ($this->tbTestType == 'microscopy') {
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span></span>';
                $this->writeHTMLCell(0, 0, 15, 05, $html, 0, 0, 0, true, 'J', true);
                if ($this->instituteAddressPosition == "header" && isset($instituteAddress) && $instituteAddress != "") {
                    $htmlInAdd = '<span style="font-weight: normal;text-align:right;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 20, $htmlInAdd, 0, 0, 0, true, 'J', true);
                }
                if ($this->instituteAddressPosition == "header" && isset($additionalInstituteDetails) && $additionalInstituteDetails != "") {
                    $htmlInDetails = '<span style="font-weight: normal;text-align:left;">' . nl2br(stripcslashes(trim($additionalInstituteDetails))) . '</span>';
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
                $htmlInAdd = '<span style="font-weight: normal;text-align:center;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
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
                $htmlInAdd = '<span style="font-weight: normal;text-align:center;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
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
                    $htmlInAdd = '<span style="font-weight: normal;text-align:right;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 20, $htmlInAdd, 0, 0, 0, true, 'J', true);
                }
                if ($this->instituteAddressPosition == "header" && isset($additionalInstituteDetails) && $additionalInstituteDetails != "") {
                    $htmlInDetails = '<span style="font-weight: normal;text-align:left;">' . nl2br(stripcslashes(trim($additionalInstituteDetails))) . '</span>';
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
                    $html .= '<br/><span style="font-weight: normal;text-align:center;font-size:11;">' . nl2br(stripcslashes(trim($instituteAddress))) . '</span>';
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
            $effectiveDate = (!empty($showTime) || $showTime != '') ? new DateTime($showTime) : null;
            $effectiveMonthYear = (!empty($effectiveDate) || $effectiveDate != '') ? $effectiveDate->format('M Y') : '';
            $this->SetFont('freesans', '', 10, '', true);
            $this->Cell(0, 10, 'Effective Date:' . $effectiveMonthYear, 0, false, 'L', 0, '', 0, false, 'T', 'M');
        } else {
            $effectiveDate = (!empty($showTime) || $showTime != '') ? new DateTime($showTime) : null;
            $effectiveMonthYear = (!empty($effectiveDate) || $effectiveDate != '') ? $effectiveDate->format('M Y') : '';
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
