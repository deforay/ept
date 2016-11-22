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
            if((isset($participant[1]) && trim($participant[1])!= '')){
                $to = $participant[1];
                $cc = $participant[2];
                $bcc = '';
                $subject = '';
                $message = '';
                $fromMail = '';
                $fromName = '';
                //Subject
                $subject.= 'PT Results Submission - 2016';
                //Message
                $message.= '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;background-color:#FFF;">';
                    $message.= '<tr><td align="center">';
                      $message.= '<table cellpadding="3" style="width:92%;font-family:Helvetica,Arial,sans-serif;margin:30px 0px 30px 0px;padding:2% 0% 0% 2%;background-color:#ffffff;">';
                        $message.= '<tr><td colspan="2">Dear Participant,</td></tr>';
                        $message.= '<tr><td colspan="2">Please login to https://ept.vlsmartconnect.com/auth/login with credentials provided earlier. Only results submitted online will be considered for evaluation.</td></tr>';
                        $message.= '<tr><td colspan="2">Once you login you can submit your PT results. The participants are allowed to submit their PT results via online ePT system anytime until the results due date on November 16, 2016.</td></tr>';
                        $message.= '<tr><td colspan="2">You can find a quick help guide here http://bit.ly/ept-pt-submission-help</td></tr>';
                        $message.= '<tr><td colspan="2">For any assistance or guidance you can reach us at pt@vlsmartconnect.com</td></tr>';
                        $message.= '<tr><td colspan="2">We request that you complete a short Feedback form : http://bit.ly/pt2016-feedback. Your feedback is highly valuable and will be used for improvement of the PT programs. </td></tr>';
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
