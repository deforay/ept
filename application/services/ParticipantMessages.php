<?php

class Application_Service_ParticipantMessages
{
    protected $tempUploadDirectory;
    public function __construct()
    {
        $this->tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
    }

    public function addParticipantMessage($params)
    {
        $userDb = new Application_Model_DbTable_ParticipantMessages();
        return $userDb->addParticipantMessage($params);
    }

    public function getParticipantMessage($params)
    {
        $userDb = new Application_Model_DbTable_ParticipantMessages();
        return $userDb->getParticipantMessage($params);
    }

    public function getParticipantMessageById($pmId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pm' => 'participant_messages'))
            ->where("id = ?", $pmId);
        $res = $db->fetchAll($sql);
        return $res[0];
    }
}
