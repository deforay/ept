<?php

class Application_Model_DbTable_TBInstruments extends Zend_Db_Table_Abstract
{
    protected $_name = 'tb_instruments';
    protected $_primary = 'instrument_id';

    public function fetchTbInstruments($pId){
        return $this->fetchAll($this->select()->where('participant_id = ' . $pId))->toArray();
    }
}