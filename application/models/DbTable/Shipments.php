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
    
    public function getShipmentOverviewDetails(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
	$dmId=$authNameSpace->dm_id;
        $query=$this->getAdapter()->select()->from(array('s'=>'shipment'),array('s.scheme_type','SHIP_YEAR'=>'year(s.shipment_date)','TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
                    ->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"),'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)")))
                    ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=sp.participant_id')
                    ->where("s.status!='pending'")
                    ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                    ->where("pmm.dm_id=?",$dmId)
                    ->group('s.scheme_type')
                    ->group('SHIP_YEAR')
                    ->order('SHIP_YEAR');
                    
        return $this->getAdapter()->fetchAll($query);
    }
    
    public function getShipmentCurrentDetails(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
	$dmId=$authNameSpace->dm_id;
        $query=$this->getAdapter()->select()->from(array('s'=>'shipment'),array('s.scheme_type','s.shipment_date','s.shipment_code','s.lastdate_response','s.shipment_id'))
                        ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array("spm.evaluation_status","spm.participant_id","RESPONSEDATE"=>"DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')","RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END")))
                        ->join(array('p'=>'participant'),'p.participant_id=spm.participant_id',array('p.first_name','p.last_name'))
                        ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
                        ->where("pmm.dm_id=?",$dmId)
                        ->where("s.status!='pending'")
                        ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                        ->where("s.lastdate_response >=  CURDATE()")
                        ->order('s.shipment_date')
                        ->order('spm.participant_id');
        return $this->getAdapter()->fetchAll($query);
    }
    
    public function getShipmentDefaultDetails(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
	$dmId=$authNameSpace->dm_id;
        $query=$this->getAdapter()->select()->from(array('s'=>'shipment'),array('SHIP_YEAR'=>'year(s.shipment_date)','s.scheme_type','s.shipment_date','s.shipment_code','s.lastdate_response','s.shipment_id'))
                        ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array("spm.evaluation_status","spm.participant_id","RESPONSEDATE"=>"DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')","ACTION" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,2,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"),"STATUS" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'On Time' WHEN '2' THEN 'Late' WHEN '0' THEN 'No Response' END")))
                        ->join(array('p'=>'participant'),'p.participant_id=spm.participant_id',array('p.first_name','p.last_name'))
                        ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
                        ->where("pmm.dm_id=?",$dmId)
                        ->where("s.status!='pending'")
                        ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                        ->where("s.lastdate_response <  CURDATE()")
                        ->where("substr(spm.evaluation_status,3,1) <> '1'")
                        ->order('s.shipment_date')
                        ->order('spm.participant_id');
        
        return $this->getAdapter()->fetchAll($query);
    }
    
    public function getShipmentAllDetails(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
	$dmId=$authNameSpace->dm_id;
        $query=$this->getAdapter()->select()->from(array('s'=>'shipment'),array('SHIP_YEAR'=>'year(s.shipment_date)','s.scheme_type','s.shipment_date','s.shipment_code','s.lastdate_response','s.shipment_id'))
                        ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array("spm.evaluation_status","spm.participant_id","RESPONSEDATE"=>"DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')","RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,2,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"),"REPORT" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,2,1) WHEN 1 THEN 'Report' END")))
                        ->join(array('p'=>'participant'),'p.participant_id=spm.participant_id',array('p.first_name','p.last_name'))
                        ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
                        ->where("pmm.dm_id=?",$dmId)
                        ->where("s.status!='pending'")
                        ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                        ->order('s.shipment_date')
                        ->order('spm.participant_id');
                    
        return $this->getAdapter()->fetchAll($query);
        
    }
}

