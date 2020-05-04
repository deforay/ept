<?php
include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    
        date_default_timezone_set('GMT');
        $filename = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "pt-2017.csv";
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
        if (!file_exists('rowcounter.txt') && !is_readable('rowcounter.txt')){
           file_put_contents('rowcounter.txt', '1'); 
        }
        $storedRowCounter = (int)file_get_contents('rowcounter.txt');
        
        
        //Zend_Debug::dump($rowCounter);die;
        //$storedRowCounter = 50;
        $currentCounter = 1;
        foreach ($data as $participant) {
            
            if($storedRowCounter > $currentCounter){
                $currentCounter++;
                continue;
            }
            
            if((isset($participant[0]) && trim($participant[0])!= '')){
                
                if(trim($participant[6]) == "" && trim($participant[7]) == ""){
                    continue;
                }
                
                $vlFile = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "certificates" . DIRECTORY_SEPARATOR."vl".DIRECTORY_SEPARATOR.str_replace('/', '_', $participant[0])."-VL-2016.pdf";
                
                $attachments = array();
                
                if (file_exists($vlFile) && is_readable($vlFile)){
                    $attachments[] = $vlFile;
                }
                $eidFile = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "certificates" . DIRECTORY_SEPARATOR."eid".DIRECTORY_SEPARATOR.str_replace('/', '_', $participant[0])."-EID-2016.pdf";
                if (file_exists($eidFile) && is_readable($eidFile)){
                    $attachments[] = $eidFile;
                }
                
               // continue;
               
                
                $to = str_replace("/",",",$participant[6]);
                $to = str_replace(";",",",$participant[6]);
                $to = str_replace(" ","",$participant[6]);
                error_log($to);
                
                $cc = str_replace("/",",",$participant[7]);
                $cc = str_replace(";",",",$participant[7]);                
                $cc = str_replace(" ","",$participant[7]);
                error_log($cc);
                error_log("======");
                //$to = $cc = "amitdgr@gmail.com";
                //Zend_Debug::dump($participant);die;
                if($to == "" || $to == null){
                    if($cc == "" || $cc == null){
                        continue;
                    }
                    else{
                        $to = $cc;
                    }
                }
                
               // $googleFormLink = urlencode($participant[9]);
                $googleFormLink =  "https://docs.google.com/forms/d/e/1FAIpQLSe_8DlG6Q81O_-U1T3FShTTtP9rma5gzOUZOZfmxjqXXGaeJg/viewform?entry.2056875419=".urlencode($participant[0])."&entry.23277302=".urlencode($participant[1])."&entry.845493072=".urlencode($participant[2])."&entry.1362557178=".urlencode($participant[3])."&entry.240654948=".urlencode($participant[4])."&entry.44456581=".urlencode($participant[5])."&entry.1553133392=".urlencode($participant[6])."&entry.1143268259=".urlencode($participant[7])."&entry.443396805=".urlencode($participant[8]);
                
                $bcc = '';
                $subject = '';
                $message = '';
                $fromMail = '';
                $fromName = '';
                //Subject
                //if(count($attachments) > 0){
                //    $subject.= '[UPDATED] Your PT 2016 Certificate and Enrollment link for PT 2017';
                //}else{
                    $subject.= '[IMMEDIATE ATTENTION] Enrollment link for PT 2017 - Please respond if not updated yet';
                //}
                
                $subject.=' | Lab ID : '.$participant[0];
                
                if(isset($participant[1]) && $participant[1] != ""){
                 $subject.=' | '.$participant[1];
                }
                
                
                
                //Message
                $message.= '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;background-color:#FFF;">';
                    $message.= '<tr><td align="center">';
                      $message.= '<table cellpadding="3" style="width:92%;font-family:Helvetica,Arial,sans-serif;margin:10px 0px 30px 0px;padding:2% 0% 0% 2%;background-color:#ffffff;">';
                        $message.= '<tr><td colspan="2">Dear PT Participant,</td></tr>';
                       // if(count($attachments) > 0){
                       //     $message.= '<tr><td colspan="2">Please find your 2016 PT certificate(s) attached with this email.</td></tr>';
                       // }
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2">We have received the Participation Confirmation and Feedback from many of the participants. Please ignore this if already filled both the PT Confirmation form and the Feedback form. </td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2"><strong>The following are immediate actions required by all PT Participants : </strong></td></tr>';
                        $message.= '<tr><td colspan="2"><ol>';
                        $message.= '<li>To be included in 2017 CDC EID and VL PT programs, you must CONFIRM participation by clicking on this link ('.$googleFormLink.').  We have assigned a new lab ID that begins with 5xxx to each lab.  You should see your 5xxx on your 2017 Participant Confirmation link when you confirm participation for 2017 PT programs. <br><br><strong>The PT panel shipments will be only sent to the laboratories that confirm participation.</strong><br><br><br></li>';
                        $message.= '<li>All participants should provide their feedback at the link at (<a href="http://bit.ly/pt2016-feedback">http://bit.ly/pt2016-feedback</a>). Your feedback will help with the programs improvement.</li>';
                        $message.= '</ol></td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2">Note : To enroll a new laboratory, please complete a new laboratory enrollment form at this link https://ept.vlsmartconnect.com/contact-us?q=register.</td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2">For any assistance or guidance you can reach us at pt@vlsmartconnect.com</td></tr>';
                        $message.= '<tr><td colspan="2">We look forward to working with you in 2017. </td></tr>';
                        $message.= '<tr><td colspan="2">Sincerely,</td></tr>';
                        $message.= '<tr><td colspan="2">Viral Load and Early Infant Diagnosis Proficiency Testing Team</td></tr>';
                        $message.= '<tr><td colspan="2"></td></tr>';
                        $message.= '<tr><td colspan="2"><small>This is a system generated mail. Please do not reply to this email</small></td></tr>';
                      $message.= '</table>';
                    $message.= '</tr></td>';
                $message.= '</table>';
                //echo $message;die;
                //$commonService->insertTempMail($to, $cc,$bcc, $subject, $message, $fromMail = null, $fromName = null);
                
                $to = explode(",",$to);
                $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
                
                $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());
                
                //$fromMail = $conf->email->config->username;
                $fromMail = "pt@vlsmartconnect.com";
                
                if ($fromName == null || $fromName == "") {
                    $fromName = "Online PT Program";
                }
                $originalMessage=html_entity_decode($message,ENT_QUOTES,'UTF-8');
                $systemMail = new Zend_Mail();
                
                $originalMessage= str_replace("&nbsp;","",strval($originalMessage));
                $originalMessage= str_replace("&amp;nbsp;","",strval($originalMessage));
                
                $systemMail->setSubject($subject);
                $systemMail->setBodyHtml(html_entity_decode($originalMessage, ENT_QUOTES, 'UTF-8'));
        
                $systemMail->setFrom($fromMail, $fromName);
                $systemMail->setReplyTo("pt@vlsmartconnect.com", $fromName);
        
                if (is_array($to)) {
                    foreach ($to as $name => $ma) {
                        if($ma=="" || $ma == null) continue;
                        $systemMail->addTo($ma, $name);
                    }
                } else {
                    $systemMail->addTo($to);
                }
                if (isset($cc) && $cc != "" && $cc != null) {
                    $cc = explode(",",$cc);
                    if (is_array($cc)) {
                        foreach ($cc as $name => $ma) {
                            if($ma=="" || $ma == null) continue;
                            $systemMail->addCc($ma, $name);
                        }
                    } else {
                        $systemMail->addCc($cc);
                    }
                }
                if (isset($bcc) && $bcc != "" && $bcc != null) {
                    if (is_array($bcc)) {
                        foreach ($bcc as $name => $mail) {
                            $systemMail->addBcc($mail, $name);
                        }
                    } else {
                        $systemMail->addBcc($bcc);
                    }
                }
                
               /* if(count($attachments) > 0){
                    foreach($attachments as $att){
                        $content = file_get_contents($att); // e.g. ("attachment/abc.pdf");
                        $f = basename($att,".pdf");
                        $attachment = new Zend_Mime_Part($content);
                        $attachment->type = 'application/pdf';
                        $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                        $attachment->encoding = Zend_Mime::ENCODING_BASE64;
                        $attachment->filename = $f."-".mt_rand().'.pdf'; // name of file
                        $systemMail->addAttachment($attachment); 
                    }
                }
                */
        
                try {
                    
                    $systemMail->send($smtpTransportObj);
                    $currentCounter++;
                    file_put_contents('rowcounter.txt', $currentCounter); 
                } catch (Exception $exc) {
                    error_log("===== MAIL SENDING FAILED - START =====");
                    error_log($exc->getMessage());
                    error_log($exc->getTraceAsString());
                    error_log("===== MAIL SENDING FAILED - END =====");
                    break;
                }
            }else{
                break;
            }
        }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/SendParticipantLoginDetails.php');
}
