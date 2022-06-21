<?php

class Application_Model_DbTable_TempMail extends Zend_Db_Table_Abstract
{

    protected $_name = 'temp_mail';
    protected $_primary = 'temp_id';

    public function insertTempMailDetails($to, $cc, $bcc, $subject, $message, $fromMail, $fromName)
    {

        if(empty($to) || empty($message)) return false;

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $fromMail = $conf->email->config->username;

        $result = $this->insert(array(
            //'message' => strip_tags(html_entity_decode(stripslashes($message),ENT_QUOTES,'UTF-8')),
            'message'       => $message,
            'from_mail'     => $fromMail,
            'to_email'      => trim($to),
            'subject'       => $subject,
            'from_full_name' => $fromName,
            'cc'            => (isset($cc) && !empty($cc)) ? trim($cc) : '',
            'bcc'           => (isset($bcc) && !empty($bcc)) ? trim($bcc) : ''
        ));
        return $result;
    }

    public function updateTempMailStatus($id)
    {
        $this->update(array('status' => 'not-send'), "dm_id = " . (int)$id);
    }

    public function deleteTempMail($id)
    {
        $this->delete("dm_id = " . (int)$id);
    }
}
