<?php
include_once('CronInit.php');

require_once('tcpdf/tcpdf.php');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

class IndividualPDF extends TCPDF
{
    public $scheme_name = '';

    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType, $layout, $datetime = "")
    {
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->layout = $layout;
        $this->dateTime = $datetime;
    }

    public function humanDateTimeFormat($date)
    {
        if ($date == "0000-00-00 00:00:00") {
            return "";
        } else {
            $dateTimeArray = explode(' ', $date);
            $dateArray = explode('-', $dateTimeArray[0]);
            $newDate = $dateArray[2] . "-";
            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] - 1];
            return $newDate .= $mon . "-" . $dateArray[0] . " " . $dateTimeArray[1];
        }
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
        } else if ($this->schemeType == 'recency') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">Individual Participant Results Report</span>';
        } else {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Report - Rapid HIV Dried Tube Specimen </span>';
        }

        $this->writeHTMLCell(0, 0, 27, 10, $html, 0, 0, 0, true, 'J', true);
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
        if (isset($this->dateTime) && $this->dateTime != '') {
            $showTime = $this->dateTime;
        } else {
            $showTime = date("Y-m-d H:i:s");
        }
        // Position at 15 mm from bottom
        $this->SetY(-12);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        //$this->Cell(0, 10, "Report generated at :".date("d-M-Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        //$this->Cell(0, 10, "Report generated on ".date("d M Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->writeHTML("<hr>", true, false, true, false, '');
        if (isset($this->layout) && $this->layout == 'zimbabwe') {
            $this->Cell(0, 05,  strtoupper($this->header), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        } else {
            $this->writeHTML("Report generated on " . $this->humanDateTimeFormat($showTime) . $finalizeReport, true, false, true, false, 'C');
        }
    }
}

// Extend the TCPDF class to create custom Header and Footer
class SummaryPDF extends TCPDF
{

    public function setSchemeName($header, $schemeName, $logo, $logoRight, $resultStatus, $schemeType, $datetime = "")
    {
        $this->scheme_name = $schemeName;
        $this->header = $header;
        $this->logo = $logo;
        $this->logoRight = $logoRight;
        $this->resultStatus = $resultStatus;
        $this->schemeType = $schemeType;
        $this->dateTime = $datetime;
    }

