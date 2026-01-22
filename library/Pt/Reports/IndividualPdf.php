<?php

use setasign\Fpdi\Tcpdf\Fpdi;

class Pt_Reports_IndividualPdf extends Fpdi
{
    public $scheme_name = '';
    public $header = '';
    public $angle = '';
    public $logo = '';
    public $logoRight = '';
    public $resultStatus = '';
    public $schemeType = '';
    public $layout = '';
    public $effectiveDate = '';
    public $config = null;
    public $watermark = '';
    public $dateFinalised = '';
    public $instituteAddressPosition = '';
    public $issuingAuthority = '';
    public $dtsPanelType = '';
    public $generalModel = null;
    public $preHeaderText = '';
    public $formVersion = '';


    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType, $layout, $effectiveDate = "", $config = "", $watermark = "", $dateFinalised = "", $instituteAddressPosition = "", $issuingAuthority = "", $dtsPanelType = "", $formVersion = "")
    {
        $this->generalModel = new Pt_Commons_General();
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->layout = $layout;
        $this->effectiveDate = $effectiveDate;
        $this->config = $config;
        $this->watermark = $watermark ?? '';
        $this->dateFinalised = $dateFinalised;
        $this->instituteAddressPosition = $instituteAddressPosition;
        $this->issuingAuthority = $issuingAuthority;
        $this->dtsPanelType = $dtsPanelType;
        $this->formVersion = $formVersion;
    }

    public function setPreHeaderText($text)
    {
        $this->preHeaderText = $text;
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
            $html = '<div style="font-weight: bold;text-align:center;background-color:#777777;color:white;height:100px;"><span style="text-align:center;font-size:10;">' . $this->header . ' | FINAL INDIVIDUAL PERFORMANCE REPORT</span></div>';
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
                $finalizeReport = '<span style="font-weight: normal;text-align:center;">' . $finalized . ' INDIVIDUAL PERFORMANCE REPORT ' . $screening . '</span>';
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
                $yPosition = 10;
                if (isset($this->preHeaderText) && !empty($this->preHeaderText)) {
                    $preHtml = '<span style="text-align:center;color:#777777;">' . $this->preHeaderText . '</span>';
                    $this->writeHTMLCell(0, 0, 15, 5, $preHtml, 0, 0, 0, true, 'J', true);
                    $yPosition = 12;
                }
                $this->writeHTMLCell(0, 0, 15, $yPosition, $html, 0, 0, 0, true, 'J', true);
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
            $finalizeReport = ' | INDIVIDUAL PERFORMANCE REPORT | FINALIZED ';
        } else {
            $finalizeReport = ' | INDIVIDUAL PERFORMANCE REPORT ';
        }

        $effectiveDateToShow = $this->effectiveDate ?? date("Y-m-d H:i:s");
        // Position from bottom - TB needs more space for additional footer content
        $footerPosition = ($this->schemeType == 'tb') ? -25 : -15;
        $this->SetY($footerPosition);
        // Set font

        $this->SetFont('freesans', '', 7);
        // Page number

        if ($this->schemeType == 'eid' || $this->schemeType == 'vl' || $this->schemeType == 'tb') {
            $this->writeHTML("<hr>", true, false, true, false, '');
            if ($this->instituteAddressPosition == "footer" && isset($instituteAddress) && $instituteAddress != "") {
                $this->writeHTML($instituteAddress, true, false, true, false, "L");
            }
        }
        $effectiveDate = (!empty($effectiveDateToShow) || $effectiveDateToShow != '') ? new DateTime($effectiveDateToShow) : null;
        if (($this->schemeType == 'eid' || $this->schemeType == 'vl' || $this->schemeType == 'tb') && isset($this->config) && $this->config != "" && $this->layout != 'zimbabwe') {
            // $this->Cell(0, 10, 'ILB-', 0, false, 'L', 0, '', 0, false, 'T', 'M');

            // $this->Ln();

            $effectiveMonthYear = (!empty($effectiveDate) || $effectiveDate != '') ? $effectiveDate->format('M Y') : '';
            $this->SetFont('freesans', '', 10);
            if ($this->schemeType == 'tb') {
                $this->SetFont('freesans', '', 9);
                if (isset($this->issuingAuthority) && !empty($this->issuingAuthority)) {
                    $html = '<table><tr><td><span style="text-align:left;">' . $this->formVersion . '</span></td><td><span style="text-align:center;">Issuing Authority : ' . $this->issuingAuthority . '</span></td><td><span style="text-align:right;">Effective Date : ' . $effectiveMonthYear . '</span></td></tr></table>';
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
                $this->writeHTML("Report generated on " . Pt_Commons_DateUtility::humanReadableDateFormat($effectiveDateToShow) . $finalizeReport, true, false, true, false, 'C');
            }
        }
        if ($this->schemeType != 'tb') {
            $this->Cell(0, 0, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }
    }
}
