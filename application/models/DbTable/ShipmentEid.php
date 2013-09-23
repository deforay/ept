<?php

class Application_Model_DbTable_ShipmentEid extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment_eid';
    protected $_primary = array('eid_shipment_id','participant_id');

    public function getShipmentEid($sId,$pId){
        return $this->fetchRow($this->select()->where("eid_shipment_id = ?",$sId)->where("participant_id = ?",$pId));
    }
    
    public function updateShipmentEid($params,$shipmentId, $participantId){
        return $this->update($params,"eid_shipment_id = $shipmentId and participant_id = '".$participantId."'");
    }

}

