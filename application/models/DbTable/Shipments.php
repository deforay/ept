<?php

class Application_Model_DbTable_Shipments extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment';
    protected $_primary = 'shipment_id';

    public function getShipmentData($sId,$pId){
        
    return $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s'=>$this->_name))
                                         ->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id')
                                         ->where("s.shipment_id = ?",$sId)
                                         ->where("sp.participant_id = ?",$pId));
    }
    
    public function updateShipmentStatus($shipmentId, $status){
        if(isset($status) && $status != null && $status != ""){
            return $this->update(array('status'=>$status),"shipment_id = $shipmentId");    
        }else{
            return 0;
        }
        
    }
    
    public function updateShipmentStatusByDistribution($distributionId, $status){
        if(isset($status) && $status != null && $status != ""){
            return $this->update(array('status'=>$status),"distribution_id = $distributionId");    
        }else{
            return 0;
        }
        
    }
    public function getPendingShipmentsByDistribution($distributionId){
        return $this->fetchAll("status ='pending' AND distribution_id = $distributionId");    
    }

}

