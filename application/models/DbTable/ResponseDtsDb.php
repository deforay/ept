<?php

class Application_Model_DbTable_ResponseDtsDb extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dts';
    protected $_primary = array('ShipmentID', 'participant_id','dts_sample_id');

}