    public function humanDateTimeFormat($date)
    {
        if ($date == "0000-00-00 00:00:00") {
            return "";
        } else {
            $dateTimeArray = explode(' ', $date);
            $dateArray = explode('-', $dateTimeArray[0]);
            $newDate = $dateArray[2] . "-";
            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] - 1];
            return $newDate .= $mon . "-" . $dateArray[0] . " " . $dateTimeArray[1];
        }
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
        } else if ($this->schemeType == 'recency') {
            $this->SetFont('helvetica', '', 10);
            $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Recency using - ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
        } else {
            //$html='<span style="font-weight: bold;text-align:center;">Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using '.$this->scheme_name.'</span><br><span style="font-weight: bold;text-align:center;">All Participants Summary Report</span><br><small  style="text-align:center;">'.$this->header.'</small>';
            $this->SetFont('helvetica', '', 10);
            if ($this->schemeType == 'dts'){
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for HIV Antibody Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            } else{
                $html = '<span style="font-weight: bold;text-align:center;"><span  style="text-align:center;">' . $this->header . '</span><br>Proficiency Testing Program for Anti-HIV Antibodies Diagnostics using ' . $this->scheme_name . '</span><br><span style="font-weight: bold; font-size:11;text-align:center;">All Participants Summary Report</span>';
            }
        }

        $this->writeHTMLCell(0, 0, 27, 10, $html, 0, 0, 0, true, 'J', true);
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
        if (isset($this->dateTime) && $this->dateTime != '') {
            $showTime = $this->dateTime;
        } else {
            $showTime = date("Y-m-d H:i:s");
        }
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 7);
        // Page number
        //$this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages()." - Report generated at :".date("d-M-Y H:i:s").$finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');

        $this->Cell(0, 10, "Report generated on " . $this->humanDateTimeFormat($showTime) . $finalizeReport, 0, false, 'C', 0, '', 0, false, 'T', 'M');
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

    $limit = 3;
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


        $header = $reportService->getReportConfigValue('report-header');
        $reportComment = $reportService->getReportConfigValue('report-comment');
        $logo = $reportService->getReportConfigValue('logo');
        $logoRight = $reportService->getReportConfigValue('logo-right');
        $layout = $reportService->getReportConfigValue('report-layout');
        $possibleDtsResults = $schemeService->getPossibleResults('dts');
        $recencyPossibleResults = $schemeService->getPossibleResults('recency');
        $passPercentage = $commonService->getConfig('pass_percentage');

        $customField1 = $commonService->getConfig('custom_field_1');
        $customField2 = $commonService->getConfig('custom_field_2');
        $haveCustom = $commonService->getConfig('custom_field_needed');
        $recencyAssay = $schemeService->getRecencyAssay();

        foreach ($evalResult as $evalRow) {

            //var_dump($evalRow);die;
            //$alertMail = new Zend_Mail();
            ini_set('memory_limit', '-1');

            $reportTypeStatus = 'not-evaluated';
            if ($evalRow['report_type'] == 'generateReport') {
                $reportTypeStatus = 'not-evaluated';
            } else if ($evalRow['report_type'] == 'finalized') {
                $reportTypeStatus = 'not-finalized';
            }
            $db->update('evaluation_queue', array('status' => $reportTypeStatus, 'last_updated_on' => new Zend_Db_Expr('now()')), 'id=' . $evalRow['id']);
            //$r = $evalService->getShipmentToEvaluate($evalRow['shipment_id'], true);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $db->select()->from(
                array('spm' => 'shipment_participant_map'),
                array(
                    'custom_field_1',
                    'custom_field_2',
                    'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                    'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed not like 'yes')")
                )
            )
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array())
                ->where("spm.shipment_id = ?", $evalRow['shipment_id'])
                ->group('spm.shipment_id');

            $totParticipantsRes = $db->fetchRow($pQuery);

            $resultStatus = $evalRow['report_type'];


            /*  for ($offset = 0; $offset <= $totParticipantsRes['reported_count']; $offset = $offset + 50) {
                 $resultArray = $evalService->getEvaluateReportsInPdf($evalRow['shipment_id'], 50, $offset); */
            //$offset = 0;
            $limit = 50;
            for ($offset = 0; $offset <= $totParticipantsRes['reported_count']; $offset += $limit) {
                $resultArray = $evalService->getEvaluateReportsInPdf($evalRow['shipment_id'], $limit, $offset);
                $endValue = $offset + ($limit - 1);
                // $endValue = $offset + 49;
                if ($endValue > $totParticipantsRes['reported_count']) {
                    $endValue = $totParticipantsRes['reported_count'];
                }
                $bulkfileNameVal = $offset . '-' . $endValue;
                if (count($resultArray) > 0) {
                    // this is the default layout
                    $participantLayoutFile = PARTICIPANT_REPORT_LAYOUT . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $resultArray['shipment'][0]['scheme_type'] . '.phtml';

                    // let us check if there is a custom layout file present for this scheme
                    if (!empty($layout)) {
                        $customLayoutFileLocation = PARTICIPANT_REPORT_LAYOUT . DIRECTORY_SEPARATOR . $layout . DIRECTORY_SEPARATOR . $resultArray['shipment'][0]['scheme_type'] . '.phtml';
                        if (file_exists($customLayoutFileLocation)) {
                            $participantLayoutFile = $customLayoutFileLocation;
                        }
                    }
                    include($participantLayoutFile);
                }
            }

            // SUMMARY REPORT
            $resultArray = $evalService->getSummaryReportsInPdf($evalRow['shipment_id']);
            $responseResult = $evalService->getResponseReports($evalRow['shipment_id']);
            $participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($evalRow['shipment_id']);
            $correctivenessArray = $reportService->getCorrectiveActionReportByShipmentId($evalRow['shipment_id']);

            if (count($resultArray) > 0) {

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

            $reportCompletedStatus = 'evaluated';
            if ($evalRow['report_type'] == 'generateReport') {
                $reportCompletedStatus = 'evaluated';
                $notifyType = 'individual_reports';
                $link = '/reports/distribution/shipment/sid/' . base64_encode($evalRow['shipment_id']);
            } else if ($evalRow['report_type'] == 'finalized') {
                $reportCompletedStatus = 'finalized';
                $notifyType = 'summary_reports';
                $link = '/reports/distribution/finalize/sid/' . base64_encode($evalRow['shipment_id']);
            }
            $update = array(
                'status' => $reportCompletedStatus,
                'last_updated_on' => new Zend_Db_Expr('now()')
            );
            if ($evalRow['report_type'] == 'finalized' && $evalRow['date_finalised'] == '') {
                $update['date_finalised'] = new Zend_Db_Expr('now()');
            }
            $db->update('shipment', array('status' => $reportCompletedStatus, 'report_in_queue' => 'no', 'updated_by_admin' => (int)$evalRow['requested_by'], 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $evalRow['shipment_id']);
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
            // $notifyType = ($evalRow['report_type'] = 'generateReport')?'individual_reports':'summary_reports';
            $commonService->insertPushNotification($title, $msgBody, $dataMsg, $pushContent['icon'], $evalRow['shipment_id'], 'new-reports', $notifyType);

            $notParticipatedMailContent = $commonService->getEmailTemplate('report');
            $subQuery = $db->select()
                ->from(array('s' => 'shipment'), array('shipment_code', 'scheme_type'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
                ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
                ->where("s.shipment_id=?", $evalRow['shipment_id'])
                ->group('dm.dm_id');
            // die($subQuery);
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
    echo ($e->getMessage()) . PHP_EOL;
    error_log($e->getTraceAsString());
    echo ('whoops! Something went wrong in scheduled-jobs/evaluate-shipment.php  - ' . $evalRow['shipment_id']);
    error_log('whoops! Something went wrong in crscheduled-jobson/evaluate-shipment.php  - ' . $evalRow['shipment_id']);
}
