<?php

class Application_Model_DbTable_ResponseGenericTest extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_generic_test';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $sampleIds = $params['sampleId'];
        
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                'shipment_map_id' => $params['smid'],
                'sample_id' => $sampleId,
                'result' => (isset($params['result'][$key]) && !empty($params['result'][$key]))?$params['result'][$key]:'',
                'repeat_result' => (isset($params['repeatResult'][$key]) && !empty($params['repeatResult'][$key]))?$params['repeatResult'][$key]:'',
                'reported_result' => (isset($params['finalResult'][$key]) && !empty($params['finalResult'][$key]))?$params['finalResult'][$key]:'',
                'additional_detail' => (isset($params['additionalDetail'][$key]) && !empty($params['additionalDetail'][$key]))?$params['additionalDetail'][$key]:'',
                'comments' => (isset($params['comments'][$key]) && !empty($params['comments'][$key]))?$params['comments'][$key]:''
            );
            /* echo "<pre>";
            print_r($data);die; */
            if (empty($res)) {
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr('now()');
                $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }
}
