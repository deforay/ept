<?php

class Application_Model_DbTable_TBInstruments extends Zend_Db_Table_Abstract
{
    protected $_name = 'tb_instruments';
    protected $_primary = 'instrument_id';

    public function fetchTbInstruments($mapId){
        return $this->fetchAll($this->select()->where('map_id = ' . $mapId)->group('instrument_serial'))->toArray();
    }
}