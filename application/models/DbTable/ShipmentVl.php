<?php

class Application_Model_DbTable_ShipmentVl extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment_vl';
    protected $_primary = array('vl_shipment_id','participant_id');

    public function getShipmentVl($sId,$pId){
        return $this->fetchRow($this->select()->where("vl_shipment_id = ?",$sId)->where("participant_id = ?",$pId));
    }
    
    public function updateShipmentVl($params,$shipmentId, $participantId){
        $params['evaluation_status'] = '11111110';
        return $this->update($params,"vl_shipment_id = $shipmentId and participant_id = '".$participantId."'");
    }

}

