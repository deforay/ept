<?php
include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    
        date_default_timezone_set('GMT');
        $filename = UPLOAD_PATH . DIRECTORY_SEPARATOR . "reminder.csv";

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
                $subject.= "Feedback | CDC 2017 2nd PT shipment for EID and VL | Lab ID : ". $participant[0] . " | ". $participant[3];
                //Message
                $message.= '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;background-color:#FFF;">';
                    $message.= '<tr><td align="center">';
                      $message.= '<table cellpadding="3" style="width:98%;font-family:Helvetica,Arial,sans-serif;margin:30px 0px 30px 0px;padding:2% 0% 0% 2%;background-color:#ffffff;text-align:justify;">';
                      
                        $message.= '<tr><td colspan="2">Dear PT Participant,</td></tr>';

                        
                        
                        $message.= '<tr><td colspan="2">We request for your <a href="https://goo.gl/forms/IQ022ckowJRvlfQy2" target="_blank">feedback</a> on this survey. Your participation in this survey is highly valuable and will be used for improvement of the PT programs. All responses will remain confidential. Survey results will be used to assess and improve the programs, as well as address key areas for prioritization and sustainability.  Thank you for your commitment to the laboratory quality assurance program. We thank you for taking 10 minutes of your time to respond to this survey.</td></tr>';

                        
                        $message.= '<tr><td colspan="2"><a href="https://goo.gl/forms/IQ022ckowJRvlfQy2" target="_blank">Click here to provide feedback for CDC 2nd PT 2017</a></td></tr>';

                        $message.= '<tr><td colspan="2">Please ignore this mail if you have already submitted your feedback.</td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2">If you have any problems please reach us at pt@vlsmartconnect.com.</td></tr>';

                        $message.= '<tr><td colspan="2">Sincerely,</td></tr>';
                        $message.= '<tr><td colspan="2">Online PT Team
                        </td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        
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
