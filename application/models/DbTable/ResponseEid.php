<?php

class Application_Model_DbTable_ResponseEid extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_eid';
    protected $_primary = array('eid_shipment_id','participant_id','eid_sample_id');

    public function updateResults($params){
        $sampleIds = $params['sampleId'];
        
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("eid_shipment_id = ".$params['hdshipId'] . " and participant_id = '".$params['hdparticipantId']. "' and eid_sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
            if($res == null || count($res) == 0){
                $this->insert(array(
                                    'eid_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'eid_sample_id'=>$sampleId,
                                    'reported_result'=>$params['result'][$key],
                                    'hiv_ct_od'=>$params['hivCtOd'][$key],
                                    'ic_qs'=>$params['icQs'][$key],
                                    'created_by' => $authNameSpace->UserID,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'eid_shipment_id'=>$params['hdshipId'],
                                    'participant_id'=>$params['hdparticipantId'],
                                    'eid_sample_id'=>$sampleId,
                                    'reported_result'=>$params['result'][$key],
                                    'hiv_ct_od'=>$params['hivCtOd'][$key],
                                    'ic_qs'=>$params['icQs'][$key],
                                    'updated_by' => $authNameSpace->UserID,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "eid_shipment_id = ".$params['hdshipId'] . " and participant_id = '".$params['hdparticipantId']. "' and eid_sample_id = ".$sampleId );                                
                
            }

        }
    }

}

