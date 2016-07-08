<?php
include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    
        date_default_timezone_set('GMT');
        $filename = UPLOAD_PATH . DIRECTORY_SEPARATOR . "participants-mail.csv";
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
            if((isset($participant[0]) && trim($participant[0])!= '') && (isset($participant[1]) && trim($participant[1])!= '')
               && (isset($participant[2]) && trim($participant[2])!= '') && (isset($participant[3]) && trim($participant[3])!= '')
               && (isset($participant[4]) && trim($participant[4])!= '')){
                $to = $participant[1];
                $cc = $participant[2];
                $bcc = '';
                $subject = '';
                $message = '';
                $fromMail = '';
                $fromName = '';
                //Subject
                $subject.= 'PT Results Report - VL2016';
                //Message
                $message.= '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;background-color:#FFF;">';
                    $message.= '<tr><td align="center">';
                      $message.= '<table cellpadding="3" style="width:92%;font-family:Helvetica,Arial,sans-serif;margin:30px 0px 30px 0px;padding:2% 0% 0% 2%;background-color:#ffffff;">';
                        //$message.= '<tr><td colspan="2">Dear <strong>'.ucwords($participant[0]).'</strong>,</td></tr>';
                        $message.= '<tr><td colspan="2">Dear Participant,</td></tr>';
                        $message.= '<tr><td colspan="2">Please login to https://ept.vlsmartconnect.com/auth/login with the following credentials </td></tr>';
                        $message.= '<tr><td width="12%"><strong>Login ID</strong> : </td><td>'.$participant[3].'</td></tr>';
                        $message.= '<tr><td width="12%"><strong>Password</strong> : </td><td>'.$participant[4].'</td></tr>';
                        $message.= '<tr><td colspan="2">This Login ID and Password is unique to your laboratory, please save this Login ID and Password for future use. You will have the option to change them when at a later date.</td></tr>';
                        $message.= '<tr><td colspan="2">Once you login you can download your VL2016-A PT results summary report for '.($participant[0]).'.</td></tr>';
                        $message.= '<tr><td colspan="2">You can find a quick help video here http://bit.ly/ept-vl2016-intro</td></tr>';
                        $message.= '<tr><td colspan="2">For any assistance or guidance you can reach us at pt@vlsmartconnect.com</td></tr>';
                        $message.= '<tr><td colspan="2">We request you to fill in this short Feedback form : http://bit.ly/vl2016-feedback</td></tr>';
                        $message.= '<tr><td colspan="2">Thanks</td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2"><small>This is a system generated mail. Please do not reply to this email</small></td></tr>';
                      $message.= '</table>';
                    $message.= '</tr></td>';
                $message.= '</table>';
                $commonService->insertTempMail($to, $cc,$bcc, $subject, $message, $fromMail = null, $fromName = null);
            }
        }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/SendParticipantLoginDetails.php');
}
