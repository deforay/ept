<?php

class Application_Model_DbTable_ShipmentEid extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment_eid';
    protected $_primary = 'eid_shipment_id';

    public function getShipmentEid($sId,$pId){
        return $this->fetchRow($this->select()->where("eid_shipment_id = ?",$sId)->where("participant_id = ?",$pId));
    }

}

