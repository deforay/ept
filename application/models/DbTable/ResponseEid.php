<?php

class Application_Model_DbTable_ResponseEid extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_eid';
    protected $_primary = array('shipment_map_id','sample_id');

    public function updateResults($params){
        $sampleIds = $params['sampleId'];
        
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if($res == null || count($res) == 0){
                $this->insert(array(
                                    'shipment_map_id'=>$params['smid'],
                                    'sample_id'=>$sampleId,
                                    'reported_result'=>$params['result'][$key],
                                    'hiv_ct_od'=>$params['hivCtOd'][$key],
                                    'ic_qs'=>$params['icQs'][$key],
                                    'created_by' => $authNameSpace->dm_id,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'reported_result'=>$params['result'][$key],
                                    'hiv_ct_od'=>$params['hivCtOd'][$key],
                                    'ic_qs'=>$params['icQs'][$key],
                                    'updated_by' => $authNameSpace->dm_id,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
                
            }

        }
    }

}

