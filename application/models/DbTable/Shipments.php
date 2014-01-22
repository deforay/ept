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
    
    public function getShipmentOverviewDetails($dmId){
        $query=$this->getAdapter()->select()->from(array('s'=>'shipment'),array('s.scheme_type','SHIP_YEAR'=>'year(s.shipment_date)','TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
                    ->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"),'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)")))
                    ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=sp.participant_id')
                    ->where("s.status!='pending'")
                    ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                    ->where("pmm.dm_id=?",$dmId)
                    ->group('s.scheme_type')
                    ->group('ship_year')
                    ->order('ship_year');
                    
        return $this->getAdapter()->fetchAll($query);
    }
}

