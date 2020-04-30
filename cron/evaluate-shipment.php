<?php

include_once 'CronInit.php';

require_once 'tcpdf/tcpdf.php';

defined('REPORT_LAYOUT')
    || define('REPORT_LAYOUT', realpath(dirname(__FILE__) . '/../report-layouts'));

defined('CRON_FOLDER')
    || define('CRON_FOLDER', realpath(dirname(__FILE__) . '/../cron'));

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);


class IndividualPDF extends TCPDF
{
    public $scheme_name = '';

    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType,$layout)
    {
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->layout = $layout;
    }

    //Page header
    public function Header()
    {
        // Logo
        //$image_file = K_PATH_IMAGES.'logo_example.jpg';
        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($image_file, 10, 8, 30, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        // if (trim($this->logoRight) != "") {
        //     if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logoRight)) {
        //         $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logoRight;
        //         $this->Image($image_file, 180, 10, 20, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        //     }
        // }

        // Set font

        $this->SetFont('helvetica', '', 10);

        $this->header = nl2br(trim($this->header));
        $this->header = preg_replace('/<br>$/', "", $this->header);

        if ($this->schemeType == 'vl') {
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for HIV Viral Load using Dried Tube Specimen</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';

            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report</span>';
        } else if ($this->schemeType == 'eid') {
            $this->SetFont('helvetica', '', 10);
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for HIV-1 Early Infant Diagnosis using Dried Blood Spot</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report</span>';
        } else {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report - Rapid HIV Dried Tube Specimen </span>';
        }

        $this->writeHTMLCell(0, 0, 42, 10, $html, 0, 0, 0, true, 'J', true);
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 38, $html, 0, 0, 0, true, 'J', true);
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
        
        // Position at 15 mm from bottom
        $this->SetY(-12);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        //$this->Cell(0, 10, "Report generated at :".date("d-M-Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        //$this->Cell(0, 10, "Report generated on ".date("d M Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->writeHTML("<hr>", true, false, true, false, '');
        $this->writeHTML("Report generated on " . date("d M Y H:i:s") . $finalizeReport, true, false, true, false, 'C');
        if(isset($this->layout) && $this->layout == 'zimbabwe'){
            $this->Cell(0, 05,  strtoupper($this->header), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
}

// Extend the TCPDF class to create custom Header and Footer
class SummaryPDF extends TCPDF
{

    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType)
    {
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
    }

    //Page header
    public function Header()
    {
        // Logo
        //$image_file = K_PATH_IMAGES.'logo_example.jpg';
        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($image_file, 10, 8, 30, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        // if (trim($this->logoRight) != "") {
        //     if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logoRight)) {
        //         $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logoRight;
        //         $this->Image($image_file, 180, 10, 20, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        //     }
        // }

        // Set font
        $this->SetFont('helvetica', '', 10);

        $this->header = nl2br(trim($this->header));
        $this->header = preg_replace('/<br>$/', "", $this->header);

        if ($this->schemeType == 'vl') {
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for HIV Viral Load using Dried Tube Specimen</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';

            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Viral Load using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
        } else if ($this->schemeType == 'eid') {
            $this->SetFont('helvetica', '', 10);
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for HIV-1 Early Infant Diagnosis using Dried Blood Spot</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV-1 Early Infant Diagnosis using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
        } else {
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using '.$this->scheme_name.'</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
        }

        $this->writeHTMLCell(0, 0, 42, 10, $html, 0, 0, 0, true, 'J', true);
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 38, $html, 0, 0, 0, true, 'J', true);
    }

    // Page footer
    public function Footer()
    {
        $finalizeReport = "";
        if (isset($this->resultStatus) && trim($this->resultStatus) == "finalized") {
            $finalizeReport = ' | SUMMARY REPORT | FINALIZED ';
        } else {
            $finalizeReport = ' | SUMMARY REPORT ';
        }
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        //$this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages()." - Report generated at :".date("d-M-Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');

        $this->Cell(0, 10, "Report generated on " . date("d M Y H:i:s") . $finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}


function dateFormat($dateIn)
{

    $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

    // $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
    $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));

    $formatDate = $config->participant->dateformat;

    if (empty($dateIn) && $dateIn == null || $dateIn == "" || $dateIn == "0000-00-00") {
        return '';
    } else {

        $dateArray = explode('-', $dateIn);
        $newDate = $dateArray[2] . "-";

        $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
        $mon = $monthsArray[$dateArray[1] - 1];
        if ($formatDate == 'dd-M-yy')
            return  $newDate . $mon . "-" . $dateArray[0];
        else
            return   $mon . "-" . $newDate  . $dateArray[0];
    }
}


try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

    //$smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

    $limit = 1;
    $sQuery = $db->select()
        ->from(array('eq' => 'evaluation_queue'))
        ->joinLeft(array('s' => 'shipment'), 's.shipment_id=eq.shipment_id', array('shipment_code', 'scheme_type'))
        ->where("eq.status=?", 'pending')
        ->limit($limit);
    $evalResult = $db->fetchAll($sQuery);

    $reportService = new Application_Service_Reports();
    $commonService = new Application_Service_Common();
    $schemeService = new Application_Service_Schemes();
    $evalService = new Application_Service_Evaluation();



    if (count($evalResult) > 0) {
        foreach ($evalResult as $evalRow) {

            //var_dump($evalRow);die;
            //$alertMail = new Zend_Mail();

            ini_set('memory_limit', '-1');

            $db->update('evaluation_queue', array('status' => 'not-evaluated', 'last_updated_on' => new Zend_Db_Expr('now()')), 'id=' . $evalRow['id']);

            $resultStatus = 'finalized';

            //$r = $evalService->getShipmentToEvaluate($evalRow['shipment_id'], true);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $db->select()->from(
                array('spm' => 'shipment_participant_map'),
                array(
                    'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                    'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed !='yes')")
                )
            )
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array())
                ->where("spm.shipment_id = ?", $evalRow['shipment_id'])
                ->group('spm.shipment_id');

            $totParticipantsRes = $db->fetchRow($pQuery);

            $header = $reportService->getReportConfigValue('report-header');
            $reportComment = $reportService->getReportConfigValue('report-comment');
            $logo = $reportService->getReportConfigValue('logo');
            $logoRight = $reportService->getReportConfigValue('logo-right');
            $layout = $reportService->getReportConfigValue('report-layout');
            $possibleDtsResults = $schemeService->getPossibleResults('dts');
            $passPercentage = $commonService->getConfig('pass_percentage');
            $comingFrom = 'generateReport';
            $customField1 = $commonService->getConfig('custom_field_1');
            $customField2 = $commonService->getConfig('custom_field_2');
            $haveCustom = $commonService->getConfig('custom_field_needed');

            $startValue = 1;
            for ($startValue = 1; $startValue <= $totParticipantsRes['reported_count']; $startValue = $startValue + 50) {
                $resultArray = $evalService->getEvaluateReportsInPdf($evalRow['shipment_id'], 50, $startValue);
                $endValue = $startValue + 49;
                if ($endValue > $totParticipantsRes['reported_count']) {
                    $endValue = $totParticipantsRes['reported_count'];
                }
                $bulkfileNameVal = $startValue . '-' . $endValue;
                if (count($resultArray) > 0) {
                    if(isset($layout) && $layout != ''){
                        $layout = REPORT_LAYOUT . DIRECTORY_SEPARATOR . 'layout-files' . DIRECTORY_SEPARATOR . $layout;
                        // die($layoutModel);
                        include($layout.'.php');
                    }else{
                        include('generate-individual-reports.php');
                    }
                }
            }

            // SUMMARY REPORT

            /* $resultArray = $evalService->getSummaryReportsInPdf($evalRow['shipment_id']);
            $responseResult = $evalService->getResponseReports($evalRow['shipment_id']);
            $participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id']);
            $correctivenessArray = $reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id']);

            if (count($resultArray) > 0) {
                include('generate-summary-pdf.php');
            } */


            $db->update('shipment', array('status' => 'evaluated', 'updated_by_admin' => (int)$evalRow['requested_by'], 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $evalRow['shipment_id']);
            $db->update('evaluation_queue', array('status' => 'evaluated', 'last_updated_on' => new Zend_Db_Expr('now()')), 'id=' . $evalRow['id']);
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo ($e->getMessage()) . PHP_EOL;
    error_log($e->getTraceAsString());
    echo ('whoops! Something went wrong in cron/evaluate-shipment.php  - ' . $evalRow['shipment_id']);
    error_log('whoops! Something went wrong in cron/evaluate-shipment.php  - ' . $evalRow['shipment_id']);
}
