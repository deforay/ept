<?php
include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    
        date_default_timezone_set('GMT');
        $filename = UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-responded.csv";

        if (!file_exists($filename) || !is_readable($filename))
            return FALSE;    
        $data = array();
        ini_set('auto_detect_line_endings', TRUE);
        if (($handle = fopen($filename, 'r')) !== false) {
            while (($line = fgetcsv($handle)) !== false) {
                $data[] = ($line);
            }
            fclose($handle);
        }
        ini_set('auto_detect_line_endings', FALSE);
        unset($data[0]);
        
        $commonService = new Application_Service_Common();
        foreach ($data as $participant) {

            


            if((isset($participant[7]) && trim($participant[7])!= '')){

                

                $to = $participant[7];
                $cc = $participant[8];
                $bcc = 'pt@vlsmartconnect.com';
                $subject = '';
                $message = '';
                $fromMail = '';
                $fromName = '';
                //Subject
                //$subject.= "Notice for CDC 2017-2nd PT shipment for EID and VL - ". $participant[0]." | ".$participant[1] ;
                $subject.= "Reminder 6 | Last Date Extended | CDC 2017 2nd PT shipment for EID and VL | Lab ID : ". $participant[0] . " | ". $participant[3];
                //Message
                $message.= '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;background-color:#FFF;">';
                    $message.= '<tr><td align="center">';
                      $message.= '<table cellpadding="3" style="width:98%;font-family:Helvetica,Arial,sans-serif;margin:30px 0px 30px 0px;padding:2% 0% 0% 2%;background-color:#ffffff;text-align:justify;">';
                        
                        $message.= '<tr><td colspan="2">Dear PT Participant,</td></tr>';
                        $message.= '<tr><td colspan="2">Our records indicate that you have not yet responded for the VL/EID Panel which was dispatched on September 14, 2017.</td></tr>';
                       
                        $message.= '<tr><td colspan="2">The results for VL2017-B PT and EID 2017-II panels are due on <strong>November 04, 2017</strong>.</td></tr>';

                        $message.= '<tr><td colspan="2">Please visit https://ept.vlsmartconnect.com/auth/login to record your result</td></tr>';
                        $message.= '<tr><td colspan="2">Login ID : '. $participant[5] . '</td></tr>';
                        $message.= '<tr><td colspan="2">Password : '. $participant[6] . '</td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2">If you have any problems please reach us at pt@vlsmartconnect.com.</td></tr>';

                        $message.= '<tr><td colspan="2">Sincerely,</td></tr>';
                        $message.= '<tr><td colspan="2">Online PT Team
                        </td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2"><small>This is a system generated mail. Please do not reply to this email</small></td></tr>';
                      $message.= '</table>';
                    $message.= '</td></tr>';
                $message.= '</table>';
                $commonService->insertTempMail($to, $cc,$bcc, $subject, $message, $fromMail = null, $fromName = null);
            }
        }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/SendParticipantLoginDetails.php');
}
