<?php

class Application_Model_DbTable_ResponseVl extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_vl';
    protected $_primary = array('shipment_map_id','sample_id');

    public function updateResults($params){
        $sampleIds = $params['sampleId'];
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            //Set tnd value if Yes
            $tnd = NULL;
            if(isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed']== 'yes'){
                $params['vlResult'][$key] = '';
            }else if(isset($params['tndReference'][$key]) && $params['tndReference'][$key]== 'yes'){
                $tnd = 'yes';
                $params['vlResult'][$key] = '0.00'; 
            }
            $count = (isset($res) && $res != "")?count($res):0;
            if ($res == null || $count == 0) {
                $this->insert(array(
                                    'shipment_map_id'=>$params['smid'],
                                    'sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'is_tnd'=>$tnd,
                                    'created_by' => $authNameSpace->dm_id,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'shipment_map_id'=>$params['smid'],
                                    'sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'is_tnd'=>$tnd,
                                    'updated_by' => $authNameSpace->UserID,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
                
            }

        }
    }

    public function updateResultsByAPI($params,$dm){
        $sampleIds = $params["vlData"]->Section3->data->no->tableRowTxt->id;
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("shipment_map_id = ".$params['mapId'] . " and sample_id = '".$sampleId ."'");
            //Set tnd value if Yes
            $tnd = NULL;
            if(isset($params["vlData"]->Section3->data->isPtTestNotPerformedRadio) && $params["vlData"]->Section3->data->isPtTestNotPerformedRadio== 'yes'){
                $params["vlData"]->Section3->data->no->vlResult[$key] = '';
            }else if(isset($params["vlData"]->Section3->data->no->tndReferenceRadioSelected[$key]) && $params["vlData"]->Section3->data->no->tndReferenceRadioSelected[$key]== 'yes'){
                $tnd = 'yes';
                $params["vlData"]->Section3->data->no->vlResult[$key] = '0.00'; 
            }
            
            if($res == null || $res === false){
                $this->insert(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Section3->data->no->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'created_by'            =>  $dm['dm_id'],
                    'created_on'            =>  new Zend_Db_Expr('now()')
                ));
            }else{
                $this->update(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Section3->data->no->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'updated_by'            =>  $dm['dm_id'],
                    'updated_on'            =>  new Zend_Db_Expr('now()')
                ), "shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );
                
            }
        }
        return true;
    }
}
