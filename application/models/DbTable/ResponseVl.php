<?php

class Application_Model_DbTable_ResponseVl extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_vl';
    protected $_primary = array('vl_shipment_id','participant_id','vl_sample_id');

    public function updateResults($params){
        $sampleIds = $params['sampleId'];
        
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("vl_shipment_id = ".$params['hdshipId'] . " and participant_id = '".$params['hdparticipantId']. "' and vl_sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
            if($res == null || count($res) == 0){
                $this->insert(array(
                                    'vl_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'vl_sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'created_by' => $authNameSpace->UserID,
                                    'created_by' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'vl_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'vl_sample_id'=>$sampleId,
                                    'reported_viral_load'=>$params['vlResult'][$key],
                                    'updated_by' => $authNameSpace->UserID,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "vl_shipment_id = ".$params['hdshipId'] . " and participant_id = '".$params['hdparticipantId']. "' and vl_sample_id = ".$sampleId );                                
                
            }

        }
    }
}
