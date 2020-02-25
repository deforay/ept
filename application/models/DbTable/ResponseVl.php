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
            if($res == null || count($res) == 0){
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
        $sampleIds = $params["vlData"]->Heading3->sampleId;
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("shipment_map_id = ".$params['mapId'] . " and sample_id = '".$sampleId ."'");
            //Set tnd value if Yes
            $tnd = NULL;
            if(isset($params["vlData"]->Heading3->ptPanelTest) && $params["vlData"]->Heading3->ptPanelTest== 'yes'){
                $params["vlData"]->Heading3->vlResult[$key] = '';
            }else if(isset($params["vlData"]->Heading3->isTND[$key]) && $params["vlData"]->Heading3->isTND[$key]== 'yes'){
                $tnd = 'yes';
                $params["vlData"]->Heading3->vlResult[$key] = '0.00'; 
            }
            
            if($res == null || $res === false){
                $this->insert(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Heading3->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'created_by'            =>  $dm['dm_id'],
                    'created_on'            =>  ($params['createdOn'] != "")?date('Y-m-d H:i:s',strtotime($params['createdOn'])):new Zend_Db_Expr('now()')
                ));
            }else{
                $this->update(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Heading3->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'updated_by'            =>  $dm['dm_id'],
                    'updated_on'            =>  ($params['updatedOn'] != "")?date('Y-m-d H:i:s',strtotime($params['updatedOn'])):new Zend_Db_Expr('now()')
                ), "shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );
                
            }

        }
    }
}
