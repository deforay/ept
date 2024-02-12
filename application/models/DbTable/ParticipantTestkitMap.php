<?php

class Application_Model_DbTable_ParticipantTestkitMap extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant_testkit_map';
    protected $_primary = 'ptm_id';

    public function fetchMappedTestKits($pid)
    {
        return $this->fetchAll("participant_id = $pid")->toArray();
    }
}

