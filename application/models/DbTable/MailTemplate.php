<?php

class Application_Model_DbTable_MailTemplate extends Zend_Db_Table_Abstract
{

    protected $_name = 'mail_template';
    protected $_primary = 'mail_temp_id';
  
    public function updateMailTemplateDetails($params) {
        $data=array(
                'mail_purpose' => $params['mailPurpose'],
                'from_name' => $params['adminName'],
                'mail_from' => $params['adminEmail'],
                'mail_cc' => $params['adminCc'],
                'mail_bcc' => $params['adminBcc'],
                'mail_subject' => $params['subject'],
                'mail_content' => $params['message'],
                'mail_footer' => $params['footer']
                );
      if(isset($params['mailId']) && $params['mailId']!=''){
          $this->update($data, "mail_temp_id=" . $params['mailId']);
      }else{
          $this->insert($data);
      }
    }
    public function getEmailTemplateDetails($mailPurpose) {
       // Zend_Debug::dump($mailPurpose);die;
        return $this->fetchRow("mail_purpose='$mailPurpose'");
    }
}

