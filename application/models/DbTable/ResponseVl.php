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
            if($res == null || count($res) == 0){
                $this->insert(array(
                                    'vl_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'vl_sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'created_by' => $authNameSpace->dm_id,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'vl_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'vl_sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'updated_by' => $authNameSpace->UserID,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
                
            }

        }
    }
}
