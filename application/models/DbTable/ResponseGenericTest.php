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
                'result' => (isset($params['result'][$key]) && !empty($params['result'][$key])) ? $params['result'][$key] : '',
                'repeat_result' => (isset($params['repeatResult'][$key]) && !empty($params['repeatResult'][$key])) ? $params['repeatResult'][$key] : '',
                'reported_result' => (isset($params['finalResult'][$key]) && !empty($params['finalResult'][$key])) ? $params['finalResult'][$key] : '',
                'additional_detail' => (isset($params['additionalDetail'][$key]) && !empty($params['additionalDetail'][$key])) ? $params['additionalDetail'][$key] : '',
                'comments' => (isset($params['comments'][$key]) && !empty($params['comments'][$key])) ? $params['comments'][$key] : ''
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

    public function removeShipmentResults($mapId)
    {

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $data = array(
            'result' => '',
            'repeat_result' => '',
            'reported_result' => '',
            'additional_detail' => '',
            'comments' => '',
            'updated_by' => $authNameSpace->dm_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );

        return $this->update($data, "shipment_map_id = " . $mapId);
    }

    public function updateResultsByAPIV2($params)
    {
        $id = 0;
        $sampleIds = $params['sample_id'];
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                'shipment_map_id' => $params['mapId'],
                'sample_id' => $sampleId,
                'result' => $params['result']->$key ?? null,
                'repeat_result' => $params['repeatResult']->$key ?? null,
                'reported_result' => $params['finalResult']->$key ?? null,
                'additional_detail' => $params['additionalDetail']->$key ?? null,
                'comments' => $params['comments']->$key ?? null
            );
            if (empty($res)) {
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr('now()');
                $id = $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $id = $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
        return $id;
    }
}
